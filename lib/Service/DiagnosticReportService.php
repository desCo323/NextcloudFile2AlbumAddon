<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\AppInfo\Application;

class DiagnosticReportService {
	public function __construct(
		private readonly SettingsService $settingsService,
		private readonly AutoSyncService $autoSyncService,
		private readonly AlbumSyncService $albumSyncService,
		private readonly ManagedAlbumDeletionService $managedAlbumDeletionService,
		private readonly LogService $logService,
	) {
	}

	public function userReport(string $userId, int $limit = 25): array {
		$limit = $this->limit($limit, 1, 100);
		$userSettings = $this->settingsService->getUserSettings($userId);
		$effectiveSettings = $this->settingsService->getEffectiveUserSettings($userId);

		$managed = $this->managedAlbumDeletionService->managedAlbums($userId, min(100, $limit));

		return [
			'meta' => $this->meta('user', $userId, $limit),
			'sendMailReady' => false,
			'sendMailLater' => true,
			'settings' => [
				'user' => $this->publicUserSettings($userSettings),
				'effective' => $this->publicEffectiveSettings($effectiveSettings),
				'admin' => $this->publicAdminSettings($this->settingsService->getAdminSettings()),
			],
			'sync' => [
				'queue' => $this->autoSyncService->queueStatusForUser($userId, min(20, $limit)),
				'cursor' => $this->albumSyncService->cursorStatus($userId, 5),
				'runs' => $this->albumSyncService->recentRuns($userId, min(20, $limit)),
			],
			'managedAlbums' => [
				'total' => $managed['total'] ?? 0,
				'truncated' => $managed['truncated'] ?? false,
				'sample' => $managed['albums'] ?? [],
			],
			'logs' => $this->logService->recentLogs($limit, null, $userId),
			'notes' => [
				'Secrets are redacted before SakuraAlbum stores diagnostic context.',
				'Email delivery is intentionally not enabled yet; this report is the payload prepared for a future mail sender.',
			],
		];
	}

	public function adminReport(?string $userId = null, int $limit = 50): array {
		$limit = $this->limit($limit, 1, 200);
		$userId = is_string($userId) && trim($userId) !== '' ? trim($userId) : null;
		$report = [
			'meta' => $this->meta('admin', $userId, $limit),
			'sendMailReady' => false,
			'sendMailLater' => true,
			'adminSettings' => $this->publicAdminSettings($this->settingsService->getAdminSettings()),
			'autoSync' => $this->autoSyncService->queueStatus(min(100, $limit)),
			'logs' => $this->logService->recentLogs($limit, null, $userId),
		];

		if ($userId !== null) {
			$report['user'] = $this->userReport($userId, min(100, $limit));
		}

		return $report;
	}

	private function meta(string $scope, ?string $userId, int $limit): array {
		return [
			'appId' => Application::APP_ID,
			'schemaVersion' => 1,
			'scope' => $scope,
			'userId' => $userId,
			'createdAt' => time(),
			'limit' => $limit,
			'redaction' => [
				'knownSecretKeys' => 'redacted-before-storage',
				'exceptionTraceArguments' => 'omitted',
			],
		];
	}

	private function publicUserSettings(array $settings): array {
		return [
			'enabled' => $settings['enabled'] ?? false,
			'sourceFolders' => $settings['sourceFolders'] ?? [],
			'includePaths' => $settings['includePaths'] ?? [],
			'excludePatterns' => $settings['excludePatterns'] ?? [],
			'namingTemplate' => $settings['namingTemplate'] ?? '',
			'separator' => $settings['separator'] ?? '',
			'albumDepth' => $settings['albumDepth'] ?? 0,
			'includeImages' => $settings['includeImages'] ?? false,
			'includeVideos' => $settings['includeVideos'] ?? false,
			'autoSyncEnabled' => $settings['autoSyncEnabled'] ?? false,
		];
	}

	private function publicEffectiveSettings(array $settings): array {
		return [
			'enabled' => $settings['enabled'] ?? false,
			'adminEnabled' => $settings['adminEnabled'] ?? false,
			'userEnabled' => $settings['userEnabled'] ?? false,
			'includePaths' => $settings['includePaths'] ?? [],
			'sourceFolders' => $settings['sourceFolders'] ?? [],
			'excludePatterns' => $settings['excludePatterns'] ?? [],
			'namingTemplate' => $settings['namingTemplate'] ?? '',
			'separator' => $settings['separator'] ?? '',
			'albumDepth' => $settings['albumDepth'] ?? 0,
			'includeImages' => $settings['includeImages'] ?? false,
			'includeVideos' => $settings['includeVideos'] ?? false,
			'autoSyncEnabled' => $settings['autoSyncEnabled'] ?? false,
			'autoSyncAvailable' => $settings['autoSyncAvailable'] ?? false,
			'autoSyncActive' => $settings['autoSyncActive'] ?? false,
			'autoSyncMode' => $settings['autoSyncMode'] ?? 'manual',
			'autoSyncDebounceSeconds' => $settings['autoSyncDebounceSeconds'] ?? 0,
			'namingSchemaVersion' => $settings['namingSchemaVersion'] ?? 0,
			'syncRemoveMissingFiles' => $settings['syncRemoveMissingFiles'] ?? false,
			'syncDeleteMissingManagedAlbums' => $settings['syncDeleteMissingManagedAlbums'] ?? false,
		];
	}

	private function publicAdminSettings(array $settings): array {
		return [
			'enabled' => $settings['enabled'] ?? false,
			'defaultIncludePaths' => $settings['defaultIncludePaths'] ?? [],
			'defaultExcludePatterns' => $settings['defaultExcludePatterns'] ?? [],
			'maxScanDepth' => $settings['maxScanDepth'] ?? 0,
			'maxPreviewFolders' => $settings['maxPreviewFolders'] ?? 0,
			'maxPreviewFiles' => $settings['maxPreviewFiles'] ?? 0,
			'maxJobFolders' => $settings['maxJobFolders'] ?? 0,
			'maxJobFiles' => $settings['maxJobFiles'] ?? 0,
			'maxAlbumsPerRun' => $settings['maxAlbumsPerRun'] ?? 0,
			'allowVideos' => $settings['allowVideos'] ?? false,
			'jobIntervalMinutes' => $settings['jobIntervalMinutes'] ?? 0,
			'autoSyncMode' => $settings['autoSyncMode'] ?? 'manual',
			'autoSyncDebounceSeconds' => $settings['autoSyncDebounceSeconds'] ?? 0,
			'autoSyncMaxUsersPerRun' => $settings['autoSyncMaxUsersPerRun'] ?? 0,
			'autoSyncMaxRuntimeSeconds' => $settings['autoSyncMaxRuntimeSeconds'] ?? 0,
			'autoSyncMaxEventsPerRun' => $settings['autoSyncMaxEventsPerRun'] ?? 0,
			'syncRemoveMissingFiles' => $settings['syncRemoveMissingFiles'] ?? false,
			'syncDeleteMissingManagedAlbums' => $settings['syncDeleteMissingManagedAlbums'] ?? false,
			'debugMode' => $settings['debugMode'] ?? false,
			'debugRetentionDays' => $settings['debugRetentionDays'] ?? 0,
			'debugMaxContextLength' => $settings['debugMaxContextLength'] ?? 0,
		];
	}

	private function limit(int $value, int $min, int $max): int {
		return max($min, min($max, $value));
	}
}
