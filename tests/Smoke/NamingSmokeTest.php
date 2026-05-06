<?php

declare(strict_types=1);

require __DIR__ . '/../../lib/Service/PathHelper.php';
require __DIR__ . '/../../lib/Service/AlbumNameFormatter.php';

use OCA\SakuraAlbum\Service\AlbumNameFormatter;
use OCA\SakuraAlbum\Service\PathHelper;

function expectSame(mixed $expected, mixed $actual, string $label): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $label . ' failed: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
		exit(1);
	}
}

function expectThrows(callable $callable, string $label): void {
	try {
		$callable();
	} catch (InvalidArgumentException) {
		return;
	}
	fwrite(STDERR, $label . ' failed: expected InvalidArgumentException' . PHP_EOL);
	exit(1);
}

$formatter = new AlbumNameFormatter();

expectSame('Photos/2024/Trip', PathHelper::normalizeUserPath('/Photos//2024/Trip/'), 'normalize path');
expectSame('/Photos/2024', PathHelper::displayPath('Photos/2024'), 'display path');
expectSame('Trip', PathHelper::basename('/Photos/2024/Trip'), 'basename');
expectSame('2024', PathHelper::parentBasename('/Photos/2024/Trip'), 'parent basename');
expectSame('2024/Trip', PathHelper::relativePath('/Photos', '/Photos/2024/Trip'), 'relative path');
expectThrows(static fn () => PathHelper::normalizeUserPath('/Photos/../Secrets'), 'reject parent segment');

expectSame('Trip', $formatter->format('/Photos', '/Photos/2024/Trip', [
	'namingTemplate' => 'leaf',
	'separator' => ' - ',
]), 'leaf format');

expectSame('2024 - Trip', $formatter->format('/Photos', '/Photos/2024/Trip', [
	'namingTemplate' => 'parent_leaf',
	'separator' => ' - ',
]), 'parent leaf format');

expectSame('Photos - 2024 - Trip', $formatter->format('/Photos', '/Photos/2024/Trip', [
	'namingTemplate' => 'root_relative',
	'separator' => '/',
]), 'root relative format with sanitized separator');

expectSame('Album', AlbumNameFormatter::sanitizeAlbumName(' / '), 'empty album fallback');

echo 'Naming smoke tests passed.' . PHP_EOL;
