<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\AppInfo\Application;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

class DiagnosticCsvExportService {
	private const MAX_STORED_CSV_FILES = 50;
	private const MAX_STORED_CSV_AGE_SECONDS = 1209600;
	private const MAX_CSV_CELL_LENGTH = 20000;
	private const MAX_CSV_MESSAGE_LENGTH = 2000;

	public function __construct(
		private readonly DiagnosticReportService $diagnosticReportService,
		private readonly IAppData $appData,
	) {
	}

	public function userCsv(string $userId, int $limit = 100): array {
		return $this->buildAndStore(
			$this->diagnosticReportService->userReport($userId, $this->limit($limit, 1, 500)),
			'user',
			$userId,
		);
	}

	public function adminCsv(?string $userId = null, int $limit = 200): array {
		return $this->buildAndStore(
			$this->diagnosticReportService->adminReport($userId, $this->limit($limit, 1, 500)),
			'admin',
			$userId,
		);
	}

	private function buildAndStore(array $report, string $scope, ?string $userId): array {
		$csv = $this->buildCsv($report);
		$filename = $this->filename($scope, $userId, (int)($report['meta']['createdAt'] ?? time()));
		$folder = $this->exportFolder();
		if ($folder->fileExists($filename)) {
			$folder->getFile($filename)->delete();
		}
		$folder->newFile($filename, $csv);
		try {
			$this->pruneStoredCsv($folder);
		} catch (\Throwable) {
		}

		return [
			'filename' => $filename,
			'csv' => $csv,
			'bytes' => strlen($csv),
			'storage' => 'appdata://' . Application::APP_ID . '/diagnostics/csv/' . $filename,
		];
	}

	private function buildCsv(array $report): string {
		$stream = fopen('php://temp', 'r+');
		if (!is_resource($stream)) {
			throw new \RuntimeException('Unable to open temporary CSV stream.');
		}
		fputcsv($stream, [
			'row_type',
			'created_at',
			'created_at_unix',
			'scope',
			'level',
			'event',
			'user_id',
			'run_id',
			'status',
			'message',
			'context_json',
		]);

		$meta = $report['meta'] ?? [];
		$this->writeRow($stream, [
			'row_type' => 'meta',
			'created_at_unix' => (int)($meta['createdAt'] ?? time()),
			'scope' => (string)($meta['scope'] ?? ''),
			'level' => 'info',
			'event' => 'diagnostic_report',
			'user_id' => $meta['userId'] ?? null,
			'message' => 'SakuraAlbum diagnostic export.',
			'context_json' => $this->json([
				'appId' => $meta['appId'] ?? Application::APP_ID,
				'schemaVersion' => $meta['schemaVersion'] ?? null,
				'limit' => $meta['limit'] ?? null,
				'redaction' => $meta['redaction'] ?? null,
			]),
		]);

		$this->writeHealthRows($stream, (array)($report['health'] ?? []), (string)($meta['scope'] ?? ''), $meta['userId'] ?? null);
		$this->writeQueueRows($stream, $report, (string)($meta['scope'] ?? ''), $meta['userId'] ?? null);
		$this->writeRunRows($stream, $report, (string)($meta['scope'] ?? ''), $meta['userId'] ?? null);
		$this->writeLogRows($stream, (array)($report['logs'] ?? []), (string)($meta['scope'] ?? ''));

		if (isset($report['user']) && is_array($report['user'])) {
			$userMeta = $report['user']['meta'] ?? [];
			$userScope = (string)($userMeta['scope'] ?? 'user');
			$userId = $userMeta['userId'] ?? null;
			$this->writeHealthRows($stream, (array)($report['user']['health'] ?? []), $userScope, $userId);
			$this->writeQueueRows($stream, $report['user'], $userScope, $userId);
			$this->writeRunRows($stream, $report['user'], $userScope, $userId);
			$this->writeLogRows($stream, (array)($report['user']['logs'] ?? []), $userScope);
		}

		rewind($stream);
		$csv = stream_get_contents($stream);
		fclose($stream);
		if ($csv === false) {
			throw new \RuntimeException('Unable to read temporary CSV stream.');
		}

		return $csv;
	}

	private function writeHealthRows(mixed $stream, array $health, string $scope, mixed $userId): void {
		foreach ((array)($health['issues'] ?? []) as $issue) {
			if (!is_array($issue)) {
				continue;
			}
			$this->writeRow($stream, [
				'row_type' => 'health_issue',
				'created_at_unix' => (int)($health['createdAt'] ?? time()),
				'scope' => $scope,
				'level' => (string)($issue['severity'] ?? 'warning'),
				'event' => (string)($issue['code'] ?? 'health_issue'),
				'user_id' => is_scalar($userId) ? (string)$userId : null,
				'status' => (string)($health['status'] ?? ''),
				'message' => (string)($issue['message'] ?? ''),
				'context_json' => $this->json($issue['context'] ?? []),
			]);
		}
	}

	private function writeQueueRows(mixed $stream, array $report, string $scope, mixed $userId): void {
		$queue = $scope === 'admin' ? ($report['autoSync'] ?? []) : (($report['sync']['queue'] ?? []));
		foreach ((array)($queue['samples'] ?? []) as $sample) {
			if (!is_array($sample)) {
				continue;
			}
			$this->writeRow($stream, [
				'row_type' => 'queue_sample',
				'created_at_unix' => (int)($sample['lastSeenAt'] ?? $sample['lockedAt'] ?? time()),
				'scope' => $scope,
				'level' => ($sample['status'] ?? '') === 'failed' ? 'error' : 'info',
				'event' => (string)($sample['eventType'] ?? 'auto_sync_queue'),
				'user_id' => (string)($sample['userId'] ?? (is_scalar($userId) ? $userId : '')),
				'status' => (string)($sample['status'] ?? ''),
				'message' => (string)($sample['lastError'] ?? ''),
				'context_json' => $this->json($sample),
			]);
		}
	}

	private function writeRunRows(mixed $stream, array $report, string $scope, mixed $userId): void {
		foreach ((array)($report['sync']['runs'] ?? []) as $run) {
			if (!is_array($run)) {
				continue;
			}
			$this->writeRow($stream, [
				'row_type' => 'sync_run',
				'created_at_unix' => (int)($run['startedAt'] ?? time()),
				'scope' => $scope,
				'level' => ($run['status'] ?? '') === 'failed' ? 'error' : 'info',
				'event' => (string)($run['runType'] ?? 'sync_run'),
				'user_id' => is_scalar($userId) ? (string)$userId : null,
				'run_id' => $run['id'] ?? null,
				'status' => (string)($run['status'] ?? ''),
				'message' => '',
				'context_json' => $this->json($run),
			]);
		}
	}

	private function writeLogRows(mixed $stream, array $logs, string $scope): void {
		foreach ($logs as $log) {
			if (!is_array($log)) {
				continue;
			}
			$this->writeRow($stream, [
				'row_type' => 'app_log',
				'created_at_unix' => (int)($log['createdAt'] ?? time()),
				'scope' => $scope,
				'level' => (string)($log['level'] ?? ''),
				'event' => (string)($log['event'] ?? ''),
				'user_id' => $log['userId'] ?? null,
				'run_id' => $log['runId'] ?? null,
				'status' => '',
				'message' => (string)($log['message'] ?? ''),
				'context_json' => $this->json($log['context'] ?? null),
			]);
		}
	}

	private function writeRow(mixed $stream, array $row): void {
		$createdAt = (int)($row['created_at_unix'] ?? time());
		fputcsv($stream, [
			$this->csvCell((string)($row['row_type'] ?? '')),
			gmdate('c', $createdAt),
			$createdAt,
			$this->csvCell((string)($row['scope'] ?? '')),
			$this->csvCell((string)($row['level'] ?? '')),
			$this->csvCell((string)($row['event'] ?? '')),
			$this->csvCell(($row['user_id'] ?? null) !== null ? (string)$row['user_id'] : ''),
			($row['run_id'] ?? null) !== null ? (string)$row['run_id'] : '',
			$this->csvCell((string)($row['status'] ?? '')),
			$this->csvCell((string)($row['message'] ?? ''), self::MAX_CSV_MESSAGE_LENGTH),
			$this->csvCell((string)($row['context_json'] ?? ''), self::MAX_CSV_CELL_LENGTH),
		]);
	}

	private function exportFolder(): ISimpleFolder {
		return $this->folder($this->folder($this->appData, 'diagnostics'), 'csv');
	}

	private function folder(IAppData|ISimpleFolder $root, string $name): ISimpleFolder {
		try {
			return $root->getFolder($name);
		} catch (NotFoundException) {
			return $root->newFolder($name);
		}
	}

	private function filename(string $scope, ?string $userId, int $createdAt): string {
		$user = $userId !== null && trim($userId) !== '' ? trim($userId) : 'all';
		$safeUser = mb_substr(preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $user) ?? 'user', 0, 80);
		return sprintf(
			'sakuraalbum-%s-%s-%s-%s.csv',
			preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $scope) ?: 'diagnostic',
			$safeUser,
			gmdate('Ymd-His', $createdAt),
			bin2hex(random_bytes(3)),
		);
	}

	private function json(mixed $value): string {
		if ($value === null || $value === []) {
			return '';
		}
		try {
			return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		} catch (\JsonException) {
			return '{"error":"json_encode_failed"}';
		}
	}

	private function limit(int $value, int $min, int $max): int {
		return max($min, min($max, $value));
	}

	private function csvCell(string $value, int $maxLength = 512): string {
		$value = str_replace("\0", '', $value);
		$value = mb_substr($value, 0, max(1, min(self::MAX_CSV_CELL_LENGTH, $maxLength)));
		if ($value !== '' && preg_match('/^[=\-+@\t\r\n]/', $value) === 1) {
			return '\'' . $value;
		}

		return $value;
	}

	private function pruneStoredCsv(ISimpleFolder $folder): void {
		$now = time();
		$files = [];
		foreach ($folder->getDirectoryListing() as $node) {
			if (!$node instanceof ISimpleFile) {
				continue;
			}
			$name = $node->getName();
			if (!str_starts_with($name, 'sakuraalbum-') || !str_ends_with($name, '.csv')) {
				continue;
			}
			$mtime = $node->getMTime();
			if ($mtime < $now - self::MAX_STORED_CSV_AGE_SECONDS) {
				try {
					$node->delete();
				} catch (\Throwable) {
				}
				continue;
			}
			$files[] = [
				'name' => $name,
				'mtime' => $mtime,
				'file' => $node,
			];
		}

		usort($files, static fn (array $left, array $right): int => $right['mtime'] <=> $left['mtime']);
		foreach (array_slice($files, self::MAX_STORED_CSV_FILES) as $entry) {
			try {
				$entry['file']->delete();
			} catch (\Throwable) {
			}
		}
	}
}
