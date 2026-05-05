<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\Photos\Album\AlbumInfo;
use OCA\Photos\Album\AlbumMapper;
use OCA\Photos\Exception\AlreadyInAlbumException;
use OCP\Files\File;
use OCP\Files\IRootFolder;

class PhotosAlbumAdapter {
	public function __construct(
		private readonly AlbumMapper $albumMapper,
		private readonly IRootFolder $rootFolder,
	) {
	}

	public function findAlbum(string $userId, string $albumName): ?array {
		$album = $this->albumMapper->getByName($albumName, $userId);
		if ($album === null) {
			return null;
		}

		return $this->albumInfoToArray($album);
	}

	public function findAlbumById(int $albumId): ?array {
		$album = $this->albumMapper->get($albumId);
		if ($album === null) {
			return null;
		}

		return $this->albumInfoToArray($album);
	}

	public function createAlbum(string $userId, string $albumName, string $location): array {
		return $this->albumInfoToArray($this->albumMapper->create($userId, $albumName, $location));
	}

	public function deleteAlbum(string $userId, int $albumId): void {
		$album = $this->albumMapper->get($albumId);
		if ($album === null) {
			throw new \RuntimeException('Photos album no longer exists.');
		}
		if ($album->getUserId() !== $userId) {
			throw new \RuntimeException('Photos album owner does not match current user.');
		}

		$this->albumMapper->delete($albumId);
	}

	public function addFileToAlbum(string $userId, int $albumId, string $filePath): string {
		$filePath = PathHelper::normalizeUserPath($filePath);
		$node = $this->rootFolder->getUserFolder($userId)->get($filePath);
		if (!$node instanceof File) {
			return 'not_file';
		}

		try {
			$this->albumMapper->addFile($albumId, $node->getId(), $userId);
			return 'linked';
		} catch (AlreadyInAlbumException) {
			return 'already_linked';
		}
	}

	private function albumInfoToArray(AlbumInfo $album): array {
		return [
			'id' => $album->getId(),
			'userId' => $album->getUserId(),
			'name' => $album->getTitle(),
			'location' => $album->getLocation(),
		];
	}
}
