<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\Photos\Album\AlbumInfo;
use OCA\Photos\Album\AlbumMapper;
use OCA\Photos\Exception\AlreadyInAlbumException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;

class PhotosAlbumAdapter {
	public function __construct(
		private readonly AlbumMapper $albumMapper,
		private readonly IRootFolder $rootFolder,
		private readonly IDBConnection $db,
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
		$fileId = $this->fileIdForPath($userId, $filePath);
		if ($fileId === null) {
			return 'not_file';
		}

		return $this->addFileIdToAlbum($albumId, $fileId, $userId);
	}

	public function fileIdForPath(string $userId, string $filePath): ?int {
		$filePath = PathHelper::normalizeUserPath($filePath);
		try {
			$node = $this->rootFolder->getUserFolder($userId)->get($filePath);
		} catch (\Throwable) {
			return null;
		}

		if (!$node instanceof File) {
			return null;
		}

		return $node->getId();
	}

	public function addFileIdToAlbum(int $albumId, int $fileId, string $owner): string {
		try {
			$this->albumMapper->addFile($albumId, $fileId, $owner);
			return 'linked';
		} catch (AlreadyInAlbumException) {
			return 'already_linked';
		}
	}

	/**
	 * @return int[]
	 */
	public function listAlbumFileIds(string $userId, int $albumId): array {
		$album = $this->albumMapper->get($albumId);
		if ($album === null) {
			throw new \RuntimeException('Photos album no longer exists.');
		}
		if ($album->getUserId() !== $userId) {
			throw new \RuntimeException('Photos album owner does not match current user.');
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('file_id')
			->from('photos_albums_files')
			->where($qb->expr()->eq('album_id', $qb->createNamedParameter($albumId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('owner', $qb->createNamedParameter($userId)));

		return array_values(array_unique(array_map('intval', $qb->executeQuery()->fetchFirstColumn())));
	}

	public function removeFileFromAlbum(int $albumId, int $fileId): void {
		$this->albumMapper->removeFile($albumId, $fileId);
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
