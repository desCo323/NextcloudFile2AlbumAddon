<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\Db\DirtyPathMapper;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\IUser;

class AutoSyncService {
	public function __construct(
		private readonly SettingsService $settingsService,
		private readonly DirtyPathMapper $dirtyPathMapper,
		private readonly AlbumSyncService $albumSyncService,
		private readonly LogService $logService,
	) {
	}

	public function recordNodeChange(Node $node, string $eventType): void {
		$auto = $this->settingsService->getAutoSyncSettings();
		if ($auto['mode'] !== 'file_events') {
			return;
		}

		$user = $node->getOwner();
		if (!$user instanceof IUser) {
			return;
		}

		$userId = $user->getUID();
		$path = $this->relativeUserPath($node, $userId);
		if ($path === null) {
			return;
		}
		$dirtyPath = $this->affectedIncludePath($userId, $path);
		if ($dirtyPath === null) {
			return;
		}

		$now = time();
		$this->dirtyPathMapper->markDirty($userId, $dirtyPath, $eventType, $now);
		$this->logService->debug('auto_sync_dirty_path_recorded', $userId, [
			'path' => $dirtyPath,
			'sourcePath' => $path,
			'eventType' => $eventType,
		]);
	}

	public function processDueChanges(): array {
		$auto = $this->settingsService->getAutoSyncSettings();
		if ($auto['mode'] !== 'file_events') {
			return [
				'processedUsers' => 0,
				'skipped' => 'auto_sync_disabled',
			];
		}

		$started = time();
		$staleOlderThan = $started - max(
			600,
			((int)$auto['debounceSeconds']) * 2,
			((int)$auto['maxRuntimeSeconds']) * 2,
		);
		$recoveredLocks = $this->dirtyPathMapper->releaseStaleProcessing($staleOlderThan, (int)$auto['maxEventsPerRun']);
		if ($recoveredLocks > 0) {
			$this->logService->warning('auto_sync_stale_locks_recovered', null, [
				'recoveredLocks' => $recoveredLocks,
				'olderThan' => $staleOlderThan,
			], 'Stale automatic sync locks were returned to the pending queue.');
		}

		$notAfter = $started - (int)$auto['debounceSeconds'];
		$users = $this->dirtyPathMapper->findDueUsers($notAfter, (int)$auto['maxUsersPerRun']);
		$summary = [
			'processedUsers' => 0,
			'succeededUsers' => 0,
			'failedUsers' => 0,
			'skippedUsers' => 0,
			'dueUsers' => count($users),
			'recoveredStaleLocks' => $recoveredLocks,
			'pendingEventsSeen' => 0,
			'lockedEvents' => 0,
			'eventLimitHits' => 0,
		];

		foreach ($users as $userId) {
			if ((time() - $started) >= (int)$auto['maxRuntimeSeconds']) {
				$summary['stoppedReason'] = 'runtime_limit';
				break;
			}

			$pending = $this->dirtyPathMapper->countPendingForUser($userId);
			$lockTime = time();
			$locked = $this->dirtyPathMapper->markUserProcessing($userId, $lockTime, (int)$auto['maxEventsPerRun'], $notAfter);
			if ($locked === 0) {
				$summary['skippedUsers']++;
				continue;
			}
			$summary['processedUsers']++;
			$summary['pendingEventsSeen'] += $pending;
			$summary['lockedEvents'] += $locked;
			if ($locked < $pending) {
				$summary['eventLimitHits']++;
			}

			try {
				$this->logService->debug('auto_sync_user_started', $userId, [
					'pendingEvents' => $pending,
					'lockedEvents' => $locked,
					'limits' => [
						'maxEventsPerRun' => (int)$auto['maxEventsPerRun'],
						'maxRuntimeSeconds' => (int)$auto['maxRuntimeSeconds'],
					],
				]);
				$dryRun = $this->albumSyncService->dryRun($userId);
				if (($dryRun['canWrite'] ?? false) !== true || ($dryRun['planFingerprint'] ?? '') === '') {
					$this->dirtyPathMapper->markUserFailed($userId, 'Automatic sync dry-run is not safe to write.', time());
					$this->logService->warning('auto_sync_dry_run_blocked', $userId, [
						'pendingEvents' => $pending,
						'lockedEvents' => $locked,
						'summary' => $dryRun['summary'] ?? [],
						'issues' => $dryRun['writeBlockedReasons'] ?? [],
					], 'Automatic SakuraAlbum sync was blocked by dry-run safety checks.');
					$summary['failedUsers']++;
					continue;
				}

				$write = $this->albumSyncService->write($userId, AlbumSyncService::WRITE_CONFIRMATION, (string)$dryRun['planFingerprint']);
				$this->dirtyPathMapper->markUserProcessed($userId, $lockTime);
				$this->logService->success('auto_sync_user_completed', $userId, [
					'pendingEvents' => $pending,
					'lockedEvents' => $locked,
					'summary' => $write['summary'] ?? [],
				], 'Automatic SakuraAlbum sync completed.');
				$summary['succeededUsers']++;
			} catch (\Throwable $e) {
				$this->dirtyPathMapper->markUserFailed($userId, $e->getMessage(), time());
				$this->logService->exception('auto_sync_user_failed', $e, $userId, [
					'pendingEvents' => $pending,
					'lockedEvents' => $locked,
				]);
				$summary['failedUsers']++;
			}
		}

		if (($summary['stoppedReason'] ?? '') === 'runtime_limit') {
			$this->logService->warning('auto_sync_runtime_limit_reached', null, [
				'summary' => $summary,
				'maxRuntimeSeconds' => (int)$auto['maxRuntimeSeconds'],
			], 'Automatic SakuraAlbum sync stopped because the runtime limit was reached.');
		}

		$this->logService->debug('auto_sync_process_completed', null, [
			'summary' => $summary,
		]);

		return $summary;
	}

	public function queueStatus(int $sampleLimit = 12): array {
		$auto = $this->settingsService->getAutoSyncSettings();
		$now = time();
		$notAfter = $now - (int)$auto['debounceSeconds'];
		$oldestPendingAt = $this->dirtyPathMapper->oldestPendingAt();
		$nextDueAt = $this->dirtyPathMapper->nextPendingDueAt((int)$auto['debounceSeconds']);

		return [
			'mode' => $auto['mode'],
			'enabled' => $auto['mode'] === 'file_events',
			'now' => $now,
			'debounceSeconds' => (int)$auto['debounceSeconds'],
			'maxUsersPerRun' => (int)$auto['maxUsersPerRun'],
			'maxRuntimeSeconds' => (int)$auto['maxRuntimeSeconds'],
			'maxEventsPerRun' => (int)$auto['maxEventsPerRun'],
			'dueBefore' => $notAfter,
			'dueUsers' => $auto['mode'] === 'file_events' ? $this->dirtyPathMapper->countDueUsers($notAfter) : 0,
			'oldestPendingAt' => $oldestPendingAt,
			'nextDueAt' => $nextDueAt !== null ? max($now, $nextDueAt) : null,
			'counts' => $this->dirtyPathMapper->countAllByStatus(),
			'samples' => $this->dirtyPathMapper->findQueueSamples($sampleLimit),
		];
	}

	private function affectedIncludePath(string $userId, string $path): ?string {
		$settings = $this->settingsService->getEffectiveUserSettings($userId);
		if (($settings['enabled'] ?? false) !== true) {
			return null;
		}

		foreach (($settings['includePaths'] ?? []) as $includePath) {
			$includePath = trim((string)$includePath, '/');
			if ($includePath === '' || $path === $includePath || str_starts_with($path, $includePath . '/')) {
				return $includePath === '' ? '/' : $includePath;
			}
		}

		return null;
	}

	private function relativeUserPath(Node $node, string $userId): ?string {
		$path = $node->getPath();
		$prefix = '/' . $userId . '/files/';
		if (str_starts_with($path, $prefix)) {
			return trim(substr($path, strlen($prefix)), '/');
		}

		$internalPath = trim($node->getInternalPath(), '/');
		if ($internalPath === '' || str_starts_with($internalPath, 'files_trashbin/')) {
			return null;
		}

		return $internalPath;
	}
}
