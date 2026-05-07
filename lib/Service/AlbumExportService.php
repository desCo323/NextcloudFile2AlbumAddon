<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\BackgroundJob\AlbumExportJob;
use OCA\SakuraAlbum\Db\DownloadJob;
use OCA\SakuraAlbum\Db\DownloadJobMapper;
use OCA\SakuraAlbum\Db\ManagedAlbum;
use OCA\SakuraAlbum\Db\ManagedAlbumMapper;
use OCP\BackgroundJob\IJobList;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

class AlbumExportService {
	public const SOURCE_MANAGED = 'managed';
	public const SOURCE_PHOTOS = 'photos';
	private const OUTPUT_ROOT = 'SakuraAlbum Exports';
	private const PART_SIZE_BYTES = 1073741824;
	private const MAX_ACTIVE_JOBS_PER_USER = 3;
	private const ALBUM_LIST_FILE_SAMPLE_LIMIT = 500;

	public function __construct(
		private readonly DownloadJobMapper $downloadJobMapper,
		private readonly ManagedAlbumMapper $managedAlbumMapper,
		private readonly PhotosAlbumAdapter $photosAlbumAdapter,
		private readonly IRootFolder $rootFolder,
		private readonly IJobList $jobList,
		private readonly SettingsService $settingsService,
		private readonly LogService $logService,
	) {
	}

	public function listDownloadableAlbums(string $userId, int $limit): array {
		$limit = max(1, min(500, $limit));
		$exportLimits = $this->settingsService->getExportLimits();
		$managedByPhotosId = [];
		foreach ($this->managedAlbumMapper->findActiveForUser($userId, 10000) as $managedAlbum) {
			if ($managedAlbum->getPhotosAlbumId() !== null) {
				$managedByPhotosId[(int)$managedAlbum->getPhotosAlbumId()] = $managedAlbum;
			}
		}

		$albums = [];
		foreach ($this->photosAlbumAdapter->listUserAlbums($userId, $limit) as $album) {
			$albumId = (int)$album['id'];
			$managedAlbum = $managedByPhotosId[$albumId] ?? null;
			$fileCount = $this->photosAlbumAdapter->countAlbumFiles($userId, $albumId);
			$sampleLimit = min($fileCount, self::ALBUM_LIST_FILE_SAMPLE_LIMIT, (int)$exportLimits['maxFiles']);
			$files = $sampleLimit > 0 ? $this->photosAlbumAdapter->listAlbumFiles($userId, $albumId, $sampleLimit) : [];
			$exactFileSample = $fileCount <= $sampleLimit;
			$totalBytes = $this->sumFileSizes($files);
			$totalBytesExact = $exactFileSample;
			$blockedReason = $this->exportBlockedReason($fileCount, $totalBytes, $totalBytesExact, $exportLimits);
			$albums[] = [
				'sourceType' => $managedAlbum instanceof ManagedAlbum ? self::SOURCE_MANAGED : self::SOURCE_PHOTOS,
				'sourceId' => $managedAlbum instanceof ManagedAlbum ? (int)$managedAlbum->getId() : $albumId,
				'photosAlbumId' => $albumId,
				'albumName' => (string)$album['name'],
				'location' => (string)($album['location'] ?? ''),
				'managed' => $managedAlbum instanceof ManagedAlbum,
				'managedId' => $managedAlbum instanceof ManagedAlbum ? (int)$managedAlbum->getId() : null,
				'fileCount' => $fileCount,
				'readableFiles' => count($files),
				'readableFilesExact' => $exactFileSample,
				'missingFiles' => $exactFileSample ? max(0, $fileCount - count($files)) : 0,
				'missingFilesExact' => $exactFileSample,
				'totalBytes' => $totalBytes,
				'totalBytesExact' => $totalBytesExact,
				'sampledFiles' => count($files),
				'exportAllowed' => $fileCount > 0 && $blockedReason === null,
				'exportBlockedReason' => $blockedReason,
			];
		}

		return [
			'albums' => $albums,
			'limit' => $limit,
			'truncated' => count($albums) >= $limit,
			'partSizeBytes' => self::PART_SIZE_BYTES,
			'limits' => $exportLimits,
		];
	}

	public function createJob(string $userId, string $sourceType, int $sourceId): array {
		$source = $this->resolveSource($userId, $sourceType, $sourceId);
		if ($this->downloadJobMapper->countActiveForUser($userId) >= self::MAX_ACTIVE_JOBS_PER_USER) {
			throw new SyncSafetyException(
				'album_export_too_many_active_jobs',
				'Too many album export jobs are already pending or running.',
				429,
				['maxActiveJobs' => self::MAX_ACTIVE_JOBS_PER_USER],
			);
		}

		$exportLimits = $this->settingsService->getExportLimits();
		$fileCount = $this->photosAlbumAdapter->countAlbumFiles($userId, (int)$source['photosAlbumId']);
		$this->assertExportFileCount($fileCount, $exportLimits);
		$files = $this->photosAlbumAdapter->listAlbumFiles($userId, (int)$source['photosAlbumId'], $fileCount);
		if ($files === []) {
			throw new SyncSafetyException(
				'album_export_empty',
				'The selected album has no readable files to export.',
				409,
			);
		}
		$totalBytes = $this->sumFileSizes($files);
		$this->assertExportByteCount($totalBytes, $exportLimits);

		$now = time();
		$job = new DownloadJob();
		$job->setUserId($userId);
		$job->setSourceType($sourceType);
		$job->setSourceId($sourceId);
		$job->setAlbumName(mb_substr((string)$source['albumName'], 0, 255));
		$job->setStatus('pending');
		$job->setFileCount(count($files));
		$job->setTotalBytes($totalBytes);
		$job->setProcessedFiles(0);
		$job->setProcessedBytes(0);
		$job->setPartCount(0);
		$job->setCreatedAt($now);
		$job->setUpdatedAt($now);
		$job = $this->downloadJobMapper->insert($job);

		$this->jobList->add(AlbumExportJob::class, ['jobId' => (int)$job->getId()]);
		$this->logService->info('album_export_job_created', $userId, [
			'summary' => [
				'jobId' => (int)$job->getId(),
				'sourceType' => $sourceType,
				'sourceId' => $sourceId,
				'albumName' => $job->getAlbumName(),
				'fileCount' => $job->getFileCount(),
				'totalBytes' => $job->getTotalBytes(),
				'missingReadableFiles' => max(0, $fileCount - count($files)),
			],
		], 'Album export job was queued.');

		return [
			'job' => $this->jobToArray($job),
			'partSizeBytes' => self::PART_SIZE_BYTES,
		];
	}

	public function jobsForUser(string $userId, int $limit): array {
		return [
			'jobs' => array_map(
				fn (DownloadJob $job): array => $this->jobToArray($job),
				$this->downloadJobMapper->findRecentForUser($userId, $limit),
			),
			'partSizeBytes' => self::PART_SIZE_BYTES,
		];
	}

	public function runJob(int $jobId): void {
		$job = $this->downloadJobMapper->findById($jobId);
		if (!$job instanceof DownloadJob) {
			return;
		}
		if (!in_array($job->getStatus(), ['pending', 'failed'], true)) {
			return;
		}

		$now = time();
		$job->setStatus('running');
		$job->setStartedAt($job->getStartedAt() ?? $now);
		$job->setUpdatedAt($now);
		$job->setLastError(null);
		$this->downloadJobMapper->update($job);

		try {
			$this->runExport($job);
		} catch (\Throwable $e) {
			$job = $this->downloadJobMapper->findById($jobId) ?? $job;
			$job->setStatus('failed');
			$job->setUpdatedAt(time());
			$job->setCompletedAt(time());
			$job->setLastError(mb_substr($e->getMessage(), 0, 4000));
			$this->downloadJobMapper->update($job);
			$this->logService->exception('album_export_job_failed', $e, $job->getUserId(), [
				'jobId' => (int)$job->getId(),
				'sourceType' => $job->getSourceType(),
				'sourceId' => $job->getSourceId(),
			]);
		}
	}

	public function downloadablePart(string $userId, int $jobId, int $partIndex): array {
		$job = $this->downloadJobMapper->findByIdForUser($jobId, $userId);
		if (!$job instanceof DownloadJob || $job->getStatus() !== 'completed') {
			throw new SyncSafetyException(
				'album_export_not_ready',
				'The selected album export is not ready for download.',
				404,
			);
		}
		$result = $this->decodedResult($job);
		$parts = $result['parts'] ?? [];
		$part = null;
		foreach ($parts as $candidate) {
			if ((int)($candidate['index'] ?? 0) === $partIndex) {
				$part = $candidate;
				break;
			}
		}
		if (!is_array($part)) {
			throw new SyncSafetyException(
				'album_export_part_not_found',
				'The selected export part was not found.',
				404,
			);
		}

		$path = PathHelper::normalizeUserPath((string)($part['path'] ?? ''));
		$node = $this->rootFolder->getUserFolder($userId)->get($path);
		if (!$node instanceof File || !$node->isReadable()) {
			throw new SyncSafetyException(
				'album_export_file_not_readable',
				'The prepared export file is not readable.',
				404,
			);
		}

		return [
			'file' => $node,
			'name' => (string)($part['name'] ?? $node->getName()),
		];
	}

	private function runExport(DownloadJob $job): void {
		if (!class_exists(\ZipArchive::class)) {
			throw new \RuntimeException('PHP ZipArchive extension is not available.');
		}

		$source = $this->resolveSource($job->getUserId(), $job->getSourceType(), $job->getSourceId());
		$exportLimits = $this->settingsService->getExportLimits();
		$fileCount = $this->photosAlbumAdapter->countAlbumFiles($job->getUserId(), (int)$source['photosAlbumId']);
		$this->assertExportFileCount($fileCount, $exportLimits);
		$files = $this->photosAlbumAdapter->listAlbumFiles($job->getUserId(), (int)$source['photosAlbumId'], $fileCount);
		if ($files === []) {
			throw new \RuntimeException('Album has no readable files.');
		}
		$totalBytes = $this->sumFileSizes($files);
		$this->assertExportByteCount($totalBytes, $exportLimits);
		$this->logService->debug('album_export_job_started', $job->getUserId(), [
			'jobId' => (int)$job->getId(),
			'sourceType' => $job->getSourceType(),
			'sourceId' => $job->getSourceId(),
			'photosAlbumId' => (int)$source['photosAlbumId'],
			'fileCount' => count($files),
			'totalAlbumFileCount' => $fileCount,
			'missingReadableFiles' => max(0, $fileCount - count($files)),
		], 'Album export job started.');

		$job->setAlbumName(mb_substr((string)$source['albumName'], 0, 255));
		$job->setFileCount(count($files));
		$job->setTotalBytes($totalBytes);
		$job->setUpdatedAt(time());
		$this->downloadJobMapper->update($job);

		$userFolder = $this->rootFolder->getUserFolder($job->getUserId());
		$outputFolderName = $this->safeFileName($job->getAlbumName()) . '-' . (int)$job->getId();
		$outputFolder = $this->ensureFolder($userFolder, [self::OUTPUT_ROOT, $outputFolderName]);
		$this->ensureMarkerFiles($outputFolder);
		$outputPath = '/' . self::OUTPUT_ROOT . '/' . $outputFolderName;

		$multiPart = $job->getTotalBytes() > self::PART_SIZE_BYTES;
		$parts = [];
		$seenNames = [];
		$partIndex = 0;
		$partBytes = 0;
		$tmpFiles = [];
		$tmpZip = null;
		$zip = null;

		$openPart = function () use (&$partIndex, &$partBytes, &$tmpFiles, &$tmpZip, &$zip): void {
			$partIndex++;
			$partBytes = 0;
			$tmpFiles = [];
			$tmpZip = tempnam(sys_get_temp_dir(), 'ska_export_zip_');
			if ($tmpZip === false) {
				throw new \RuntimeException('Could not create temporary ZIP file.');
			}
			$zip = new \ZipArchive();
			$result = $zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
			if ($result !== true) {
				throw new \RuntimeException('Could not open temporary ZIP archive.');
			}
		};

		$closePart = function () use (&$zip, &$tmpZip, &$tmpFiles, &$parts, &$partIndex, &$partBytes, $outputFolder, $outputPath, $job, $multiPart): void {
			if (!$zip instanceof \ZipArchive || $tmpZip === null) {
				return;
			}
			$currentZip = $zip;
			$zip = null;
			$currentZip->close();
			foreach ($tmpFiles as $tmpFile) {
				if (is_string($tmpFile) && is_file($tmpFile)) {
					@unlink($tmpFile);
				}
			}
			$partName = $multiPart
				? sprintf('%s.part%03d.zip', $this->safeFileName($job->getAlbumName()), $partIndex)
				: sprintf('%s.zip', $this->safeFileName($job->getAlbumName()));
			$partName = $this->nonExistingFileName($outputFolder, $partName);
			$handle = fopen($tmpZip, 'rb');
			if (!is_resource($handle)) {
				throw new \RuntimeException('Could not read prepared ZIP part.');
			}
			$zipSize = is_file($tmpZip) ? (int)filesize($tmpZip) : 0;
			try {
				$outputFolder->newFile($partName, $handle);
			} finally {
				if (is_resource($handle)) {
					fclose($handle);
				}
				@unlink($tmpZip);
			}
			$parts[] = [
				'index' => $partIndex,
				'name' => $partName,
				'path' => $outputPath . '/' . $partName,
				'size' => $zipSize,
				'uncompressedBytes' => $partBytes,
			];
			$this->logService->debug('album_export_part_completed', $job->getUserId(), [
				'jobId' => (int)$job->getId(),
				'partIndex' => $partIndex,
				'partName' => $partName,
				'zipBytes' => $zipSize,
				'uncompressedBytes' => $partBytes,
			], 'Album export ZIP part was written to user files.');
			$tmpZip = null;
			$tmpFiles = [];
		};

		$processedFiles = 0;
		$processedBytes = 0;
		try {
			$openPart();
			foreach ($files as $file) {
				$size = max(0, (int)$file->getSize());
				if ($partBytes > 0 && $partBytes + $size > self::PART_SIZE_BYTES) {
					$closePart();
					$job = $this->downloadJobMapper->findById((int)$job->getId()) ?? $job;
					$job->setPartCount(count($parts));
					$job->setUpdatedAt(time());
					$this->downloadJobMapper->update($job);
					$openPart();
				}

				if (!$zip instanceof \ZipArchive) {
					throw new \RuntimeException('ZIP archive is not open.');
				}
				$tmpFile = $this->copyToTemporaryFile($file);
				$tmpFiles[] = $tmpFile;
				$internalName = $this->uniqueZipEntryName($file->getName(), $seenNames);
				if (!$zip->addFile($tmpFile, $internalName)) {
					throw new \RuntimeException('Could not add file to ZIP archive.');
				}
				$partBytes += $size;
				$processedFiles++;
				$processedBytes += $size;
				if ($processedFiles % 10 === 0 || $processedFiles === count($files)) {
					$job = $this->downloadJobMapper->findById((int)$job->getId()) ?? $job;
					$job->setProcessedFiles($processedFiles);
					$job->setProcessedBytes($processedBytes);
					$job->setUpdatedAt(time());
					$this->downloadJobMapper->update($job);
				}
			}
			$closePart();
		} finally {
			if ($zip instanceof \ZipArchive) {
				$zip->close();
			}
			foreach ($tmpFiles as $tmpFile) {
				if (is_string($tmpFile) && is_file($tmpFile)) {
					@unlink($tmpFile);
				}
			}
			if (is_string($tmpZip) && is_file($tmpZip)) {
				@unlink($tmpZip);
			}
		}

		$job = $this->downloadJobMapper->findById((int)$job->getId()) ?? $job;
		$job->setStatus('completed');
		$job->setProcessedFiles($processedFiles);
		$job->setProcessedBytes($processedBytes);
		$job->setPartCount(count($parts));
		$job->setOutputPath($outputPath);
		$job->setResultJson(json_encode([
			'parts' => $parts,
			'partSizeBytes' => self::PART_SIZE_BYTES,
			'multiPart' => $multiPart,
			'completedAt' => time(),
		], JSON_UNESCAPED_SLASHES));
		$job->setUpdatedAt(time());
		$job->setCompletedAt(time());
		$this->downloadJobMapper->update($job);

		$this->logService->success('album_export_job_completed', $job->getUserId(), [
			'summary' => [
				'jobId' => (int)$job->getId(),
				'albumName' => $job->getAlbumName(),
				'fileCount' => $processedFiles,
				'totalBytes' => $processedBytes,
				'partCount' => count($parts),
				'outputPath' => $outputPath,
			],
		], 'Album export job completed.');
	}

	private function resolveSource(string $userId, string $sourceType, int $sourceId): array {
		if ($sourceType === self::SOURCE_MANAGED) {
			$managed = $this->managedAlbumMapper->findActiveForUserAndIds($userId, [$sourceId], 1)[0] ?? null;
			if (!$managed instanceof ManagedAlbum || $managed->getPhotosAlbumId() === null) {
				throw new SyncSafetyException(
					'managed_album_not_found',
					'The selected SakuraAlbum managed album was not found.',
					404,
				);
			}
			$album = $this->photosAlbumAdapter->findAlbumById((int)$managed->getPhotosAlbumId());
			if ($album === null || ($album['userId'] ?? '') !== $userId || ($album['name'] ?? '') !== $managed->getAlbumName()) {
				throw new SyncSafetyException(
					'album_export_identity_mismatch',
					'The managed album no longer matches the Photos album.',
					409,
				);
			}

			return [
				'sourceType' => self::SOURCE_MANAGED,
				'sourceId' => $sourceId,
				'photosAlbumId' => (int)$managed->getPhotosAlbumId(),
				'albumName' => $managed->getAlbumName(),
			];
		}

		if ($sourceType === self::SOURCE_PHOTOS) {
			$album = $this->photosAlbumAdapter->findAlbumById($sourceId);
			if ($album === null || ($album['userId'] ?? '') !== $userId) {
				throw new SyncSafetyException(
					'photos_album_not_found',
					'The selected Photos album was not found.',
					404,
				);
			}

			return [
				'sourceType' => self::SOURCE_PHOTOS,
				'sourceId' => $sourceId,
				'photosAlbumId' => $sourceId,
				'albumName' => (string)$album['name'],
			];
		}

		throw new SyncSafetyException(
			'album_export_invalid_source_type',
			'Invalid album export source type.',
			400,
		);
	}

	/**
	 * @param File[] $files
	 */
	private function sumFileSizes(array $files): int {
		$total = 0;
		foreach ($files as $file) {
			$total += max(0, (int)$file->getSize());
		}

		return $total;
	}

	private function exportBlockedReason(int $fileCount, int $knownBytes, bool $bytesExact, array $limits): ?string {
		if ($fileCount > (int)$limits['maxFiles']) {
			return 'max_export_files_exceeded';
		}
		if ($knownBytes > (int)$limits['maxBytes']) {
			return 'max_export_bytes_exceeded';
		}
		if (!$bytesExact && $knownBytes > 0) {
			return null;
		}

		return null;
	}

	private function assertExportFileCount(int $fileCount, array $limits): void {
		$maxFiles = (int)$limits['maxFiles'];
		if ($fileCount > $maxFiles) {
			throw new SyncSafetyException(
				'album_export_limit_exceeded',
				'The selected album exceeds the administrator file limit for background exports.',
				413,
				[
					'reason' => 'max_export_files_exceeded',
					'fileCount' => $fileCount,
					'maxFiles' => $maxFiles,
				],
			);
		}
	}

	private function assertExportByteCount(int $totalBytes, array $limits): void {
		$maxBytes = (int)$limits['maxBytes'];
		if ($totalBytes > $maxBytes) {
			throw new SyncSafetyException(
				'album_export_limit_exceeded',
				'The selected album exceeds the administrator byte limit for background exports.',
				413,
				[
					'reason' => 'max_export_bytes_exceeded',
					'totalBytes' => $totalBytes,
					'maxBytes' => $maxBytes,
				],
			);
		}
	}

	private function copyToTemporaryFile(File $file): string {
		$input = $file->fopen('rb');
		if (!is_resource($input)) {
			throw new \RuntimeException('Could not read album file.');
		}
		$tmp = tempnam(sys_get_temp_dir(), 'ska_export_file_');
		if ($tmp === false) {
			fclose($input);
			throw new \RuntimeException('Could not create temporary file.');
		}
		$output = fopen($tmp, 'wb');
		if (!is_resource($output)) {
			fclose($input);
			@unlink($tmp);
			throw new \RuntimeException('Could not write temporary file.');
		}
		try {
			stream_copy_to_stream($input, $output);
		} finally {
			fclose($input);
			fclose($output);
		}

		return $tmp;
	}

	private function ensureFolder(Folder $root, array $parts): Folder {
		$current = $root;
		foreach ($parts as $part) {
			$part = $this->safeFileName((string)$part);
			try {
				$node = $current->get($part);
			} catch (NotFoundException) {
				$node = $current->newFolder($part);
			}
			if (!$node instanceof Folder) {
				throw new \RuntimeException('Export path is blocked by a file.');
			}
			$current = $node;
		}

		return $current;
	}

	private function ensureMarkerFiles(Folder $folder): void {
		foreach (['.nomedia', '.noimage'] as $name) {
			try {
				$folder->get($name);
			} catch (NotFoundException) {
				$folder->newFile($name, '');
			}
		}
	}

	private function nonExistingFileName(Folder $folder, string $name): string {
		$base = $name;
		$dot = strrpos($name, '.');
		$stem = $dot === false ? $name : substr($name, 0, $dot);
		$extension = $dot === false ? '' : substr($name, $dot);
		$counter = 2;
		while ($folder->nodeExists($name)) {
			$name = mb_substr($stem, 0, 150) . '-' . $counter . $extension;
			$counter++;
		}

		return $name !== '' ? $name : $base;
	}

	private function uniqueZipEntryName(string $name, array &$seen): string {
		$name = $this->safeZipName($name);
		$base = $name;
		$counter = 2;
		while (isset($seen[mb_strtolower($name)])) {
			$dot = strrpos($base, '.');
			$name = $dot === false || $dot === 0
				? mb_substr($base, 0, 160) . '-' . $counter
				: mb_substr(substr($base, 0, $dot), 0, 150) . '-' . $counter . substr($base, $dot);
			$counter++;
		}
		$seen[mb_strtolower($name)] = true;

		return $name;
	}

	private function safeZipName(string $name): string {
		$name = str_replace(["\0", '/', '\\'], '_', $name);
		$name = trim($name);
		return $name !== '' ? mb_substr($name, 0, 180) : 'file';
	}

	private function safeFileName(string $name): string {
		$name = preg_replace('/[^a-zA-Z0-9._ -]+/', '_', $name) ?? 'SakuraAlbum';
		$name = trim($name, " ._\t\n\r\0\x0B");
		return mb_substr($name !== '' ? $name : 'SakuraAlbum', 0, 120);
	}

	private function jobToArray(DownloadJob $job): array {
		$result = $this->decodedResult($job);
		return [
			'jobId' => (int)$job->getId(),
			'sourceType' => $job->getSourceType(),
			'sourceId' => $job->getSourceId(),
			'albumName' => $job->getAlbumName(),
			'status' => $job->getStatus(),
			'fileCount' => $job->getFileCount(),
			'totalBytes' => $job->getTotalBytes(),
			'processedFiles' => $job->getProcessedFiles(),
			'processedBytes' => $job->getProcessedBytes(),
			'partCount' => $job->getPartCount(),
			'outputPath' => $job->getOutputPath(),
			'parts' => $result['parts'] ?? [],
			'progressPercent' => $this->jobProgressPercent($job),
			'lastError' => $job->getLastError(),
			'createdAt' => $job->getCreatedAt(),
			'updatedAt' => $job->getUpdatedAt(),
			'startedAt' => $job->getStartedAt(),
			'completedAt' => $job->getCompletedAt(),
		];
	}

	private function decodedResult(DownloadJob $job): array {
		$json = $job->getResultJson();
		if ($json === null || $json === '') {
			return [];
		}
		$decoded = json_decode($json, true);
		return is_array($decoded) ? $decoded : [];
	}

	private function jobProgressPercent(DownloadJob $job): int {
		if ($job->getStatus() === 'completed') {
			return 100;
		}
		if ($job->getStatus() === 'failed') {
			return 100;
		}
		$total = $job->getTotalBytes();
		if ($total <= 0) {
			return $job->getStatus() === 'running' ? 5 : 0;
		}

		return max(0, min(99, (int)floor(($job->getProcessedBytes() / $total) * 100)));
	}
}
