<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\BackgroundJob;

use OCA\SakuraAlbum\Service\AutoSyncService;
use OCA\SakuraAlbum\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class AutoSyncJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly AutoSyncService $autoSyncService,
		private readonly SettingsService $settingsService,
	) {
		parent::__construct($time);
		$this->setAllowParallelRuns(false);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
		$this->setInterval(max(60, $this->settingsService->getAdminSettings()['jobIntervalMinutes'] * 60));
	}

	protected function run($argument): void {
		$this->autoSyncService->processDueChanges();
	}
}
