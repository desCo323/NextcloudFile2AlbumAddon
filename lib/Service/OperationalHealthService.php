<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\Db\ManagedAlbumMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;

class OperationalHealthService {
	public function __construct(
		private readonly SettingsService $settingsService,
		private readonly ManagedAlbumMapper $managedAlbumMapper,
		private readonly IDBConnection $db,
		private readonly IConfig $config,
	) {
	}

	public function adminHealth(?string $userId = null): array {
		$now = time();
		$admin = $this->settingsService->getAdminSettings();
		$auto = $this->settingsService->getAutoSyncSettings();
		$thresholds = $this->thresholds($auto);
		$issues = [];

		if (($admin['debugMode'] ?? false) !== true) {
			$issues[] = $this->issue('debug_disabled', 'warning', 'Debug logging is disabled; diagnostic reports will contain less operational context.');
		}
		if (($auto['globalEnabled'] ?? false) !== true) {
			$issues[] = $this->issue('auto_sync_global_disabled', 'warning', 'Automatic SakuraAlbum sync is globally disabled.');
		}
		if (($auto['mode'] ?? 'manual') !== 'file_events') {
			$issues[] = $this->issue('auto_sync_manual_mode', 'warning', 'Automatic SakuraAlbum sync is in manual mode.');
		}
		if (($auto['windowActive'] ?? true) !== true) {
			$issues[] = $this->issue('auto_sync_window_closed', 'warning', 'Automatic sync is waiting for the configured maintenance window.', [
				'nextWindowAt' => $auto['nextWindowAt'] ?? null,
				'windowStart' => $auto['windowStart'] ?? '',
				'windowEnd' => $auto['windowEnd'] ?? '',
			]);
		}

		$queue = $this->queueHealth($now, $thresholds, $userId);
		$issues = array_merge($issues, $queue['issues']);

		$runs = $this->runHealth($now, $thresholds, $userId);
		$issues = array_merge($issues, $runs['issues']);

		$cursors = $this->cursorHealth($now, $thresholds, $userId);
		$issues = array_merge($issues, $cursors['issues']);

		$exports = $this->exportHealth($now, $thresholds, $userId);
		$issues = array_merge($issues, $exports['issues']);

		$logs = $this->logHealth($now, $userId);
		$issues = array_merge($issues, $logs['issues']);

		$missingManagedAlbums = $userId !== null
			? $this->managedAlbumMapper->countMissingPhotosAlbumsForUser($userId)
			: $this->managedAlbumMapper->countMissingPhotosAlbums();
		if ($missingManagedAlbums > 0) {
			$issues[] = $this->issue('missing_managed_photos_albums', 'warning', 'SakuraAlbum tracking rows point to Photos albums that no longer exist.', [
				'count' => $missingManagedAlbums,
			]);
		}

		$cron = $this->cronHealth($now, $thresholds);
		$issues = array_merge($issues, $cron['issues']);

		return [
			'status' => $this->overallStatus($issues),
			'createdAt' => $now,
			'scope' => $userId !== null ? 'user' : 'admin',
			'userId' => $userId,
			'thresholds' => $thresholds,
			'summary' => [
				'issueCount' => count($issues),
				'criticalCount' => count(array_filter($issues, static fn (array $issue): bool => ($issue['severity'] ?? '') === 'critical')),
				'warningCount' => count(array_filter($issues, static fn (array $issue): bool => ($issue['severity'] ?? '') === 'warning')),
				'debugMode' => (bool)($admin['debugMode'] ?? false),
				'autoSyncMode' => $auto['mode'] ?? 'manual',
				'autoSyncWindowActive' => (bool)($auto['windowActive'] ?? true),
				'missingManagedPhotosAlbums' => $missingManagedAlbums,
			],
			'queue' => $queue['summary'],
			'runs' => $runs['summary'],
			'cursors' => $cursors['summary'],
			'exports' => $exports['summary'],
			'logs' => $logs['summary'],
			'cron' => $cron['summary'],
			'issues' => $issues,
		];
	}

	public function userHealth(string $userId): array {
		return $this->adminHealth($userId);
	}

	private function thresholds(array $auto): array {
		$debounce = max(30, (int)($auto['debounceSeconds'] ?? 300));
		$runtime = max(5, (int)($auto['maxRuntimeSeconds'] ?? 30));
		return [
			'queueDueGraceSeconds' => max(600, $debounce * 4, $runtime * 4),
			'processingStaleSeconds' => max(600, $debounce * 2, $runtime * 2),
			'runningRunStaleSeconds' => max(600, $runtime * 3),
			'cursorStaleSeconds' => max(900, $debounce * 4, $runtime * 6),
			'exportStaleSeconds' => 3600,
			'cronStaleSeconds' => 900,
			'recentLogWindowSeconds' => 86400,
		];
	}

	private function queueHealth(int $now, array $thresholds, ?string $userId): array {
		$counts = $this->countByStatus('sakuraalbum_dirty_paths', 'status', $userId);
		$oldestPending = $this->minColumn('sakuraalbum_dirty_paths', 'last_seen_at', 'pending', $userId);
		$oldestProcessing = $this->minColumn('sakuraalbum_dirty_paths', 'locked_at', 'processing', $userId);
		$oldestFailed = $this->minColumn('sakuraalbum_dirty_paths', 'last_seen_at', 'failed', $userId);
		$issues = [];

		if (($counts['failed'] ?? 0) > 0) {
			$issues[] = $this->issue('failed_auto_sync_queue_entries', 'critical', 'Automatic sync has failed queue entries.', [
				'failed' => $counts['failed'],
				'oldestFailedAt' => $oldestFailed,
			]);
		}
		if ($oldestProcessing !== null && ($now - $oldestProcessing) > $thresholds['processingStaleSeconds']) {
			$issues[] = $this->issue('stale_auto_sync_processing_lock', 'critical', 'Automatic sync has a stale processing lock.', [
				'oldestProcessingAt' => $oldestProcessing,
				'ageSeconds' => $now - $oldestProcessing,
			]);
		}
		if ($oldestPending !== null && ($now - $oldestPending) > $thresholds['queueDueGraceSeconds']) {
			$issues[] = $this->issue('overdue_auto_sync_queue', 'warning', 'Automatic sync has pending work older than the configured grace period.', [
				'oldestPendingAt' => $oldestPending,
				'ageSeconds' => $now - $oldestPending,
			]);
		}

		return [
			'summary' => [
				'counts' => $counts,
				'oldestPendingAt' => $oldestPending,
				'oldestProcessingAt' => $oldestProcessing,
				'oldestFailedAt' => $oldestFailed,
			],
			'issues' => $issues,
		];
	}

	private function runHealth(int $now, array $thresholds, ?string $userId): array {
		$running = $this->countRows('sakuraalbum_runs', ['status' => 'running'], $userId);
		$failedRecent = $this->countRowsSince('sakuraalbum_runs', 'started_at', $now - $thresholds['recentLogWindowSeconds'], ['status' => 'failed'], $userId);
		$oldestRunning = $this->minColumn('sakuraalbum_runs', 'started_at', 'running', $userId);
		$issues = [];

		if ($oldestRunning !== null && ($now - $oldestRunning) > $thresholds['runningRunStaleSeconds']) {
			$issues[] = $this->issue('stale_running_sync_run', 'critical', 'A SakuraAlbum sync run is still marked running after the stale threshold.', [
				'oldestRunningAt' => $oldestRunning,
				'ageSeconds' => $now - $oldestRunning,
			]);
		}
		if ($failedRecent > 0) {
			$issues[] = $this->issue('recent_failed_sync_runs', 'warning', 'Recent SakuraAlbum sync runs failed.', [
				'failedRecent' => $failedRecent,
			]);
		}

		return [
			'summary' => [
				'running' => $running,
				'failedRecent' => $failedRecent,
				'oldestRunningAt' => $oldestRunning,
			],
			'issues' => $issues,
		];
	}

	private function cursorHealth(int $now, array $thresholds, ?string $userId): array {
		$pending = $this->countRows('sakuraalbum_sync_cursors', ['status' => 'pending'], $userId);
		$failed = $this->countRows('sakuraalbum_sync_cursors', ['status' => 'failed'], $userId);
		$oldestPending = $this->minColumn('sakuraalbum_sync_cursors', 'updated_at', 'pending', $userId);
		$issues = [];

		if ($failed > 0) {
			$issues[] = $this->issue('failed_sync_cursors', 'critical', 'Chunked background sync has failed cursors.', [
				'failed' => $failed,
			]);
		}
		if ($oldestPending !== null && ($now - $oldestPending) > $thresholds['cursorStaleSeconds']) {
			$issues[] = $this->issue('stale_pending_sync_cursor', 'warning', 'A chunked background sync cursor has not progressed recently.', [
				'oldestPendingAt' => $oldestPending,
				'ageSeconds' => $now - $oldestPending,
			]);
		}

		return [
			'summary' => [
				'pending' => $pending,
				'failed' => $failed,
				'oldestPendingAt' => $oldestPending,
			],
			'issues' => $issues,
		];
	}

	private function exportHealth(int $now, array $thresholds, ?string $userId): array {
		$pending = $this->countRows('sakuraalbum_download_jobs', ['status' => 'pending'], $userId);
		$running = $this->countRows('sakuraalbum_download_jobs', ['status' => 'running'], $userId);
		$failedRecent = $this->countRowsSince('sakuraalbum_download_jobs', 'updated_at', $now - $thresholds['recentLogWindowSeconds'], ['status' => 'failed'], $userId);
		$oldestActive = $this->oldestExportActiveAt($userId);
		$issues = [];

		if ($failedRecent > 0) {
			$issues[] = $this->issue('recent_failed_album_exports', 'warning', 'Recent album export jobs failed.', [
				'failedRecent' => $failedRecent,
			]);
		}
		if ($oldestActive !== null && ($now - $oldestActive) > $thresholds['exportStaleSeconds']) {
			$issues[] = $this->issue('stale_album_export_job', 'warning', 'An album export job is pending or running longer than expected.', [
				'oldestActiveAt' => $oldestActive,
				'ageSeconds' => $now - $oldestActive,
			]);
		}

		return [
			'summary' => [
				'pending' => $pending,
				'running' => $running,
				'failedRecent' => $failedRecent,
				'oldestActiveAt' => $oldestActive,
			],
			'issues' => $issues,
		];
	}

	private function logHealth(int $now, ?string $userId): array {
		$since = $now - 86400;
		$errors = $this->countLogRowsSince('error', $since, $userId);
		$warnings = $this->countLogRowsSince('warning', $since, $userId);
		$issues = [];

		if ($errors > 0) {
			$issues[] = $this->issue('recent_error_logs', 'critical', 'SakuraAlbum recorded error logs in the last 24 hours.', [
				'errors' => $errors,
			]);
		}
		if ($warnings > 0) {
			$issues[] = $this->issue('recent_warning_logs', 'warning', 'SakuraAlbum recorded warning logs in the last 24 hours.', [
				'warnings' => $warnings,
			]);
		}

		return [
			'summary' => [
				'errorsLast24h' => $errors,
				'warningsLast24h' => $warnings,
			],
			'issues' => $issues,
		];
	}

	private function cronHealth(int $now, array $thresholds): array {
		$lastCron = (int)$this->config->getAppValue('core', 'lastcron', '0');
		$mode = (string)$this->config->getAppValue('core', 'backgroundjobs_mode', '');
		$issues = [];

		if ($lastCron <= 0) {
			$issues[] = $this->issue('nextcloud_cron_not_recorded', 'critical', 'Nextcloud has not recorded a Cron run.');
		} elseif (($now - $lastCron) > $thresholds['cronStaleSeconds']) {
			$issues[] = $this->issue('nextcloud_cron_stale', 'critical', 'Nextcloud Cron is older than the SakuraAlbum health threshold.', [
				'lastCronAt' => $lastCron,
				'ageSeconds' => $now - $lastCron,
			]);
		}

		return [
			'summary' => [
				'backgroundJobsMode' => $mode,
				'lastCronAt' => $lastCron > 0 ? $lastCron : null,
				'lastCronAgeSeconds' => $lastCron > 0 ? $now - $lastCron : null,
			],
			'issues' => $issues,
		];
	}

	private function countByStatus(string $table, string $statusColumn, ?string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select($statusColumn)
			->selectAlias($qb->func()->count('*'), 'row_count')
			->from($table)
			->groupBy($statusColumn);
		$this->filterUser($qb, $table, $userId);

		$result = ['pending' => 0, 'processing' => 0, 'running' => 0, 'failed' => 0, 'total' => 0];
		foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
			$status = (string)($row[$statusColumn] ?? '');
			$count = (int)($row['row_count'] ?? 0);
			$result[$status] = $count;
			$result['total'] += $count;
		}

		return $result;
	}

	private function minColumn(string $table, string $column, string $status, ?string $userId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->min($column), 'min_value')
			->from($table)
			->where($qb->expr()->eq('status', $qb->createNamedParameter($status)))
			->andWhere($qb->expr()->isNotNull($column));
		$this->filterUser($qb, $table, $userId, hasWhere: true);

		$value = $qb->executeQuery()->fetchOne();
		return $value !== false && $value !== null ? (int)$value : null;
	}

	private function countRows(string $table, array $equals, ?string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))->from($table);
		$this->applyEquals($qb, $equals);
		$this->filterUser($qb, $table, $userId, hasWhere: $equals !== []);
		return (int)$qb->executeQuery()->fetchOne();
	}

	private function countRowsSince(string $table, string $timeColumn, int $since, array $equals, ?string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($table)
			->where($qb->expr()->gte($timeColumn, $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)));
		$this->applyEquals($qb, $equals, hasWhere: true);
		$this->filterUser($qb, $table, $userId, hasWhere: true);
		return (int)$qb->executeQuery()->fetchOne();
	}

	private function countLogRowsSince(string $level, int $since, ?string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from('sakuraalbum_logs')
			->where($qb->expr()->gte('created_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('level', $qb->createNamedParameter($level)))
			->andWhere($qb->expr()->notIn('event', $qb->createNamedParameter([
				'diagnostic_health_issues_detected',
				'admin_diagnostic_health_issues_detected',
			], IQueryBuilder::PARAM_STR_ARRAY)));
		$this->filterUser($qb, 'sakuraalbum_logs', $userId, hasWhere: true);
		return (int)$qb->executeQuery()->fetchOne();
	}

	private function oldestExportActiveAt(?string $userId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->min('updated_at'), 'oldest_active_at')
			->from('sakuraalbum_download_jobs')
			->where($qb->expr()->in('status', $qb->createNamedParameter(['pending', 'running'], IQueryBuilder::PARAM_STR_ARRAY)));
		$this->filterUser($qb, 'sakuraalbum_download_jobs', $userId, hasWhere: true);
		$value = $qb->executeQuery()->fetchOne();
		return $value !== false && $value !== null ? (int)$value : null;
	}

	private function applyEquals(IQueryBuilder $qb, array $equals, bool $hasWhere = false): void {
		foreach ($equals as $column => $value) {
			$expr = $qb->expr()->eq((string)$column, $qb->createNamedParameter($value));
			if ($hasWhere) {
				$qb->andWhere($expr);
			} else {
				$qb->where($expr);
				$hasWhere = true;
			}
		}
	}

	private function filterUser(IQueryBuilder $qb, string $table, ?string $userId, bool $hasWhere = false): void {
		if ($userId === null) {
			return;
		}
		$column = $table === 'photos_albums' ? 'user' : 'user_id';
		$expr = $qb->expr()->eq($column, $qb->createNamedParameter($userId));
		if ($hasWhere) {
			$qb->andWhere($expr);
			return;
		}
		$qb->where($expr);
	}

	private function issue(string $code, string $severity, string $message, array $context = []): array {
		return [
			'code' => $code,
			'severity' => $severity,
			'message' => $message,
			'context' => $context,
		];
	}

	private function overallStatus(array $issues): string {
		foreach ($issues as $issue) {
			if (($issue['severity'] ?? '') === 'critical') {
				return 'critical';
			}
		}
		return $issues === [] ? 'ok' : 'warning';
	}
}
