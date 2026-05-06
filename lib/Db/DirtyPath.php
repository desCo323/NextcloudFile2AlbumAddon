<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getPath()
 * @method void setPath(string $path)
 * @method string getEventType()
 * @method void setEventType(string $eventType)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int getChangeCount()
 * @method void setChangeCount(int $changeCount)
 * @method int getAttempts()
 * @method void setAttempts(int $attempts)
 * @method int getFirstSeenAt()
 * @method void setFirstSeenAt(int $firstSeenAt)
 * @method int getLastSeenAt()
 * @method void setLastSeenAt(int $lastSeenAt)
 * @method int|null getLockedAt()
 * @method void setLockedAt(?int $lockedAt)
 * @method string|null getLastError()
 * @method void setLastError(?string $lastError)
 */
class DirtyPath extends Entity {
	protected string $userId = '';
	protected string $path = '';
	protected string $eventType = '';
	protected string $status = 'pending';
	protected int $changeCount = 1;
	protected int $attempts = 0;
	protected int $firstSeenAt = 0;
	protected int $lastSeenAt = 0;
	protected ?int $lockedAt = null;
	protected ?string $lastError = null;

	protected array $_fieldTypes = [
		'id' => Types::BIGINT,
		'userId' => Types::STRING,
		'path' => Types::STRING,
		'eventType' => Types::STRING,
		'status' => Types::STRING,
		'changeCount' => Types::INTEGER,
		'attempts' => Types::INTEGER,
		'firstSeenAt' => Types::BIGINT,
		'lastSeenAt' => Types::BIGINT,
		'lockedAt' => Types::BIGINT,
		'lastError' => Types::TEXT,
	];
}
