<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ManagedAlbum>
 */
class ManagedAlbumMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'sakuraalbum_albums', ManagedAlbum::class);
	}

	/**
	 * @return ManagedAlbum[]
	 */
	public function findForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('album_name', 'ASC');

		return $this->findEntities($qb);
	}

	public function findByIdentity(string $userId, string $configHash, string $targetPath): ?ManagedAlbum {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('config_hash', $qb->createNamedParameter($configHash)))
			->andWhere($qb->expr()->eq('target_path', $qb->createNamedParameter($targetPath)))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	public function findByPhotosAlbumId(string $userId, int $photosAlbumId): ?ManagedAlbum {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('photos_album_id', $qb->createNamedParameter($photosAlbumId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	public function deleteForUserAndIds(string $userId, array $ids): int {
		$ids = array_values(array_filter($ids, static fn (mixed $id): bool => is_numeric($id)));
		if ($ids === []) {
			return 0;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));

		return $qb->executeStatement();
	}
}
