<?php

declare(strict_types=1);

use OCA\SakuraAlbum\Db\ManagedAlbumMapper;
use OCA\SakuraAlbum\Db\DirtyPathMapper;
use OCA\SakuraAlbum\Service\AccountResetService;
use OCA\SakuraAlbum\Service\AlbumExportService;
use OCA\SakuraAlbum\Service\AlbumSyncService;
use OCA\SakuraAlbum\Service\AutoSyncService;
use OCA\SakuraAlbum\Service\PhotosAlbumAdapter;
use OCA\SakuraAlbum\Service\SettingsService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Server;

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This regression test must run from CLI.\n");
	exit(1);
}

if ((getenv('SAKURAALBUM_LIVE_SMOKE') ?: '') !== '1') {
	fwrite(STDERR, "Refusing live regression test without SAKURAALBUM_LIVE_SMOKE=1.\n");
	exit(1);
}

traceStep('start');

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
traceStep('nextcloud_booted');

$userId = getenv('SAKURAALBUM_TEST_USER') ?: 'albentest';
$sourcePath = trim(getenv('SAKURAALBUM_TEST_SOURCE') ?: 'Photos/SakuraAlbum104Regression', '/');
$displaySource = '/' . $sourcePath;
$oneAlbumPath = $displaySource . '/OneAlbum';
$skipPath = $displaySource . '/SkipMe';
$depthZeroPath = $displaySource . '/DepthZero';

$settingsService = Server::get(SettingsService::class);
traceStep('settings_service_loaded');
$syncService = Server::get(AlbumSyncService::class);
$autoSyncService = Server::get(AutoSyncService::class);
$resetService = Server::get(AccountResetService::class);
$managedAlbumMapper = Server::get(ManagedAlbumMapper::class);
$dirtyPathMapper = Server::get(DirtyPathMapper::class);
$photosAlbumAdapter = Server::get(PhotosAlbumAdapter::class);
$exportService = Server::get(AlbumExportService::class);
$rootFolder = Server::get(IRootFolder::class);
traceStep('services_loaded');

$initialAdmin = $settingsService->getAdminSettings();
$summary = [
	'userId' => $userId,
	'source' => $displaySource,
	'steps' => [],
];

try {
	traceStep('test_try_started');
	resetAccountIfPossible($resetService, $userId, $summary, 'initial_reset');
	traceStep('initial_reset_complete');
	$userFolder = $rootFolder->getUserFolder($userId);
	traceStep('user_folder_loaded');
	deleteIfExists($userFolder, $sourcePath);
	traceStep('source_deleted_if_exists');
	deleteIfExists($userFolder, 'SakuraAlbum Exports');
	traceStep('exports_deleted_if_exists');

	putTinyPng(ensureFolder($userFolder, $sourcePath . '/Root'), 'root.png');
	traceStep('root_png_written');
	putTinyPng(ensureFolder($userFolder, $sourcePath . '/OneAlbum/A/B'), 'one.png');
	traceStep('one_png_written');
	putTinyPng(ensureFolder($userFolder, $sourcePath . '/SkipMe'), 'skip.png');
	traceStep('skip_png_written');
	putTinyPng(ensureFolder($userFolder, $sourcePath . '/DepthZero/C/D'), 'depth-zero.png');
	traceStep('depth_zero_png_written');
	$summary['steps'][] = 'test_files_created';
	traceStep('admin_settings_save_start');

	$settingsService->saveAdminSettings(array_merge($initialAdmin, [
		'enabled' => true,
		'defaultIncludePaths' => [$displaySource],
		'defaultExcludePatterns' => ['.nomedia', '.noimage'],
		'maxScanDepth' => 6,
		'maxPreviewFolders' => 200,
		'maxPreviewFiles' => 500,
		'maxJobFolders' => 200,
		'maxJobFiles' => 500,
		'maxAlbumsPerRun' => 50,
		'allowVideos' => false,
		'autoSyncMode' => 'file_events',
		'autoSyncDebounceSeconds' => 30,
		'autoSyncMaxUsersPerRun' => 5,
		'autoSyncMaxRuntimeSeconds' => 120,
		'autoSyncMaxEventsPerRun' => 500,
		'autoSyncWindowStart' => '',
		'autoSyncWindowEnd' => '',
		'syncRemoveMissingFiles' => true,
		'syncDeleteMissingManagedAlbums' => true,
		'debugMode' => true,
	]));
	traceStep('admin_settings_save_done');
	traceStep('user_settings_save_start');
	$settingsService->saveUserSettings($userId, [
		'enabled' => true,
		'includePaths' => [$displaySource],
		'sourceFolders' => [[
			'path' => $displaySource,
			'enabled' => true,
			'mode' => 'depth',
			'albumDepth' => 2,
		]],
		'folderRules' => [
			[
				'path' => $oneAlbumPath,
				'enabled' => true,
				'mode' => 'single_album',
				'albumDepth' => 0,
			],
			[
				'path' => $skipPath,
				'enabled' => true,
				'mode' => 'exclude',
				'albumDepth' => 0,
			],
			[
				'path' => $depthZeroPath,
				'enabled' => true,
				'mode' => 'depth',
				'albumDepth' => 0,
			],
		],
		'excludePatterns' => [],
		'namingTemplate' => 'root_relative',
		'separator' => ' - ',
		'albumDepth' => 2,
		'includeImages' => true,
		'includeVideos' => false,
		'autoSyncEnabled' => true,
	]);
	traceStep('user_settings_save_done');
	$summary['steps'][] = 'settings_saved';
	traceStep('dry_run_start');

	$dryRun = $syncService->dryRun($userId);
	traceStep('dry_run_done');
	if (($dryRun['canWrite'] ?? false) !== true) {
		throw new RuntimeException('Dry-run is not writable: ' . json_encode($dryRun['writeBlockedReasons'] ?? []));
	}
	assertNoAlbumPath($dryRun['albums'] ?? [], $skipPath, 'excluded folder must not be planned');
	$summary['dryRun'] = $dryRun['summary'];

	$queued = $autoSyncService->queueUserRefresh($userId, 'regression_104');
	if (($queued['queued'] ?? false) !== true) {
		throw new RuntimeException('Auto refresh was not queued: ' . json_encode($queued));
	}
	$dirtyPathMapper->markDirty($userId, $displaySource, 'regression_104_due', time() - 31);
	traceStep('auto_initial_process_start');
	$auto = $autoSyncService->processDueChanges();
	traceStep('auto_initial_process_done');
	assertSame(1, (int)($auto['succeededUsers'] ?? -1), 'auto sync succeeded users');
	$summary['autoInitial'] = $auto;

	$managedAlbums = $managedAlbumMapper->findActiveForUser($userId, 20);
	if (count($managedAlbums) < 2) {
		throw new RuntimeException('Expected at least two managed albums after auto sync.');
	}
	$summary['managedAfterInitial'] = count($managedAlbums);

	$deletedAlbumId = null;
	foreach ($managedAlbums as $managedAlbum) {
		if ($managedAlbum->getPhotosAlbumId() !== null) {
			$deletedAlbumId = (int)$managedAlbum->getPhotosAlbumId();
			$photosAlbumAdapter->deleteAlbum($userId, $deletedAlbumId);
			break;
		}
	}
	if ($deletedAlbumId === null) {
		throw new RuntimeException('Could not find a Photos album id to delete for missing-managed test.');
	}
	$summary['deletedPhotosAlbumId'] = $deletedAlbumId;

	$autoRepair = $autoSyncService->processDueChanges();
	if ((int)($autoRepair['missingManagedAlbumUsersQueued'] ?? 0) < 1 || (int)($autoRepair['succeededUsers'] ?? 0) < 1) {
		throw new RuntimeException('Missing managed album repair did not run: ' . json_encode($autoRepair));
	}
	$summary['autoRepair'] = $autoRepair;

	$downloadable = $exportService->listDownloadableAlbums($userId, 50);
	if (count($downloadable['albums'] ?? []) < 1) {
		throw new RuntimeException('No downloadable albums found after sync.');
	}
	$firstAlbum = $downloadable['albums'][0];
	$export = $exportService->createJob($userId, (string)$firstAlbum['sourceType'], (int)$firstAlbum['sourceId']);
	$jobId = (int)($export['job']['jobId'] ?? 0);
	if ($jobId <= 0) {
		throw new RuntimeException('Export job id missing.');
	}
	$exportService->runJob($jobId);
	$jobs = $exportService->jobsForUser($userId, 10);
	$job = findJob($jobs['jobs'] ?? [], $jobId);
	if (($job['status'] ?? '') !== 'completed' || (int)($job['partCount'] ?? 0) < 1) {
		throw new RuntimeException('Export job did not complete: ' . json_encode($job));
	}
	$part = $exportService->downloadablePart($userId, $jobId, 1);
	if (!$part['file'] instanceof File || (int)$part['file']->getSize() <= 0) {
		throw new RuntimeException('Export part is not readable or empty.');
	}
	$summary['export'] = [
		'jobId' => $jobId,
		'status' => $job['status'],
		'partCount' => $job['partCount'],
		'outputPath' => $job['outputPath'],
		'partSize' => (int)$part['file']->getSize(),
	];
	$summary['steps'][] = 'checks_completed';
	traceStep('checks_completed');
} catch (Throwable $testError) {
	$summary['testError'] = [
		'class' => $testError::class,
		'message' => $testError->getMessage(),
		'file' => $testError->getFile(),
		'line' => $testError->getLine(),
	];
	traceStep('test_error_caught');
} finally {
	traceStep('cleanup_started');
	try {
		resetAccountIfPossible($resetService, $userId, $summary, 'final_reset');
		if ((int)($summary['final_reset']['deletedDownloadJobs'] ?? 0) > 0 && (int)($summary['final_reset']['removedDownloadQueueJobs'] ?? 0) < 1) {
			throw new RuntimeException('Final reset did not remove the queued album export job.');
		}
		$userFolder = $rootFolder->getUserFolder($userId);
		deleteIfExists($userFolder, $sourcePath);
		deleteIfExists($userFolder, 'SakuraAlbum Exports');
		$settingsService->resetUserSettings($userId);
		$settingsService->saveAdminSettings($initialAdmin);
		$summary['steps'][] = 'cleanup_completed';
		traceStep('cleanup_completed');
	} catch (Throwable $cleanupError) {
		$summary['cleanupError'] = $cleanupError->getMessage();
	}
}

$resultJson = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$resultFile = getenv('SAKURAALBUM_RESULT_FILE') ?: '';
if ($resultFile !== '') {
	file_put_contents($resultFile, $resultJson);
}
echo $resultJson;
traceStep('summary_written');

function resetAccountIfPossible(AccountResetService $resetService, string $userId, array &$summary, string $label): void {
	traceStep($label . '_dry_run_start');
	$dryRun = $resetService->dryRun($userId);
	traceStep($label . '_dry_run_done');
	if (($dryRun['canReset'] ?? false) !== true) {
		$summary[$label] = [
			'skipped' => true,
			'reasons' => $dryRun['resetBlockedReasons'] ?? [],
		];
		traceStep($label . '_skipped');
		return;
	}
	traceStep($label . '_reset_start');
	$result = $resetService->reset(
		$userId,
		AccountResetService::RESET_CONFIRMATION,
		(string)$dryRun['planFingerprint'],
		(string)$dryRun['deletePlanFingerprint'],
	);
	traceStep($label . '_reset_done');
	$summary[$label] = $result['summary'] ?? [];
}

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

function deleteIfExists(Folder $userFolder, string $path): void {
	$path = trim($path, '/');
	if ($path === '' || !$userFolder->nodeExists($path)) {
		return;
	}
	$userFolder->get($path)->delete();
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

function assertNoAlbumPath(array $albums, string $forbiddenPath, string $label): void {
	foreach ($albums as $album) {
		if (str_starts_with((string)($album['targetPath'] ?? ''), $forbiddenPath)) {
			throw new RuntimeException("Unexpected {$label}: " . json_encode($album));
		}
	}
}

function findJob(array $jobs, int $jobId): array {
	foreach ($jobs as $job) {
		if ((int)($job['jobId'] ?? 0) === $jobId) {
			return $job;
		}
	}

	throw new RuntimeException("Could not find export job {$jobId}.");
}

function assertSame(int|string $expected, int|string $actual, string $label): void {
	if ($expected !== $actual) {
		throw new RuntimeException("Unexpected {$label}: expected {$expected}, got {$actual}");
	}
}

function traceStep(string $step): void {
	$traceFile = getenv('SAKURAALBUM_TRACE_FILE') ?: '';
	if ($traceFile === '') {
		return;
	}
	file_put_contents($traceFile, date('c') . ' ' . $step . "\n", FILE_APPEND);
}
