<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int|null getPhotosAlbumId()
 * @method void setPhotosAlbumId(?int $photosAlbumId)
 * @method string getAlbumName()
 * @method void setAlbumName(string $albumName)
 * @method string getSourceRoot()
 * @method void setSourceRoot(string $sourceRoot)
 * @method string getTargetPath()
 * @method void setTargetPath(string $targetPath)
 * @method string getNamingTemplate()
 * @method void setNamingTemplate(string $namingTemplate)
 * @method int getNamingSchemaVersion()
 * @method void setNamingSchemaVersion(int $namingSchemaVersion)
 * @method string getConfigHash()
 * @method void setConfigHash(string $configHash)
 * @method int getMediaCount()
 * @method void setMediaCount(int $mediaCount)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method int|null getLastSyncAt()
 * @method void setLastSyncAt(?int $lastSyncAt)
 */
class ManagedAlbum extends Entity {
	protected string $userId = '';
	protected ?int $photosAlbumId = null;
	protected string $albumName = '';
	protected string $sourceRoot = '';
	protected string $targetPath = '';
	protected string $namingTemplate = '';
	protected int $namingSchemaVersion = 1;
	protected string $configHash = '';
	protected int $mediaCount = 0;
	protected string $status = 'planned';
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
	protected ?int $lastSyncAt = null;

	protected array $_fieldTypes = [
		'id' => Types::BIGINT,
		'userId' => Types::STRING,
		'photosAlbumId' => Types::BIGINT,
		'albumName' => Types::STRING,
		'sourceRoot' => Types::STRING,
		'targetPath' => Types::STRING,
		'namingTemplate' => Types::STRING,
		'namingSchemaVersion' => Types::INTEGER,
		'configHash' => Types::STRING,
		'mediaCount' => Types::INTEGER,
		'status' => Types::STRING,
		'createdAt' => Types::BIGINT,
		'updatedAt' => Types::BIGINT,
		'lastSyncAt' => Types::BIGINT,
	];
}
