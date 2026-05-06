<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

final class PathHelper {
	public static function normalizeUserPath(string $path): string {
		$path = trim(str_replace('\\', '/', $path));
		$path = trim($path, '/');
		$parts = [];

		foreach (explode('/', $path) as $part) {
			$part = trim($part);
			if ($part === '' || $part === '.') {
				continue;
			}
			if ($part === '..' || str_contains($part, "\0")) {
				throw new \InvalidArgumentException('Invalid path segment');
			}
			$parts[] = $part;
		}

		return implode('/', $parts);
	}

	public static function displayPath(string $path): string {
		$path = self::normalizeUserPath($path);
		return $path === '' ? '/' : '/' . $path;
	}

	public static function basename(string $path): string {
		$path = self::normalizeUserPath($path);
		if ($path === '') {
			return 'Root';
		}
		$parts = explode('/', $path);
		return end($parts) ?: 'Root';
	}

	public static function parentBasename(string $path): string {
		$path = self::normalizeUserPath($path);
		$parts = explode('/', $path);
		array_pop($parts);
		if ($parts === []) {
			return '';
		}
		return end($parts) ?: '';
	}

	public static function relativePath(string $root, string $path): string {
		$root = self::normalizeUserPath($root);
		$path = self::normalizeUserPath($path);

		if ($root === '') {
			return $path;
		}
		if ($path === $root) {
			return '';
		}
		if (str_starts_with($path, $root . '/')) {
			return substr($path, strlen($root) + 1);
		}

		return $path;
	}
}
