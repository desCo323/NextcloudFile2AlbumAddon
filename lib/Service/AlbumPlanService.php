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

	private function buildPlan(string $userId, array $settings, array $limits, bool $includeFiles): array {
		$warnings = [];
		$albums = [];
		$summary = [
			'foldersScanned' => 0,
			'filesScanned' => 0,
			'mediaFiles' => 0,
			'plannedLinks' => 0,
			'plannedAlbums' => 0,
			'collisions' => 0,
			'truncated' => false,
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
			$this->scanFolder($rootNode, $rootPath, $rootPath, 0, $sourceSettings, $limits, $includeFiles, $albums, $summary, $warnings, (string)($source['id'] ?? ''));
			if ($summary['truncated']) {
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
	): void {
		if ($summary['truncated']) {
			return;
		}
		if ($depth > (int)$limits['maxDepth']) {
			return;
		}
		if ($this->isExcluded($currentPath, $settings['excludePatterns'] ?? [])) {
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
			$nodes = $folder->getDirectoryListing();
		} catch (StorageNotAvailableException) {
			$warnings[] = ['code' => 'storage_unavailable', 'path' => PathHelper::displayPath($currentPath)];
			return;
		}

		foreach ($nodes as $node) {
			if ($node instanceof File) {
				$summary['filesScanned']++;
				if ($summary['filesScanned'] > (int)$limits['maxFiles']) {
					$summary['truncated'] = true;
					$warnings[] = ['code' => 'max_files_reached', 'limit' => (int)$limits['maxFiles']];
					return;
				}

				if (!$this->isMediaFile($node, $settings)) {
					continue;
				}

				$summary['mediaFiles']++;
				$summary['plannedLinks']++;
				$targetPath = $this->targetAlbumPath($sourceRoot, $currentPath, (int)$settings['albumDepth']);
				$key = $sourceId . "\n" . $sourceRoot . "\n" . $targetPath;
				$filePath = trim($currentPath . '/' . $node->getName(), '/');

				if (!isset($albums[$key])) {
					$albums[$key] = [
						'sourceId' => $sourceId,
						'sourceRoot' => PathHelper::displayPath($sourceRoot),
						'targetPath' => PathHelper::displayPath($targetPath),
						'albumName' => $this->albumNameFormatter->format($sourceRoot, $targetPath, $settings),
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
			$this->scanFolder($node, $sourceRoot, $childPath, $depth + 1, $settings, $limits, $includeFiles, $albums, $summary, $warnings, $sourceId);
			if ($summary['truncated']) {
				return;
			}
		}
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
