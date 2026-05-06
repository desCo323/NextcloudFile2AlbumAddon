<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\BackgroundJob\AutoSyncJob;
use OCA\SakuraAlbum\Db\DirtyPathMapper;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IUser;

class AutoSyncService {
	private const AUTO_SYNC_JOB_CLASS = 'OCA\\SakuraAlbum\\BackgroundJob\\AutoSyncJob';
	private const AUTO_SYNC_NUDGE_INTERVAL_SECONDS = 60;
	private const AUTO_SYNC_NUDGE_DELAY_SECONDS = 15;
	private int $autoSyncNudgeLastAt = 0;

	public function __construct(
		private readonly SettingsService $settingsService,
		private readonly DirtyPathMapper $dirtyPathMapper,
		private readonly AlbumSyncService $albumSyncService,
		private readonly LogService $logService,
		private readonly IDBConnection $db,
		private readonly IJobList $jobList,
		private readonly IConfig $config,
	) {
	}

	public function recordNodeChange(Node $node, string $eventType): void {
		try {
			$this->recordNodeChangeInternal($node, $eventType);
		} catch (\Throwable $e) {
			$this->logService->exception('auto_sync_file_event_failed', $e, null, [
				'eventType' => $eventType,
				'nodeClass' => $node::class,
			]);
		}
	}

	private function recordNodeChangeInternal(Node $node, string $eventType): void {
		$auto = $this->settingsService->getAutoSyncSettings();
		if (($auto['globalEnabled'] ?? false) !== true || $auto['mode'] !== 'file_events') {
			$this->logService->debug('auto_sync_file_event_ignored', null, [
				'reason' => ($auto['globalEnabled'] ?? false) !== true ? 'global_disabled' : 'manual_mode',
				'eventType' => $eventType,
			]);
			return;
		}

		$user = $node->getOwner();
		if (!$user instanceof IUser) {
			$this->logService->debug('auto_sync_file_event_ignored', null, [
				'reason' => 'missing_owner',
				'eventType' => $eventType,
				'nodePath' => $node->getPath(),
			]);
			return;
		}

		$userId = $user->getUID();
		$path = $this->relativeUserPath($node, $userId);
		if ($path === null) {
			$this->logService->debug('auto_sync_file_event_ignored', $userId, [
				'reason' => 'outside_user_files',
				'eventType' => $eventType,
				'nodePath' => $node->getPath(),
				'internalPath' => $node->getInternalPath(),
			]);
			return;
		}
		$dirtyPath = $this->affectedAutoSyncPath($userId, $path);
		if ($dirtyPath === null) {
			$this->logService->debug('auto_sync_file_event_ignored', $userId, [
				'reason' => 'outside_configured_sources',
				'eventType' => $eventType,
				'sourcePath' => $path,
			]);
			return;
		}

		$now = time();
		$this->dirtyPathMapper->markDirty($userId, $dirtyPath, $eventType, $now);
		$this->ensureAutoSyncRunnerQueued();
		$this->logService->debug('auto_sync_dirty_path_recorded', $userId, [
			'path' => $dirtyPath,
			'sourcePath' => $path,
			'eventType' => $eventType,
		]);
	}

	public function processDueChanges(): array {
		$auto = $this->settingsService->getAutoSyncSettings();
		if (($auto['globalEnabled'] ?? false) !== true) {
			$summary = [
				'processedUsers' => 0,
				'skipped' => 'auto_sync_disabled',
				'skippedReasons' => [
					'auto_sync_disabled' => 1,
				],
				'skippedUsers' => 0,
				'succeededUsers' => 0,
				'failedUsers' => 0,
				'dueUsers' => 0,
				'recoveredStaleLocks' => 0,
				'pendingEventsSeen' => 0,
				'lockedEvents' => 0,
				'eventLimitHits' => 0,
				'continuedUsers' => 0,
			];
			$this->logService->warning('auto_sync_disabled', null, [
				'summary' => $summary,
			], 'Automatic SakuraAlbum sync is disabled in global admin settings.');
			return $summary;
		}

		if ($auto['mode'] !== 'file_events') {
			return [
				'processedUsers' => 0,
				'skipped' => 'auto_sync_disabled',
				'skippedReasons' => [
					'auto_sync_disabled' => 1,
				],
				'skippedUsers' => 0,
				'succeededUsers' => 0,
				'failedUsers' => 0,
				'dueUsers' => 0,
				'recoveredStaleLocks' => 0,
				'pendingEventsSeen' => 0,
				'lockedEvents' => 0,
				'eventLimitHits' => 0,
				'continuedUsers' => 0,
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
				'skippedReasons' => [
					'outside_auto_sync_window' => 1,
				],
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
		$retryLater = $started + max(60, (int)$auto['debounceSeconds']);
		$retriedFolders = $this->dirtyPathMapper->requeueFailedForFolderUnavailable(6, $retryLater);
		if ($retriedFolders > 0) {
			$this->logService->warning('auto_sync_user_retry_queued', null, [
				'retriedFailedUsers' => $retriedFolders,
				'nextRetryAt' => $retryLater,
			], 'Automatic SakuraAlbum users with transient folder-unavailable state were requeued for a retry.');
		}

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
			'skippedReasons' => [],
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
				$summary['skippedReasons']['auto_sync_user_locked'] = ($summary['skippedReasons']['auto_sync_user_locked'] ?? 0) + 1;
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
					$summary['skippedReasons']['user_auto_sync_disabled'] = ($summary['skippedReasons']['user_auto_sync_disabled'] ?? 0) + 1;
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
				], ($write['hasMore'] ?? false) === true
					? 'Automatic SakuraAlbum sync completed one chunk and queued the next one.'
					: 'Automatic SakuraAlbum sync completed.');
				$summary['succeededUsers']++;
			} catch (\Throwable $e) {
				$queueError = $this->queueFailureMessage($e);
				if ($this->isTransientRetryableError($e)) {
					$retryAt = time() + max(120, (int)$auto['debounceSeconds']);
					$retried = $this->dirtyPathMapper->markUserRetry($userId, $retryAt, $queueError);
					if ($retried > 0) {
						$this->logService->warning('auto_sync_user_retry_scheduled', $userId, [
							'pendingEvents' => $pending,
							'lockedEvents' => $locked,
							'retryAt' => $retryAt,
							'errorClass' => $e::class,
						], 'Automatic SakuraAlbum sync detected a transient filesystem condition. The queue entry was moved back to pending with a retry delay.');
						$summary['continuedUsers'] = (int)($summary['continuedUsers'] ?? 0) + 1;
						$summary['skippedReasons']['auto_sync_user_retry_scheduled'] = ($summary['skippedReasons']['auto_sync_user_retry_scheduled'] ?? 0) + 1;
						continue;
					}
				}

				$this->dirtyPathMapper->markUserFailed($userId, $queueError, time());
				$this->logService->exception('auto_sync_user_failed', $e, $userId, [
					'pendingEvents' => $pending,
					'lockedEvents' => $locked,
				]);
				$summary['failedUsers']++;
			}

		}

		if (($summary['stoppedReason'] ?? '') === 'runtime_limit') {
			$summary['skippedReasons']['runtime_limit'] = 1;
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

	private function isTransientRetryableError(\Throwable $e): bool {
		if ($e instanceof SyncSafetyException) {
			if ($e->getErrorCode() === 'auto_chunk_plan_not_safe') {
				$details = $e->getDetails();
				foreach ($details['issues'] ?? [] as $issue) {
					if (($issue['code'] ?? '') === 'blocking_warning' && ($issue['warningCode'] ?? '') === 'user_folder_unavailable') {
						return true;
					}
				}
			}
		}

		$message = mb_strtolower($e->getMessage());
		$retriableTokens = [
			'user_folder_unavailable',
			'storage_unavailable',
			'filesystem',
			'temporary',
			'not available',
			'temporarily',
		];

		foreach ($retriableTokens as $token) {
			if (str_contains($message, (string)$token)) {
				return true;
			}
		}

		return false;
	}

	private function queueFailureMessage(\Throwable $e): string {
		if ($e instanceof SyncSafetyException) {
			$details = $e->getDetails();
			$warningCodes = [];
			foreach ($details['issues'] ?? [] as $issue) {
				if (($issue['code'] ?? '') === 'blocking_warning' && ($issue['warningCode'] ?? '') !== '') {
					$warningCodes[] = (string)$issue['warningCode'];
				}
			}

			if ($warningCodes !== []) {
				return sprintf(
					'%s: %s',
					$e->getErrorCode(),
					implode('|', $warningCodes),
				);
			}

			return sprintf('%s: %s', $e->getErrorCode(), $e->getMessage());
		}

		return mb_substr($e->getMessage(), 0, 1000);
	}

	public function queueStatus(int $sampleLimit = 12): array {
		$auto = $this->settingsService->getAutoSyncSettings();
		$job = $this->autoSyncJobHealth();
		$now = time();
		$notAfter = $now - (int)$auto['debounceSeconds'];
		$oldestPendingAt = $this->dirtyPathMapper->oldestPendingAt();
		$nextDueAt = $this->dirtyPathMapper->nextPendingDueAt((int)$auto['debounceSeconds']);
		$automationBlockingReason = null;
		if (($auto['globalEnabled'] ?? false) !== true) {
			$automationBlockingReason = 'global_disabled';
		} elseif ($auto['mode'] !== 'file_events') {
			$automationBlockingReason = 'manual_mode';
		} elseif (($job['backgroundJobsMode'] ?? 'cron') === 'cron' && !($job['backgroundJobsCronHealthy'] ?? true)) {
			$automationBlockingReason = $job['backgroundJobsCronReason'] === 'cron_not_recorded' ? 'cron_not_recorded' : 'cron_stale';
		} elseif (($auto['windowActive'] ?? true) !== true) {
			$automationBlockingReason = 'outside_window';
		} elseif (($job['exists'] ?? false) !== true) {
			$automationBlockingReason = 'missing_job_record';
		}

		return [
			'mode' => $auto['mode'],
			'enabled' => $auto['mode'] === 'file_events' && ($auto['globalEnabled'] ?? true) === true,
			'processingEnabled' => $auto['mode'] === 'file_events' && ($auto['globalEnabled'] ?? true) === true && ($auto['windowActive'] ?? true) === true,
			'globalEnabled' => $auto['globalEnabled'] ?? true,
			'automationBlockingReason' => $automationBlockingReason,
			'windowActive' => $auto['windowActive'] ?? true,
			'windowReason' => $auto['windowReason'] ?? 'always_open',
			'windowStart' => $auto['windowStart'] ?? '',
			'windowEnd' => $auto['windowEnd'] ?? '',
			'nextWindowAt' => $auto['nextWindowAt'] ?? null,
			'backgroundJobsMode' => $job['backgroundJobsMode'],
			'backgroundJobsLastCronAt' => $job['backgroundJobsLastCronAt'],
			'backgroundJobsCronAgeSeconds' => $job['backgroundJobsCronAgeSeconds'],
			'backgroundJobsCronHealthy' => $job['backgroundJobsCronHealthy'],
			'backgroundJobsCronReason' => $job['backgroundJobsCronReason'],
			'job' => $job,
			'now' => $now,
			'debounceSeconds' => (int)$auto['debounceSeconds'],
			'maxUsersPerRun' => (int)$auto['maxUsersPerRun'],
			'maxRuntimeSeconds' => (int)$auto['maxRuntimeSeconds'],
			'maxEventsPerRun' => (int)$auto['maxEventsPerRun'],
			'dueBefore' => $notAfter,
			'dueUsers' => $auto['mode'] === 'file_events' && ($auto['globalEnabled'] ?? true) === true && ($auto['windowActive'] ?? true) === true ? $this->dirtyPathMapper->countDueUsers($notAfter) : 0,
			'dueUsersWaitingForWindow' => $auto['mode'] === 'file_events' && ($auto['globalEnabled'] ?? true) === true && ($auto['windowActive'] ?? true) !== true ? $this->dirtyPathMapper->countDueUsers($notAfter) : 0,
			'oldestPendingAt' => $oldestPendingAt,
			'nextDueAt' => $nextDueAt !== null ? max($now, $nextDueAt) : null,
			'counts' => $this->dirtyPathMapper->countAllByStatus(),
			'samples' => $this->dirtyPathMapper->findQueueSamples($sampleLimit),
		];
	}

	public function queueStatusForUser(string $userId, int $sampleLimit = 8): array {
		$auto = $this->settingsService->getAutoSyncSettings();
		$job = $this->autoSyncJobHealth();
		$now = time();
		$oldestPendingAt = $this->dirtyPathMapper->oldestPendingAtForUser($userId);
		$nextDueAt = $this->dirtyPathMapper->nextPendingDueAtForUser($userId, (int)$auto['debounceSeconds']);

		return [
			'mode' => $auto['mode'],
			'enabled' => $auto['mode'] === 'file_events' && ($auto['globalEnabled'] ?? true) === true,
			'globalEnabled' => $auto['globalEnabled'] ?? true,
			'processingEnabled' => $auto['mode'] === 'file_events' && ($auto['globalEnabled'] ?? true) === true && ($auto['windowActive'] ?? true) === true,
			'windowActive' => $auto['windowActive'] ?? true,
			'windowReason' => $auto['windowReason'] ?? 'always_open',
			'windowStart' => $auto['windowStart'] ?? '',
			'windowEnd' => $auto['windowEnd'] ?? '',
			'nextWindowAt' => $auto['nextWindowAt'] ?? null,
			'backgroundJobsMode' => $job['backgroundJobsMode'],
			'backgroundJobsLastCronAt' => $job['backgroundJobsLastCronAt'],
			'backgroundJobsCronAgeSeconds' => $job['backgroundJobsCronAgeSeconds'],
			'backgroundJobsCronHealthy' => $job['backgroundJobsCronHealthy'],
			'backgroundJobsCronReason' => $job['backgroundJobsCronReason'],
			'job' => $job,
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
		if (($auto['globalEnabled'] ?? false) !== true) {
			return [
				'queued' => false,
				'reason' => 'admin_auto_sync_disabled_global',
				'queuedPaths' => [],
			];
		}
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
		if ($queuedPaths !== []) {
			$this->ensureAutoSyncRunnerQueued();
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

	private function autoSyncJobHealth(): array {
		$now = time();
		$admin = $this->settingsService->getAdminSettings();
		$configuredIntervalSeconds = max(60, (int)$admin['jobIntervalMinutes'] * 60);
		$backgroundJobs = $this->backgroundJobsState($configuredIntervalSeconds, $now);
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'last_run', 'last_checked', 'reserved_at', 'time_sensitive', 'argument')
			->from('jobs')
			->where($qb->expr()->eq('class', $qb->createNamedParameter(self::AUTO_SYNC_JOB_CLASS)))
			->setMaxResults(1);

		$row = $qb->executeQuery()->fetch();
		if (!is_array($row)) {
			return [
				'backgroundJobsMode' => $backgroundJobs['mode'],
				'backgroundJobsLastCronAt' => $backgroundJobs['lastCronAt'],
				'backgroundJobsCronAgeSeconds' => $backgroundJobs['lastCronAgeSeconds'],
				'backgroundJobsCronHealthy' => $backgroundJobs['cronHealthy'],
				'backgroundJobsCronReason' => $backgroundJobs['reason'],
				'exists' => false,
				'reason' => 'missing_job_record',
				'configuredIntervalSeconds' => $configuredIntervalSeconds,
				'status' => 'missing',
				'now' => $now,
				'lastRunAt' => null,
				'nextScheduledAt' => null,
				'lastCheckedAt' => null,
				'reservedAt' => null,
				'stale' => true,
				'jobAgeSeconds' => null,
			];
		}

		$lastRunAt = (int)$row['last_run'];
		$lastCheckedAt = (int)($row['last_checked'] ?? 0);
		$reservedAt = (int)($row['reserved_at'] ?? 0);
		$timeSensitive = (int)($row['time_sensitive'] ?? 0);
		$nextScheduledAt = max($lastRunAt + $configuredIntervalSeconds, $lastCheckedAt);
		$lastRunAge = $lastRunAt > 0 ? max(0, $now - $lastRunAt) : null;
		$runDue = $nextScheduledAt > 0 ? max(0, $nextScheduledAt - $now) : 0;
		$stale = $now - max($lastCheckedAt, $lastRunAt, 0) > max(600, $configuredIntervalSeconds * 2);
		$running = $reservedAt > 0 && $now - $reservedAt < 1800;

		$status = 'unknown';
		if ($running) {
			$status = 'running';
		} elseif ($lastCheckedAt <= 0 || $lastRunAt <= 0) {
			$status = 'bootstrapped';
		} elseif ($runDue <= 0) {
			$status = 'ready_or_overdue';
		} else {
			$status = 'scheduled';
		}

		return [
			'backgroundJobsMode' => $backgroundJobs['mode'],
			'backgroundJobsLastCronAt' => $backgroundJobs['lastCronAt'],
			'backgroundJobsCronAgeSeconds' => $backgroundJobs['lastCronAgeSeconds'],
			'backgroundJobsCronHealthy' => $backgroundJobs['cronHealthy'],
			'backgroundJobsCronReason' => $backgroundJobs['reason'],
			'exists' => true,
			'reason' => null,
			'configuredIntervalSeconds' => $configuredIntervalSeconds,
			'status' => $status,
			'timeSensitive' => $timeSensitive === 1,
			'nextScheduledAt' => $nextScheduledAt,
			'lastRunAt' => $lastRunAt > 0 ? $lastRunAt : null,
			'lastCheckedAt' => $lastCheckedAt > 0 ? $lastCheckedAt : null,
			'reservedAt' => $reservedAt > 0 ? $reservedAt : null,
			'stale' => $stale,
			'runDueInSeconds' => $runDue,
			'lastRunAgeSeconds' => $lastRunAge,
			'jobAgeSeconds' => $lastRunAge,
			'jobId' => (string)($row['id'] ?? ''),
			'argument' => $row['argument'] ?? null,
			'now' => $now,
		];
	}

	private function ensureAutoSyncRunnerQueued(): void {
		$now = time();
		if (($now - $this->autoSyncNudgeLastAt) < self::AUTO_SYNC_NUDGE_INTERVAL_SECONDS) {
			return;
		}

		$this->autoSyncNudgeLastAt = $now;

		$auto = $this->settingsService->getAutoSyncSettings();
		if (($auto['globalEnabled'] ?? false) !== true || $auto['mode'] !== 'file_events') {
			return;
		}

		try {
			$this->setAutoSyncJobTimedFlag();
			if (!$this->jobList->has(AutoSyncJob::class, null)) {
				$this->jobList->add(AutoSyncJob::class);
			}
			if (method_exists($this->jobList, 'scheduleAfter')) {
				$runAfter = $now + self::AUTO_SYNC_NUDGE_DELAY_SECONDS;
				$this->jobList->scheduleAfter(AutoSyncJob::class, $runAfter, null);
				$this->forceAutoSyncRunnerToRunSoon($runAfter);
			}
			$this->logService->debug('auto_sync_runner_nudged', null, [
				'runAfter' => $now + self::AUTO_SYNC_NUDGE_DELAY_SECONDS,
				'jobMode' => $auto['mode'],
				'backgroundJobsMode' => $this->backgroundJobsMode(),
			], 'Automatic SakuraAlbum background job was queued for near-term execution after queue update.');
		} catch (\Throwable $e) {
			$this->logService->warning('auto_sync_runner_nudge_failed', null, [
				'errorClass' => $e::class,
				'errorMessage' => mb_substr($e->getMessage(), 0, 256),
			], 'Failed to queue the automatic SakuraAlbum background job.');
		}
	}

	private function setAutoSyncJobTimedFlag(): void {
		$argumentHash = hash('sha256', json_encode(null));
		$qb = $this->db->getQueryBuilder();
		$qb->update('jobs')
			->set('time_sensitive', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('class', $qb->createNamedParameter(self::AUTO_SYNC_JOB_CLASS)))
			->andWhere($qb->expr()->eq('argument_hash', $qb->createNamedParameter($argumentHash)));
		$qb->executeStatement();
	}

	private function forceAutoSyncRunnerToRunSoon(int $runAfter): void {
		if (!$this->jobList->has(AutoSyncJob::class, null)) {
			return;
		}

		try {
			if (method_exists($this->jobList, 'resetBackgroundJob')) {
				$iterator = $this->jobList->getJobsIterator(AutoSyncJob::class, 1, 0);
				foreach ($iterator as $job) {
					if ($job instanceof AutoSyncJob) {
						$this->jobList->resetBackgroundJob($job);
						$this->jobList->scheduleAfter(AutoSyncJob::class, $runAfter, null);
						return;
					}
				}
			}
		} catch (\Throwable) {
			// Fallback to direct table update below.
		}

		$argumentHash = hash('sha256', json_encode(null));
		$qb = $this->db->getQueryBuilder();
		$qb->update('jobs')
			->set('last_run', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('reserved_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('last_checked', $qb->createNamedParameter($runAfter, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('class', $qb->createNamedParameter(self::AUTO_SYNC_JOB_CLASS)))
			->andWhere($qb->expr()->eq('argument_hash', $qb->createNamedParameter($argumentHash)));
		$qb->executeStatement();
	}

	private function backgroundJobsState(int $configuredIntervalSeconds, int $now): array {
		$mode = $this->backgroundJobsMode();
		$rawLastCron = $this->config->getAppValue('core', 'lastcron', '0');
		$lastCronAt = is_numeric($rawLastCron) ? (int)$rawLastCron : null;
		$lastCronAgeSeconds = $lastCronAt !== null && $lastCronAt > 0 ? max(0, $now - $lastCronAt) : null;

		$cronHealthy = true;
		$reason = null;
		if ($mode === 'cron') {
			if ($lastCronAt === null || $lastCronAt <= 0) {
				$cronHealthy = false;
				$reason = 'cron_not_recorded';
			} elseif ($lastCronAgeSeconds !== null && $lastCronAgeSeconds > max(900, $configuredIntervalSeconds * 2)) {
				$cronHealthy = false;
				$reason = 'cron_stale';
			}
		}

		return [
			'mode' => $mode,
			'lastCronAt' => $lastCronAt,
			'lastCronAgeSeconds' => $lastCronAgeSeconds,
			'cronHealthy' => $cronHealthy,
			'reason' => $reason,
		];
	}

	private function backgroundJobsMode(): string {
		return $this->config->getAppValue('core', 'backgroundjobs_mode', 'cron');
	}
}
