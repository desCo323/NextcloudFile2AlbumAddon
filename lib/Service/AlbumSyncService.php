<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Db\ManagedAlbum;
use OCA\SakuraAlbum\Db\ManagedAlbumMapper;
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
	];

	public function __construct(
		private readonly SettingsService $settingsService,
		private readonly AlbumPlanService $albumPlanService,
		private readonly PhotosAlbumAdapter $photosAlbumAdapter,
		private readonly ManagedAlbumMapper $managedAlbumMapper,
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

	private function writePlan(string $userId, array $plan, array $settings, string $configHash, int $runId): array {
		$summary = [
			'createdAlbums' => 0,
			'updatedManagedAlbums' => 0,
			'processedAlbums' => 0,
			'linkedFiles' => 0,
			'alreadyLinkedFiles' => 0,
			'missingFiles' => 0,
			'fileErrors' => 0,
			'albumErrors' => 0,
		];
		$fileErrorLogs = 0;

		foreach ($plan['albums'] as $album) {
			if (!in_array($album['writeAction'] ?? '', ['create', 'update_managed'], true)) {
				continue;
			}

			try {
				$albumInfo = $this->prepareWritableAlbum($userId, $album);
				if (($albumInfo['created'] ?? false) === true) {
					$summary['createdAlbums']++;
				} else {
					$summary['updatedManagedAlbums']++;
				}
				$summary['processedAlbums']++;
				$albumId = (int)$albumInfo['id'];
				$this->saveManagedAlbum($userId, $album, $settings, $configHash, $albumId, 'syncing');

				foreach (($album['files'] ?? []) as $filePath) {
					try {
						$result = $this->photosAlbumAdapter->addFileToAlbum($userId, $albumId, (string)$filePath);
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
				}

				$this->saveManagedAlbum($userId, $album, $settings, $configHash, $albumId, 'synced');
				$this->logService->debug('album_write_completed', $userId, [
					'albumName' => $album['albumName'],
					'mediaCount' => $album['mediaCount'],
					'action' => $album['writeAction'],
				], '', $runId);
			} catch (\Throwable $e) {
				$summary['albumErrors']++;
				$this->logService->exception('album_write_failed', $e, $userId, [
					'albumName' => $album['albumName'] ?? '',
					'writeAction' => $album['writeAction'] ?? '',
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
			'excludePatterns' => $excludePatterns,
			'namingTemplate' => $settings['namingTemplate'] ?? '',
			'separator' => $settings['separator'] ?? '',
			'albumDepth' => $settings['albumDepth'] ?? 0,
			'includeImages' => $settings['includeImages'] ?? true,
			'includeVideos' => $settings['includeVideos'] ?? false,
			], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
	}

	private function planFingerprint(array $plan, string $configHash): string {
		$albums = array_map(
			static fn (array $album): array => [
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
			'version' => 1,
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
}
