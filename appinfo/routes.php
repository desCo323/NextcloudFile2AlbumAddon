<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'adminSettings#get', 'url' => '/api/v1/admin/settings', 'verb' => 'GET'],
		['name' => 'adminSettings#update', 'url' => '/api/v1/admin/settings', 'verb' => 'PUT'],
		['name' => 'adminSettings#logs', 'url' => '/api/v1/admin/logs', 'verb' => 'GET'],
		['name' => 'adminSettings#autoSyncStatus', 'url' => '/api/v1/admin/auto-sync/status', 'verb' => 'GET'],
		['name' => 'userSettings#get', 'url' => '/api/v1/user/settings', 'verb' => 'GET'],
		['name' => 'userSettings#update', 'url' => '/api/v1/user/settings', 'verb' => 'PUT'],
		['name' => 'folder#index', 'url' => '/api/v1/folders', 'verb' => 'GET'],
		['name' => 'preview#plan', 'url' => '/api/v1/preview', 'verb' => 'POST'],
		['name' => 'sync#dryRun', 'url' => '/api/v1/sync/dry-run', 'verb' => 'POST'],
		['name' => 'sync#write', 'url' => '/api/v1/sync/write', 'verb' => 'POST'],
		['name' => 'sync#runs', 'url' => '/api/v1/sync/runs', 'verb' => 'GET'],
		['name' => 'sync#status', 'url' => '/api/v1/sync/status', 'verb' => 'GET'],
		['name' => 'sync#queueUpdate', 'url' => '/api/v1/sync/queue-update', 'verb' => 'POST'],
		['name' => 'managedAlbum#index', 'url' => '/api/v1/albums/managed', 'verb' => 'GET'],
		['name' => 'managedAlbum#dryRunDelete', 'url' => '/api/v1/albums/delete/dry-run', 'verb' => 'POST'],
		['name' => 'managedAlbum#delete', 'url' => '/api/v1/albums/delete', 'verb' => 'POST'],
	],
];
