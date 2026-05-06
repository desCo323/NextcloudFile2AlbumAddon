<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Listener;

use OCA\SakuraAlbum\Service\AutoSyncService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;

/**
 * @template-implements IEventListener<NodeCreatedEvent|NodeWrittenEvent|NodeDeletedEvent|NodeRenamedEvent>
 */
class FileChangeListener implements IEventListener {
	public function __construct(
		private readonly AutoSyncService $autoSyncService,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof NodeCreatedEvent) {
			$this->autoSyncService->recordNodeChange($event->getNode(), 'created');
			return;
		}
		if ($event instanceof NodeWrittenEvent) {
			$this->autoSyncService->recordNodeChange($event->getNode(), 'written');
			return;
		}
		if ($event instanceof NodeDeletedEvent) {
			$this->autoSyncService->recordNodeChange($event->getNode(), 'deleted');
			return;
		}
		if ($event instanceof NodeRenamedEvent) {
			$this->autoSyncService->recordNodeChange($event->getSource(), 'renamed_source');
			$this->autoSyncService->recordNodeChange($event->getTarget(), 'renamed_target');
		}
	}
}
