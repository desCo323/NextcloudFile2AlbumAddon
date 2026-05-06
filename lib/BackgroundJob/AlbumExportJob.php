<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\BackgroundJob;

use OCA\SakuraAlbum\Service\AlbumExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

class AlbumExportJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly AlbumExportService $albumExportService,
	) {
		parent::__construct($time);
		$this->setAllowParallelRuns(false);
	}

	protected function run($argument): void {
		$jobId = is_array($argument) && isset($argument['jobId']) ? (int)$argument['jobId'] : 0;
		if ($jobId <= 0) {
			return;
		}

		$this->albumExportService->runJob($jobId);
	}
}
