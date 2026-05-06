<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\StorageNotAvailableException;

class FolderBrowserService {
	public function __construct(
		private readonly IRootFolder $rootFolder,
	) {
	}

	public function listFolders(string $userId, string $path, int $limit): array {
		$limit = max(1, min(300, $limit));
		$currentPath = PathHelper::normalizeUserPath($path);
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$current = $currentPath === '' ? $userFolder : $userFolder->get($currentPath);
		if (!$current instanceof Folder) {
			throw new \InvalidArgumentException('Path is not a folder.');
		}

		$folders = [];
		try {
			foreach ($current->getDirectoryListing() as $node) {
				if (!$node instanceof Folder) {
					continue;
				}
				$childPath = trim($currentPath . '/' . $node->getName(), '/');
				$folders[] = [
					'name' => $node->getName(),
					'path' => PathHelper::displayPath($childPath),
					'hasChildren' => $this->hasChildFolders($node),
				];
			}
		} catch (StorageNotAvailableException) {
			throw new \RuntimeException('Storage is currently unavailable.');
		}

		usort($folders, static fn (array $a, array $b): int => strnatcasecmp((string)$a['name'], (string)$b['name']));
		$total = count($folders);

		return [
			'current' => [
				'name' => $currentPath === '' ? 'Dateien' : PathHelper::basename($currentPath),
				'path' => PathHelper::displayPath($currentPath),
			],
			'parent' => $this->parentPath($currentPath),
			'folders' => array_slice($folders, 0, $limit),
			'total' => $total,
			'limit' => $limit,
			'truncated' => $total > $limit,
		];
	}

	private function hasChildFolders(Folder $folder): bool {
		try {
			foreach ($folder->getDirectoryListing() as $node) {
				if ($node instanceof Folder) {
					return true;
				}
			}
		} catch (\Throwable) {
			return false;
		}

		return false;
	}

	private function parentPath(string $path): ?string {
		$path = PathHelper::normalizeUserPath($path);
		if ($path === '') {
			return null;
		}

		$parts = explode('/', $path);
		array_pop($parts);
		return PathHelper::displayPath(implode('/', $parts));
	}
}
