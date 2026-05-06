<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getConfigHash()
 * @method void setConfigHash(string $configHash)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getCursorPath()
 * @method void setCursorPath(?string $cursorPath)
 * @method int getProcessedFiles()
 * @method void setProcessedFiles(int $processedFiles)
 * @method int getProcessedAlbums()
 * @method void setProcessedAlbums(int $processedAlbums)
 * @method int getChunkCount()
 * @method void setChunkCount(int $chunkCount)
 * @method int getAttempts()
 * @method void setAttempts(int $attempts)
 * @method int|null getLockedAt()
 * @method void setLockedAt(?int $lockedAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method int|null getCompletedAt()
 * @method void setCompletedAt(?int $completedAt)
 * @method string|null getSummaryJson()
 * @method void setSummaryJson(?string $summaryJson)
 * @method string|null getLastError()
 * @method void setLastError(?string $lastError)
 */
class SyncCursor extends Entity {
	protected string $userId = '';
	protected string $configHash = '';
	protected string $status = 'pending';
	protected ?string $cursorPath = null;
	protected int $processedFiles = 0;
	protected int $processedAlbums = 0;
	protected int $chunkCount = 0;
	protected int $attempts = 0;
	protected ?int $lockedAt = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
	protected ?int $completedAt = null;
	protected ?string $summaryJson = null;
	protected ?string $lastError = null;

	protected array $_fieldTypes = [
		'id' => Types::BIGINT,
		'userId' => Types::STRING,
		'configHash' => Types::STRING,
		'status' => Types::STRING,
		'cursorPath' => Types::STRING,
		'processedFiles' => Types::INTEGER,
		'processedAlbums' => Types::INTEGER,
		'chunkCount' => Types::INTEGER,
		'attempts' => Types::INTEGER,
		'lockedAt' => Types::BIGINT,
		'createdAt' => Types::BIGINT,
		'updatedAt' => Types::BIGINT,
		'completedAt' => Types::BIGINT,
		'summaryJson' => Types::TEXT,
		'lastError' => Types::TEXT,
	];
}
