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

	public function countByStatusForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('status')
			->selectAlias($qb->func()->count('*'), 'row_count')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->groupBy('status');

		$counts = [
			'pending' => 0,
			'processing' => 0,
			'failed' => 0,
		];
		foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
			$status = (string)($row['status'] ?? '');
			if ($status !== '') {
				$counts[$status] = (int)($row['row_count'] ?? 0);
			}
		}

		$counts['total'] = array_sum($counts);
		return $counts;
	}

	public function countDueUsers(int $notAfter): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')
			->from($this->tableName)
			->where($qb->expr()->eq('status', $qb->createNamedParameter('pending')))
			->andWhere($qb->expr()->lte('last_seen_at', $qb->createNamedParameter($notAfter, IQueryBuilder::PARAM_INT)))
			->groupBy('user_id');

		return count($qb->executeQuery()->fetchFirstColumn());
	}

	public function countAllByStatus(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('status')
			->selectAlias($qb->func()->count('*'), 'row_count')
			->from($this->tableName)
			->groupBy('status');

		$counts = [
			'pending' => 0,
			'processing' => 0,
			'failed' => 0,
		];
		foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
			$status = (string)($row['status'] ?? '');
			if ($status !== '') {
				$counts[$status] = (int)($row['row_count'] ?? 0);
			}
		}

		$counts['total'] = array_sum($counts);
		return $counts;
	}

	public function oldestPendingAt(): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->min('last_seen_at'), 'oldest_pending_at')
			->from($this->tableName)
			->where($qb->expr()->eq('status', $qb->createNamedParameter('pending')));

		$value = $qb->executeQuery()->fetchOne();
		return $value !== false && $value !== null ? (int)$value : null;
	}

	public function oldestPendingAtForUser(string $userId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->min('last_seen_at'), 'oldest_pending_at')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('pending')));

		$value = $qb->executeQuery()->fetchOne();
		return $value !== false && $value !== null ? (int)$value : null;
	}

	public function nextPendingDueAt(int $debounceSeconds): ?int {
		$oldest = $this->oldestPendingAt();
		return $oldest !== null ? $oldest + max(30, $debounceSeconds) : null;
	}

	public function nextPendingDueAtForUser(string $userId, int $debounceSeconds): ?int {
		$oldest = $this->oldestPendingAtForUser($userId);
		return $oldest !== null ? $oldest + max(30, $debounceSeconds) : null;
	}

	public function findQueueSamples(int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'path', 'event_type', 'status', 'change_count', 'attempts', 'first_seen_at', 'last_seen_at', 'locked_at', 'last_error')
			->from($this->tableName)
			->orderBy('status', 'ASC')
			->addOrderBy('last_seen_at', 'ASC')
			->setMaxResults(max(1, min(100, $limit)));

		return array_map(static fn (array $row): array => [
			'userId' => (string)($row['user_id'] ?? ''),
			'path' => (string)($row['path'] ?? ''),
			'eventType' => (string)($row['event_type'] ?? ''),
			'status' => (string)($row['status'] ?? ''),
			'changeCount' => (int)($row['change_count'] ?? 0),
			'attempts' => (int)($row['attempts'] ?? 0),
			'firstSeenAt' => (int)($row['first_seen_at'] ?? 0),
			'lastSeenAt' => (int)($row['last_seen_at'] ?? 0),
			'lockedAt' => $row['locked_at'] !== null ? (int)$row['locked_at'] : null,
			'lastError' => $row['last_error'] !== null ? mb_substr((string)$row['last_error'], 0, 240) : null,
		], $qb->executeQuery()->fetchAllAssociative());
	}

	public function findQueueSamplesForUser(string $userId, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'path', 'event_type', 'status', 'change_count', 'attempts', 'first_seen_at', 'last_seen_at', 'locked_at', 'last_error')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('status', 'ASC')
			->addOrderBy('last_seen_at', 'ASC')
			->setMaxResults(max(1, min(50, $limit)));

		return array_map(static fn (array $row): array => [
			'userId' => (string)($row['user_id'] ?? ''),
			'path' => (string)($row['path'] ?? ''),
			'eventType' => (string)($row['event_type'] ?? ''),
			'status' => (string)($row['status'] ?? ''),
			'changeCount' => (int)($row['change_count'] ?? 0),
			'attempts' => (int)($row['attempts'] ?? 0),
			'firstSeenAt' => (int)($row['first_seen_at'] ?? 0),
			'lastSeenAt' => (int)($row['last_seen_at'] ?? 0),
			'lockedAt' => $row['locked_at'] !== null ? (int)$row['locked_at'] : null,
			'lastError' => $row['last_error'] !== null ? mb_substr((string)$row['last_error'], 0, 240) : null,
		], $qb->executeQuery()->fetchAllAssociative());
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

	public function deleteForUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

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

	public function markUserRetry(string $userId, int $retryAt, string $error): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter('pending'))
			->set('locked_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('attempts', $qb->func()->add('attempts', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->set('last_seen_at', $qb->createNamedParameter($retryAt, IQueryBuilder::PARAM_INT))
			->set('last_error', $qb->createNamedParameter(mb_substr($error, 0, 1000)))
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['processing', 'failed'], IQueryBuilder::PARAM_STR_ARRAY)));

		return $qb->executeStatement();
	}

	public function requeueFailedForFolderUnavailable(int $maxAttempts, int $retryAt): int {
		$token = 'user_folder_unavailable';
		$blockedMessage = 'Chunked automatic album sync is blocked because the current chunk is not safe to write.';
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('user_id')
			->from($this->tableName)
			->where($qb->expr()->eq('status', $qb->createNamedParameter('failed')))
			->andWhere($qb->expr()->lt('attempts', $qb->createNamedParameter($maxAttempts, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->like('last_error', $qb->createNamedParameter('%' . $token . '%')),
				$qb->expr()->like('last_error', $qb->createNamedParameter('%auto_chunk_plan_not_safe:%')),
				$qb->expr()->like('last_error', $qb->createNamedParameter('%' . $blockedMessage . '%')),
			));
		$users = array_map('strval', $qb->executeQuery()->fetchFirstColumn());

		$requeued = 0;
		foreach ($users as $userId) {
			if ($userId === '') {
				continue;
			}
			$requeued += $this->markUserRetry($userId, $retryAt, 'Retrying automatic sync after transient folder-unavailable condition.');
		}

		return $requeued;
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
		if ($path === '') {
			return '/';
		}
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
