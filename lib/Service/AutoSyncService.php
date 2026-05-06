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
		$dirtyPath = $this->affectedAutoSyncPath($userId, $path);
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
		if (($auto['windowActive'] ?? true) !== true) {
			$summary = [
				'processedUsers' => 0,
				'succeededUsers' => 0,
				'failedUsers' => 0,
				'skippedUsers' => 0,
				'dueUsers' => 0,
				'recoveredStaleLocks' => 0,
				'pendingEventsSeen' => 0,
				'lockedEvents' => 0,
				'eventLimitHits' => 0,
				'continuedUsers' => 0,
				'skipped' => 'outside_auto_sync_window',
				'windowStart' => $auto['windowStart'] ?? '',
				'windowEnd' => $auto['windowEnd'] ?? '',
				'nextWindowAt' => $auto['nextWindowAt'] ?? null,
			];
			$this->logService->debug('auto_sync_window_closed', null, [
				'summary' => $summary,
			], 'Automatic SakuraAlbum sync is waiting for the configured low-load window.');
			return $summary;
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
			'continuedUsers' => 0,
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
				$settings = $this->settingsService->getEffectiveUserSettings($userId);
				if (($settings['enabled'] ?? false) !== true || ($settings['autoSyncActive'] ?? false) !== true) {
					$this->dirtyPathMapper->markUserProcessed($userId, $lockTime);
					$summary['skippedUsers']++;
					$this->logService->info('auto_sync_user_skipped_disabled', $userId, [
						'pendingEvents' => $pending,
						'lockedEvents' => $locked,
						'enabled' => $settings['enabled'] ?? false,
						'autoSyncActive' => $settings['autoSyncActive'] ?? false,
					], 'Automatic SakuraAlbum sync skipped queued work because the user or automatic opt-in is disabled.');
					continue;
				}
				$this->logService->debug('auto_sync_user_started', $userId, [
					'pendingEvents' => $pending,
					'lockedEvents' => $locked,
					'limits' => [
						'maxEventsPerRun' => (int)$auto['maxEventsPerRun'],
						'maxRuntimeSeconds' => (int)$auto['maxRuntimeSeconds'],
					],
				]);
				$write = $this->albumSyncService->writeChunk($userId);
				$this->dirtyPathMapper->markUserProcessed($userId, $lockTime);
				if (($write['hasMore'] ?? false) === true) {
					$this->queueUserRefresh($userId, 'chunk_continue');
					$summary['continuedUsers'] = (int)($summary['continuedUsers'] ?? 0) + 1;
				}
				$this->logService->success('auto_sync_user_completed', $userId, [
					'pendingEvents' => $pending,
					'lockedEvents' => $locked,
					'summary' => $write['summary'] ?? [],
					'hasMore' => $write['hasMore'] ?? false,
				], ($write['hasMore'] ?? false) === true ? 'Automatic SakuraAlbum sync completed one chunk and queued the next one.' : 'Automatic SakuraAlbum sync completed.');
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
			'processingEnabled' => $auto['mode'] === 'file_events' && ($auto['windowActive'] ?? true) === true,
			'windowActive' => $auto['windowActive'] ?? true,
			'windowReason' => $auto['windowReason'] ?? 'always_open',
			'windowStart' => $auto['windowStart'] ?? '',
			'windowEnd' => $auto['windowEnd'] ?? '',
			'nextWindowAt' => $auto['nextWindowAt'] ?? null,
			'now' => $now,
			'debounceSeconds' => (int)$auto['debounceSeconds'],
			'maxUsersPerRun' => (int)$auto['maxUsersPerRun'],
			'maxRuntimeSeconds' => (int)$auto['maxRuntimeSeconds'],
			'maxEventsPerRun' => (int)$auto['maxEventsPerRun'],
			'dueBefore' => $notAfter,
			'dueUsers' => $auto['mode'] === 'file_events' && ($auto['windowActive'] ?? true) === true ? $this->dirtyPathMapper->countDueUsers($notAfter) : 0,
			'dueUsersWaitingForWindow' => $auto['mode'] === 'file_events' && ($auto['windowActive'] ?? true) !== true ? $this->dirtyPathMapper->countDueUsers($notAfter) : 0,
			'oldestPendingAt' => $oldestPendingAt,
			'nextDueAt' => $nextDueAt !== null ? max($now, $nextDueAt) : null,
			'counts' => $this->dirtyPathMapper->countAllByStatus(),
			'samples' => $this->dirtyPathMapper->findQueueSamples($sampleLimit),
		];
	}

	public function queueStatusForUser(string $userId, int $sampleLimit = 8): array {
		$auto = $this->settingsService->getAutoSyncSettings();
		$now = time();
		$oldestPendingAt = $this->dirtyPathMapper->oldestPendingAtForUser($userId);
		$nextDueAt = $this->dirtyPathMapper->nextPendingDueAtForUser($userId, (int)$auto['debounceSeconds']);

		return [
			'mode' => $auto['mode'],
			'enabled' => $auto['mode'] === 'file_events',
			'processingEnabled' => $auto['mode'] === 'file_events' && ($auto['windowActive'] ?? true) === true,
			'windowActive' => $auto['windowActive'] ?? true,
			'windowReason' => $auto['windowReason'] ?? 'always_open',
			'windowStart' => $auto['windowStart'] ?? '',
			'windowEnd' => $auto['windowEnd'] ?? '',
			'nextWindowAt' => $auto['nextWindowAt'] ?? null,
			'now' => $now,
			'debounceSeconds' => (int)$auto['debounceSeconds'],
			'nextDueAt' => $nextDueAt !== null ? max($now, $nextDueAt) : null,
			'oldestPendingAt' => $oldestPendingAt,
			'counts' => $this->dirtyPathMapper->countByStatusForUser($userId),
			'samples' => $this->dirtyPathMapper->findQueueSamplesForUser($userId, $sampleLimit),
		];
	}

	public function queueUserRefresh(string $userId, string $eventType = 'settings_update'): array {
		$auto = $this->settingsService->getAutoSyncSettings();
		$settings = $this->settingsService->getEffectiveUserSettings($userId);
		if ($auto['mode'] !== 'file_events') {
			return [
				'queued' => false,
				'reason' => 'admin_auto_sync_disabled',
				'queuedPaths' => [],
			];
		}
		if (($settings['adminGroupAllowed'] ?? true) !== true) {
			return [
				'queued' => false,
				'reason' => 'admin_group_not_allowed',
				'queuedPaths' => [],
			];
		}
		if (($settings['enabled'] ?? false) !== true || ($settings['autoSyncActive'] ?? false) !== true) {
			return [
				'queued' => false,
				'reason' => 'user_auto_sync_disabled',
				'queuedPaths' => [],
			];
		}

		$paths = [];
		foreach (($settings['sourceFolders'] ?? []) as $source) {
			if (($source['enabled'] ?? true) !== true) {
				continue;
			}
			$paths[] = (string)($source['path'] ?? '');
		}
		if ($paths === []) {
			$paths = $settings['includePaths'] ?? [];
		}

		$queuedPaths = [];
		$now = time();
		foreach ($paths as $path) {
			try {
				$path = PathHelper::normalizeUserPath((string)$path);
			} catch (\InvalidArgumentException) {
				continue;
			}
			$storedPath = $path === '' ? '/' : $path;
			$this->dirtyPathMapper->markDirty($userId, $storedPath, $eventType, $now);
			$queuedPaths[] = PathHelper::displayPath($storedPath);
		}

		$this->logService->debug('auto_sync_user_refresh_queued', $userId, [
			'eventType' => $eventType,
			'queuedPaths' => $queuedPaths,
		]);

		return [
			'queued' => $queuedPaths !== [],
			'reason' => $queuedPaths === [] ? 'no_source_folders' : '',
			'queuedPaths' => $queuedPaths,
			'queue' => $this->queueStatusForUser($userId),
		];
	}

	private function affectedAutoSyncPath(string $userId, string $path): ?string {
		$settings = $this->settingsService->getEffectiveUserSettings($userId);
		if (($settings['enabled'] ?? false) !== true || ($settings['autoSyncActive'] ?? false) !== true) {
			return null;
		}

		$normalPath = PathHelper::normalizeUserPath($path);
		foreach ($this->autoSyncQueuePaths($settings) as $syncPath) {
			$normalSyncPath = PathHelper::normalizeUserPath($syncPath);
			if ($normalSyncPath === '') {
				return '/';
			}
			if ($normalPath === $normalSyncPath || str_starts_with($normalPath, $normalSyncPath . '/')) {
				return '/' . $normalSyncPath;
			}
		}

		return null;
	}

	private function autoSyncQueuePaths(array $settings): array {
		$paths = [];
		foreach (($settings['sourceFolders'] ?? []) as $source) {
			if (!is_array($source) || (($source['enabled'] ?? true) !== true)) {
				continue;
			}
			$path = (string)($source['path'] ?? '');
			if ($path === '') {
				continue;
			}
			$paths[] = $path;
		}

		if ($paths === []) {
			$paths = array_merge($paths, $settings['includePaths'] ?? []);
		}

		$unique = [];
		foreach ($paths as $path) {
			if (!is_string($path)) {
				continue;
			}
			$normalized = PathHelper::normalizeUserPath($path);
			$key = mb_strtolower($normalized);
			$unique[$key] = '/'. $normalized;
		}

		return array_values($unique);
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
