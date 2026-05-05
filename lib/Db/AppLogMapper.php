<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<AppLog>
 */
class AppLogMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'sakuraalbum_logs', AppLog::class);
	}

	/**
	 * @return AppLog[]
	 */
	public function findRecent(int $limit = 100, ?string $level = null, ?string $userId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->orderBy('created_at', 'DESC')
			->setMaxResults(max(1, min(500, $limit)));

		if ($level !== null && $level !== '') {
			$qb->andWhere($qb->expr()->eq('level', $qb->createNamedParameter($level)));
		}
		if ($userId !== null && $userId !== '') {
			$qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		}

		return $this->findEntities($qb);
	}

	public function deleteOlderThan(int $timestamp): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->lt('created_at', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)));

		return $qb->executeStatement();
	}
}
