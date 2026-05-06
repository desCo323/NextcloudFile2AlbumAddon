<?php

declare(strict_types=1);

use OCA\SakuraAlbum\Service\AccountResetService;
use OCA\SakuraAlbum\Service\AlbumSyncService;
use OCA\SakuraAlbum\Service\ManagedAlbumDownloadService;
use OCA\SakuraAlbum\Service\SettingsService;
use OCA\SakuraAlbum\Db\ManagedAlbumMapper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Server;

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This smoke test must run from CLI.\n");
	exit(1);
}

if ((getenv('SAKURAALBUM_LIVE_SMOKE') ?: '') !== '1') {
	fwrite(STDERR, "Refusing live smoke test without SAKURAALBUM_LIVE_SMOKE=1.\n");
	exit(1);
}

if (!defined('OC_CONSOLE')) {
	define('OC_CONSOLE', 1);
}

$nextcloudRoot = getenv('NEXTCLOUD_ROOT') ?: '/var/www/nextcloud';
$base = rtrim($nextcloudRoot, '/') . '/lib/base.php';
if (!is_file($base)) {
	fwrite(STDERR, "Nextcloud base.php not found: {$base}\n");
	exit(1);
}

require_once $base;

$userId = getenv('SAKURAALBUM_TEST_USER') ?: 'albentest';
$sourcePath = trim(getenv('SAKURAALBUM_TEST_SOURCE') ?: 'Photos/SakuraAlbumV1Smoke', '/');
$fileName = 'sakuraalbum-smoke.png';
$displaySource = '/' . $sourcePath;

$settingsService = Server::get(SettingsService::class);
$syncService = Server::get(AlbumSyncService::class);
$downloadService = Server::get(ManagedAlbumDownloadService::class);
$resetService = Server::get(AccountResetService::class);
$managedAlbumMapper = Server::get(ManagedAlbumMapper::class);
$rootFolder = Server::get(IRootFolder::class);

$initialAdmin = $settingsService->getAdminSettings();
$summary = [
	'userId' => $userId,
	'source' => $displaySource,
	'steps' => [],
];

try {
	$userFolder = $rootFolder->getUserFolder($userId);
	$testFolder = ensureFolder($userFolder, $sourcePath);
	putTinyPng($testFolder, $fileName);
	$summary['steps'][] = 'test_file_created';

	$settingsService->saveAdminSettings(array_merge($initialAdmin, [
		'enabled' => true,
		'defaultIncludePaths' => [$displaySource],
		'defaultExcludePatterns' => ['.nomedia', '.noimage'],
		'maxScanDepth' => 4,
		'maxPreviewFolders' => 50,
		'maxPreviewFiles' => 100,
		'maxJobFolders' => 50,
		'maxJobFiles' => 100,
		'maxAlbumsPerRun' => 10,
		'allowVideos' => false,
		'autoSyncMode' => 'manual',
		'debugMode' => true,
	]));
	$settingsService->saveUserSettings($userId, [
		'enabled' => true,
		'includePaths' => [$displaySource],
		'sourceFolders' => [[
			'path' => $displaySource,
			'enabled' => true,
			'mode' => 'single_album',
			'albumDepth' => 0,
		]],
		'excludePatterns' => [],
		'namingTemplate' => 'root_relative',
		'separator' => ' - ',
		'albumDepth' => 1,
		'includeImages' => true,
		'includeVideos' => false,
		'autoSyncEnabled' => false,
	]);
	$summary['steps'][] = 'settings_prepared';

	$dryRun = $syncService->dryRun($userId);
	assertSame(1, (int)($dryRun['summary']['plannedAlbums'] ?? -1), 'planned album count');
	assertSame(1, (int)($dryRun['summary']['plannedLinks'] ?? -1), 'planned link count');
	if (($dryRun['canWrite'] ?? false) !== true) {
		throw new RuntimeException('Dry-run is not writable: ' . json_encode($dryRun['writeBlockedReasons'] ?? []));
	}
	$summary['dryRun'] = $dryRun['summary'];

	$write = $syncService->write($userId, AlbumSyncService::WRITE_CONFIRMATION, (string)$dryRun['planFingerprint']);
	assertSame(1, (int)($write['summary']['processedAlbums'] ?? -1), 'processed album count');
	assertSame(1, (int)($write['summary']['linkedFiles'] ?? -1), 'linked file count');
	$summary['write'] = $write['summary'];

	$managed = managedAlbumIdFromMapper($managedAlbumMapper, $userId, $displaySource);
	$download = $downloadService->prepare($userId, $managed);
	assertSame(1, (int)($download['fileCount'] ?? -1), 'download file count');
	$summary['download'] = [
		'managedId' => $managed,
		'fileCount' => $download['fileCount'],
		'totalBytes' => $download['totalBytes'],
	];

	$resetDryRun = $resetService->dryRun($userId);
	if (($resetDryRun['canReset'] ?? false) !== true) {
		throw new RuntimeException('Reset dry-run is blocked: ' . json_encode($resetDryRun['resetBlockedReasons'] ?? []));
	}
	$reset = $resetService->reset(
		$userId,
		AccountResetService::RESET_CONFIRMATION,
		(string)$resetDryRun['planFingerprint'],
		(string)$resetDryRun['deletePlanFingerprint'],
	);
	assertSame('account_reset_completed', (string)($reset['status'] ?? ''), 'reset status');
	$summary['reset'] = $reset['summary'];
	$summary['steps'][] = 'reset_completed';
} finally {
	try {
		$settingsService->resetUserSettings($userId);
		$settingsService->saveAdminSettings($initialAdmin);
		$userFolder = $rootFolder->getUserFolder($userId);
		$node = $userFolder->nodeExists($sourcePath) ? $userFolder->get($sourcePath) : null;
		if ($node instanceof Folder) {
			$node->delete();
		}
		$summary['steps'][] = 'cleanup_completed';
	} catch (Throwable $cleanupError) {
		$summary['cleanupError'] = $cleanupError->getMessage();
	}
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

function ensureFolder(Folder $userFolder, string $path): Folder {
	$current = $userFolder;
	foreach (explode('/', trim($path, '/')) as $part) {
		if ($part === '') {
			continue;
		}
		if (!$current->nodeExists($part)) {
			$current = $current->newFolder($part);
			continue;
		}
		$node = $current->get($part);
		if (!$node instanceof Folder) {
			throw new RuntimeException("Path segment is not a folder: {$part}");
		}
		$current = $node;
	}

	return $current;
}

function putTinyPng(Folder $folder, string $name): File {
	if ($folder->nodeExists($name)) {
		$folder->get($name)->delete();
	}
	$file = $folder->newFile($name);
	$file->putContent(base64_decode(
		'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
		true,
	));

	return $file;
}

function managedAlbumIdFromMapper(ManagedAlbumMapper $mapper, string $userId, string $sourceRoot): int {
	foreach ($mapper->findActiveForUser($userId, 20) as $album) {
		if ($album->getSourceRoot() === $sourceRoot) {
			return (int)$album->getId();
		}
	}

	throw new RuntimeException('Could not find the smoke test managed album after write.');
}

function assertSame(int|string $expected, int|string $actual, string $label): void {
	if ($expected !== $actual) {
		throw new RuntimeException("Unexpected {$label}: expected {$expected}, got {$actual}");
	}
}
