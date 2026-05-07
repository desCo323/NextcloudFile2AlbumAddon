<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

final class PathHelper {
	private const MAX_PATH_BYTES = 2048;
	private const MAX_PATH_SEGMENTS = 64;
	private const MAX_SEGMENT_BYTES = 255;

	public static function normalizeUserPath(string $path): string {
		$path = str_replace('\\', '/', $path);
		if (strlen($path) > self::MAX_PATH_BYTES) {
			throw new \InvalidArgumentException('Path is too long');
		}
		if (str_contains($path, "\0") || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
			throw new \InvalidArgumentException('Invalid control character in path');
		}
		$path = trim($path);
		$path = trim($path, '/');
		$parts = [];

		foreach (explode('/', $path) as $rawPart) {
			$part = trim($rawPart);
			if ($part === '' || $part === '.') {
				continue;
			}
			if ($part === '..') {
				throw new \InvalidArgumentException('Invalid path segment');
			}
			if (strlen($part) > self::MAX_SEGMENT_BYTES) {
				throw new \InvalidArgumentException('Path segment is too long');
			}
			$parts[] = $part;
			if (count($parts) > self::MAX_PATH_SEGMENTS) {
				throw new \InvalidArgumentException('Path has too many segments');
			}
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
