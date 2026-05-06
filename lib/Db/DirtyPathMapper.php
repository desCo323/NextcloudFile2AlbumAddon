<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<DirtyPath>
 */
class DirtyPathMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'sakuraalbum_dirty_paths', DirtyPath::class);
	}

	public function markDirty(string $userId, string $path, string $eventType, int $now): void {
		$path = $this->storedPath($path);
		$eventType = mb_substr($eventType, 0, 32);
		$existing = $this->findByUserAndPath($userId, $path);
		if ($existing !== null) {
			$existing->setEventType($eventType);
			$existing->setStatus('pending');
			$existing->setChangeCount($existing->getChangeCount() + 1);
			$existing->setLastSeenAt($now);
			$existing->setLockedAt(null);
			$existing->setLastError(null);
			$this->update($existing);
			return;
		}

		$dirty = new DirtyPath();
		$dirty->setUserId($userId);
		$dirty->setPath($path);
		$dirty->setEventType($eventType);
		$dirty->setStatus('pending');
		$dirty->setChangeCount(1);
		$dirty->setAttempts(0);
		$dirty->setFirstSeenAt($now);
		$dirty->setLastSeenAt($now);
		$this->insert($dirty);
	}

	/**
	 * @return string[]
	 */
	public function findDueUsers(int $notAfter, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('user_id')
			->from($this->tableName)
			->where($qb->expr()->eq('status', $qb->createNamedParameter('pending')))
			->andWhere($qb->expr()->lte('last_seen_at', $qb->createNamedParameter($notAfter, IQueryBuilder::PARAM_INT)))
			->orderBy('user_id', 'ASC')
			->setMaxResults(max(1, min(1000, $limit)));

		return array_values(array_map('strval', $qb->executeQuery()->fetchFirstColumn()));
	}

	public function countPendingForUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('pending')));

		return (int)$qb->executeQuery()->fetchOne();
	}

	public function markUserProcessing(string $userId, int $now, int $maxEvents, int $notAfter): int {
		$ids = $this->pendingIdsForUser($userId, $maxEvents, $notAfter);
		if ($ids === []) {
			return 0;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter('processing'))
			->set('locked_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));

		return $qb->executeStatement();
	}

	public function releaseStaleProcessing(int $olderThan, int $limit): int {
		$ids = $this->staleProcessingIds($olderThan, $limit);
		if ($ids === []) {
			return 0;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter('pending'))
			->set('locked_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('last_error', $qb->createNamedParameter('Recovered from stale automatic sync processing lock.'))
			->set('attempts', $qb->func()->add('attempts', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));

		return $qb->executeStatement();
	}

	public function markUserProcessed(string $userId, int $processedNotAfter): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('status', $qb->createNamedParameter('processing')),
				$qb->expr()->andX(
					$qb->expr()->eq('status', $qb->createNamedParameter('pending')),
					$qb->expr()->lte('last_seen_at', $qb->createNamedParameter($processedNotAfter, IQueryBuilder::PARAM_INT)),
				),
			));

		return $qb->executeStatement();
	}

	public function markUserFailed(string $userId, string $error, int $now): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter('failed'))
			->set('locked_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('last_error', $qb->createNamedParameter(mb_substr($error, 0, 1000)))
			->set('last_seen_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('processing')));

		return $qb->executeStatement();
	}

	private function findByUserAndPath(string $userId, string $path): ?DirtyPath {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('path', $qb->createNamedParameter($path)))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	private function storedPath(string $path): string {
		$path = trim($path, '/');
		if (mb_strlen($path) <= 1024) {
			return $path;
		}

		return mb_substr($path, 0, 950) . '#sha256-' . hash('sha256', $path);
	}

	/**
	 * @return int[]
	 */
	private function pendingIdsForUser(string $userId, int $maxEvents, int $notAfter): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('pending')))
			->andWhere($qb->expr()->lte('last_seen_at', $qb->createNamedParameter($notAfter, IQueryBuilder::PARAM_INT)))
			->orderBy('last_seen_at', 'ASC')
			->setMaxResults(max(1, min(100000, $maxEvents)));

		return array_map('intval', $qb->executeQuery()->fetchFirstColumn());
	}

	/**
	 * @return int[]
	 */
	private function staleProcessingIds(int $olderThan, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->tableName)
			->where($qb->expr()->eq('status', $qb->createNamedParameter('processing')))
			->andWhere($qb->expr()->lte('locked_at', $qb->createNamedParameter($olderThan, IQueryBuilder::PARAM_INT)))
			->orderBy('locked_at', 'ASC')
			->setMaxResults(max(1, min(100000, $limit)));

		return array_map('intval', $qb->executeQuery()->fetchFirstColumn());
	}
}
