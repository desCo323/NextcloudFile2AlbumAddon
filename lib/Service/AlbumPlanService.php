<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\StorageNotAvailableException;

class AlbumPlanService {
	private const IMAGE_MIME_PREFIX = 'image/';
	private const VIDEO_MIME_PREFIX = 'video/';

	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly AlbumNameFormatter $albumNameFormatter,
	) {
	}

	public function buildPreview(string $userId, array $settings, array $limits): array {
		return $this->buildPlan($userId, $settings, $limits, false);
	}

	public function buildExecutionPlan(string $userId, array $settings, array $limits): array {
		return $this->buildPlan($userId, $settings, $limits, true);
	}

	public function buildExecutionChunk(string $userId, array $settings, array $limits, ?string $startAfterFile): array {
		$startAfterFile = $startAfterFile !== null && $startAfterFile !== ''
			? PathHelper::normalizeUserPath($startAfterFile)
			: '';
		return $this->buildPlan($userId, $settings, $limits, true, true, $startAfterFile);
	}

	private function buildPlan(
		string $userId,
		array $settings,
		array $limits,
		bool $includeFiles,
		bool $chunkMode = false,
		string $startAfterFile = '',
	): array {
		$warnings = [];
		$albums = [];
		$summary = [
			'foldersScanned' => 0,
			'filesScanned' => 0,
			'skippedBeforeCursor' => 0,
			'mediaFiles' => 0,
			'plannedLinks' => 0,
			'plannedAlbums' => 0,
			'collisions' => 0,
			'truncated' => false,
			'chunked' => $chunkMode,
			'hasMore' => false,
			'startAfterFile' => $startAfterFile !== '' ? PathHelper::displayPath($startAfterFile) : '',
			'cursorFound' => $startAfterFile === '',
			'lastCursorPath' => '',
			'lastMediaFilePath' => '',
		];

		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (\Throwable $e) {
			return $this->errorPreview('user_folder_unavailable', 'User folder is unavailable.');
		}

		$sources = $this->sourceFolders($settings);
		if ($sources === []) {
			$warnings[] = ['code' => 'no_include_paths', 'message' => 'No include folders configured.'];
		}
		array_push($warnings, ...$this->sourcePathWarnings($sources));
		array_push($warnings, ...$this->folderRuleWarnings($settings['folderRules'] ?? [], $sources));

		foreach ($sources as $source) {
			if (($source['enabled'] ?? true) !== true) {
				continue;
			}
			$includePath = (string)($source['path'] ?? '');
			try {
				$rootPath = PathHelper::normalizeUserPath((string)$includePath);
			} catch (\InvalidArgumentException) {
				$warnings[] = ['code' => 'invalid_include_path', 'path' => (string)$includePath];
				continue;
			}

			try {
				$rootNode = $rootPath === '' ? $userFolder : $userFolder->get($rootPath);
			} catch (NotFoundException) {
				$warnings[] = ['code' => 'missing_include_path', 'path' => PathHelper::displayPath($rootPath)];
				continue;
			}

			if (!$rootNode instanceof Folder) {
				$warnings[] = ['code' => 'include_path_not_folder', 'path' => PathHelper::displayPath($rootPath)];
				continue;
			}

			$sourceSettings = $this->settingsForSource($settings, $source);
			$this->scanFolder(
				$rootNode,
				$rootPath,
				$rootPath,
				0,
				$sourceSettings,
				$limits,
				$includeFiles,
				$albums,
				$summary,
				$warnings,
				(string)($source['id'] ?? ''),
				$chunkMode,
				$startAfterFile,
			);
			if ($summary['truncated'] || $summary['hasMore']) {
				break;
			}
		}

		$this->markCollisions($albums, $summary);
		$summary['plannedAlbums'] = count($albums);

		return [
			'schemaVersion' => 1,
			'namingSchemaVersion' => Application::NAMING_SCHEMA_VERSION,
			'summary' => $summary,
			'albums' => array_values($albums),
			'warnings' => $warnings,
		];
	}

	private function scanFolder(
		Folder $folder,
		string $sourceRoot,
		string $currentPath,
		int $depth,
		array $settings,
		array $limits,
		bool $includeFiles,
		array &$albums,
		array &$summary,
		array &$warnings,
		string $sourceId,
		bool $chunkMode = false,
		string $startAfterFile = '',
	): void {
		if ($summary['truncated'] || $summary['hasMore']) {
			return;
		}
		if ($depth > (int)$limits['maxDepth']) {
			return;
		}
		$folderSettings = $this->settingsForFolderPath($settings, $sourceRoot, $currentPath);
		if (($folderSettings['_folderRuleMode'] ?? '') === 'exclude') {
			$warnings[] = [
				'code' => 'folder_rule_skip',
				'path' => PathHelper::displayPath($currentPath),
			];
			return;
		}
		if ($this->isExcluded($currentPath, $folderSettings['excludePatterns'] ?? [])) {
			return;
		}

		$summary['foldersScanned']++;
		if ($summary['foldersScanned'] > (int)$limits['maxFolders']) {
			$summary['truncated'] = true;
			$warnings[] = ['code' => 'max_folders_reached', 'limit' => (int)$limits['maxFolders']];
			return;
		}

		try {
			if ($folder->nodeExists('.nomedia') || $folder->nodeExists('.noimage')) {
				$warnings[] = ['code' => 'media_marker_skip', 'path' => PathHelper::displayPath($currentPath)];
				return;
			}
			$nodes = $this->sortedNodes($folder->getDirectoryListing());
		} catch (StorageNotAvailableException) {
			$warnings[] = ['code' => 'storage_unavailable', 'path' => PathHelper::displayPath($currentPath)];
			return;
		}

		foreach ($nodes as $node) {
			if ($node instanceof File) {
				$filePath = trim($currentPath . '/' . $node->getName(), '/');
				if ($chunkMode && ($summary['cursorFound'] ?? true) !== true) {
					$summary['skippedBeforeCursor']++;
					if ($filePath === $startAfterFile) {
						$summary['cursorFound'] = true;
					}
					continue;
				}

				$summary['filesScanned']++;
				if ($summary['filesScanned'] > (int)$limits['maxFiles']) {
					if ($chunkMode) {
						$summary['hasMore'] = true;
						$warnings[] = [
							'code' => 'chunk_file_limit_reached',
							'limit' => (int)$limits['maxFiles'],
							'lastCursorPath' => $summary['lastCursorPath'],
						];
					} else {
						$summary['truncated'] = true;
						$warnings[] = ['code' => 'max_files_reached', 'limit' => (int)$limits['maxFiles']];
					}
					return;
				}
				$summary['lastCursorPath'] = PathHelper::displayPath($filePath);

				if (!$this->isMediaFile($node, $folderSettings)) {
					continue;
				}

				$summary['mediaFiles']++;
				$summary['plannedLinks']++;
				$albumDepthRoot = (string)($folderSettings['_albumDepthRoot'] ?? $sourceRoot);
				$targetPath = $this->targetAlbumPath($albumDepthRoot, $currentPath, (int)$folderSettings['albumDepth']);
				$key = $sourceId . "\n" . $sourceRoot . "\n" . $targetPath;
				$summary['lastMediaFilePath'] = PathHelper::displayPath($filePath);

				if (!isset($albums[$key])) {
					$albums[$key] = [
						'sourceId' => $sourceId,
						'sourceRoot' => PathHelper::displayPath($sourceRoot),
						'targetPath' => PathHelper::displayPath($targetPath),
						'albumName' => $this->albumNameFormatter->format($sourceRoot, $targetPath, $folderSettings),
						'mediaCount' => 0,
						'aggregated' => $targetPath !== $currentPath,
						'sampleFiles' => [],
						'collision' => false,
					];
					if ($includeFiles) {
						$albums[$key]['files'] = [];
					}
				}

				$albums[$key]['mediaCount']++;
				if (count($albums[$key]['sampleFiles']) < 3) {
					$albums[$key]['sampleFiles'][] = $node->getName();
				}
				if ($includeFiles) {
					$albums[$key]['files'][] = $filePath;
				}
			}
		}

		foreach ($nodes as $node) {
			if (!$node instanceof Folder) {
				continue;
			}
			$childPath = trim($currentPath . '/' . $node->getName(), '/');
			$this->scanFolder($node, $sourceRoot, $childPath, $depth + 1, $settings, $limits, $includeFiles, $albums, $summary, $warnings, $sourceId, $chunkMode, $startAfterFile);
			if ($summary['truncated'] || $summary['hasMore']) {
				return;
			}
		}
	}

	private function sortedNodes(array $nodes): array {
		usort($nodes, static function ($left, $right): int {
			$leftIsFolder = $left instanceof Folder;
			$rightIsFolder = $right instanceof Folder;
			if ($leftIsFolder !== $rightIsFolder) {
				return $leftIsFolder ? 1 : -1;
			}
			return strnatcasecmp($left->getName(), $right->getName());
		});

		return $nodes;
	}

	private function targetAlbumPath(string $sourceRoot, string $currentPath, int $albumDepth): string {
		$relative = PathHelper::relativePath($sourceRoot, $currentPath);
		if ($relative === '' || $albumDepth === 0) {
			return $sourceRoot;
		}

		$parts = explode('/', $relative);
		if (count($parts) <= $albumDepth) {
			return $currentPath;
		}

		return trim($sourceRoot . '/' . implode('/', array_slice($parts, 0, $albumDepth)), '/');
	}

	private function isMediaFile(File $file, array $settings): bool {
		$mime = strtolower($file->getMimeType());

		if (($settings['includeImages'] ?? true) && str_starts_with($mime, self::IMAGE_MIME_PREFIX)) {
			return true;
		}
		if (($settings['includeVideos'] ?? false) && str_starts_with($mime, self::VIDEO_MIME_PREFIX)) {
			return true;
		}

		return false;
	}

	private function isExcluded(string $path, array $patterns): bool {
		$path = PathHelper::normalizeUserPath($path);
		$basename = PathHelper::basename($path);

		foreach ($patterns as $pattern) {
			if (!is_string($pattern) || $pattern === '') {
				continue;
			}
			try {
				$normalized = PathHelper::normalizeUserPath($pattern);
			} catch (\InvalidArgumentException) {
				continue;
			}

			if ($normalized === $path || $normalized === $basename) {
				return true;
			}
			if ($normalized !== '' && str_starts_with($path . '/', $normalized . '/')) {
				return true;
			}
			if (fnmatch($normalized, $path) || fnmatch($normalized, $basename)) {
				return true;
			}
		}

		return false;
	}

	private function markCollisions(array &$albums, array &$summary): void {
		$names = [];
		foreach ($albums as $key => $album) {
			$names[mb_strtolower($album['albumName'])][] = $key;
		}

		foreach ($names as $keys) {
			if (count($keys) < 2) {
				continue;
			}
			$summary['collisions'] += count($keys);
			foreach ($keys as $key) {
				$albums[$key]['collision'] = true;
			}
		}
	}

	private function sourceFolders(array $settings): array {
		if (isset($settings['sourceFolders']) && is_array($settings['sourceFolders']) && $settings['sourceFolders'] !== []) {
			return array_values($settings['sourceFolders']);
		}

		return array_map(static fn (string $path): array => [
			'id' => 'legacy_' . substr(hash('sha256', $path), 0, 16),
			'path' => $path,
			'enabled' => true,
			'effectiveAlbumDepth' => (int)($settings['albumDepth'] ?? 1),
			'effectiveNamingTemplate' => (string)($settings['namingTemplate'] ?? 'root_relative'),
			'effectiveSeparator' => (string)($settings['separator'] ?? ' - '),
		], $settings['includePaths'] ?? []);
	}

	private function settingsForSource(array $settings, array $source): array {
		$result = $settings;
		$result['albumDepth'] = (int)($source['effectiveAlbumDepth'] ?? $settings['albumDepth'] ?? 1);
		$result['namingTemplate'] = (string)($source['effectiveNamingTemplate'] ?? $settings['namingTemplate'] ?? 'root_relative');
		$result['separator'] = (string)($source['effectiveSeparator'] ?? $settings['separator'] ?? ' - ');
		$result['_albumDepthRoot'] = PathHelper::normalizeUserPath((string)($source['path'] ?? ''));
		return $result;
	}

	private function settingsForFolderPath(array $settings, string $sourceRoot, string $currentPath): array {
		$currentPath = PathHelper::normalizeUserPath($currentPath);
		$sourceRoot = PathHelper::normalizeUserPath($sourceRoot);
		$bestRule = null;
		$bestLength = -1;

		foreach (($settings['folderRules'] ?? []) as $rule) {
			if (!is_array($rule) || (($rule['enabled'] ?? true) !== true)) {
				continue;
			}
			try {
				$rulePath = PathHelper::normalizeUserPath((string)($rule['path'] ?? ''));
			} catch (\InvalidArgumentException) {
				continue;
			}
			if (!$this->pathWithin($rulePath, $sourceRoot) || !$this->pathWithin($currentPath, $rulePath)) {
				continue;
			}
			$length = mb_strlen($rulePath);
			if ($length > $bestLength) {
				$bestRule = array_merge($rule, ['_normalizedPath' => $rulePath]);
				$bestLength = $length;
			}
		}

		if ($bestRule === null) {
			return $settings;
		}

		$mode = (string)($bestRule['mode'] ?? 'depth');
		$result = $settings;
		$result['_folderRuleId'] = (string)($bestRule['id'] ?? '');
		$result['_folderRuleMode'] = $mode;
		$result['_albumDepthRoot'] = (string)$bestRule['_normalizedPath'];
		$result['albumDepth'] = (int)($bestRule['effectiveAlbumDepth'] ?? $bestRule['albumDepth'] ?? $settings['albumDepth'] ?? 1);
		$result['namingTemplate'] = (string)($bestRule['effectiveNamingTemplate'] ?? $bestRule['namingTemplate'] ?? $settings['namingTemplate'] ?? 'root_relative');
		$result['separator'] = (string)($bestRule['effectiveSeparator'] ?? $bestRule['separator'] ?? $settings['separator'] ?? ' - ');

		if ($mode === 'single_album' || $mode === 'exclude') {
			$result['albumDepth'] = 0;
		}

		return $result;
	}

	private function sourcePathWarnings(array $sources): array {
		$warnings = [];
		$paths = [];

		foreach ($sources as $source) {
			if (($source['enabled'] ?? true) !== true) {
				continue;
			}
			try {
				$path = PathHelper::normalizeUserPath((string)($source['path'] ?? ''));
			} catch (\InvalidArgumentException) {
				continue;
			}
			$paths[] = [
				'id' => (string)($source['id'] ?? ''),
				'path' => $path,
				'displayPath' => PathHelper::displayPath($path),
			];
		}

		for ($i = 0, $count = count($paths); $i < $count; $i++) {
			for ($j = $i + 1; $j < $count; $j++) {
				$left = $paths[$i];
				$right = $paths[$j];
				if ($this->pathsOverlap($left['path'], $right['path'])) {
					$warnings[] = [
						'code' => 'overlapping_source_paths',
						'path' => $left['displayPath'],
						'otherPath' => $right['displayPath'],
					];
				}
			}
		}

		return $warnings;
	}

	private function folderRuleWarnings(array $rules, array $sources): array {
		$warnings = [];
		$sourcePaths = [];
		foreach ($sources as $source) {
			if (($source['enabled'] ?? true) !== true) {
				continue;
			}
			try {
				$sourcePaths[] = PathHelper::normalizeUserPath((string)($source['path'] ?? ''));
			} catch (\InvalidArgumentException) {
			}
		}

		foreach ($rules as $rule) {
			if (!is_array($rule) || (($rule['enabled'] ?? true) !== true)) {
				continue;
			}
			try {
				$rulePath = PathHelper::normalizeUserPath((string)($rule['path'] ?? ''));
			} catch (\InvalidArgumentException) {
				$warnings[] = ['code' => 'invalid_folder_rule_path', 'path' => (string)($rule['path'] ?? '')];
				continue;
			}

			$insideSource = false;
			foreach ($sourcePaths as $sourcePath) {
				if ($this->pathWithin($rulePath, $sourcePath)) {
					$insideSource = true;
					break;
				}
			}
			if (!$insideSource) {
				$warnings[] = [
					'code' => 'folder_rule_outside_sources',
					'path' => PathHelper::displayPath($rulePath),
				];
			}
		}

		return $warnings;
	}

	private function pathWithin(string $path, string $root): bool {
		if ($root === '') {
			return true;
		}

		return $path === $root || str_starts_with($path, $root . '/');
	}

	private function pathsOverlap(string $left, string $right): bool {
		if ($left === $right) {
			return true;
		}
		if ($left === '' || $right === '') {
			return true;
		}
		return str_starts_with($left, $right . '/') || str_starts_with($right, $left . '/');
	}

	private function errorPreview(string $code, string $message): array {
		return [
			'schemaVersion' => 1,
			'namingSchemaVersion' => Application::NAMING_SCHEMA_VERSION,
			'summary' => [
				'foldersScanned' => 0,
				'filesScanned' => 0,
				'mediaFiles' => 0,
				'plannedLinks' => 0,
				'plannedAlbums' => 0,
				'collisions' => 0,
				'truncated' => false,
			],
			'albums' => [],
			'warnings' => [
				['code' => $code, 'message' => $message],
			],
		];
	}
}
