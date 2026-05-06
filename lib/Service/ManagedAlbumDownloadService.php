<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\Db\ManagedAlbum;
use OCA\SakuraAlbum\Db\ManagedAlbumMapper;
use OCP\Files\File;

class ManagedAlbumDownloadService {
	public function __construct(
		private readonly SettingsService $settingsService,
		private readonly ManagedAlbumMapper $managedAlbumMapper,
		private readonly PhotosAlbumAdapter $photosAlbumAdapter,
		private readonly LogService $logService,
	) {
	}

	public function prepare(string $userId, int $managedId): array {
		$managedAlbum = $this->managedAlbum($userId, $managedId);
		$this->assertDownloadableManagedAlbum($managedAlbum);

		$limits = $this->downloadLimits();
		$fileIds = $this->photosAlbumAdapter->listAlbumFileIds($userId, (int)$managedAlbum->getPhotosAlbumId());
		$fileCount = count($fileIds);
		if ($fileCount === 0) {
			throw new SyncSafetyException(
				'album_download_empty',
				'The selected managed album has no downloadable files.',
				409,
			);
		}
		if ($fileCount > $limits['maxFiles']) {
			throw new SyncSafetyException(
				'album_download_file_limit_exceeded',
				'The selected album exceeds the configured direct-download file limit.',
				413,
				[
					'fileCount' => $fileCount,
					'maxFiles' => $limits['maxFiles'],
				],
			);
		}

		$files = $this->photosAlbumAdapter->listAlbumFiles($userId, (int)$managedAlbum->getPhotosAlbumId(), $limits['maxFiles']);
		$totalSize = 0;
		$entries = [];
		$missingFiles = max(0, $fileCount - count($files));
		foreach ($files as $file) {
			$size = (int)$file->getSize();
			if ($size < 0) {
				throw new SyncSafetyException(
					'album_download_unknown_file_size',
					'At least one album file has an unknown size. Direct ZIP download is blocked.',
					409,
				);
			}
			$totalSize += $size;
			$entries[] = [
				'fileId' => $file->getId(),
				'name' => $file->getName(),
				'size' => $size,
				'mtime' => $file->getMTime(),
			];
		}

		if ($missingFiles > 0) {
			throw new SyncSafetyException(
				'album_download_missing_files',
				'Some album files are no longer readable by the current user.',
				409,
				[
					'missingFiles' => $missingFiles,
					'fileCount' => $fileCount,
				],
			);
		}
		if ($totalSize > $limits['maxBytes']) {
			throw new SyncSafetyException(
				'album_download_size_limit_exceeded',
				'The selected album exceeds the configured direct-download size limit.',
				413,
				[
					'totalBytes' => $totalSize,
					'maxBytes' => $limits['maxBytes'],
				],
			);
		}

		$result = [
			'managedId' => (int)$managedAlbum->getId(),
			'albumName' => $managedAlbum->getAlbumName(),
			'fileCount' => $fileCount,
			'totalBytes' => $totalSize,
			'maxFiles' => $limits['maxFiles'],
			'maxBytes' => $limits['maxBytes'],
			'downloadName' => $this->downloadName($managedAlbum),
			'entries' => array_slice($entries, 0, 20),
			'entrySampleLimit' => 20,
			'canDownload' => true,
		];

		$this->logService->info('managed_album_download_prepared', $userId, [
			'summary' => [
				'managedId' => (int)$managedAlbum->getId(),
				'albumName' => $managedAlbum->getAlbumName(),
				'fileCount' => $fileCount,
				'totalBytes' => $totalSize,
			],
		], 'Managed album download was prepared.');

		return $result;
	}

	/**
	 * @return array{name:string, files:array<int, array{file:File, internalName:string}>}
	 */
	public function zipPlan(string $userId, int $managedId): array {
		$prepared = $this->prepare($userId, $managedId);
		$managedAlbum = $this->managedAlbum($userId, $managedId);
		$files = $this->photosAlbumAdapter->listAlbumFiles(
			$userId,
			(int)$managedAlbum->getPhotosAlbumId(),
			(int)$prepared['maxFiles'],
		);

		return [
			'name' => (string)$prepared['downloadName'],
			'files' => $this->zipEntries($files),
		];
	}

	private function managedAlbum(string $userId, int $managedId): ManagedAlbum {
		$albums = $this->managedAlbumMapper->findActiveForUserAndIds($userId, [$managedId], 1);
		$managedAlbum = $albums[0] ?? null;
		if (!$managedAlbum instanceof ManagedAlbum) {
			throw new SyncSafetyException(
				'managed_album_not_found',
				'The selected SakuraAlbum managed album was not found.',
				404,
			);
		}

		return $managedAlbum;
	}

	private function assertDownloadableManagedAlbum(ManagedAlbum $managedAlbum): void {
		$photosAlbumId = $managedAlbum->getPhotosAlbumId();
		if ($photosAlbumId === null) {
			throw new SyncSafetyException(
				'album_download_tracking_only',
				'The selected managed album has no Photos album id.',
				409,
			);
		}

		$photosAlbum = $this->photosAlbumAdapter->findAlbumById($photosAlbumId);
		if ($photosAlbum === null) {
			throw new SyncSafetyException(
				'album_download_photos_album_missing',
				'The Photos album no longer exists.',
				409,
			);
		}
		if (($photosAlbum['userId'] ?? '') !== $managedAlbum->getUserId()
			|| ($photosAlbum['name'] ?? '') !== $managedAlbum->getAlbumName()) {
			throw new SyncSafetyException(
				'album_download_identity_mismatch',
				'The Photos album no longer matches SakuraAlbum tracking.',
				409,
			);
		}
	}

	private function downloadLimits(): array {
		$admin = $this->settingsService->getAdminSettings();
		return [
			'maxFiles' => max(1, min(100000, (int)($admin['maxDownloadFiles'] ?? 1000))),
			'maxBytes' => max(1048576, min(2147483647, (int)($admin['maxDownloadBytes'] ?? 2147483647))),
		];
	}

	private function downloadName(ManagedAlbum $managedAlbum): string {
		$name = preg_replace('/[^a-zA-Z0-9._ -]+/', '_', $managedAlbum->getAlbumName()) ?? 'SakuraAlbum';
		$name = trim($name, " ._\t\n\r\0\x0B");
		return mb_substr($name !== '' ? $name : 'SakuraAlbum', 0, 120);
	}

	/**
	 * @param File[] $files
	 * @return array<int, array{file:File, internalName:string}>
	 */
	private function zipEntries(array $files): array {
		$entries = [];
		$seen = [];
		foreach ($files as $file) {
			$name = $this->safeZipName($file->getName());
			$base = $name;
			$counter = 2;
			while (isset($seen[mb_strtolower($name)])) {
				$name = $this->deduplicatedName($base, $counter);
				$counter++;
			}
			$seen[mb_strtolower($name)] = true;
			$entries[] = [
				'file' => $file,
				'internalName' => $name,
			];
		}

		return $entries;
	}

	private function safeZipName(string $name): string {
		$name = str_replace(["\0", '/', '\\'], '_', $name);
		$name = trim($name);
		return $name !== '' ? mb_substr($name, 0, 180) : 'file';
	}

	private function deduplicatedName(string $name, int $counter): string {
		$dot = strrpos($name, '.');
		if ($dot === false || $dot === 0) {
			return mb_substr($name, 0, 160) . '-' . $counter;
		}

		return mb_substr(substr($name, 0, $dot), 0, 150) . '-' . $counter . substr($name, $dot);
	}
}
