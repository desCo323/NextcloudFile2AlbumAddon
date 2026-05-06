<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

class SyncSafetyException extends \RuntimeException {
	public function __construct(
		private readonly string $errorCode,
		string $message,
		private readonly int $httpStatus = 400,
		private readonly array $details = [],
	) {
		parent::__construct($message);
	}

	public function getErrorCode(): string {
		return $this->errorCode;
	}

	public function getHttpStatus(): int {
		return $this->httpStatus;
	}

	public function getDetails(): array {
		return $this->details;
	}
}
