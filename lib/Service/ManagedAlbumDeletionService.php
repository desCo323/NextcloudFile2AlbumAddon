<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\Db\ManagedAlbum;
use OCA\SakuraAlbum\Db\ManagedAlbumMapper;
use OCA\SakuraAlbum\Db\SyncRunMapper;

class ManagedAlbumDeletionService {
	public const DELETE_CONFIRMATION = 'DELETE_MANAGED_ALBUMS';
	private const PLAN_FINGERPRINT_TTL_SECONDS = 900;

	public function __construct(
		private readonly SettingsService $settingsService,
		private readonly AlbumPlanService $albumPlanService,
		private readonly PhotosAlbumAdapter $photosAlbumAdapter,
		private readonly ManagedAlbumMapper $managedAlbumMapper,
		private readonly SyncRunMapper $syncRunMapper,
		private readonly LogService $logService,
	) {
	}

	public function managedAlbums(string $userId, int $limit = 200): array {
		$maxAlbums = (int)$this->settingsService->getJobLimits()['maxAlbums'];
		$limit = max(1, min($limit, $maxAlbums, 500));
		$total = $this->managedAlbumMapper->countActiveForUser($userId);
		$albums = $this->managedAlbumMapper->findActiveForUser($userId, $limit);

		return [
			'total' => $total,
			'limit' => $limit,
			'truncated' => $total > count($albums),
			'confirmationText' => self::DELETE_CONFIRMATION,
			'albums' => array_map(
				fn (ManagedAlbum $album): array => $this->publicManagedAlbum($album),
				$albums,
			),
		];
	}

	public function dryRunDelete(string $userId, array $albumIds = [], bool $deleteAll = false): array {
		return $this->executeDelete($userId, $albumIds, $deleteAll, '', '', false);
	}

	public function dryRunDeleteStale(string $userId): array {
		$stale = $this->staleManagedAlbumIds($userId);
		if (($stale['blocked'] ?? false) === true) {
			return [
				'runId' => null,
				'mode' => 'delete_dry_run',
				'status' => 'delete_dry_run_blocked',
				'canDelete' => false,
				'deleteBlockedReasons' => $stale['issues'],
				'confirmationText' => self::DELETE_CONFIRMATION,
				'planFingerprint' => '',
				'summary' => [
					'deleteAll' => false,
					'staleOnly' => true,
					'plannedAlbums' => 0,
					'currentPlannedAlbums' => $stale['currentPlannedAlbums'],
					'currentPlanTruncated' => $stale['currentPlanTruncated'],
					'safetyIssueCount' => count($stale['issues']),
					'deleteBlocked' => true,
				],
				'albums' => [],
			];
		}

		if ($stale['albumIds'] === []) {
			return [
				'runId' => null,
				'mode' => 'delete_dry_run',
				'status' => 'delete_dry_run_completed',
				'canDelete' => false,
				'deleteBlockedReasons' => [
					[
						'code' => 'no_stale_managed_albums',
						'message' => 'No stale SakuraAlbum-managed albums were found for the current settings.',
					],
				],
				'confirmationText' => self::DELETE_CONFIRMATION,
				'planFingerprint' => '',
				'summary' => [
					'deleteAll' => false,
					'staleOnly' => true,
					'totalActiveManagedAlbums' => $stale['totalActiveManagedAlbums'],
					'plannedAlbums' => 0,
					'currentPlannedAlbums' => $stale['currentPlannedAlbums'],
					'currentPlanTruncated' => false,
					'safetyIssueCount' => 1,
					'deleteBlocked' => true,
				],
				'albums' => [],
			];
		}

		$result = $this->executeDelete($userId, $stale['albumIds'], false, '', '', false);
		$result['summary']['staleOnly'] = true;
		$result['summary']['currentPlannedAlbums'] = $stale['currentPlannedAlbums'];
		$result['summary']['totalActiveManagedAlbums'] = $stale['totalActiveManagedAlbums'];
		return $result;
	}

	public function delete(string $userId, array $albumIds, bool $deleteAll, string $confirmation, string $planFingerprint): array {
		return $this->executeDelete($userId, $albumIds, $deleteAll, $confirmation, $planFingerprint, true);
	}

	private function executeDelete(string $userId, array $albumIds, bool $deleteAll, string $confirmation, string $planFingerprint, bool $write): array {
		$run = $this->syncRunMapper->start($userId, $write ? 'delete_write' : 'delete_dry_run');
		$runId = (int)$run->getId();
		$started = microtime(true);

		$this->logService->info($write ? 'managed_delete_started' : 'managed_delete_dry_run_started', $userId, [
			'summary' => [
				'deleteAll' => $deleteAll,
				'selectedAlbumIds' => count($albumIds),
			],
		], $write ? 'Managed album delete run started.' : 'Managed album delete dry-run started.', $runId);

		try {
			if ($write && $confirmation !== self::DELETE_CONFIRMATION) {
				throw new SyncSafetyException(
					'delete_confirmation_required',
					'Managed album deletion requires the exact confirmation text.',
					400,
					['requiredConfirmation' => self::DELETE_CONFIRMATION],
				);
			}

			$plan = $this->buildDeletePlan($userId, $albumIds, $deleteAll);
			$issues = $this->deleteSafetyIssues($plan);
			$plan['summary']['safetyIssueCount'] = count($issues);
			$currentPlanFingerprint = $this->planFingerprint($plan);

			if ($write && $issues !== []) {
				throw new SyncSafetyException(
					'delete_plan_not_safe',
					'Managed album deletion is blocked because the current plan is not safe to write.',
					409,
					['issues' => $issues],
				);
			}
			if ($write && $planFingerprint === '') {
				throw new SyncSafetyException(
					'delete_plan_fingerprint_required',
					'Run a fresh delete dry-run before deleting managed albums.',
					400,
				);
			}
			if ($write && !hash_equals($currentPlanFingerprint, $planFingerprint)) {
				throw new SyncSafetyException(
					'delete_plan_changed',
					'The managed-album delete plan changed after the dry-run. Run the delete dry-run again before deleting.',
					409,
				);
			}
			if ($write) {
				$this->assertRecentDeleteDryRunFingerprint($userId, $planFingerprint);
			}

			$status = 'delete_dry_run_completed';
			if ($write) {
				$deleteSummary = $this->deletePlan($userId, $plan, $runId);
				$plan['summary'] = array_merge($plan['summary'], $deleteSummary);
				$status = ($deleteSummary['deleteErrors'] ?? 0) > 0
					? 'delete_completed_with_errors'
					: 'delete_completed';
			}

			$summary = $this->runSummary($plan['summary'], $started, $issues);
			$summary['planFingerprint'] = $currentPlanFingerprint;
			$this->syncRunMapper->finish($run, $status, $summary);
			$this->logService->success($write ? 'managed_delete_completed' : 'managed_delete_dry_run_completed', $userId, [
				'durationMs' => $summary['durationMs'],
				'summary' => $summary,
			], $write ? 'Managed album delete run completed.' : 'Managed album delete dry-run completed.', $runId);

			return [
				'runId' => $runId,
				'mode' => $write ? 'delete_write' : 'delete_dry_run',
				'status' => $status,
				'canDelete' => $issues === [],
				'deleteBlockedReasons' => $issues,
				'confirmationText' => self::DELETE_CONFIRMATION,
				'planFingerprint' => $currentPlanFingerprint,
				'summary' => $summary,
				'albums' => $this->publicDeleteAlbums($plan['albums']),
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
				'errorCode' => $write ? 'managed_delete_failed' : 'managed_delete_dry_run_failed',
			];
			$this->syncRunMapper->finish($run, 'failed', $summary, $e->getMessage());
			$this->logService->exception($summary['errorCode'], $e, $userId, [
				'durationMs' => $summary['durationMs'],
			], $runId);
			throw $e;
		}
	}

	private function assertRecentDeleteDryRunFingerprint(string $userId, string $planFingerprint): void {
		$since = time() - self::PLAN_FINGERPRINT_TTL_SECONDS;
		$runs = $this->syncRunMapper->findRecentFinishedForUserAndType($userId, 'delete_dry_run', 'delete_dry_run_completed', $since, 20);
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
			'delete_plan_fingerprint_not_recent',
			'Run a fresh delete dry-run before deleting managed albums.',
			409,
		);
	}

	private function buildDeletePlan(string $userId, array $albumIds, bool $deleteAll): array {
		$limits = $this->settingsService->getJobLimits();
		$maxAlbums = max(1, (int)$limits['maxAlbums']);
		if (!$deleteAll && count($albumIds) > $maxAlbums) {
			throw new SyncSafetyException(
				'delete_selection_limit_exceeded',
				'The selected managed album count exceeds the configured per-run album limit.',
				413,
				[
					'selectedAlbums' => count($albumIds),
					'maxAlbums' => $maxAlbums,
				],
			);
		}
		$selectedIds = $this->normalizeIds($albumIds);
		$totalActive = $this->managedAlbumMapper->countActiveForUser($userId);

		if (!$deleteAll && $selectedIds === []) {
			throw new SyncSafetyException(
				'no_managed_albums_selected',
				'Select at least one managed album before requesting a delete dry-run.',
				400,
			);
		}

		if (!$deleteAll && count($selectedIds) > $maxAlbums) {
			throw new SyncSafetyException(
				'delete_selection_limit_exceeded',
				'The selected managed album count exceeds the configured per-run album limit.',
				413,
				[
					'selectedAlbums' => count($selectedIds),
					'maxAlbums' => $maxAlbums,
				],
			);
		}

		$managedAlbums = $deleteAll
			? $this->managedAlbumMapper->findActiveForUser($userId, $maxAlbums + 1)
			: $this->managedAlbumMapper->findActiveForUserAndIds($userId, $selectedIds, $maxAlbums);

		$truncated = $deleteAll && count($managedAlbums) > $maxAlbums;
		if ($truncated) {
			$managedAlbums = array_slice($managedAlbums, 0, $maxAlbums);
		}

		$foundIds = array_map(static fn (ManagedAlbum $album): int => (int)$album->getId(), $managedAlbums);
		$missingIds = $deleteAll ? [] : array_values(array_diff($selectedIds, $foundIds));

		$summary = [
			'deleteAll' => $deleteAll,
			'totalActiveManagedAlbums' => $totalActive,
			'selectedAlbums' => $deleteAll ? min($totalActive, $maxAlbums) : count($selectedIds),
			'plannedAlbums' => count($managedAlbums),
			'wouldDeletePhotosAlbums' => 0,
			'wouldCleanupTrackingRecords' => 0,
			'blockedAlbums' => 0,
			'missingSelections' => count($missingIds),
			'truncated' => $truncated,
			'maxAlbums' => $maxAlbums,
		];

		$albums = [];
		foreach ($managedAlbums as $managedAlbum) {
			$entry = $this->buildDeletePlanEntry($managedAlbum);
			if (($entry['deleteAction'] ?? '') === 'delete_photos_album') {
				$summary['wouldDeletePhotosAlbums']++;
			} elseif (($entry['deleteAction'] ?? '') === 'cleanup_tracking_only') {
				$summary['wouldCleanupTrackingRecords']++;
			} else {
				$summary['blockedAlbums']++;
			}
			$albums[] = $entry;
		}

		return [
			'summary' => $summary,
			'missingIds' => $missingIds,
			'albums' => $albums,
		];
	}

	private function staleManagedAlbumIds(string $userId): array {
		$settings = $this->settingsService->getEffectiveUserSettings($userId);
		$limits = $this->settingsService->getJobLimits();
		$plan = $this->albumPlanService->buildExecutionPlan($userId, $settings, $limits);
		$summary = $plan['summary'] ?? [];
		$issues = [];
		if (($summary['truncated'] ?? false) === true || ($summary['hasMore'] ?? false) === true) {
			$issues[] = [
				'code' => 'stale_cleanup_plan_truncated',
				'message' => 'The current album plan was truncated by resource limits. Stale cleanup is blocked until a complete plan can be built.',
			];
		}
		foreach (($plan['warnings'] ?? []) as $warning) {
			$code = (string)($warning['code'] ?? '');
			if (in_array($code, ['max_folders_reached', 'max_files_reached', 'storage_unavailable', 'user_folder_unavailable'], true)) {
				$issues[] = [
					'code' => 'stale_cleanup_blocking_warning',
					'warningCode' => $code,
				];
			}
		}
		if ($issues !== []) {
			return [
				'blocked' => true,
				'issues' => $issues,
				'albumIds' => [],
				'currentPlannedAlbums' => (int)($summary['plannedAlbums'] ?? 0),
				'currentPlanTruncated' => true,
			];
		}

		$currentTargets = [];
		foreach (($plan['albums'] ?? []) as $album) {
			$currentTargets[$this->targetPathKey((string)($album['targetPath'] ?? ''))] = true;
		}

		$maxAlbums = max(1, (int)$limits['maxAlbums']);
		$active = $this->managedAlbumMapper->findActiveForUser($userId, $maxAlbums + 1);
		$staleIds = [];
		foreach (array_slice($active, 0, $maxAlbums) as $managedAlbum) {
			if (!isset($currentTargets[$this->targetPathKey($managedAlbum->getTargetPath())])) {
				$staleIds[] = (int)$managedAlbum->getId();
			}
		}

		return [
			'blocked' => false,
			'issues' => [],
			'albumIds' => $staleIds,
			'totalActiveManagedAlbums' => $this->managedAlbumMapper->countActiveForUser($userId),
			'currentPlannedAlbums' => (int)($summary['plannedAlbums'] ?? 0),
			'currentPlanTruncated' => false,
		];
	}

	private function buildDeletePlanEntry(ManagedAlbum $managedAlbum): array {
		$entry = [
			'_entity' => $managedAlbum,
			'managedId' => (int)$managedAlbum->getId(),
			'albumName' => $managedAlbum->getAlbumName(),
			'sourceRoot' => $managedAlbum->getSourceRoot(),
			'targetPath' => $managedAlbum->getTargetPath(),
			'mediaCount' => $managedAlbum->getMediaCount(),
			'status' => $managedAlbum->getStatus(),
			'lastSyncAt' => $managedAlbum->getLastSyncAt(),
			'photosAlbumPresent' => false,
			'deleteAction' => 'blocked',
			'blockReason' => '',
		];

		$photosAlbumId = $managedAlbum->getPhotosAlbumId();
		if ($photosAlbumId === null) {
			$entry['deleteAction'] = 'cleanup_tracking_only';
			$entry['blockReason'] = 'no_photos_album_id';
			return $entry;
		}

		$photosAlbum = $this->photosAlbumAdapter->findAlbumById($photosAlbumId);
		if ($photosAlbum === null) {
			$entry['deleteAction'] = 'cleanup_tracking_only';
			$entry['blockReason'] = 'photos_album_missing';
			return $entry;
		}

		$entry['photosAlbumPresent'] = true;
		if (($photosAlbum['userId'] ?? '') !== $managedAlbum->getUserId()) {
			$entry['blockReason'] = 'photos_album_owner_mismatch';
			return $entry;
		}

		if (($photosAlbum['name'] ?? '') !== $managedAlbum->getAlbumName()) {
			$entry['blockReason'] = 'photos_album_name_mismatch';
			return $entry;
		}

		$entry['deleteAction'] = 'delete_photos_album';
		return $entry;
	}

	private function deleteSafetyIssues(array $plan): array {
		$summary = $plan['summary'] ?? [];
		$issues = [];

		if (($summary['plannedAlbums'] ?? 0) === 0) {
			$issues[] = [
				'code' => 'no_deletable_managed_albums',
				'message' => 'No active SakuraAlbum managed albums were found for the current selection.',
			];
		}
		if (($summary['truncated'] ?? false) === true) {
			$issues[] = [
				'code' => 'delete_plan_truncated',
				'message' => 'The selected delete-all plan exceeds the configured per-run album limit.',
				'maxAlbums' => $summary['maxAlbums'] ?? null,
			];
		}
		if (($summary['missingSelections'] ?? 0) > 0) {
			$issues[] = [
				'code' => 'managed_album_selection_missing',
				'count' => (int)$summary['missingSelections'],
			];
		}
		if (($summary['blockedAlbums'] ?? 0) > 0) {
			$issues[] = [
				'code' => 'managed_album_delete_blocked',
				'count' => (int)$summary['blockedAlbums'],
			];
		}

		return $issues;
	}

	private function deletePlan(string $userId, array $plan, int $runId): array {
		$summary = [
			'deletedPhotosAlbums' => 0,
			'cleanedTrackingRecords' => 0,
			'deleteErrors' => 0,
		];

		foreach ($plan['albums'] as $album) {
			$managedAlbum = $album['_entity'] ?? null;
			if (!$managedAlbum instanceof ManagedAlbum) {
				continue;
			}

			try {
				if (($album['deleteAction'] ?? '') === 'delete_photos_album') {
					$photosAlbumId = $managedAlbum->getPhotosAlbumId();
					if ($photosAlbumId === null) {
						throw new \RuntimeException('Managed album lost its Photos album id before deletion.');
					}

					$this->assertStillDeletable($managedAlbum);
					$this->photosAlbumAdapter->deleteAlbum($userId, $photosAlbumId);
					$this->managedAlbumMapper->markDeleted($managedAlbum);
					$summary['deletedPhotosAlbums']++;
					$this->logService->success('managed_album_deleted', $userId, [
						'managedId' => (int)$managedAlbum->getId(),
						'albumName' => $managedAlbum->getAlbumName(),
					], 'Managed Photos album deleted.', $runId);
					continue;
				}

				if (($album['deleteAction'] ?? '') === 'cleanup_tracking_only') {
					$this->managedAlbumMapper->markDeleted($managedAlbum);
					$summary['cleanedTrackingRecords']++;
					$this->logService->success('managed_album_tracking_cleaned', $userId, [
						'managedId' => (int)$managedAlbum->getId(),
						'albumName' => $managedAlbum->getAlbumName(),
						'reason' => $album['blockReason'] ?? '',
					], 'Managed album tracking record cleaned.', $runId);
				}
			} catch (\Throwable $e) {
				$summary['deleteErrors']++;
				$this->logService->exception('managed_album_delete_failed', $e, $userId, [
					'managedId' => (int)$managedAlbum->getId(),
					'albumName' => $managedAlbum->getAlbumName(),
					'action' => $album['deleteAction'] ?? '',
				], $runId);
			}
		}

		return $summary;
	}

	private function assertStillDeletable(ManagedAlbum $managedAlbum): void {
		$photosAlbumId = $managedAlbum->getPhotosAlbumId();
		if ($photosAlbumId === null) {
			throw new \RuntimeException('Managed album has no Photos album id.');
		}

		$photosAlbum = $this->photosAlbumAdapter->findAlbumById($photosAlbumId);
		if ($photosAlbum === null) {
			throw new \RuntimeException('Photos album disappeared before deletion.');
		}
		if (($photosAlbum['userId'] ?? '') !== $managedAlbum->getUserId()) {
			throw new \RuntimeException('Photos album owner changed before deletion.');
		}
		if (($photosAlbum['name'] ?? '') !== $managedAlbum->getAlbumName()) {
			throw new \RuntimeException('Photos album name changed before deletion.');
		}
	}

	private function runSummary(array $summary, float $started, array $issues): array {
		$summary['durationMs'] = (int)((microtime(true) - $started) * 1000);
		$summary['deleteBlocked'] = $issues !== [];
		return $summary;
	}

	private function planFingerprint(array $plan): string {
		$albums = array_map(
			static fn (array $album): array => [
				'managedId' => (int)($album['managedId'] ?? 0),
				'albumName' => (string)($album['albumName'] ?? ''),
				'targetPath' => (string)($album['targetPath'] ?? ''),
				'mediaCount' => (int)($album['mediaCount'] ?? 0),
				'photosAlbumPresent' => (bool)($album['photosAlbumPresent'] ?? false),
				'deleteAction' => (string)($album['deleteAction'] ?? ''),
				'blockReason' => (string)($album['blockReason'] ?? ''),
			],
			$plan['albums'] ?? [],
		);

		return hash('sha256', json_encode([
			'version' => 1,
			'missingIds' => array_map('intval', $plan['missingIds'] ?? []),
			'summary' => [
				'deleteAll' => (bool)($plan['summary']['deleteAll'] ?? false),
				'totalActiveManagedAlbums' => (int)($plan['summary']['totalActiveManagedAlbums'] ?? 0),
				'plannedAlbums' => (int)($plan['summary']['plannedAlbums'] ?? 0),
				'wouldDeletePhotosAlbums' => (int)($plan['summary']['wouldDeletePhotosAlbums'] ?? 0),
				'wouldCleanupTrackingRecords' => (int)($plan['summary']['wouldCleanupTrackingRecords'] ?? 0),
				'blockedAlbums' => (int)($plan['summary']['blockedAlbums'] ?? 0),
				'missingSelections' => (int)($plan['summary']['missingSelections'] ?? 0),
				'truncated' => (bool)($plan['summary']['truncated'] ?? false),
				'staleOnly' => (bool)($plan['summary']['staleOnly'] ?? false),
				'safetyIssueCount' => (int)($plan['summary']['safetyIssueCount'] ?? 0),
			],
			'albums' => $albums,
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
	}

	private function targetPathKey(string $path): string {
		return PathHelper::displayPath($path);
	}

	private function publicManagedAlbum(ManagedAlbum $album): array {
		return [
			'managedId' => (int)$album->getId(),
			'albumName' => $album->getAlbumName(),
			'sourceRoot' => $album->getSourceRoot(),
			'targetPath' => $album->getTargetPath(),
			'mediaCount' => $album->getMediaCount(),
			'status' => $album->getStatus(),
			'lastSyncAt' => $album->getLastSyncAt(),
		];
	}

	private function publicDeleteAlbums(array $albums): array {
		$publicKeys = array_flip([
			'managedId',
			'albumName',
			'sourceRoot',
			'targetPath',
			'mediaCount',
			'status',
			'lastSyncAt',
			'photosAlbumPresent',
			'deleteAction',
			'blockReason',
		]);

		return array_map(
			static fn (array $album): array => array_intersect_key($album, $publicKeys),
			$albums,
		);
	}

	/**
	 * @return int[]
	 */
	private function normalizeIds(array $ids): array {
		$normalized = [];
		foreach ($ids as $id) {
			if (!is_numeric($id)) {
				continue;
			}
			$id = (int)$id;
			if ($id <= 0) {
				continue;
			}
			$normalized[] = $id;
		}

		return array_values(array_unique($normalized));
	}
}
