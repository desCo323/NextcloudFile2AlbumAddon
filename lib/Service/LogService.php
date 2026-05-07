<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Db\AppLog;
use OCA\SakuraAlbum\Db\AppLogMapper;
use OCP\AppFramework\Services\IAppConfig;
use Psr\Log\LoggerInterface;

class LogService {
	private const LOG_RETENTION_CHECK_INTERVAL_SECONDS = 3600;

	private const SENSITIVE_KEYS = [
		'password',
		'passwd',
		'app_password',
		'apppassword',
		'token',
		'requesttoken',
		'authorization',
		'auth',
		'secret',
		'credential',
		'credentials',
		'cookie',
		'cookies',
	];

	private const SENSITIVE_STRING_PATTERNS = [
		'/ghp_[A-Za-z0-9_]{20,}/' => '[redacted-github-token]',
		'/github_pat_[A-Za-z0-9_]{20,}/' => '[redacted-github-token]',
		'/Bearer\s+[A-Za-z0-9._~+\/=-]{16,}/i' => 'Bearer [redacted]',
		'/Basic\s+[A-Za-z0-9+\/=]{16,}/i' => 'Basic [redacted]',
	];

	public function __construct(
		private readonly AppLogMapper $logMapper,
		private readonly IAppConfig $appConfig,
		private readonly LoggerInterface $serverLogger,
	) {
	}

	public function debug(string $event, ?string $userId = null, array $context = [], string $message = '', ?int $runId = null): void {
		if (!$this->isDebugMode()) {
			return;
		}
		$this->write('debug', $event, $userId, $context, $message, $runId);
	}

	public function success(string $event, ?string $userId = null, array $context = [], string $message = '', ?int $runId = null): void {
		$this->write('success', $event, $userId, $context, $message, $runId);
	}

	public function info(string $event, ?string $userId = null, array $context = [], string $message = '', ?int $runId = null): void {
		$this->write('info', $event, $userId, $context, $message, $runId);
	}

	public function warning(string $event, ?string $userId = null, array $context = [], string $message = '', ?int $runId = null): void {
		$this->write('warning', $event, $userId, $context, $message, $runId);
	}

	public function error(string $event, ?string $userId = null, array $context = [], string $message = '', ?int $runId = null): void {
		$this->write('error', $event, $userId, $context, $message, $runId);
	}

	public function exception(string $event, \Throwable $e, ?string $userId = null, array $context = [], ?int $runId = null): void {
		$context['exception'] = [
			'class' => $e::class,
			'code' => $e->getCode(),
			'message' => $e->getMessage(),
		];
			if ($this->isDebugMode()) {
				$context['exception']['file'] = $e->getFile();
				$context['exception']['line'] = $e->getLine();
				$context['exception']['trace'] = $this->safeTrace($e);
			}

		$this->error($event, $userId, $context, $e->getMessage(), $runId);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function recentLogs(int $limit = 100, ?string $level = null, ?string $userId = null): array {
		return array_map(
			static fn (AppLog $log): array => [
				'id' => $log->getId(),
				'level' => $log->getLevel(),
				'event' => $log->getEvent(),
				'userId' => $log->getUserId(),
				'runId' => $log->getRunId(),
				'message' => $log->getMessage(),
				'context' => $log->getContextJson() !== null ? json_decode($log->getContextJson(), true) : null,
				'createdAt' => $log->getCreatedAt(),
			],
			$this->logMapper->findRecent($limit, $level, $userId),
		);
	}

	private function write(string $level, string $event, ?string $userId, array $context, string $message, ?int $runId): void {
		$debug = $this->isDebugMode();
		$level = $this->normalizeLevel($level);
		$event = mb_substr(preg_replace('/[^a-zA-Z0-9_.:-]+/', '_', $event) ?? 'unknown', 0, 96);
		$message = mb_substr($this->sanitizeString($message), 0, 512);
		$context = $this->sanitizeContext($context, 0);

		if (!$debug && in_array($level, ['success', 'info'], true)) {
			$context = $this->compactContext($context);
		}

		$contextJson = $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
		if ($contextJson !== null) {
			$contextJson = mb_substr($contextJson, 0, $this->maxContextLength());
		}

		try {
			$entry = new AppLog();
			$entry->setLevel($level);
			$entry->setEvent($event);
			$entry->setUserId($userId);
			$entry->setRunId($runId);
			$entry->setMessage($message);
			$entry->setContextJson($contextJson);
			$entry->setCreatedAt(time());
			$this->logMapper->insert($entry);
		} catch (\Throwable $e) {
			$this->serverLogger->error('SakuraAlbum failed to write app log', [
				'app' => Application::APP_ID,
				'event' => $event,
				'exception' => $e,
			]);
		}
		try {
			$this->cleanupOldLogsIfDue();
		} catch (\Throwable $e) {
			$this->serverLogger->warning('SakuraAlbum failed to prune old app logs', [
				'app' => Application::APP_ID,
				'exception' => $e,
			]);
		}

		if (in_array($level, ['warning', 'error'], true)) {
			$this->serverLogger->{$level}('SakuraAlbum ' . $event . ($message !== '' ? ': ' . $message : ''), [
				'app' => Application::APP_ID,
				'userId' => $userId,
				'runId' => $runId,
			]);
		}
	}

	private function isDebugMode(): bool {
		return $this->appConfig->getAppValueBool('debugMode', false);
	}

	private function maxContextLength(): int {
		return max(1000, min(100000, $this->appConfig->getAppValueInt('debugMaxContextLength', 8000)));
	}

	private function cleanupOldLogsIfDue(): void {
		$now = time();
		$lastCleanupAt = $this->appConfig->getAppValueInt('lastLogRetentionAt', 0);
		if ($lastCleanupAt > $now - self::LOG_RETENTION_CHECK_INTERVAL_SECONDS) {
			return;
		}

		$this->appConfig->setAppValueInt('lastLogRetentionAt', $now);
		$retentionDays = max(1, min(365, $this->appConfig->getAppValueInt('debugRetentionDays', 14)));
		$this->logMapper->deleteOlderThan($now - ($retentionDays * 86400));
	}

	private function normalizeLevel(string $level): string {
		return in_array($level, ['debug', 'info', 'success', 'warning', 'error'], true) ? $level : 'info';
	}

	private function sanitizeContext(mixed $value, int $depth): mixed {
		if ($depth > 8) {
			return '[max-depth]';
		}
		if (is_array($value)) {
			$result = [];
			foreach ($value as $key => $item) {
				$keyString = is_scalar($key) ? (string)$key : 'key';
				if ($this->isSensitiveKey($keyString)) {
					$result[$keyString] = '[redacted]';
					continue;
				}
				$result[$keyString] = $this->sanitizeContext($item, $depth + 1);
			}
			return $result;
		}
		if ($value instanceof \Throwable) {
			return [
				'class' => $value::class,
				'message' => $value->getMessage(),
			];
		}
		if (is_object($value)) {
			return [
				'class' => $value::class,
			];
		}
		if (is_string($value)) {
			return mb_substr($this->sanitizeString($value), 0, 2000);
		}
		if (is_scalar($value) || $value === null) {
			return $value;
		}

		return '[' . gettype($value) . ']';
	}

	private function compactContext(array $context): array {
		$allowed = ['summary', 'count', 'counts', 'durationMs', 'warningCount', 'errorCode'];
		return array_intersect_key($context, array_flip($allowed));
	}

	private function isSensitiveKey(string $key): bool {
		$key = strtolower($key);
		foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
			if (str_contains($key, $sensitiveKey)) {
				return true;
			}
		}

		return false;
	}

	private function sanitizeString(string $value): string {
		$value = str_replace("\0", '', $value);
		foreach (self::SENSITIVE_STRING_PATTERNS as $pattern => $replacement) {
			$value = preg_replace($pattern, $replacement, $value) ?? $value;
		}

		return $value;
	}

	private function safeTrace(\Throwable $e): array {
		return array_map(
			static fn (array $frame): array => array_filter([
				'file' => isset($frame['file']) && is_string($frame['file']) ? $frame['file'] : null,
				'line' => isset($frame['line']) && is_int($frame['line']) ? $frame['line'] : null,
				'class' => isset($frame['class']) && is_string($frame['class']) ? $frame['class'] : null,
				'type' => isset($frame['type']) && is_string($frame['type']) ? $frame['type'] : null,
				'function' => isset($frame['function']) && is_string($frame['function']) ? $frame['function'] : null,
			], static fn (mixed $value): bool => $value !== null),
			array_slice($e->getTrace(), 0, 12),
		);
	}
}
