<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getSourceType()
 * @method void setSourceType(string $sourceType)
 * @method int getSourceId()
 * @method void setSourceId(int $sourceId)
 * @method string getAlbumName()
 * @method void setAlbumName(string $albumName)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int getFileCount()
 * @method void setFileCount(int $fileCount)
 * @method int getTotalBytes()
 * @method void setTotalBytes(int $totalBytes)
 * @method int getProcessedFiles()
 * @method void setProcessedFiles(int $processedFiles)
 * @method int getProcessedBytes()
 * @method void setProcessedBytes(int $processedBytes)
 * @method int getPartCount()
 * @method void setPartCount(int $partCount)
 * @method string|null getOutputPath()
 * @method void setOutputPath(?string $outputPath)
 * @method string|null getResultJson()
 * @method void setResultJson(?string $resultJson)
 * @method string|null getLastError()
 * @method void setLastError(?string $lastError)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method int|null getStartedAt()
 * @method void setStartedAt(?int $startedAt)
 * @method int|null getCompletedAt()
 * @method void setCompletedAt(?int $completedAt)
 */
class DownloadJob extends Entity {
	protected string $userId = '';
	protected string $sourceType = '';
	protected int $sourceId = 0;
	protected string $albumName = '';
	protected string $status = 'pending';
	protected int $fileCount = 0;
	protected int $totalBytes = 0;
	protected int $processedFiles = 0;
	protected int $processedBytes = 0;
	protected int $partCount = 0;
	protected ?string $outputPath = null;
	protected ?string $resultJson = null;
	protected ?string $lastError = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
	protected ?int $startedAt = null;
	protected ?int $completedAt = null;

	protected array $_fieldTypes = [
		'id' => Types::BIGINT,
		'userId' => Types::STRING,
		'sourceType' => Types::STRING,
		'sourceId' => Types::BIGINT,
		'albumName' => Types::STRING,
		'status' => Types::STRING,
		'fileCount' => Types::INTEGER,
		'totalBytes' => Types::BIGINT,
		'processedFiles' => Types::INTEGER,
		'processedBytes' => Types::BIGINT,
		'partCount' => Types::INTEGER,
		'outputPath' => Types::STRING,
		'resultJson' => Types::TEXT,
		'lastError' => Types::TEXT,
		'createdAt' => Types::BIGINT,
		'updatedAt' => Types::BIGINT,
		'startedAt' => Types::BIGINT,
		'completedAt' => Types::BIGINT,
	];
}
