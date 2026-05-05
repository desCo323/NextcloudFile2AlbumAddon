<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Service;

class AlbumNameFormatter {
	public const TEMPLATES = [
		'leaf',
		'parent_leaf',
		'root_relative',
	];

	public function format(string $sourceRoot, string $targetPath, array $settings): string {
		$template = (string)($settings['namingTemplate'] ?? 'root_relative');
		$separator = self::sanitizeSeparator((string)($settings['separator'] ?? ' - '));

		if (!in_array($template, self::TEMPLATES, true)) {
			$template = 'root_relative';
		}

		$name = match ($template) {
			'leaf' => PathHelper::basename($targetPath),
			'parent_leaf' => $this->formatParentLeaf($targetPath, $separator),
			default => $this->formatRootRelative($sourceRoot, $targetPath, $separator),
		};

		return self::sanitizeAlbumName($name);
	}

	public static function sanitizeSeparator(string $separator): string {
		$separator = preg_replace('/[\/\\\\\x00-\x1F\x7F]+/', ' ', $separator) ?? ' - ';
		$separator = mb_substr($separator, 0, 20);
		$separator = trim($separator);
		return $separator === '' ? ' - ' : ' ' . trim($separator) . ' ';
	}

	public static function sanitizeAlbumName(string $name): string {
		$name = preg_replace('/[\/\\\\\x00-\x1F\x7F]+/', '-', $name) ?? '';
		$name = preg_replace('/\s+/', ' ', $name) ?? '';
		$name = trim($name, " \t\n\r\0\x0B.-");
		$name = mb_substr($name, 0, 240);
		return $name === '' ? 'Album' : $name;
	}

	private function formatParentLeaf(string $targetPath, string $separator): string {
		$parent = PathHelper::parentBasename($targetPath);
		$leaf = PathHelper::basename($targetPath);

		if ($parent === '') {
			return $leaf;
		}

		return $parent . $separator . $leaf;
	}

	private function formatRootRelative(string $sourceRoot, string $targetPath, string $separator): string {
		$rootName = PathHelper::basename($sourceRoot);
		$relative = PathHelper::relativePath($sourceRoot, $targetPath);

		if ($relative === '') {
			return $rootName;
		}

		return $rootName . $separator . str_replace('/', $separator, $relative);
	}
}
