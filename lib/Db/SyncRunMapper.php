<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<SyncRun>
 */
class SyncRunMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'sakuraalbum_runs', SyncRun::class);
	}

	public function start(string $userId, string $runType): SyncRun {
		$run = new SyncRun();
		$run->setUserId($userId);
		$run->setRunType($runType);
		$run->setStatus('running');
		$run->setStartedAt(time());
		$run->setFinishedAt(null);
		$run->setSummaryJson(null);
		$run->setErrorMessage(null);

		return $this->insert($run);
	}

	public function finish(SyncRun $run, string $status, array $summary = [], ?string $errorMessage = null): SyncRun {
		$run->setStatus($status);
		$run->setFinishedAt(time());
		$run->setSummaryJson($summary === [] ? null : json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
		$run->setErrorMessage($errorMessage !== null ? mb_substr($errorMessage, 0, 2000) : null);

		return $this->update($run);
	}

	public function updateRunningSummary(int $runId, string $userId, array $summary): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('summary_json', $qb->createNamedParameter(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($runId)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('running')));

		return $qb->executeStatement();
	}

	/**
	 * @return SyncRun[]
	 */
	public function findRecentForUser(string $userId, int $limit = 20): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('started_at', 'DESC')
			->setMaxResults(max(1, min(100, $limit)));

		return $this->findEntities($qb);
	}

	/**
	 * @return SyncRun[]
	 */
	public function findRecentFinishedForUserAndType(
		string $userId,
		string $runType,
		string $status,
		int $since,
		int $limit = 20,
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('run_type', $qb->createNamedParameter($runType)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)))
			->andWhere($qb->expr()->gte('finished_at', $qb->createNamedParameter($since)))
			->orderBy('finished_at', 'DESC')
			->setMaxResults(max(1, min(50, $limit)));

		return $this->findEntities($qb);
	}
}
