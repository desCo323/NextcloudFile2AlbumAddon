<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/Service/PathHelper.php';

use OCA\SakuraAlbum\Service\PathHelper;

function securityFail(string $message): never {
	fwrite(STDERR, $message . PHP_EOL);
	exit(1);
}

function assertInvalidPath(string $path, string $label): void {
	try {
		PathHelper::normalizeUserPath($path);
	} catch (\InvalidArgumentException) {
		return;
	}

	securityFail("Expected invalid path to be rejected: {$label}");
}

if (PathHelper::displayPath('Photos//SakuraAlbum') !== '/Photos/SakuraAlbum') {
	securityFail('Normal user paths must still normalize predictably.');
}

assertInvalidPath('../config.php', 'traversal');
assertInvalidPath("Photos/\0secret", 'nul-byte');
assertInvalidPath("Photos/\nsecret", 'control-character');
assertInvalidPath('Photos/' . str_repeat('a', 256), 'long-segment');
assertInvalidPath(implode('/', array_fill(0, 65, 'x')), 'too-many-segments');
assertInvalidPath(str_repeat('a', 2049), 'long-path');
