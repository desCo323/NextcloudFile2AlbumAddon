<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getRunType()
 * @method void setRunType(string $runType)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int getStartedAt()
 * @method void setStartedAt(int $startedAt)
 * @method int|null getFinishedAt()
 * @method void setFinishedAt(?int $finishedAt)
 * @method string|null getSummaryJson()
 * @method void setSummaryJson(?string $summaryJson)
 * @method string|null getErrorMessage()
 * @method void setErrorMessage(?string $errorMessage)
 */
class SyncRun extends Entity {
	protected string $userId = '';
	protected string $runType = '';
	protected string $status = '';
	protected int $startedAt = 0;
	protected ?int $finishedAt = null;
	protected ?string $summaryJson = null;
	protected ?string $errorMessage = null;

	protected array $_fieldTypes = [
		'id' => Types::BIGINT,
		'userId' => Types::STRING,
		'runType' => Types::STRING,
		'status' => Types::STRING,
		'startedAt' => Types::BIGINT,
		'finishedAt' => Types::BIGINT,
		'summaryJson' => Types::TEXT,
		'errorMessage' => Types::TEXT,
	];
}
