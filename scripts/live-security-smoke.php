<?php

declare(strict_types=1);

use OCA\SakuraAlbum\Db\ManagedAlbumMapper;
use OCA\SakuraAlbum\Service\AccountResetService;
use OCA\SakuraAlbum\Service\AlbumExportService;
use OCA\SakuraAlbum\Service\AlbumSyncService;
use OCA\SakuraAlbum\Service\DiagnosticCsvExportService;
use OCA\SakuraAlbum\Service\LogService;
use OCA\SakuraAlbum\Service\PathHelper;
use OCA\SakuraAlbum\Service\SettingsService;
use OCA\SakuraAlbum\Service\SyncSafetyException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Server;

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This security smoke test must run from CLI.\n");
	exit(1);
}

if ((getenv('SAKURAALBUM_LIVE_SECURITY_SMOKE') ?: '') !== '1') {
	fwrite(STDERR, "Refusing live security smoke test without SAKURAALBUM_LIVE_SECURITY_SMOKE=1.\n");
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
$sourcePath = trim(getenv('SAKURAALBUM_TEST_SOURCE') ?: 'Photos/SakuraAlbumSecuritySmoke', '/');
$displaySource = '/' . $sourcePath;
$outputPath = null;

$settingsService = Server::get(SettingsService::class);
$syncService = Server::get(AlbumSyncService::class);
$exportService = Server::get(AlbumExportService::class);
$resetService = Server::get(AccountResetService::class);
$diagnosticCsvExportService = Server::get(DiagnosticCsvExportService::class);
$logService = Server::get(LogService::class);
$managedAlbumMapper = Server::get(ManagedAlbumMapper::class);
$rootFolder = Server::get(IRootFolder::class);

$initialAdmin = $settingsService->getAdminSettings();
$summary = [
	'userId' => $userId,
	'source' => $displaySource,
	'steps' => [],
];

try {
	resetAccountIfPossible($resetService, $userId, $summary, 'initial_reset');
	$userFolder = $rootFolder->getUserFolder($userId);
	deleteIfExists($userFolder, $sourcePath);

	assertInvalidPath('../config.php', 'traversal');
	assertInvalidPath("Photos/\0secret", 'nul-byte');
	assertInvalidPath("Photos/\nsecret", 'control-character');
	assertInvalidPath('Photos/' . str_repeat('a', 256), 'long-segment');
	assertInvalidPath(implode('/', array_fill(0, 65, 'x')), 'too-many-segments');
	assertInvalidPath(str_repeat('a', 2049), 'long-path');
	$summary['steps'][] = 'path_hardening_checked';

	$folder = ensureFolder($userFolder, $sourcePath);
	putTinyPng($folder, 'one.png');
	putTinyPng($folder, 'two.png');
	$summary['steps'][] = 'test_files_created';

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
		'maxExportFiles' => 1,
		'maxExportBytes' => 1048576,
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
	$summary['steps'][] = 'settings_saved';

	$dryRun = $syncService->dryRun($userId);
	assertSame(1, (int)($dryRun['summary']['plannedAlbums'] ?? -1), 'planned album count');
	assertSame(2, (int)($dryRun['summary']['plannedLinks'] ?? -1), 'planned link count');
	if (($dryRun['canWrite'] ?? false) !== true) {
		throw new RuntimeException('Dry-run is not writable: ' . json_encode($dryRun['writeBlockedReasons'] ?? []));
	}

	$write = $syncService->write($userId, AlbumSyncService::WRITE_CONFIRMATION, (string)$dryRun['planFingerprint']);
	assertSame(2, (int)($write['summary']['linkedFiles'] ?? -1), 'linked file count');
	$managedId = managedAlbumIdFromMapper($managedAlbumMapper, $userId, $displaySource);
	$summary['write'] = $write['summary'];
	$summary['managedId'] = $managedId;

	try {
		$exportService->createJob($userId, AlbumExportService::SOURCE_MANAGED, $managedId);
		throw new RuntimeException('Expected background export limit to block the job.');
	} catch (SyncSafetyException $e) {
		if ($e->getErrorCode() !== 'album_export_limit_exceeded'
			|| ($e->getDetails()['reason'] ?? '') !== 'max_export_files_exceeded') {
			throw $e;
		}
		$summary['exportLimitBlocked'] = $e->getDetails();
	}

	$settingsService->saveAdminSettings(array_merge($settingsService->getAdminSettings(), [
		'maxExportFiles' => 10,
		'maxExportBytes' => 1048576,
	]));
	$export = $exportService->createJob($userId, AlbumExportService::SOURCE_MANAGED, $managedId);
	$jobId = (int)($export['job']['jobId'] ?? 0);
	if ($jobId <= 0) {
		throw new RuntimeException('Export job id missing.');
	}
	$exportService->runJob($jobId);
	$job = findJob($exportService->jobsForUser($userId, 10)['jobs'] ?? [], $jobId);
	if (($job['status'] ?? '') !== 'completed' || (int)($job['partCount'] ?? 0) < 1) {
		throw new RuntimeException('Export job did not complete: ' . json_encode($job));
	}
	$outputPath = (string)($job['outputPath'] ?? '');
	$part = $exportService->downloadablePart($userId, $jobId, 1);
	if (!$part['file'] instanceof File || (int)$part['file']->getSize() <= 0) {
		throw new RuntimeException('Export part is not readable or empty.');
	}
	$summary['exportAllowed'] = [
		'jobId' => $jobId,
		'partCount' => $job['partCount'],
		'outputPath' => $outputPath,
		'partSize' => (int)$part['file']->getSize(),
	];

	$logService->info('security_smoke_formula_cell', $userId, [], '=1+1');
	$csv = $diagnosticCsvExportService->userCsv($userId, 50);
	if (($csv['bytes'] ?? 0) <= 0 || !str_contains((string)$csv['csv'], "'=1+1")) {
		throw new RuntimeException('Diagnostic CSV did not contain formula-safe log message.');
	}
	$summary['diagnosticCsv'] = [
		'filename' => $csv['filename'] ?? '',
		'bytes' => $csv['bytes'] ?? 0,
		'storage' => $csv['storage'] ?? '',
	];
	$summary['steps'][] = 'security_checks_completed';
} finally {
	try {
		resetAccountIfPossible($resetService, $userId, $summary, 'final_reset');
		$userFolder = $rootFolder->getUserFolder($userId);
		deleteIfExists($userFolder, $sourcePath);
		if (is_string($outputPath) && $outputPath !== '') {
			deleteIfExists($userFolder, $outputPath);
		}
		$settingsService->resetUserSettings($userId);
		$settingsService->saveAdminSettings($initialAdmin);
		$summary['steps'][] = 'cleanup_completed';
	} catch (Throwable $cleanupError) {
		$summary['cleanupError'] = $cleanupError->getMessage();
	}
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

function assertInvalidPath(string $path, string $label): void {
	try {
		PathHelper::normalizeUserPath($path);
	} catch (InvalidArgumentException) {
		return;
	}

	throw new RuntimeException("Expected invalid path to be rejected: {$label}");
}

function resetAccountIfPossible(AccountResetService $resetService, string $userId, array &$summary, string $label): void {
	$dryRun = $resetService->dryRun($userId);
	if (($dryRun['canReset'] ?? false) !== true) {
		$summary[$label] = [
			'skipped' => true,
			'reasons' => $dryRun['resetBlockedReasons'] ?? [],
		];
		return;
	}
	$result = $resetService->reset(
		$userId,
		AccountResetService::RESET_CONFIRMATION,
		(string)$dryRun['planFingerprint'],
		(string)$dryRun['deletePlanFingerprint'],
	);
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

function managedAlbumIdFromMapper(ManagedAlbumMapper $mapper, string $userId, string $sourceRoot): int {
	foreach ($mapper->findActiveForUser($userId, 20) as $album) {
		if ($album->getSourceRoot() === $sourceRoot) {
			return (int)$album->getId();
		}
	}

	throw new RuntimeException('Could not find the security smoke managed album after write.');
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
