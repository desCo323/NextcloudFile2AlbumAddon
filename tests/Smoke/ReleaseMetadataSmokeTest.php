<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function fail(string $message): never {
	fwrite(STDERR, $message . PHP_EOL);
	exit(1);
}

function requireFile(string $root, string $path): string {
	$file = $root . '/' . $path;
	if (!is_file($file)) {
		fail("Missing required file: {$path}");
	}

	return $file;
}

function requireContains(string $root, string $path, string $needle): void {
	$content = file_get_contents(requireFile($root, $path));
	if ($content === false || !str_contains($content, $needle)) {
		fail("Expected {$path} to contain {$needle}");
	}
}

$info = simplexml_load_file(requireFile($root, 'appinfo/info.xml'));
if (!$info) {
	fail('Could not read appinfo/info.xml');
}

$appId = (string)$info->id;
$version = (string)$info->version;
if ($appId !== 'sakuraalbum') {
	fail('App id must be sakuraalbum');
}
if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(-[0-9A-Za-z.-]+)?$/', $version)) {
	fail('App version must be semver without build metadata');
}
if (stripos((string)$info->name, 'nextcloud') !== false) {
	fail('App name must not contain Nextcloud');
}
if (mb_strlen((string)$info->summary) > 128) {
	fail('App summary exceeds schema length');
}
if ((string)$info->licence !== 'AGPL-3.0-or-later') {
	fail('App license must be AGPL-3.0-or-later');
}
if ((string)$info->repository === '' || (string)$info->bugs === '' || (string)$info->website === '') {
	fail('Repository, bugs, and website metadata are required');
}
if ((string)$info->documentation->user === '' || (string)$info->documentation->admin === '' || (string)$info->documentation->developer === '') {
	fail('Documentation links are required for release metadata');
}
if ((string)$info->{'background-jobs'}->job !== 'OCA\SakuraAlbum\BackgroundJob\AutoSyncJob') {
	fail('Background job metadata is missing');
}

$package = json_decode(file_get_contents(requireFile($root, 'package.json')) ?: '', true);
if (!is_array($package) || ($package['version'] ?? '') !== $version) {
	fail('package.json version must match appinfo/info.xml');
}

foreach ([
	'README.md',
	'SECURITY.md',
	'CHANGELOG.md',
	'CHANGELOG.en.md',
	'docs/USER_GUIDE.md',
	'docs/ADMIN_GUIDE.md',
	'docs/DEVELOPER_NOTES.md',
	'docs/PRIVACY.md',
	'docs/STORE_RELEASE_CHECKLIST.md',
] as $path) {
	requireFile($root, $path);
}

requireContains($root, 'CHANGELOG.md', "## {$version}");
requireContains($root, 'CHANGELOG.en.md', "## {$version}");
requireContains($root, 'docs/STORE_RELEASE_CHECKLIST.md', 'PhotosAlbumAdapter');
requireContains($root, 'docs/PRIVACY.md', 'Future mail sending');

$suffix = str_replace('.', '', $version);
requireContains($root, 'lib/Settings/Admin.php', "admin-settings-{$suffix}");
requireContains($root, 'lib/Settings/Personal.php', "personal-settings-{$suffix}");
requireFile($root, "js/admin-settings-{$suffix}.js");
requireFile($root, "js/personal-settings-{$suffix}.js");
