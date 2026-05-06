<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\AppInfo\Application;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Config\IUserConfig;
use OCP\IGroupManager;

class SettingsService {
	private const USER_SETTINGS_KEY = 'settings';
	private const ADMIN_GLOBAL_ENABLED_KEY = 'globalEnabled';

	private const ADMIN_DEFAULTS = [
		'enabled' => false,
		'allowedGroups' => [],
		'defaultIncludePaths' => ['/Photos'],
		'defaultExcludePatterns' => ['.nomedia', '.noimage'],
		'maxScanDepth' => 8,
		'maxPreviewFolders' => 500,
		'maxPreviewFiles' => 5000,
		'maxJobFolders' => 5000,
		'maxJobFiles' => 50000,
		'maxAlbumsPerRun' => 500,
		'maxManagedAlbumsPerUser' => 0,
		'maxManagedFilesPerUser' => 0,
		'maxDownloadFiles' => 1000,
		'maxDownloadBytes' => 2147483648,
		'allowVideos' => false,
		'jobIntervalMinutes' => 360,
		'autoSyncMode' => 'file_events',
		'autoSyncDebounceSeconds' => 300,
		'autoSyncMaxUsersPerRun' => 3,
		'autoSyncMaxRuntimeSeconds' => 30,
		'autoSyncMaxEventsPerRun' => 200,
		'autoSyncWindowStart' => '',
		'autoSyncWindowEnd' => '',
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
		'sourceFolders' => [],
		'excludePatterns' => [],
		'namingTemplate' => 'root_relative',
		'separator' => ' - ',
		'albumDepth' => 1,
		'includeImages' => true,
		'includeVideos' => false,
		'autoSyncEnabled' => true,
	];

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IUserConfig $userConfig,
		private readonly IGroupManager $groupManager,
	) {
	}

	public function getAdminSettings(): array {
		return [
			'enabled' => $this->appConfig->getAppValueBool(self::ADMIN_GLOBAL_ENABLED_KEY, self::ADMIN_DEFAULTS['enabled']),
			'allowedGroups' => $this->groupList($this->appConfig->getAppValueArray('allowedGroups', self::ADMIN_DEFAULTS['allowedGroups'], lazy: true)),
			'defaultIncludePaths' => $this->appConfig->getAppValueArray('defaultIncludePaths', self::ADMIN_DEFAULTS['defaultIncludePaths'], lazy: true),
			'defaultExcludePatterns' => $this->appConfig->getAppValueArray('defaultExcludePatterns', self::ADMIN_DEFAULTS['defaultExcludePatterns'], lazy: true),
			'maxScanDepth' => $this->appConfig->getAppValueInt('maxScanDepth', self::ADMIN_DEFAULTS['maxScanDepth']),
			'maxPreviewFolders' => $this->appConfig->getAppValueInt('maxPreviewFolders', self::ADMIN_DEFAULTS['maxPreviewFolders']),
			'maxPreviewFiles' => $this->appConfig->getAppValueInt('maxPreviewFiles', self::ADMIN_DEFAULTS['maxPreviewFiles']),
			'maxJobFolders' => $this->appConfig->getAppValueInt('maxJobFolders', self::ADMIN_DEFAULTS['maxJobFolders']),
			'maxJobFiles' => $this->appConfig->getAppValueInt('maxJobFiles', self::ADMIN_DEFAULTS['maxJobFiles']),
			'maxAlbumsPerRun' => $this->appConfig->getAppValueInt('maxAlbumsPerRun', self::ADMIN_DEFAULTS['maxAlbumsPerRun']),
			'maxManagedAlbumsPerUser' => $this->appConfig->getAppValueInt('maxManagedAlbumsPerUser', self::ADMIN_DEFAULTS['maxManagedAlbumsPerUser']),
			'maxManagedFilesPerUser' => $this->appConfig->getAppValueInt('maxManagedFilesPerUser', self::ADMIN_DEFAULTS['maxManagedFilesPerUser']),
			'maxDownloadFiles' => $this->appConfig->getAppValueInt('maxDownloadFiles', self::ADMIN_DEFAULTS['maxDownloadFiles']),
			'maxDownloadBytes' => $this->appConfig->getAppValueInt('maxDownloadBytes', self::ADMIN_DEFAULTS['maxDownloadBytes']),
			'allowVideos' => $this->appConfig->getAppValueBool('allowVideos', self::ADMIN_DEFAULTS['allowVideos']),
			'jobIntervalMinutes' => $this->appConfig->getAppValueInt('jobIntervalMinutes', self::ADMIN_DEFAULTS['jobIntervalMinutes']),
			'autoSyncMode' => $this->autoSyncMode($this->appConfig->getAppValueString('autoSyncMode', self::ADMIN_DEFAULTS['autoSyncMode'])),
			'autoSyncDebounceSeconds' => $this->appConfig->getAppValueInt('autoSyncDebounceSeconds', self::ADMIN_DEFAULTS['autoSyncDebounceSeconds']),
			'autoSyncMaxUsersPerRun' => $this->appConfig->getAppValueInt('autoSyncMaxUsersPerRun', self::ADMIN_DEFAULTS['autoSyncMaxUsersPerRun']),
			'autoSyncMaxRuntimeSeconds' => $this->appConfig->getAppValueInt('autoSyncMaxRuntimeSeconds', self::ADMIN_DEFAULTS['autoSyncMaxRuntimeSeconds']),
			'autoSyncMaxEventsPerRun' => $this->appConfig->getAppValueInt('autoSyncMaxEventsPerRun', self::ADMIN_DEFAULTS['autoSyncMaxEventsPerRun']),
			'autoSyncWindowStart' => $this->timeOfDay($this->appConfig->getAppValueString('autoSyncWindowStart', self::ADMIN_DEFAULTS['autoSyncWindowStart'])),
			'autoSyncWindowEnd' => $this->timeOfDay($this->appConfig->getAppValueString('autoSyncWindowEnd', self::ADMIN_DEFAULTS['autoSyncWindowEnd'])),
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
			'allowedGroups' => $this->groupList($input['allowedGroups'] ?? self::ADMIN_DEFAULTS['allowedGroups']),
			'defaultIncludePaths' => $this->pathList($input['defaultIncludePaths'] ?? self::ADMIN_DEFAULTS['defaultIncludePaths']),
			'defaultExcludePatterns' => $this->stringList($input['defaultExcludePatterns'] ?? self::ADMIN_DEFAULTS['defaultExcludePatterns']),
			'maxScanDepth' => $this->intValue($input['maxScanDepth'] ?? self::ADMIN_DEFAULTS['maxScanDepth'], 0, 20),
			'maxPreviewFolders' => $this->intValue($input['maxPreviewFolders'] ?? self::ADMIN_DEFAULTS['maxPreviewFolders'], 10, 20000),
			'maxPreviewFiles' => $this->intValue($input['maxPreviewFiles'] ?? self::ADMIN_DEFAULTS['maxPreviewFiles'], 10, 200000),
			'maxJobFolders' => $this->intValue($input['maxJobFolders'] ?? self::ADMIN_DEFAULTS['maxJobFolders'], 10, 100000),
			'maxJobFiles' => $this->intValue($input['maxJobFiles'] ?? self::ADMIN_DEFAULTS['maxJobFiles'], 10, 1000000),
			'maxAlbumsPerRun' => $this->intValue($input['maxAlbumsPerRun'] ?? self::ADMIN_DEFAULTS['maxAlbumsPerRun'], 1, 5000),
			'maxManagedAlbumsPerUser' => $this->intValue($input['maxManagedAlbumsPerUser'] ?? self::ADMIN_DEFAULTS['maxManagedAlbumsPerUser'], 0, 100000),
			'maxManagedFilesPerUser' => $this->intValue($input['maxManagedFilesPerUser'] ?? self::ADMIN_DEFAULTS['maxManagedFilesPerUser'], 0, 5000000),
			'maxDownloadFiles' => $this->intValue($input['maxDownloadFiles'] ?? self::ADMIN_DEFAULTS['maxDownloadFiles'], 1, 100000),
			'maxDownloadBytes' => $this->intValue($input['maxDownloadBytes'] ?? self::ADMIN_DEFAULTS['maxDownloadBytes'], 1048576, 2147483647),
			'allowVideos' => $this->boolValue($input['allowVideos'] ?? self::ADMIN_DEFAULTS['allowVideos']),
			'jobIntervalMinutes' => $this->intValue($input['jobIntervalMinutes'] ?? self::ADMIN_DEFAULTS['jobIntervalMinutes'], 5, 10080),
			'autoSyncMode' => $this->autoSyncMode((string)($input['autoSyncMode'] ?? self::ADMIN_DEFAULTS['autoSyncMode'])),
			'autoSyncDebounceSeconds' => $this->intValue($input['autoSyncDebounceSeconds'] ?? self::ADMIN_DEFAULTS['autoSyncDebounceSeconds'], 30, 86400),
			'autoSyncMaxUsersPerRun' => $this->intValue($input['autoSyncMaxUsersPerRun'] ?? self::ADMIN_DEFAULTS['autoSyncMaxUsersPerRun'], 1, 1000),
			'autoSyncMaxRuntimeSeconds' => $this->intValue($input['autoSyncMaxRuntimeSeconds'] ?? self::ADMIN_DEFAULTS['autoSyncMaxRuntimeSeconds'], 5, 3600),
			'autoSyncMaxEventsPerRun' => $this->intValue($input['autoSyncMaxEventsPerRun'] ?? self::ADMIN_DEFAULTS['autoSyncMaxEventsPerRun'], 1, 100000),
			'autoSyncWindowStart' => $this->timeOfDay($input['autoSyncWindowStart'] ?? self::ADMIN_DEFAULTS['autoSyncWindowStart']),
			'autoSyncWindowEnd' => $this->timeOfDay($input['autoSyncWindowEnd'] ?? self::ADMIN_DEFAULTS['autoSyncWindowEnd']),
			'syncRemoveMissingFiles' => $this->boolValue($input['syncRemoveMissingFiles'] ?? self::ADMIN_DEFAULTS['syncRemoveMissingFiles']),
			'syncDeleteMissingManagedAlbums' => $this->boolValue($input['syncDeleteMissingManagedAlbums'] ?? self::ADMIN_DEFAULTS['syncDeleteMissingManagedAlbums']),
			'requireBulkDeleteConfirmation' => true,
			'debugMode' => $this->boolValue($input['debugMode'] ?? self::ADMIN_DEFAULTS['debugMode']),
			'debugRetentionDays' => $this->intValue($input['debugRetentionDays'] ?? self::ADMIN_DEFAULTS['debugRetentionDays'], 1, 365),
			'debugMaxContextLength' => $this->intValue($input['debugMaxContextLength'] ?? self::ADMIN_DEFAULTS['debugMaxContextLength'], 1000, 100000),
		];

		$this->appConfig->setAppValueBool(self::ADMIN_GLOBAL_ENABLED_KEY, $settings['enabled']);
		$this->appConfig->setAppValueArray('allowedGroups', $settings['allowedGroups'], lazy: true);
		$this->appConfig->setAppValueArray('defaultIncludePaths', $settings['defaultIncludePaths'], lazy: true);
		$this->appConfig->setAppValueArray('defaultExcludePatterns', $settings['defaultExcludePatterns'], lazy: true);
		$this->appConfig->setAppValueInt('maxScanDepth', $settings['maxScanDepth']);
		$this->appConfig->setAppValueInt('maxPreviewFolders', $settings['maxPreviewFolders']);
		$this->appConfig->setAppValueInt('maxPreviewFiles', $settings['maxPreviewFiles']);
		$this->appConfig->setAppValueInt('maxJobFolders', $settings['maxJobFolders']);
		$this->appConfig->setAppValueInt('maxJobFiles', $settings['maxJobFiles']);
		$this->appConfig->setAppValueInt('maxAlbumsPerRun', $settings['maxAlbumsPerRun']);
		$this->appConfig->setAppValueInt('maxManagedAlbumsPerUser', $settings['maxManagedAlbumsPerUser']);
		$this->appConfig->setAppValueInt('maxManagedFilesPerUser', $settings['maxManagedFilesPerUser']);
		$this->appConfig->setAppValueInt('maxDownloadFiles', $settings['maxDownloadFiles']);
		$this->appConfig->setAppValueInt('maxDownloadBytes', $settings['maxDownloadBytes']);
		$this->appConfig->setAppValueBool('allowVideos', $settings['allowVideos']);
		$this->appConfig->setAppValueInt('jobIntervalMinutes', $settings['jobIntervalMinutes']);
		$this->appConfig->setAppValueString('autoSyncMode', $settings['autoSyncMode']);
		$this->appConfig->setAppValueInt('autoSyncDebounceSeconds', $settings['autoSyncDebounceSeconds']);
		$this->appConfig->setAppValueInt('autoSyncMaxUsersPerRun', $settings['autoSyncMaxUsersPerRun']);
		$this->appConfig->setAppValueInt('autoSyncMaxRuntimeSeconds', $settings['autoSyncMaxRuntimeSeconds']);
		$this->appConfig->setAppValueInt('autoSyncMaxEventsPerRun', $settings['autoSyncMaxEventsPerRun']);
		$this->appConfig->setAppValueString('autoSyncWindowStart', $settings['autoSyncWindowStart']);
		$this->appConfig->setAppValueString('autoSyncWindowEnd', $settings['autoSyncWindowEnd']);
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

	public function availableGroups(int $limit = 200): array {
		$limit = max(1, min(500, $limit));
		$groups = array_map(
			static fn ($group): array => [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			],
			$this->groupManager->search('', $limit, 0),
		);
		usort($groups, static fn (array $left, array $right): int => mb_strtolower($left['displayName']) <=> mb_strtolower($right['displayName']));

		return $groups;
	}

	public function getUserSettings(string $userId): array {
		$stored = $this->userConfig->getValueArray($userId, Application::APP_ID, self::USER_SETTINGS_KEY, [], lazy: true);
		return $this->normalizeUserSettings($stored, includeAdminDefaults: false);
	}

	public function getEffectiveUserSettings(string $userId, ?array $overrides = null): array {
		$admin = $this->getAdminSettings();
		$user = $this->getUserSettings($userId);
		$groupAllowed = $this->isUserInAllowedGroups($userId, $admin['allowedGroups'] ?? []);
		$adminEnabledForUser = $admin['enabled'] && $groupAllowed;

		if ($overrides !== null) {
			$user = $this->normalizeUserSettings(array_merge($user, $overrides), includeAdminDefaults: false);
		}

		$sourceFolders = $user['sourceFolders'] !== []
			? $user['sourceFolders']
			: $this->sourceFoldersFromPaths(
				$user['includePaths'] !== [] ? $user['includePaths'] : $admin['defaultIncludePaths'],
				$user,
			);
		$includePaths = array_values(array_map(
			static fn (array $source): string => (string)$source['path'],
			array_filter($sourceFolders, static fn (array $source): bool => ($source['enabled'] ?? true) === true),
		));
		$excludePatterns = array_values(array_unique(array_merge(
			$admin['defaultExcludePatterns'],
			$user['excludePatterns'],
		)));
		$defaultAlbumDepth = min($user['albumDepth'], $admin['maxScanDepth']);
		$autoSyncAvailable = $adminEnabledForUser && $admin['autoSyncMode'] === 'file_events';
		$autoSyncActive = $autoSyncAvailable && $user['enabled'];

		return [
			'enabled' => $adminEnabledForUser && $user['enabled'],
			'adminEnabled' => $adminEnabledForUser,
			'adminGlobalEnabled' => $admin['enabled'],
			'adminGroupAllowed' => $groupAllowed,
			'allowedGroups' => $admin['allowedGroups'] ?? [],
			'userEnabled' => $user['enabled'],
			'includePaths' => $includePaths,
			'sourceFolders' => $this->effectiveSourceFolders($sourceFolders, $user, $admin),
			'excludePatterns' => $excludePatterns,
			'namingTemplate' => $user['namingTemplate'],
			'separator' => $user['separator'],
			'albumDepth' => $defaultAlbumDepth,
			'includeImages' => $user['includeImages'],
			'includeVideos' => $admin['allowVideos'] && $user['includeVideos'],
			'autoSyncEnabled' => $autoSyncActive,
			'autoSyncAvailable' => $autoSyncAvailable,
			'autoSyncActive' => $autoSyncActive,
			'autoSyncMode' => $admin['autoSyncMode'],
			'autoSyncDebounceSeconds' => $admin['autoSyncDebounceSeconds'],
			'autoSyncWindowStart' => $admin['autoSyncWindowStart'],
			'autoSyncWindowEnd' => $admin['autoSyncWindowEnd'],
			'namingSchemaVersion' => Application::NAMING_SCHEMA_VERSION,
			'syncRemoveMissingFiles' => $admin['syncRemoveMissingFiles'],
			'syncDeleteMissingManagedAlbums' => $admin['syncDeleteMissingManagedAlbums'],
			'maxManagedAlbumsPerUser' => $admin['maxManagedAlbumsPerUser'],
			'maxManagedFilesPerUser' => $admin['maxManagedFilesPerUser'],
			'maxDownloadFiles' => $admin['maxDownloadFiles'],
			'maxDownloadBytes' => $admin['maxDownloadBytes'],
		];
	}

	public function saveUserSettings(string $userId, array $input): array {
		$settings = $this->normalizeUserSettings($input, includeAdminDefaults: false);
		$admin = $this->getAdminSettings();
		if (($admin['autoSyncMode'] ?? '') === 'file_events') {
			$settings['autoSyncEnabled'] = $settings['enabled'];
		}
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

	public function resetUserSettings(string $userId): array {
		$this->userConfig->deleteUserConfig($userId, Application::APP_ID, self::USER_SETTINGS_KEY);

		return $this->normalizeUserSettings([], includeAdminDefaults: false);
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
			'maxManagedAlbumsPerUser' => $admin['maxManagedAlbumsPerUser'],
			'maxManagedFilesPerUser' => $admin['maxManagedFilesPerUser'],
		];
	}

	public function getAutoSyncSettings(): array {
		$admin = $this->getAdminSettings();
		$window = $this->autoSyncWindowState($admin);
		return [
			'globalEnabled' => (bool)$admin['enabled'],
			'mode' => $admin['autoSyncMode'],
			'debounceSeconds' => $admin['autoSyncDebounceSeconds'],
			'maxUsersPerRun' => $admin['autoSyncMaxUsersPerRun'],
			'maxRuntimeSeconds' => $admin['autoSyncMaxRuntimeSeconds'],
			'maxEventsPerRun' => $admin['autoSyncMaxEventsPerRun'],
			'windowStart' => $admin['autoSyncWindowStart'],
			'windowEnd' => $admin['autoSyncWindowEnd'],
			'windowActive' => $window['active'],
			'windowReason' => $window['reason'],
			'nextWindowAt' => $window['nextWindowAt'],
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
			'sourceFolders' => $this->sourceFolderList($input['sourceFolders'] ?? $defaults['sourceFolders']),
			'excludePatterns' => $this->stringList($input['excludePatterns'] ?? $defaults['excludePatterns']),
			'namingTemplate' => $namingTemplate,
			'separator' => AlbumNameFormatter::sanitizeSeparator((string)($input['separator'] ?? $defaults['separator'])),
			'albumDepth' => $this->intValue($input['albumDepth'] ?? $defaults['albumDepth'], 0, 20),
			'includeImages' => $this->boolValue($input['includeImages'] ?? $defaults['includeImages']),
			'includeVideos' => $this->boolValue($input['includeVideos'] ?? $defaults['includeVideos']),
			'autoSyncEnabled' => $this->boolValue($input['autoSyncEnabled'] ?? $defaults['autoSyncEnabled']),
		];
	}

	private function pathList(mixed $value): array {
		$paths = $this->stringList($value);
		$normalized = [];
		foreach ($paths as $path) {
			try {
				$path = '/' . PathHelper::normalizeUserPath($path);
			} catch (\InvalidArgumentException) {
				continue;
			}
			$path = rtrim($path, '/');
			$normalized[] = $path === '' ? '/' : $path;
		}

		return array_values(array_unique($normalized));
	}

	private function sourceFolderList(mixed $value): array {
		if (!is_array($value)) {
			return [];
		}

		$result = [];
		$seen = [];
		foreach ($value as $item) {
			if (!is_array($item)) {
				continue;
			}

			$paths = $this->pathList([$item['path'] ?? '']);
			if ($paths === []) {
				continue;
			}
			$path = $paths[0];
			$key = mb_strtolower($path);
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;

			$mode = (string)($item['mode'] ?? 'default');
			if (!in_array($mode, ['default', 'depth', 'single_album'], true)) {
				$mode = 'default';
			}
			$namingTemplate = (string)($item['namingTemplate'] ?? '');
			if ($namingTemplate !== '' && !in_array($namingTemplate, AlbumNameFormatter::TEMPLATES, true)) {
				$namingTemplate = '';
			}

			$result[] = [
				'id' => $this->sourceFolderId($path),
				'path' => $path,
				'enabled' => $this->boolValue($item['enabled'] ?? true),
				'mode' => $mode,
				'albumDepth' => $this->intValue($item['albumDepth'] ?? 1, 0, 20),
				'namingTemplate' => $namingTemplate,
				'separator' => $this->optionalSeparator($item['separator'] ?? ''),
			];
		}

		return $result;
	}

	private function sourceFoldersFromPaths(array $paths, array $user): array {
		return array_map(fn (string $path): array => [
			'id' => $this->sourceFolderId($path),
			'path' => $path,
			'enabled' => true,
			'mode' => 'default',
			'albumDepth' => (int)$user['albumDepth'],
			'namingTemplate' => '',
			'separator' => '',
		], $paths);
	}

	private function effectiveSourceFolders(array $sourceFolders, array $user, array $admin): array {
		$defaultDepth = min((int)$user['albumDepth'], (int)$admin['maxScanDepth']);

		return array_map(function (array $source) use ($user, $admin, $defaultDepth): array {
			$mode = (string)($source['mode'] ?? 'default');
			$depth = match ($mode) {
				'single_album' => 0,
				'depth' => min((int)($source['albumDepth'] ?? $defaultDepth), (int)$admin['maxScanDepth']),
				default => $defaultDepth,
			};
			$namingTemplate = (string)($source['namingTemplate'] ?? '');
			$separator = (string)($source['separator'] ?? '');

			return array_merge($source, [
				'effectiveAlbumDepth' => $depth,
				'effectiveNamingTemplate' => $namingTemplate !== '' ? $namingTemplate : $user['namingTemplate'],
				'effectiveSeparator' => $separator !== '' ? $separator : $user['separator'],
				'usesDefaultRules' => $mode === 'default' && $namingTemplate === '' && $separator === '',
			]);
		}, $sourceFolders);
	}

	private function sourceFolderId(string $path): string {
		return 'src_' . substr(hash('sha256', PathHelper::displayPath($path)), 0, 20);
	}

	private function optionalSeparator(mixed $value): string {
		if (!is_scalar($value)) {
			return '';
		}
		$value = trim((string)$value);
		return $value === '' ? '' : AlbumNameFormatter::sanitizeSeparator($value);
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

	private function groupList(mixed $value): array {
		$groups = [];
		foreach ($this->stringList($value) as $group) {
			$group = mb_substr($group, 0, 128);
			if ($group === '' || str_contains($group, '/') || str_contains($group, '\\')) {
				continue;
			}
			$groups[] = $group;
			if (count($groups) >= 100) {
				break;
			}
		}

		return array_values(array_unique($groups));
	}

	private function timeOfDay(mixed $value): string {
		if (!is_scalar($value)) {
			return '';
		}
		$value = trim((string)$value);
		if ($value === '') {
			return '';
		}
		if (!preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $value, $matches)) {
			return '';
		}

		return sprintf('%02d:%02d', (int)$matches[1], (int)$matches[2]);
	}

	private function isUserInAllowedGroups(string $userId, array $allowedGroups): bool {
		$allowedGroups = $this->groupList($allowedGroups);
		if ($allowedGroups === []) {
			return true;
		}

		foreach ($allowedGroups as $groupId) {
			if ($this->groupManager->isInGroup($userId, $groupId)) {
				return true;
			}
		}

		return false;
	}

	private function autoSyncWindowState(array $admin, ?int $now = null): array {
		$start = $this->timeOfDay($admin['autoSyncWindowStart'] ?? '');
		$end = $this->timeOfDay($admin['autoSyncWindowEnd'] ?? '');
		$now ??= time();
		if ($start === '' || $end === '' || $start === $end) {
			return [
				'active' => true,
				'reason' => 'always_open',
				'nextWindowAt' => null,
			];
		}

		$currentMinute = ((int)date('G', $now) * 60) + (int)date('i', $now);
		$startMinute = $this->minuteOfDay($start);
		$endMinute = $this->minuteOfDay($end);
		$active = $startMinute < $endMinute
			? ($currentMinute >= $startMinute && $currentMinute < $endMinute)
			: ($currentMinute >= $startMinute || $currentMinute < $endMinute);

		return [
			'active' => $active,
			'reason' => $active ? 'inside_window' : 'outside_window',
			'nextWindowAt' => $active ? null : $this->nextWindowAt($now, $startMinute),
		];
	}

	private function minuteOfDay(string $time): int {
		[$hour, $minute] = array_map('intval', explode(':', $time, 2));
		return ($hour * 60) + $minute;
	}

	private function nextWindowAt(int $now, int $startMinute): int {
		$todayStart = strtotime(date('Y-m-d', $now) . sprintf(' %02d:%02d:00', intdiv($startMinute, 60), $startMinute % 60));
		if ($todayStart === false) {
			return $now;
		}
		if ($todayStart > $now) {
			return $todayStart;
		}

		return $todayStart + 86400;
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
