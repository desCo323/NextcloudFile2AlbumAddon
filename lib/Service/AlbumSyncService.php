<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Db\ManagedAlbum;
use OCA\SakuraAlbum\Db\ManagedAlbumMapper;
use OCA\SakuraAlbum\Db\SyncCursor;
use OCA\SakuraAlbum\Db\SyncCursorMapper;
use OCA\SakuraAlbum\Db\SyncRun;
use OCA\SakuraAlbum\Db\SyncRunMapper;

class AlbumSyncService {
	public const WRITE_CONFIRMATION = 'CREATE_ALBUMS';
	private const PLAN_FINGERPRINT_TTL_SECONDS = 900;

	private const BLOCKING_WARNING_CODES = [
		'no_include_paths',
		'invalid_include_path',
		'missing_include_path',
		'include_path_not_folder',
		'max_folders_reached',
		'max_files_reached',
		'storage_unavailable',
		'user_folder_unavailable',
		'overlapping_source_paths',
	];

	public function __construct(
		private readonly SettingsService $settingsService,
		private readonly AlbumPlanService $albumPlanService,
		private readonly PhotosAlbumAdapter $photosAlbumAdapter,
		private readonly ManagedAlbumMapper $managedAlbumMapper,
		private readonly SyncCursorMapper $syncCursorMapper,
		private readonly SyncRunMapper $syncRunMapper,
		private readonly LogService $logService,
	) {
	}

	public function dryRun(string $userId, ?array $settingsOverride = null): array {
		return $this->execute($userId, 'dry_run', false, '', $settingsOverride);
	}

	public function write(string $userId, string $confirmation, string $planFingerprint): array {
		return $this->execute($userId, 'write', true, $confirmation, null, $planFingerprint);
	}

	public function writeChunk(string $userId): array {
		return $this->executeChunk($userId);
	}

	public function recentRuns(string $userId, int $limit = 10): array {
		return array_map(
			static fn (SyncRun $run): array => [
				'id' => $run->getId(),
				'runType' => $run->getRunType(),
				'status' => $run->getStatus(),
				'startedAt' => $run->getStartedAt(),
				'finishedAt' => $run->getFinishedAt(),
				'summary' => $run->getSummaryJson() !== null ? json_decode($run->getSummaryJson(), true) : null,
				'errorMessage' => $run->getErrorMessage(),
			],
			$this->syncRunMapper->findRecentForUser($userId, $limit),
		);
	}

	public function cursorStatus(string $userId, int $limit = 5): array {
		$settings = $this->settingsService->getEffectiveUserSettings($userId);
		$currentConfigHash = $this->configHash($settings);
		$current = $this->syncCursorMapper->findForUserConfig($userId, $currentConfigHash);
		$cursors = array_map(
			fn (SyncCursor $cursor): array => $this->publicCursor($cursor, $currentConfigHash),
			$this->syncCursorMapper->findRecentForUser($userId, $limit),
		);

		return [
			'currentConfigHash' => $currentConfigHash,
			'current' => $current !== null ? $this->publicCursor($current, $currentConfigHash) : null,
			'cursors' => $cursors,
		];
	}

	private function executeChunk(string $userId): array {
		$run = $this->syncRunMapper->start($userId, 'auto_chunk');
		$runId = (int)$run->getId();
		$started = microtime(true);

		$this->logService->info('auto_chunk_started', $userId, [
			'summary' => [
				'runType' => 'auto_chunk',
			],
		], 'Chunked automatic album sync started.', $runId);

		try {
			$settings = $this->settingsService->getEffectiveUserSettings($userId);
			$limits = $this->settingsService->getJobLimits();
			$configHash = $this->configHash($settings);
			$cursor = $this->syncCursorMapper->findOrCreate($userId, $configHash, time());
			$startAfter = $cursor->getCursorPath();
			$plan = $this->albumPlanService->buildExecutionChunk($userId, $settings, $limits, $startAfter);

			if ($startAfter !== null && $startAfter !== '' && ($plan['summary']['cursorFound'] ?? true) !== true) {
				$this->logService->warning('auto_chunk_cursor_missing', $userId, [
					'cursorPath' => PathHelper::displayPath($startAfter),
					'configHash' => $configHash,
				], 'Chunk cursor file is no longer present. The chunked sync restarts from the beginning and relies on idempotent link handling.', $runId);
				$plan = $this->albumPlanService->buildExecutionChunk($userId, $settings, $limits, null);
				$plan['summary']['cursorReset'] = true;
			}

			$this->applyAlbumLimit($plan, $limits);
			$plan = $this->inspectExistingAlbums($userId, $plan, $configHash);
			$issues = $this->writeSafetyIssues($settings, $plan, $limits);
			$plan['summary']['safetyIssueCount'] = count($issues);
			$currentPlanFingerprint = $this->planFingerprint($plan, $configHash);

			if ($issues !== []) {
				$summary = $this->runSummary($plan['summary'], $started, $issues);
				$summary['planFingerprint'] = $currentPlanFingerprint;
				$this->syncCursorMapper->markChunkResult($cursor, 'failed', $cursor->getCursorPath(), $summary, 'Chunk plan is not safe to write.');
				throw new SyncSafetyException(
					'auto_chunk_plan_not_safe',
					'Chunked automatic album sync is blocked because the current chunk is not safe to write.',
					409,
					['issues' => $issues],
				);
			}

			$writeSummary = [];
			if (($plan['albums'] ?? []) !== []) {
				$writeSummary = $this->writePlan($userId, $plan, $settings, $configHash, $runId, partial: true);
				$plan['summary'] = array_merge($plan['summary'], $writeSummary);
			} else {
				$plan['summary'] = array_merge($plan['summary'], [
					'createdAlbums' => 0,
					'updatedManagedAlbums' => 0,
					'processedAlbums' => 0,
					'plannedWritableAlbums' => 0,
					'plannedWritableLinks' => 0,
					'processedLinks' => 0,
					'linkedFiles' => 0,
					'alreadyLinkedFiles' => 0,
					'missingFiles' => 0,
					'fileErrors' => 0,
					'albumErrors' => 0,
					'progressPercent' => 100,
					'progressStage' => 'completed',
				]);
			}

			$hasMore = ($plan['summary']['hasMore'] ?? false) === true;
			$lastCursorPath = (string)($plan['summary']['lastCursorPath'] ?? '');
			$cursorPath = $lastCursorPath !== '' ? PathHelper::normalizeUserPath($lastCursorPath) : $cursor->getCursorPath();
			$status = $hasMore ? 'auto_chunk_partial' : 'auto_chunk_completed';
			$cursorStatus = $hasMore ? 'pending' : 'completed';
			$summary = $this->runSummary($plan['summary'], $started, []);
			$summary['planFingerprint'] = $currentPlanFingerprint;
			$summary['hasMore'] = $hasMore;
			$summary['cursorPath'] = $cursorPath !== null && $cursorPath !== '' ? PathHelper::displayPath($cursorPath) : '';
			$this->syncCursorMapper->markChunkResult($cursor, $cursorStatus, $cursorPath, $summary);
			$this->syncRunMapper->finish($run, $status, $summary);
			$this->logService->success($status, $userId, [
				'durationMs' => $summary['durationMs'],
				'summary' => $summary,
				'warningCount' => count($plan['warnings'] ?? []),
			], $hasMore ? 'Chunked automatic album sync completed a partial chunk.' : 'Chunked automatic album sync completed all currently planned media.', $runId);

			return [
				'runId' => $runId,
				'mode' => 'auto_chunk',
				'status' => $status,
				'hasMore' => $hasMore,
				'canWrite' => true,
				'writeBlockedReasons' => [],
				'planFingerprint' => $currentPlanFingerprint,
				'summary' => $summary,
				'warnings' => $plan['warnings'] ?? [],
				'albums' => $this->publicAlbums($plan['albums'] ?? []),
				'cursor' => $this->publicCursor($this->syncCursorMapper->findForUserConfig($userId, $configHash), $configHash),
			];
		} catch (SyncSafetyException $e) {
			$summary = [
				'durationMs' => (int)((microtime(true) - $started) * 1000),
				'errorCode' => $e->getErrorCode(),
				'details' => $e->getDetails(),
			];
			$this->syncRunMapper->finish($run, 'failed', $summary, $e->getMessage());
			$this->logService->warning($e->getErrorCode(), $userId, [
				'summary' => $summary,
			], $e->getMessage(), $runId);
			throw $e;
		} catch (\Throwable $e) {
			$summary = [
				'durationMs' => (int)((microtime(true) - $started) * 1000),
				'errorCode' => 'auto_chunk_failed',
			];
			$this->syncRunMapper->finish($run, 'failed', $summary, $e->getMessage());
			$this->logService->exception('auto_chunk_failed', $e, $userId, [
				'durationMs' => $summary['durationMs'],
			], $runId);
			throw $e;
		}
	}

	private function execute(string $userId, string $runType, bool $write, string $confirmation, ?array $settingsOverride, string $planFingerprint = ''): array {
		$run = $this->syncRunMapper->start($userId, $runType);
		$runId = (int)$run->getId();
		$started = microtime(true);

		$this->logService->info($write ? 'write_started' : 'dry_run_started', $userId, [
			'summary' => [
				'runType' => $runType,
			],
		], $write ? 'Album write run started.' : 'Album dry-run started.', $runId);

		try {
			if ($write && $confirmation !== self::WRITE_CONFIRMATION) {
				throw new SyncSafetyException(
					'write_confirmation_required',
					'Album creation requires the exact confirmation text.',
					400,
					['requiredConfirmation' => self::WRITE_CONFIRMATION],
				);
			}

			$settings = $this->settingsService->getEffectiveUserSettings($userId, $settingsOverride);
			$limits = $this->settingsService->getJobLimits();
			$configHash = $this->configHash($settings);
			$plan = $this->albumPlanService->buildExecutionPlan($userId, $settings, $limits);
			$this->applyAlbumLimit($plan, $limits);
			$plan = $this->inspectExistingAlbums($userId, $plan, $configHash);
			$issues = $this->writeSafetyIssues($settings, $plan, $limits);
			$plan['summary']['safetyIssueCount'] = count($issues);
			$currentPlanFingerprint = $this->planFingerprint($plan, $configHash);

			if ($write && $issues !== []) {
				throw new SyncSafetyException(
					'write_plan_not_safe',
					'Album creation is blocked because the current plan is not safe to write.',
					409,
					['issues' => $issues],
				);
			}
			if ($write && $planFingerprint === '') {
				throw new SyncSafetyException(
					'write_plan_fingerprint_required',
					'Run a fresh dry-run before starting an album write job.',
					400,
				);
			}
			if ($write && !hash_equals($currentPlanFingerprint, $planFingerprint)) {
				throw new SyncSafetyException(
					'write_plan_changed',
					'The album write plan changed after the dry-run. Run the dry-run again before writing.',
					409,
				);
			}
			if ($write) {
				$this->assertRecentDryRunFingerprint($userId, $planFingerprint);
			}

			$status = 'dry_run_completed';
			if ($write) {
				$writeSummary = $this->writePlan($userId, $plan, $settings, $configHash, $runId);
				$plan['summary'] = array_merge($plan['summary'], $writeSummary);
				$status = ($writeSummary['albumErrors'] ?? 0) > 0 || ($writeSummary['fileErrors'] ?? 0) > 0
					? 'write_completed_with_errors'
					: 'write_completed';
			}

			$summary = $this->runSummary($plan['summary'], $started, $issues);
			$summary['planFingerprint'] = $currentPlanFingerprint;
			$this->syncRunMapper->finish($run, $status, $summary);
			$this->logService->success($write ? 'write_completed' : 'dry_run_completed', $userId, [
				'durationMs' => $summary['durationMs'],
				'summary' => $summary,
				'warningCount' => count($plan['warnings'] ?? []),
			], $write ? 'Album write run completed.' : 'Album dry-run completed.', $runId);

			return [
				'runId' => $runId,
				'mode' => $runType,
				'status' => $status,
				'canWrite' => $issues === [],
				'writeBlockedReasons' => $issues,
				'confirmationText' => self::WRITE_CONFIRMATION,
				'planFingerprint' => $currentPlanFingerprint,
				'summary' => $summary,
				'warnings' => $plan['warnings'] ?? [],
				'albums' => $this->publicAlbums($plan['albums'] ?? []),
			];
		} catch (SyncSafetyException $e) {
			$summary = [
				'durationMs' => (int)((microtime(true) - $started) * 1000),
				'errorCode' => $e->getErrorCode(),
				'details' => $e->getDetails(),
			];
			$this->syncRunMapper->finish($run, 'failed', $summary, $e->getMessage());
			$this->logService->warning($e->getErrorCode(), $userId, [
				'summary' => $summary,
			], $e->getMessage(), $runId);
			throw $e;
		} catch (\Throwable $e) {
			$summary = [
				'durationMs' => (int)((microtime(true) - $started) * 1000),
				'errorCode' => 'sync_failed',
			];
			$this->syncRunMapper->finish($run, 'failed', $summary, $e->getMessage());
			$this->logService->exception($write ? 'write_failed' : 'dry_run_failed', $e, $userId, [
				'durationMs' => $summary['durationMs'],
			], $runId);
			throw $e;
		}
	}

	private function assertRecentDryRunFingerprint(string $userId, string $planFingerprint): void {
		$since = time() - self::PLAN_FINGERPRINT_TTL_SECONDS;
		$runs = $this->syncRunMapper->findRecentFinishedForUserAndType($userId, 'dry_run', 'dry_run_completed', $since, 20);
		foreach ($runs as $run) {
			$summaryJson = $run->getSummaryJson();
			if ($summaryJson === null) {
				continue;
			}

			$summary = json_decode($summaryJson, true);
			if (!is_array($summary)) {
				continue;
			}

			if (hash_equals((string)($summary['planFingerprint'] ?? ''), $planFingerprint)) {
				return;
			}
		}

		throw new SyncSafetyException(
			'write_plan_fingerprint_not_recent',
			'Run a fresh dry-run before starting an album write job.',
			409,
		);
	}

	private function inspectExistingAlbums(string $userId, array $plan, string $configHash): array {
		$summary = [
			'wouldCreateAlbums' => 0,
			'wouldUpdateManagedAlbums' => 0,
			'blockedExistingAlbums' => 0,
			'blockedCollisions' => 0,
		];

		foreach ($plan['albums'] as &$album) {
			if (($album['collision'] ?? false) === true) {
				$album['writeAction'] = 'blocked_collision';
				$album['existingAlbum'] = false;
				$album['managed'] = false;
				$summary['blockedCollisions']++;
				continue;
			}

			$managedByIdentity = $this->managedAlbumMapper->findByIdentity($userId, $configHash, (string)$album['targetPath']);
			$existing = $this->photosAlbumAdapter->findAlbum($userId, (string)$album['albumName']);
			$managedByPhotoId = $existing !== null
				? $this->managedAlbumMapper->findByPhotosAlbumId($userId, (int)$existing['id'])
				: null;
			$isManaged = $existing !== null && $this->isManagedExistingAlbum($managedByIdentity, $managedByPhotoId, (int)$existing['id']);

			$album['existingAlbum'] = $existing !== null;
			$album['managed'] = $isManaged;
			$album['_existingAlbumId'] = $existing['id'] ?? null;
			$album['_managedAlbumId'] = $managedByIdentity?->getId() ?? $managedByPhotoId?->getId();

			if ($existing === null) {
				$album['writeAction'] = 'create';
				$summary['wouldCreateAlbums']++;
				continue;
			}

			if ($isManaged) {
				$album['writeAction'] = 'update_managed';
				$summary['wouldUpdateManagedAlbums']++;
				continue;
			}

			$album['writeAction'] = 'blocked_existing_album';
			$summary['blockedExistingAlbums']++;
		}
		unset($album);

		$plan['summary'] = array_merge($plan['summary'], $summary);
		return $plan;
	}

	private function writePlan(string $userId, array $plan, array $settings, string $configHash, int $runId, bool $partial = false): array {
		$writableAlbums = array_values(array_filter(
			$plan['albums'] ?? [],
			static fn (array $album): bool => in_array($album['writeAction'] ?? '', ['create', 'update_managed'], true),
		));
		$plannedWritableLinks = array_sum(array_map(
			static fn (array $album): int => count($album['files'] ?? []),
			$writableAlbums,
		));
		$summary = [
			'createdAlbums' => 0,
			'updatedManagedAlbums' => 0,
			'processedAlbums' => 0,
			'plannedWritableAlbums' => count($writableAlbums),
			'plannedWritableLinks' => $plannedWritableLinks,
			'linkedFiles' => 0,
			'alreadyLinkedFiles' => 0,
			'missingFiles' => 0,
			'removedFiles' => 0,
			'removeErrors' => 0,
			'skippedStaleRemovalAlbums' => 0,
			'deletedMissingManagedAlbums' => 0,
			'cleanedMissingTrackingRecords' => 0,
			'missingManagedAlbumDeleteErrors' => 0,
			'missingManagedAlbumCleanupTruncated' => false,
			'fileErrors' => 0,
			'albumErrors' => 0,
			'progressPercent' => count($writableAlbums) === 0 && $plannedWritableLinks === 0 ? 100 : 0,
			'progressStage' => 'preparing',
		];
		$fileErrorLogs = 0;
		$removeErrorLogs = 0;
		$currentTargetPaths = [];
		$this->persistWriteProgress($runId, $userId, $plan, $summary, 'preparing');
		$lastProgressUpdate = microtime(true);

		foreach ($writableAlbums as $album) {
			try {
				$albumInfo = $this->prepareWritableAlbum($userId, $album);
				if (($albumInfo['created'] ?? false) === true) {
					$summary['createdAlbums']++;
				} else {
					$summary['updatedManagedAlbums']++;
				}
				$summary['processedAlbums']++;
				$albumId = (int)$albumInfo['id'];
				$currentTargetPaths[$this->targetPathKey((string)$album['targetPath'])] = true;
				$this->saveManagedAlbum($userId, $album, $settings, $configHash, $albumId, 'syncing');

				$desiredFileIds = [];
				$albumMissingFiles = 0;
				foreach (($album['files'] ?? []) as $filePath) {
					try {
						$fileId = $this->photosAlbumAdapter->fileIdForPath($userId, (string)$filePath);
						if ($fileId === null) {
							$albumMissingFiles++;
							$summary['missingFiles']++;
							continue;
						}

						$desiredFileIds[$fileId] = true;
						$result = $this->photosAlbumAdapter->addFileIdToAlbum($albumId, $fileId, $userId);
						if ($result === 'linked') {
							$summary['linkedFiles']++;
						} elseif ($result === 'already_linked') {
							$summary['alreadyLinkedFiles']++;
						} else {
							$summary['missingFiles']++;
						}
					} catch (\Throwable $e) {
						$summary['fileErrors']++;
						if ($fileErrorLogs < 25) {
							$fileErrorLogs++;
							$this->logService->exception('file_link_failed', $e, $userId, [
								'albumName' => $album['albumName'],
								'filePath' => $filePath,
							], $runId);
						}
					}

					$processedLinks = $this->processedLinks($summary);
					if ($processedLinks % 50 === 0 || (microtime(true) - $lastProgressUpdate) >= 2.0) {
						$this->persistWriteProgress($runId, $userId, $plan, $summary, 'linking_files');
						$lastProgressUpdate = microtime(true);
					}
				}

				if (!$partial && ($settings['syncRemoveMissingFiles'] ?? true) === true && ($album['writeAction'] ?? '') === 'update_managed') {
					if ($albumMissingFiles > 0) {
						$summary['skippedStaleRemovalAlbums']++;
						$this->logService->warning('stale_file_removal_skipped', $userId, [
							'albumName' => $album['albumName'],
							'missingFiles' => $albumMissingFiles,
						], 'Stale file cleanup was skipped because some planned files disappeared during this run.', $runId);
					} else {
						$cleanup = $this->removeStaleAlbumFiles($userId, $albumId, array_keys($desiredFileIds), $album, $runId, $removeErrorLogs);
						$summary['removedFiles'] += $cleanup['removedFiles'];
						$summary['removeErrors'] += $cleanup['removeErrors'];
					}
				}

				$this->saveManagedAlbum($userId, $album, $settings, $configHash, $albumId, 'synced');
				$this->logService->debug('album_write_completed', $userId, [
					'albumName' => $album['albumName'],
					'mediaCount' => $album['mediaCount'],
					'action' => $album['writeAction'],
				], '', $runId);
				$this->persistWriteProgress($runId, $userId, $plan, $summary, 'album_completed');
				$lastProgressUpdate = microtime(true);
			} catch (\Throwable $e) {
				$summary['albumErrors']++;
				$this->logService->exception('album_write_failed', $e, $userId, [
					'albumName' => $album['albumName'] ?? '',
					'writeAction' => $album['writeAction'] ?? '',
				], $runId);
				$this->persistWriteProgress($runId, $userId, $plan, $summary, 'album_error');
			}
		}

		if (!$partial && ($settings['syncDeleteMissingManagedAlbums'] ?? false) === true) {
			if ($summary['albumErrors'] === 0 && $summary['fileErrors'] === 0) {
				$this->persistWriteProgress($runId, $userId, $plan, $summary, 'cleanup_missing_albums');
				$missingCleanup = $this->deleteMissingManagedAlbums($userId, $configHash, $currentTargetPaths, $summary['processedAlbums'], $runId);
				$summary['deletedMissingManagedAlbums'] += $missingCleanup['deletedMissingManagedAlbums'];
				$summary['cleanedMissingTrackingRecords'] += $missingCleanup['cleanedMissingTrackingRecords'];
				$summary['missingManagedAlbumDeleteErrors'] += $missingCleanup['missingManagedAlbumDeleteErrors'];
				$summary['missingManagedAlbumCleanupTruncated'] = $missingCleanup['missingManagedAlbumCleanupTruncated'];
			} else {
				$this->logService->warning('missing_managed_album_cleanup_skipped', $userId, [
					'albumErrors' => $summary['albumErrors'],
					'fileErrors' => $summary['fileErrors'],
				], 'Missing managed album cleanup was skipped because the write run had errors.', $runId);
			}
		}

		$summary['progressPercent'] = 100;
		$summary['progressStage'] = 'completed';
		$this->persistWriteProgress($runId, $userId, $plan, $summary, 'completed');

		return $summary;
	}

	private function persistWriteProgress(int $runId, string $userId, array $plan, array &$summary, string $stage): void {
		$totalUnits = max(1, (int)$summary['plannedWritableAlbums'] + (int)$summary['plannedWritableLinks']);
		$doneUnits = min($totalUnits, (int)$summary['processedAlbums'] + $this->processedLinks($summary));
		$percent = $stage === 'completed' ? 100 : min(99, (int)floor(($doneUnits / $totalUnits) * 100));
		$summary['processedLinks'] = $this->processedLinks($summary);
		$summary['progressPercent'] = $percent;
		$summary['progressStage'] = $stage;

		$this->syncRunMapper->updateRunningSummary($runId, $userId, [
			'plannedAlbums' => (int)($plan['summary']['plannedAlbums'] ?? 0),
			'plannedLinks' => (int)($plan['summary']['plannedLinks'] ?? 0),
			'plannedWritableAlbums' => (int)$summary['plannedWritableAlbums'],
			'plannedWritableLinks' => (int)$summary['plannedWritableLinks'],
			'processedAlbums' => (int)$summary['processedAlbums'],
			'processedLinks' => (int)$summary['processedLinks'],
			'linkedFiles' => (int)$summary['linkedFiles'],
			'alreadyLinkedFiles' => (int)$summary['alreadyLinkedFiles'],
			'missingFiles' => (int)$summary['missingFiles'],
			'fileErrors' => (int)$summary['fileErrors'],
			'albumErrors' => (int)$summary['albumErrors'],
			'progressPercent' => $percent,
			'progressStage' => $stage,
			'updatedAt' => time(),
		]);
	}

	private function processedLinks(array $summary): int {
		return (int)$summary['linkedFiles']
			+ (int)$summary['alreadyLinkedFiles']
			+ (int)$summary['missingFiles']
			+ (int)$summary['fileErrors'];
	}

	private function removeStaleAlbumFiles(
		string $userId,
		int $albumId,
		array $desiredFileIds,
		array $album,
		int $runId,
		int &$removeErrorLogs,
	): array {
		$summary = [
			'removedFiles' => 0,
			'removeErrors' => 0,
		];
		$desired = array_fill_keys(array_map('intval', $desiredFileIds), true);

		foreach ($this->photosAlbumAdapter->listAlbumFileIds($userId, $albumId) as $fileId) {
			if (isset($desired[(int)$fileId])) {
				continue;
			}

			try {
				$this->photosAlbumAdapter->removeFileFromAlbum($albumId, (int)$fileId);
				$summary['removedFiles']++;
			} catch (\Throwable $e) {
				$summary['removeErrors']++;
				if ($removeErrorLogs < 25) {
					$removeErrorLogs++;
					$this->logService->exception('stale_file_remove_failed', $e, $userId, [
						'albumName' => $album['albumName'] ?? '',
						'fileId' => (int)$fileId,
					], $runId);
				}
			}
		}

		return $summary;
	}

	private function deleteMissingManagedAlbums(string $userId, string $configHash, array $currentTargetPaths, int $processedAlbums, int $runId): array {
		$summary = [
			'deletedMissingManagedAlbums' => 0,
			'cleanedMissingTrackingRecords' => 0,
			'missingManagedAlbumDeleteErrors' => 0,
			'missingManagedAlbumCleanupTruncated' => false,
		];
		$maxAlbums = max(1, (int)$this->settingsService->getJobLimits()['maxAlbums']);
		$remainingAlbumBudget = max(0, $maxAlbums - $processedAlbums);
		if ($remainingAlbumBudget === 0) {
			$summary['missingManagedAlbumCleanupTruncated'] = true;
			$this->logService->warning('missing_managed_album_cleanup_limited', $userId, [
				'maxAlbums' => $maxAlbums,
				'processedAlbums' => $processedAlbums,
			], 'Missing managed album cleanup was skipped because the per-run album budget was already used.', $runId);
			return $summary;
		}

		$managedAlbums = $this->managedAlbumMapper->findActiveByConfigHash($userId, $configHash, $remainingAlbumBudget + 1);
		if (count($managedAlbums) > $remainingAlbumBudget) {
			$summary['missingManagedAlbumCleanupTruncated'] = true;
			$managedAlbums = array_slice($managedAlbums, 0, $remainingAlbumBudget);
		}

		foreach ($managedAlbums as $managedAlbum) {
			if (isset($currentTargetPaths[$this->targetPathKey($managedAlbum->getTargetPath())])) {
				continue;
			}

			try {
				$photosAlbumId = $managedAlbum->getPhotosAlbumId();
				if ($photosAlbumId === null) {
					$this->managedAlbumMapper->markDeleted($managedAlbum);
					$summary['cleanedMissingTrackingRecords']++;
					continue;
				}

				$photosAlbum = $this->photosAlbumAdapter->findAlbumById($photosAlbumId);
				if ($photosAlbum === null) {
					$this->managedAlbumMapper->markDeleted($managedAlbum);
					$summary['cleanedMissingTrackingRecords']++;
					continue;
				}

				if (($photosAlbum['userId'] ?? '') !== $userId || ($photosAlbum['name'] ?? '') !== $managedAlbum->getAlbumName()) {
					$summary['missingManagedAlbumDeleteErrors']++;
					$this->logService->warning('missing_managed_album_delete_blocked', $userId, [
						'managedId' => (int)$managedAlbum->getId(),
						'albumName' => $managedAlbum->getAlbumName(),
						'photosAlbumId' => $photosAlbumId,
					], 'Missing managed album cleanup was blocked because the Photos album no longer matches SakuraAlbum tracking.', $runId);
					continue;
				}

				$this->photosAlbumAdapter->deleteAlbum($userId, $photosAlbumId);
				$this->managedAlbumMapper->markDeleted($managedAlbum);
				$summary['deletedMissingManagedAlbums']++;
				$this->logService->success('missing_managed_album_deleted', $userId, [
					'managedId' => (int)$managedAlbum->getId(),
					'albumName' => $managedAlbum->getAlbumName(),
					'targetPath' => $managedAlbum->getTargetPath(),
				], 'Managed Photos album no longer present in the current plan was deleted.', $runId);
			} catch (\Throwable $e) {
				$summary['missingManagedAlbumDeleteErrors']++;
				$this->logService->exception('missing_managed_album_delete_failed', $e, $userId, [
					'managedId' => (int)$managedAlbum->getId(),
					'albumName' => $managedAlbum->getAlbumName(),
				], $runId);
			}
		}

		return $summary;
	}

	private function prepareWritableAlbum(string $userId, array $album): array {
		if (($album['writeAction'] ?? '') === 'update_managed' && isset($album['_existingAlbumId'])) {
			return [
				'id' => (int)$album['_existingAlbumId'],
				'created' => false,
			];
		}

		$existing = $this->photosAlbumAdapter->findAlbum($userId, (string)$album['albumName']);
		if ($existing !== null) {
			throw new SyncSafetyException(
				'write_race_existing_album',
				'An album with the generated name appeared after the dry-run safety check.',
				409,
				['albumName' => $album['albumName']],
			);
		}

		$created = $this->photosAlbumAdapter->createAlbum($userId, (string)$album['albumName'], (string)$album['targetPath']);
		$created['created'] = true;
		return $created;
	}

	private function saveManagedAlbum(
		string $userId,
		array $album,
		array $settings,
		string $configHash,
		int $photosAlbumId,
		string $status,
	): void {
		$existing = $this->managedAlbumMapper->findByIdentity($userId, $configHash, (string)$album['targetPath'])
			?? $this->managedAlbumMapper->findByPhotosAlbumId($userId, $photosAlbumId);
		$now = time();
		$entity = $existing ?? new ManagedAlbum();
		if ($existing === null) {
			$entity->setCreatedAt($now);
		}

		$entity->setUserId($userId);
		$entity->setPhotosAlbumId($photosAlbumId);
		$entity->setAlbumName((string)$album['albumName']);
		$entity->setSourceRoot((string)$album['sourceRoot']);
		$entity->setTargetPath((string)$album['targetPath']);
		$entity->setNamingTemplate((string)$settings['namingTemplate']);
		$entity->setNamingSchemaVersion(Application::NAMING_SCHEMA_VERSION);
		$entity->setConfigHash($configHash);
		$entity->setMediaCount((int)$album['mediaCount']);
		$entity->setStatus($status);
		$entity->setUpdatedAt($now);
		$entity->setLastSyncAt($now);

		if ($existing === null) {
			$this->managedAlbumMapper->insert($entity);
			return;
		}

		$this->managedAlbumMapper->update($entity);
	}

	private function isManagedExistingAlbum(?ManagedAlbum $managedByIdentity, ?ManagedAlbum $managedByPhotoId, int $photosAlbumId): bool {
		if ($managedByPhotoId !== null) {
			return true;
		}
		if ($managedByIdentity === null) {
			return false;
		}

		return $managedByIdentity->getPhotosAlbumId() === null || $managedByIdentity->getPhotosAlbumId() === $photosAlbumId;
	}

	private function targetPathKey(string $path): string {
		return PathHelper::displayPath($path);
	}

	private function writeSafetyIssues(array $settings, array $plan, array $limits): array {
		$issues = [];
		$summary = $plan['summary'] ?? [];

		if (($settings['enabled'] ?? false) !== true) {
			$issues[] = [
				'code' => 'write_disabled',
				'message' => 'Global admin setting and personal setting must both be enabled before albums can be created.',
				'adminEnabled' => $settings['adminEnabled'] ?? false,
				'userEnabled' => $settings['userEnabled'] ?? false,
			];
		}
		if (($summary['truncated'] ?? false) === true) {
			$issues[] = ['code' => 'plan_truncated', 'message' => 'The scan hit a configured resource limit.'];
		}
		if (($summary['collisions'] ?? 0) > 0) {
			$issues[] = ['code' => 'album_name_collisions', 'count' => (int)$summary['collisions']];
		}
		if (($summary['plannedAlbums'] ?? 0) > (int)$limits['maxAlbums']) {
			$issues[] = ['code' => 'max_albums_reached', 'limit' => (int)$limits['maxAlbums']];
		}
		if (($summary['blockedExistingAlbums'] ?? 0) > 0) {
			$issues[] = ['code' => 'existing_unmanaged_albums', 'count' => (int)$summary['blockedExistingAlbums']];
		}

		foreach (($plan['warnings'] ?? []) as $warning) {
			$code = (string)($warning['code'] ?? '');
			if (in_array($code, self::BLOCKING_WARNING_CODES, true)) {
				$issues[] = [
					'code' => 'blocking_warning',
					'warningCode' => $code,
					'path' => $warning['path'] ?? null,
				];
			}
		}

		return $issues;
	}

	private function applyAlbumLimit(array &$plan, array $limits): void {
		$plannedAlbums = (int)($plan['summary']['plannedAlbums'] ?? 0);
		if ($plannedAlbums <= (int)$limits['maxAlbums']) {
			$plan['summary']['albumLimitExceeded'] = false;
			return;
		}

		$plan['summary']['albumLimitExceeded'] = true;
		$plan['warnings'][] = [
			'code' => 'max_albums_reached',
			'limit' => (int)$limits['maxAlbums'],
			'plannedAlbums' => $plannedAlbums,
		];
	}

	private function configHash(array $settings): string {
		$includePaths = $settings['includePaths'] ?? [];
		$excludePatterns = $settings['excludePatterns'] ?? [];
		sort($includePaths);
		sort($excludePatterns);

		return hash('sha256', json_encode([
			'namingSchemaVersion' => Application::NAMING_SCHEMA_VERSION,
			'includePaths' => $includePaths,
			'sourceFolders' => $this->configSourceFolders($settings['sourceFolders'] ?? []),
			'excludePatterns' => $excludePatterns,
			'namingTemplate' => $settings['namingTemplate'] ?? '',
			'separator' => $settings['separator'] ?? '',
			'albumDepth' => $settings['albumDepth'] ?? 0,
			'includeImages' => $settings['includeImages'] ?? true,
			'includeVideos' => $settings['includeVideos'] ?? false,
			], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
	}

	private function configSourceFolders(array $sourceFolders): array {
		$result = array_map(static fn (array $source): array => [
			'id' => (string)($source['id'] ?? ''),
			'path' => (string)($source['path'] ?? ''),
			'enabled' => (bool)($source['enabled'] ?? true),
			'mode' => (string)($source['mode'] ?? 'default'),
			'effectiveAlbumDepth' => (int)($source['effectiveAlbumDepth'] ?? 0),
			'effectiveNamingTemplate' => (string)($source['effectiveNamingTemplate'] ?? ''),
			'effectiveSeparator' => (string)($source['effectiveSeparator'] ?? ''),
		], $sourceFolders);
		usort($result, static fn (array $a, array $b): int => [$a['path'], $a['id']] <=> [$b['path'], $b['id']]);
		return $result;
	}

	private function planFingerprint(array $plan, string $configHash): string {
		$albums = array_map(
			static fn (array $album): array => [
				'sourceId' => (string)($album['sourceId'] ?? ''),
				'sourceRoot' => (string)($album['sourceRoot'] ?? ''),
				'targetPath' => (string)($album['targetPath'] ?? ''),
				'albumName' => (string)($album['albumName'] ?? ''),
				'mediaCount' => (int)($album['mediaCount'] ?? 0),
				'collision' => (bool)($album['collision'] ?? false),
				'writeAction' => (string)($album['writeAction'] ?? ''),
				'existingAlbum' => (bool)($album['existingAlbum'] ?? false),
				'managed' => (bool)($album['managed'] ?? false),
			],
			$plan['albums'] ?? [],
		);

		return hash('sha256', json_encode([
			'version' => 2,
			'configHash' => $configHash,
			'summary' => [
				'plannedAlbums' => (int)($plan['summary']['plannedAlbums'] ?? 0),
				'plannedLinks' => (int)($plan['summary']['plannedLinks'] ?? 0),
				'collisions' => (int)($plan['summary']['collisions'] ?? 0),
				'truncated' => (bool)($plan['summary']['truncated'] ?? false),
				'safetyIssueCount' => (int)($plan['summary']['safetyIssueCount'] ?? 0),
			],
			'albums' => $albums,
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
	}

	private function runSummary(array $summary, float $started, array $issues): array {
		$summary['durationMs'] = (int)((microtime(true) - $started) * 1000);
		$summary['writeBlocked'] = $issues !== [];
		return $summary;
	}

	private function publicAlbums(array $albums): array {
		$publicKeys = array_flip([
			'sourceId',
			'sourceRoot',
			'targetPath',
			'albumName',
			'mediaCount',
			'aggregated',
			'sampleFiles',
			'collision',
			'writeAction',
			'existingAlbum',
			'managed',
		]);

		return array_map(
			static fn (array $album): array => array_intersect_key($album, $publicKeys),
			$albums,
		);
	}

	private function publicCursor(?SyncCursor $cursor, string $currentConfigHash): ?array {
		if ($cursor === null) {
			return null;
		}

		$summary = $cursor->getSummaryJson() !== null ? json_decode($cursor->getSummaryJson(), true) : null;
		return [
			'id' => $cursor->getId(),
			'status' => $cursor->getStatus(),
			'currentConfig' => hash_equals($currentConfigHash, $cursor->getConfigHash()),
			'cursorPath' => $cursor->getCursorPath() !== null ? PathHelper::displayPath($cursor->getCursorPath()) : '',
			'processedFiles' => $cursor->getProcessedFiles(),
			'processedAlbums' => $cursor->getProcessedAlbums(),
			'chunkCount' => $cursor->getChunkCount(),
			'attempts' => $cursor->getAttempts(),
			'createdAt' => $cursor->getCreatedAt(),
			'updatedAt' => $cursor->getUpdatedAt(),
			'completedAt' => $cursor->getCompletedAt(),
			'summary' => is_array($summary) ? $summary : null,
			'lastError' => $cursor->getLastError(),
		];
	}
}
