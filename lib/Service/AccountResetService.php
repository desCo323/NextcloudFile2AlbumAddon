<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\BackgroundJob\AlbumExportJob;
use OCA\SakuraAlbum\Db\DirtyPathMapper;
use OCA\SakuraAlbum\Db\DownloadJobMapper;
use OCA\SakuraAlbum\Db\ManagedAlbumMapper;
use OCA\SakuraAlbum\Db\SyncCursorMapper;
use OCP\BackgroundJob\IJobList;

class AccountResetService {
	public const RESET_CONFIRMATION = 'RESET_SAKURAALBUM';

	public function __construct(
		private readonly ManagedAlbumDeletionService $managedAlbumDeletionService,
		private readonly ManagedAlbumMapper $managedAlbumMapper,
		private readonly DirtyPathMapper $dirtyPathMapper,
		private readonly SyncCursorMapper $syncCursorMapper,
		private readonly DownloadJobMapper $downloadJobMapper,
		private readonly IJobList $jobList,
		private readonly SettingsService $settingsService,
		private readonly LogService $logService,
	) {
	}

	public function dryRun(string $userId): array {
		$deletePlan = $this->managedAlbumDeletionService->dryRunDelete($userId, [], true);
		$plan = $this->resetPlan($userId, $deletePlan);
		$this->logService->info('account_reset_dry_run_completed', $userId, [
			'summary' => $plan['summary'],
		], 'SakuraAlbum account reset dry-run completed.', (int)($deletePlan['runId'] ?? 0));

		return $plan;
	}

	public function reset(string $userId, string $confirmation, string $planFingerprint, string $deletePlanFingerprint): array {
		if ($confirmation !== self::RESET_CONFIRMATION) {
			throw new SyncSafetyException(
				'account_reset_confirmation_required',
				'SakuraAlbum account reset requires the exact confirmation text.',
				400,
				['requiredConfirmation' => self::RESET_CONFIRMATION],
			);
		}
		if ($planFingerprint === '' || $deletePlanFingerprint === '') {
			throw new SyncSafetyException(
				'account_reset_plan_required',
				'Run a fresh SakuraAlbum reset preview before resetting the account.',
				400,
			);
		}

		$dryRun = $this->dryRun($userId);
		if (!hash_equals((string)$dryRun['planFingerprint'], $planFingerprint)) {
			throw new SyncSafetyException(
				'account_reset_plan_changed',
				'The SakuraAlbum reset plan changed after the preview. Run the reset preview again.',
				409,
			);
		}

		$deleteResult = ((int)($dryRun['summary']['activeManagedAlbums'] ?? 0)) > 0
			? $this->managedAlbumDeletionService->delete(
				$userId,
				[],
				true,
				ManagedAlbumDeletionService::DELETE_CONFIRMATION,
				$deletePlanFingerprint,
			)
			: $dryRun['deleteResult'];

		$deletedDirtyPaths = $this->dirtyPathMapper->deleteForUser($userId);
		$deletedCursors = $this->syncCursorMapper->deleteForUser($userId);
		$downloadJobIds = $this->downloadJobMapper->findIdsForUser($userId);
		$removedDownloadQueueJobs = $this->removeDownloadQueueJobs($userId, $downloadJobIds);
		$deletedDownloadJobs = $this->downloadJobMapper->deleteForUser($userId);
		$settings = $this->settingsService->resetUserSettings($userId);
		$summary = $this->resetSummary($deleteResult, $deletedDirtyPaths, $deletedCursors, $deletedDownloadJobs, $removedDownloadQueueJobs);

		$this->logService->success('account_reset_completed', $userId, [
			'summary' => $summary,
		], 'SakuraAlbum account reset completed.', (int)($deleteResult['runId'] ?? 0));

		return [
			'status' => 'account_reset_completed',
			'confirmationText' => self::RESET_CONFIRMATION,
			'planFingerprint' => $planFingerprint,
			'deletePlanFingerprint' => $deletePlanFingerprint,
			'summary' => $summary,
			'deleteResult' => $deleteResult,
			'settings' => $settings,
			'effectiveSettings' => $this->settingsService->getEffectiveUserSettings($userId),
		];
	}

	private function resetPlan(string $userId, array $deletePlan): array {
		$summary = $this->resetSummary($deletePlan, 0, 0, 0, 0);
		$summary['activeManagedAlbums'] = $this->managedAlbumMapper->countActiveForUser($userId);
		$summary['willResetSettings'] = true;
		$summary['willClearQueueAndCursors'] = true;
		$deleteIssues = $deletePlan['deleteBlockedReasons'] ?? [];
		$summary['deleteCanRun'] = ($deletePlan['canDelete'] ?? false) === true || $this->onlyNoAlbumsIssue($deleteIssues);
		$summary['deleteBlockedReasons'] = $deletePlan['deleteBlockedReasons'] ?? [];
		$fingerprint = $this->planFingerprint($summary, (string)($deletePlan['planFingerprint'] ?? ''));

		return [
			'status' => 'account_reset_dry_run_completed',
			'canReset' => $summary['deleteCanRun'],
			'resetBlockedReasons' => $summary['deleteCanRun'] ? [] : $summary['deleteBlockedReasons'],
			'confirmationText' => self::RESET_CONFIRMATION,
			'planFingerprint' => $fingerprint,
			'deletePlanFingerprint' => (string)($deletePlan['planFingerprint'] ?? ''),
			'summary' => $summary,
			'deleteResult' => $deletePlan,
		];
	}

	private function resetSummary(array $deleteResult, int $deletedDirtyPaths, int $deletedCursors, int $deletedDownloadJobs, int $removedDownloadQueueJobs): array {
		$deleteSummary = $deleteResult['summary'] ?? [];

		return [
			'plannedAlbums' => (int)($deleteSummary['plannedAlbums'] ?? 0),
			'wouldDeletePhotosAlbums' => (int)($deleteSummary['wouldDeletePhotosAlbums'] ?? 0),
			'wouldCleanupTrackingRecords' => (int)($deleteSummary['wouldCleanupTrackingRecords'] ?? 0),
			'deletedPhotosAlbums' => (int)($deleteSummary['deletedPhotosAlbums'] ?? 0),
			'cleanedTrackingRecords' => (int)($deleteSummary['cleanedTrackingRecords'] ?? 0),
			'blockedAlbums' => (int)($deleteSummary['blockedAlbums'] ?? 0),
			'deleteErrors' => (int)($deleteSummary['deleteErrors'] ?? 0),
			'deletedDirtyPaths' => $deletedDirtyPaths,
			'deletedSyncCursors' => $deletedCursors,
			'deletedDownloadJobs' => $deletedDownloadJobs,
			'removedDownloadQueueJobs' => $removedDownloadQueueJobs,
		];
	}

	/**
	 * @param int[] $downloadJobIds
	 */
	private function removeDownloadQueueJobs(string $userId, array $downloadJobIds): int {
		$removed = 0;
		foreach ($downloadJobIds as $downloadJobId) {
			try {
				$this->jobList->remove(AlbumExportJob::class, ['jobId' => $downloadJobId]);
				$removed++;
			} catch (\Throwable $e) {
				$this->logService->exception('account_reset_export_queue_cleanup_failed', $e, $userId, [
					'downloadJobId' => $downloadJobId,
				]);
			}
		}

		return $removed;
	}

	private function onlyNoAlbumsIssue(array $issues): bool {
		if ($issues === []) {
			return false;
		}

		foreach ($issues as $issue) {
			if (($issue['code'] ?? '') !== 'no_deletable_managed_albums') {
				return false;
			}
		}

		return true;
	}

	private function planFingerprint(array $summary, string $deletePlanFingerprint): string {
		return hash('sha256', json_encode([
			'version' => 1,
			'deletePlanFingerprint' => $deletePlanFingerprint,
			'summary' => $summary,
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
	}
}
