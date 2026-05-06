<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\AppInfo;

use OCA\SakuraAlbum\BackgroundJob\AutoSyncJob;
use OCA\SakuraAlbum\Listener\FileChangeListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'sakuraalbum';
	public const NAMING_SCHEMA_VERSION = 1;

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(NodeCreatedEvent::class, FileChangeListener::class);
		$context->registerEventListener(NodeWrittenEvent::class, FileChangeListener::class);
		$context->registerEventListener(NodeDeletedEvent::class, FileChangeListener::class);
		$context->registerEventListener(NodeRenamedEvent::class, FileChangeListener::class);
	}

	public function boot(IBootContext $context): void {
		$jobList = $context->getAppContainer()->get(IJobList::class);
		if (!$jobList->has(AutoSyncJob::class, null)) {
			$jobList->add(AutoSyncJob::class);
		}
	}
}
