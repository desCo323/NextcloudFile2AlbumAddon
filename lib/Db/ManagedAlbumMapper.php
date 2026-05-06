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
	public function findForUser(string $userId, int $limit = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('album_name', 'ASC');

		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}

		return $this->findEntities($qb);
	}

	/**
	 * @return ManagedAlbum[]
	 */
	public function findActiveForUser(string $userId, int $limit = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->neq('status', $qb->createNamedParameter('deleted')))
			->orderBy('updated_at', 'DESC')
			->addOrderBy('album_name', 'ASC');

		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}

		return $this->findEntities($qb);
	}

	/**
	 * @return ManagedAlbum[]
	 */
	public function findActiveByConfigHash(string $userId, string $configHash, int $limit = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('config_hash', $qb->createNamedParameter($configHash)))
			->andWhere($qb->expr()->neq('status', $qb->createNamedParameter('deleted')))
			->orderBy('target_path', 'ASC');

		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}

		return $this->findEntities($qb);
	}

	public function countActiveForUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->neq('status', $qb->createNamedParameter('deleted')));

		return (int)$qb->executeQuery()->fetchOne();
	}

	public function sumActiveMediaCountForUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->sum('media_count'), 'media_count_sum')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->neq('status', $qb->createNamedParameter('deleted')));

		$value = $qb->executeQuery()->fetchOne();
		return $value !== false && $value !== null ? (int)$value : 0;
	}

	/**
	 * @return array<int,int>
	 */
	public function activeMediaCountsForUserAndIds(string $userId, array $ids): array {
		$ids = $this->normalizeIds($ids);
		if ($ids === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'media_count')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->neq('status', $qb->createNamedParameter('deleted')))
			->andWhere($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));

		$result = [];
		foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
			$result[(int)$row['id']] = (int)($row['media_count'] ?? 0);
		}

		return $result;
	}

	/**
	 * @return ManagedAlbum[]
	 */
	public function findActiveForUserAndIds(string $userId, array $ids, int $limit = 0): array {
		$ids = $this->normalizeIds($ids);
		if ($ids === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->neq('status', $qb->createNamedParameter('deleted')))
			->andWhere($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('album_name', 'ASC');

		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}

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
		$ids = $this->normalizeIds($ids);
		if ($ids === []) {
			return 0;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));

		return $qb->executeStatement();
	}

	public function markDeleted(ManagedAlbum $album): ManagedAlbum {
		$now = time();
		$album->setPhotosAlbumId(null);
		$album->setStatus('deleted');
		$album->setUpdatedAt($now);
		$album->setLastSyncAt($now);

		return $this->update($album);
	}

	/**
	 * @return int[]
	 */
	private function normalizeIds(array $ids): array {
		$normalized = [];
		foreach ($ids as $id) {
			if (!is_numeric($id)) {
				continue;
			}
			$id = (int)$id;
			if ($id <= 0) {
				continue;
			}
			$normalized[] = $id;
		}

		return array_values(array_unique($normalized));
	}
}
