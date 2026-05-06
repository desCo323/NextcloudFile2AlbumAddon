<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<SyncCursor>
 */
class SyncCursorMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'sakuraalbum_sync_cursors', SyncCursor::class);
	}

	public function findForUserConfig(string $userId, string $configHash): ?SyncCursor {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('config_hash', $qb->createNamedParameter($configHash)))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * @return SyncCursor[]
	 */
	public function findRecentForUser(string $userId, int $limit = 5): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('updated_at', 'DESC')
			->setMaxResults(max(1, min(20, $limit)));

		return $this->findEntities($qb);
	}

	public function findOrCreate(string $userId, string $configHash, int $now): SyncCursor {
		$existing = $this->findForUserConfig($userId, $configHash);
		if ($existing !== null) {
			if ($existing->getStatus() === 'completed') {
				$existing->setStatus('pending');
				$existing->setCursorPath(null);
				$existing->setProcessedFiles(0);
				$existing->setProcessedAlbums(0);
				$existing->setChunkCount(0);
				$existing->setAttempts(0);
				$existing->setCompletedAt(null);
				$existing->setSummaryJson(null);
				$existing->setLastError(null);
			}
			$existing->setLockedAt($now);
			$existing->setUpdatedAt($now);
			return $this->update($existing);
		}

		$cursor = new SyncCursor();
		$cursor->setUserId($userId);
		$cursor->setConfigHash($configHash);
		$cursor->setStatus('pending');
		$cursor->setCursorPath(null);
		$cursor->setProcessedFiles(0);
		$cursor->setProcessedAlbums(0);
		$cursor->setChunkCount(0);
		$cursor->setAttempts(0);
		$cursor->setLockedAt($now);
		$cursor->setCreatedAt($now);
		$cursor->setUpdatedAt($now);
		$cursor->setCompletedAt(null);
		$cursor->setSummaryJson(null);
		$cursor->setLastError(null);

		return $this->insert($cursor);
	}

	public function markChunkResult(SyncCursor $cursor, string $status, ?string $cursorPath, array $summary, ?string $lastError = null): SyncCursor {
		$now = time();
		$cursor->setStatus($status);
		$cursor->setCursorPath($cursorPath !== null && $cursorPath !== '' ? $this->storedPath($cursorPath) : null);
		$cursor->setProcessedFiles($cursor->getProcessedFiles() + (int)($summary['processedLinks'] ?? 0));
		$cursor->setProcessedAlbums($cursor->getProcessedAlbums() + (int)($summary['processedAlbums'] ?? 0));
		$cursor->setChunkCount($cursor->getChunkCount() + 1);
		$cursor->setAttempts($status === 'failed' ? $cursor->getAttempts() + 1 : 0);
		$cursor->setLockedAt(null);
		$cursor->setUpdatedAt($now);
		$cursor->setCompletedAt($status === 'completed' ? $now : null);
		$cursor->setSummaryJson(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
		$cursor->setLastError($lastError !== null ? mb_substr($lastError, 0, 1000) : null);

		return $this->update($cursor);
	}

	public function deleteForUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $qb->executeStatement();
	}

	private function storedPath(string $path): string {
		$path = trim($path, '/');
		if (mb_strlen($path) <= 1024) {
			return $path;
		}

		return mb_substr($path, 0, 950) . '#sha256-' . hash('sha256', $path);
	}
}
