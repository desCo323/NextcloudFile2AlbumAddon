<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function uiFail(string $message): never {
	fwrite(STDERR, $message . PHP_EOL);
	exit(1);
}

function readText(string $root, string $path): string {
	$content = file_get_contents($root . '/' . $path);
	if ($content === false) {
		uiFail("Could not read {$path}");
	}

	return $content;
}

function assertUiContains(string $content, string $needle, string $source): void {
	if (!str_contains($content, $needle)) {
		uiFail("Expected {$source} to contain {$needle}");
	}
}

$admin = readText($root, 'js/admin-settings.js');
$personal = readText($root, 'js/personal-settings.js');
$routes = readText($root, 'appinfo/routes.php');
$css = readText($root, 'css/settings.css');

foreach ([
	'Automatik vorbereiten',
	'Gruppen laden',
	'Rollout: erlaubte Gruppen',
	'Benutzerquote: Alben',
	'Export: Dateilimit',
	'Auto: Wartungsfenster Start',
	'Auto-Status laden',
	'Faellige Jobs jetzt verarbeiten',
	'Diagnosebericht',
	'/apps/sakuraalbum/api/v1/admin/auto-sync/status',
	'/apps/sakuraalbum/api/v1/admin/auto-sync/process-due',
	'/apps/sakuraalbum/api/v1/admin/groups',
	'/apps/sakuraalbum/api/v1/admin/diagnostics/report',
	'Neue, geaenderte, verschobene, geloeschte oder umbenannte',
] as $needle) {
	assertUiContains($admin, $needle, 'js/admin-settings.js');
}

foreach ([
	'Quellordner hinzufuegen',
	'Ordnerregel hinzufuegen',
	'Automatisch aktuell halten',
	'Gruppe gesperrt',
	'Wartungsfenster',
	'Automatik einschalten',
	'Update vormerken',
	'Album-Downloads',
	'Erweiterte manuelle Testfunktionen',
	'Fehlerbericht vorbereiten',
	'Details zur aktuellen Aktivitaet',
	'/apps/sakuraalbum/api/v1/folders',
	'/apps/sakuraalbum/api/v1/sync/status',
	'/apps/sakuraalbum/api/v1/albums/export',
	'/apps/sakuraalbum/api/v1/diagnostics/report',
	'Alles in ein Album',
] as $needle) {
	assertUiContains($personal, $needle, 'js/personal-settings.js');
}

foreach ([
	'adminSettings#processAutoSync',
	'adminSettings#groups',
	'diagnostics#adminReport',
	'diagnostics#personalReport',
	'folder#index',
	'sync#status',
	'sync#queueUpdate',
	'albumExport#albums',
	'albumExport#create',
] as $needle) {
	assertUiContains($routes, $needle, 'appinfo/routes.php');
}

foreach ([
	'sakuraalbum-progress',
	'sakuraalbum-folder-browser',
	'sakuraalbum-automation-card',
	'sakuraalbum-report-json',
	'sakuraalbum-group-picker',
	'sakuraalbum-button-danger:hover',
	'.sakuraalbum-settings button:disabled',
	'color: #ffffff',
] as $needle) {
	assertUiContains($css, $needle, 'css/settings.css');
}

if (preg_match('/OC\.generateUrl\([^)]*\?/', $admin . "\n" . $personal) === 1) {
	uiFail('Query strings must be appended after OC.generateUrl');
}
