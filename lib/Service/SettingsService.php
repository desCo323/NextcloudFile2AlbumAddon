<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\AppInfo\Application;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Config\IUserConfig;

class SettingsService {
	private const USER_SETTINGS_KEY = 'settings';
	private const ADMIN_GLOBAL_ENABLED_KEY = 'globalEnabled';

	private const ADMIN_DEFAULTS = [
		'enabled' => false,
		'defaultIncludePaths' => ['/Photos'],
		'defaultExcludePatterns' => ['.nomedia', '.noimage'],
		'maxScanDepth' => 8,
		'maxPreviewFolders' => 500,
		'maxPreviewFiles' => 5000,
		'maxJobFolders' => 5000,
		'maxJobFiles' => 50000,
		'maxAlbumsPerRun' => 500,
		'allowVideos' => false,
		'jobIntervalMinutes' => 360,
		'autoSyncMode' => 'manual',
		'autoSyncDebounceSeconds' => 300,
		'autoSyncMaxUsersPerRun' => 3,
		'autoSyncMaxRuntimeSeconds' => 30,
		'autoSyncMaxEventsPerRun' => 200,
		'syncRemoveMissingFiles' => true,
		'syncDeleteMissingManagedAlbums' => false,
		'requireBulkDeleteConfirmation' => true,
		'debugMode' => false,
		'debugRetentionDays' => 14,
		'debugMaxContextLength' => 8000,
	];

	private const USER_DEFAULTS = [
		'enabled' => false,
		'includePaths' => [],
		'excludePatterns' => [],
		'namingTemplate' => 'root_relative',
		'separator' => ' - ',
		'albumDepth' => 1,
		'includeImages' => true,
		'includeVideos' => false,
	];

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IUserConfig $userConfig,
	) {
	}

	public function getAdminSettings(): array {
		return [
			'enabled' => $this->appConfig->getAppValueBool(self::ADMIN_GLOBAL_ENABLED_KEY, self::ADMIN_DEFAULTS['enabled']),
			'defaultIncludePaths' => $this->appConfig->getAppValueArray('defaultIncludePaths', self::ADMIN_DEFAULTS['defaultIncludePaths'], lazy: true),
			'defaultExcludePatterns' => $this->appConfig->getAppValueArray('defaultExcludePatterns', self::ADMIN_DEFAULTS['defaultExcludePatterns'], lazy: true),
			'maxScanDepth' => $this->appConfig->getAppValueInt('maxScanDepth', self::ADMIN_DEFAULTS['maxScanDepth']),
			'maxPreviewFolders' => $this->appConfig->getAppValueInt('maxPreviewFolders', self::ADMIN_DEFAULTS['maxPreviewFolders']),
			'maxPreviewFiles' => $this->appConfig->getAppValueInt('maxPreviewFiles', self::ADMIN_DEFAULTS['maxPreviewFiles']),
			'maxJobFolders' => $this->appConfig->getAppValueInt('maxJobFolders', self::ADMIN_DEFAULTS['maxJobFolders']),
			'maxJobFiles' => $this->appConfig->getAppValueInt('maxJobFiles', self::ADMIN_DEFAULTS['maxJobFiles']),
			'maxAlbumsPerRun' => $this->appConfig->getAppValueInt('maxAlbumsPerRun', self::ADMIN_DEFAULTS['maxAlbumsPerRun']),
			'allowVideos' => $this->appConfig->getAppValueBool('allowVideos', self::ADMIN_DEFAULTS['allowVideos']),
			'jobIntervalMinutes' => $this->appConfig->getAppValueInt('jobIntervalMinutes', self::ADMIN_DEFAULTS['jobIntervalMinutes']),
			'autoSyncMode' => $this->autoSyncMode($this->appConfig->getAppValueString('autoSyncMode', self::ADMIN_DEFAULTS['autoSyncMode'])),
			'autoSyncDebounceSeconds' => $this->appConfig->getAppValueInt('autoSyncDebounceSeconds', self::ADMIN_DEFAULTS['autoSyncDebounceSeconds']),
			'autoSyncMaxUsersPerRun' => $this->appConfig->getAppValueInt('autoSyncMaxUsersPerRun', self::ADMIN_DEFAULTS['autoSyncMaxUsersPerRun']),
			'autoSyncMaxRuntimeSeconds' => $this->appConfig->getAppValueInt('autoSyncMaxRuntimeSeconds', self::ADMIN_DEFAULTS['autoSyncMaxRuntimeSeconds']),
			'autoSyncMaxEventsPerRun' => $this->appConfig->getAppValueInt('autoSyncMaxEventsPerRun', self::ADMIN_DEFAULTS['autoSyncMaxEventsPerRun']),
			'syncRemoveMissingFiles' => $this->appConfig->getAppValueBool('syncRemoveMissingFiles', self::ADMIN_DEFAULTS['syncRemoveMissingFiles']),
			'syncDeleteMissingManagedAlbums' => $this->appConfig->getAppValueBool('syncDeleteMissingManagedAlbums', self::ADMIN_DEFAULTS['syncDeleteMissingManagedAlbums']),
			'requireBulkDeleteConfirmation' => true,
			'debugMode' => $this->appConfig->getAppValueBool('debugMode', self::ADMIN_DEFAULTS['debugMode']),
			'debugRetentionDays' => $this->appConfig->getAppValueInt('debugRetentionDays', self::ADMIN_DEFAULTS['debugRetentionDays']),
			'debugMaxContextLength' => $this->appConfig->getAppValueInt('debugMaxContextLength', self::ADMIN_DEFAULTS['debugMaxContextLength']),
		];
	}

	public function saveAdminSettings(array $input): array {
		$settings = [
			'enabled' => $this->boolValue($input['enabled'] ?? self::ADMIN_DEFAULTS['enabled']),
			'defaultIncludePaths' => $this->pathList($input['defaultIncludePaths'] ?? self::ADMIN_DEFAULTS['defaultIncludePaths']),
			'defaultExcludePatterns' => $this->stringList($input['defaultExcludePatterns'] ?? self::ADMIN_DEFAULTS['defaultExcludePatterns']),
			'maxScanDepth' => $this->intValue($input['maxScanDepth'] ?? self::ADMIN_DEFAULTS['maxScanDepth'], 0, 20),
			'maxPreviewFolders' => $this->intValue($input['maxPreviewFolders'] ?? self::ADMIN_DEFAULTS['maxPreviewFolders'], 10, 20000),
			'maxPreviewFiles' => $this->intValue($input['maxPreviewFiles'] ?? self::ADMIN_DEFAULTS['maxPreviewFiles'], 10, 200000),
			'maxJobFolders' => $this->intValue($input['maxJobFolders'] ?? self::ADMIN_DEFAULTS['maxJobFolders'], 10, 100000),
			'maxJobFiles' => $this->intValue($input['maxJobFiles'] ?? self::ADMIN_DEFAULTS['maxJobFiles'], 10, 1000000),
			'maxAlbumsPerRun' => $this->intValue($input['maxAlbumsPerRun'] ?? self::ADMIN_DEFAULTS['maxAlbumsPerRun'], 1, 5000),
			'allowVideos' => $this->boolValue($input['allowVideos'] ?? self::ADMIN_DEFAULTS['allowVideos']),
			'jobIntervalMinutes' => $this->intValue($input['jobIntervalMinutes'] ?? self::ADMIN_DEFAULTS['jobIntervalMinutes'], 5, 10080),
			'autoSyncMode' => $this->autoSyncMode((string)($input['autoSyncMode'] ?? self::ADMIN_DEFAULTS['autoSyncMode'])),
			'autoSyncDebounceSeconds' => $this->intValue($input['autoSyncDebounceSeconds'] ?? self::ADMIN_DEFAULTS['autoSyncDebounceSeconds'], 30, 86400),
			'autoSyncMaxUsersPerRun' => $this->intValue($input['autoSyncMaxUsersPerRun'] ?? self::ADMIN_DEFAULTS['autoSyncMaxUsersPerRun'], 1, 1000),
			'autoSyncMaxRuntimeSeconds' => $this->intValue($input['autoSyncMaxRuntimeSeconds'] ?? self::ADMIN_DEFAULTS['autoSyncMaxRuntimeSeconds'], 5, 3600),
			'autoSyncMaxEventsPerRun' => $this->intValue($input['autoSyncMaxEventsPerRun'] ?? self::ADMIN_DEFAULTS['autoSyncMaxEventsPerRun'], 1, 100000),
			'syncRemoveMissingFiles' => $this->boolValue($input['syncRemoveMissingFiles'] ?? self::ADMIN_DEFAULTS['syncRemoveMissingFiles']),
			'syncDeleteMissingManagedAlbums' => $this->boolValue($input['syncDeleteMissingManagedAlbums'] ?? self::ADMIN_DEFAULTS['syncDeleteMissingManagedAlbums']),
			'requireBulkDeleteConfirmation' => true,
			'debugMode' => $this->boolValue($input['debugMode'] ?? self::ADMIN_DEFAULTS['debugMode']),
			'debugRetentionDays' => $this->intValue($input['debugRetentionDays'] ?? self::ADMIN_DEFAULTS['debugRetentionDays'], 1, 365),
			'debugMaxContextLength' => $this->intValue($input['debugMaxContextLength'] ?? self::ADMIN_DEFAULTS['debugMaxContextLength'], 1000, 100000),
		];

		$this->appConfig->setAppValueBool(self::ADMIN_GLOBAL_ENABLED_KEY, $settings['enabled']);
		$this->appConfig->setAppValueArray('defaultIncludePaths', $settings['defaultIncludePaths'], lazy: true);
		$this->appConfig->setAppValueArray('defaultExcludePatterns', $settings['defaultExcludePatterns'], lazy: true);
		$this->appConfig->setAppValueInt('maxScanDepth', $settings['maxScanDepth']);
		$this->appConfig->setAppValueInt('maxPreviewFolders', $settings['maxPreviewFolders']);
		$this->appConfig->setAppValueInt('maxPreviewFiles', $settings['maxPreviewFiles']);
		$this->appConfig->setAppValueInt('maxJobFolders', $settings['maxJobFolders']);
		$this->appConfig->setAppValueInt('maxJobFiles', $settings['maxJobFiles']);
		$this->appConfig->setAppValueInt('maxAlbumsPerRun', $settings['maxAlbumsPerRun']);
		$this->appConfig->setAppValueBool('allowVideos', $settings['allowVideos']);
		$this->appConfig->setAppValueInt('jobIntervalMinutes', $settings['jobIntervalMinutes']);
		$this->appConfig->setAppValueString('autoSyncMode', $settings['autoSyncMode']);
		$this->appConfig->setAppValueInt('autoSyncDebounceSeconds', $settings['autoSyncDebounceSeconds']);
		$this->appConfig->setAppValueInt('autoSyncMaxUsersPerRun', $settings['autoSyncMaxUsersPerRun']);
		$this->appConfig->setAppValueInt('autoSyncMaxRuntimeSeconds', $settings['autoSyncMaxRuntimeSeconds']);
		$this->appConfig->setAppValueInt('autoSyncMaxEventsPerRun', $settings['autoSyncMaxEventsPerRun']);
		$this->appConfig->setAppValueBool('syncRemoveMissingFiles', $settings['syncRemoveMissingFiles']);
		$this->appConfig->setAppValueBool('syncDeleteMissingManagedAlbums', $settings['syncDeleteMissingManagedAlbums']);
		$this->appConfig->setAppValueBool('requireBulkDeleteConfirmation', $settings['requireBulkDeleteConfirmation']);
		$this->appConfig->setAppValueBool('debugMode', $settings['debugMode']);
		$this->appConfig->setAppValueInt('debugRetentionDays', $settings['debugRetentionDays']);
		$this->appConfig->setAppValueInt('debugMaxContextLength', $settings['debugMaxContextLength']);

		return $settings;
	}

	public function isDebugMode(): bool {
		return $this->appConfig->getAppValueBool('debugMode', self::ADMIN_DEFAULTS['debugMode']);
	}

	public function getUserSettings(string $userId): array {
		$stored = $this->userConfig->getValueArray($userId, Application::APP_ID, self::USER_SETTINGS_KEY, [], lazy: true);
		return $this->normalizeUserSettings($stored, includeAdminDefaults: false);
	}

	public function getEffectiveUserSettings(string $userId, ?array $overrides = null): array {
		$admin = $this->getAdminSettings();
		$user = $this->getUserSettings($userId);

		if ($overrides !== null) {
			$user = $this->normalizeUserSettings(array_merge($user, $overrides), includeAdminDefaults: false);
		}

		$includePaths = $user['includePaths'] !== [] ? $user['includePaths'] : $admin['defaultIncludePaths'];
		$excludePatterns = array_values(array_unique(array_merge(
			$admin['defaultExcludePatterns'],
			$user['excludePatterns'],
		)));

		return [
			'enabled' => $admin['enabled'] && $user['enabled'],
			'adminEnabled' => $admin['enabled'],
			'userEnabled' => $user['enabled'],
			'includePaths' => $includePaths,
			'excludePatterns' => $excludePatterns,
			'namingTemplate' => $user['namingTemplate'],
			'separator' => $user['separator'],
			'albumDepth' => min($user['albumDepth'], $admin['maxScanDepth']),
			'includeImages' => $user['includeImages'],
			'includeVideos' => $admin['allowVideos'] && $user['includeVideos'],
			'namingSchemaVersion' => Application::NAMING_SCHEMA_VERSION,
			'syncRemoveMissingFiles' => $admin['syncRemoveMissingFiles'],
			'syncDeleteMissingManagedAlbums' => $admin['syncDeleteMissingManagedAlbums'],
		];
	}

	public function saveUserSettings(string $userId, array $input): array {
		$settings = $this->normalizeUserSettings($input, includeAdminDefaults: false);
		$this->userConfig->setValueArray(
			$userId,
			Application::APP_ID,
			self::USER_SETTINGS_KEY,
			$settings,
			lazy: true,
			flags: IUserConfig::FLAG_INTERNAL,
		);

		return $settings;
	}

	public function getPreviewLimits(): array {
		$admin = $this->getAdminSettings();
		return [
			'maxDepth' => $admin['maxScanDepth'],
			'maxFolders' => $admin['maxPreviewFolders'],
			'maxFiles' => $admin['maxPreviewFiles'],
		];
	}

	public function getJobLimits(): array {
		$admin = $this->getAdminSettings();
		return [
			'maxDepth' => $admin['maxScanDepth'],
			'maxFolders' => $admin['maxJobFolders'],
			'maxFiles' => $admin['maxJobFiles'],
			'maxAlbums' => $admin['maxAlbumsPerRun'],
		];
	}

	public function getAutoSyncSettings(): array {
		$admin = $this->getAdminSettings();
		return [
			'mode' => $admin['autoSyncMode'],
			'debounceSeconds' => $admin['autoSyncDebounceSeconds'],
			'maxUsersPerRun' => $admin['autoSyncMaxUsersPerRun'],
			'maxRuntimeSeconds' => $admin['autoSyncMaxRuntimeSeconds'],
			'maxEventsPerRun' => $admin['autoSyncMaxEventsPerRun'],
		];
	}

	private function normalizeUserSettings(array $input, bool $includeAdminDefaults): array {
		$defaults = self::USER_DEFAULTS;
		if ($includeAdminDefaults) {
			$admin = $this->getAdminSettings();
			$defaults['includePaths'] = $admin['defaultIncludePaths'];
			$defaults['excludePatterns'] = $admin['defaultExcludePatterns'];
		}

		$namingTemplate = (string)($input['namingTemplate'] ?? $defaults['namingTemplate']);
		if (!in_array($namingTemplate, AlbumNameFormatter::TEMPLATES, true)) {
			$namingTemplate = $defaults['namingTemplate'];
		}

		return [
			'enabled' => $this->boolValue($input['enabled'] ?? $defaults['enabled']),
			'includePaths' => $this->pathList($input['includePaths'] ?? $defaults['includePaths']),
			'excludePatterns' => $this->stringList($input['excludePatterns'] ?? $defaults['excludePatterns']),
			'namingTemplate' => $namingTemplate,
			'separator' => AlbumNameFormatter::sanitizeSeparator((string)($input['separator'] ?? $defaults['separator'])),
			'albumDepth' => $this->intValue($input['albumDepth'] ?? $defaults['albumDepth'], 0, 20),
			'includeImages' => $this->boolValue($input['includeImages'] ?? $defaults['includeImages']),
			'includeVideos' => $this->boolValue($input['includeVideos'] ?? $defaults['includeVideos']),
		];
	}

	private function pathList(mixed $value): array {
		$paths = $this->stringList($value);
		$normalized = [];
		foreach ($paths as $path) {
			$path = '/' . PathHelper::normalizeUserPath($path);
			$path = rtrim($path, '/');
			$normalized[] = $path === '' ? '/' : $path;
		}

		return array_values(array_unique($normalized));
	}

	private function stringList(mixed $value): array {
		if (is_string($value)) {
			$value = preg_split('/\R+/', $value) ?: [];
		}

		if (!is_array($value)) {
			return [];
		}

		$result = [];
		foreach ($value as $item) {
			if (!is_scalar($item)) {
				continue;
			}
			$item = trim((string)$item);
			if ($item === '' || str_contains($item, "\0")) {
				continue;
			}
			$result[] = mb_substr($item, 0, 512);
		}

		return array_values(array_unique($result));
	}

	private function boolValue(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value)) {
			return $value === 1;
		}
		if (is_string($value)) {
			return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
		}
		return false;
	}

	private function autoSyncMode(string $value): string {
		return in_array($value, ['manual', 'file_events'], true) ? $value : self::ADMIN_DEFAULTS['autoSyncMode'];
	}

	private function intValue(mixed $value, int $min, int $max): int {
		$value = is_numeric($value) ? (int)$value : $min;
		return max($min, min($max, $value));
	}
}
