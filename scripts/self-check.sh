#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

echo "Checking PHP syntax"
while IFS= read -r -d '' file; do
	php -l "$file" >/dev/null
done < <(find appinfo lib templates tests -name '*.php' -print0)

echo "Checking JavaScript syntax"
while IFS= read -r -d '' file; do
	node --check "$file" >/dev/null
done < <(find js -name '*.js' -print0)

echo "Checking app metadata XML"
php -r '$xml = simplexml_load_file("appinfo/info.xml"); if (!$xml || (string)$xml->id !== "sakuraalbum" || (string)$xml->name !== "SakuraAlbum") { fwrite(STDERR, "Invalid appinfo/info.xml\n"); exit(1); }'
APP_VERSION="$(php -r '$xml = simplexml_load_file("appinfo/info.xml"); echo (string)$xml->version;' )"
ASSET_SUFFIX="${APP_VERSION//./}"
if command -v curl >/dev/null 2>&1; then
	echo "Validating app metadata against the official schema"
	schema_file="$(mktemp)"
	trap 'rm -f "$schema_file"' EXIT
	curl -fsSL https://apps.nextcloud.com/schema/apps/info.xsd -o "$schema_file"
	php -r '$doc = new DOMDocument(); $doc->load("appinfo/info.xml"); if (!$doc->schemaValidate($argv[1])) { fwrite(STDERR, "appinfo/info.xml does not validate against the official schema\n"); exit(1); }' "$schema_file"
fi

echo "Running naming smoke tests"
php tests/Smoke/NamingSmokeTest.php >/dev/null
php tests/Smoke/ReleaseMetadataSmokeTest.php >/dev/null
php tests/Smoke/UiSmokeTest.php >/dev/null

echo "Scanning for accidental GitHub personal access tokens"
token_prefix="ghp"
if rg -n "${token_prefix}_[A-Za-z0-9_]{20,}" . >/dev/null; then
	echo "Potential GitHub token found in source tree" >&2
	exit 1
fi

echo "Checking controller CSRF posture"
if rg -n "NoCSRFRequired" lib/Controller appinfo >/dev/null; then
	echo "Controllers must not disable CSRF protection" >&2
	exit 1
fi

echo "Checking admin app-config key isolation"
if rg -n "AppValue[A-Za-z]*\\('enabled'" lib/Service/SettingsService.php >/dev/null; then
	echo "Admin settings must not use app config key 'enabled'; Nextcloud reserves it for app activation state" >&2
	exit 1
fi

echo "Checking write/delete fingerprint freshness guards"
if ! rg -n "assertRecentDryRunFingerprint" lib/Service/AlbumSyncService.php >/dev/null; then
	echo "Album write service must verify a recent server-recorded dry-run fingerprint" >&2
	exit 1
fi
if ! rg -n "assertRecentDeleteDryRunFingerprint" lib/Service/ManagedAlbumDeletionService.php >/dev/null; then
	echo "Managed delete service must verify a recent server-recorded delete dry-run fingerprint" >&2
	exit 1
fi

echo "Checking automatic sync safety guards"
if ! rg -n "setAllowParallelRuns\\(false\\)" lib/BackgroundJob/AutoSyncJob.php >/dev/null; then
	echo "Automatic sync job must disallow parallel runs" >&2
	exit 1
fi
for command in Preview Sync DeleteGenerated; do
	if ! rg -n "OCA\\\\SakuraAlbum\\\\Command\\\\${command}" appinfo/info.xml >/dev/null; then
		echo "OCC command metadata is missing: ${command}" >&2
		exit 1
	fi
	if [[ ! -f "lib/Command/${command}.php" ]]; then
		echo "OCC command class is missing: ${command}" >&2
		exit 1
	fi
done
if ! rg -n "dry-run.*required|dry_run_required" lib/Command/Sync.php lib/Command/DeleteGenerated.php >/dev/null; then
	echo "OCC sync/delete helpers must require explicit dry-run mode" >&2
	exit 1
fi
if rg -n "albumSyncService->write|managedAlbumDeletionService->delete\\(" lib/Command >/dev/null; then
	echo "OCC helpers must not call write/delete methods directly" >&2
	exit 1
fi
for event in NodeCreatedEvent NodeDeletedEvent NodeRenamedEvent NodeWrittenEvent; do
	if ! rg -n "registerEventListener\\(${event}::class" lib/AppInfo/Application.php >/dev/null; then
		echo "Automatic sync file-event listener is missing: ${event}" >&2
		exit 1
	fi
done
if ! rg -n "'syncDeleteMissingManagedAlbums' => false" lib/Service/SettingsService.php >/dev/null; then
	echo "Missing managed album cleanup must stay disabled by default" >&2
	exit 1
fi
if ! rg -n "releaseStaleProcessing" lib/Db/DirtyPathMapper.php lib/Service/AutoSyncService.php >/dev/null; then
	echo "Automatic sync must recover stale processing locks" >&2
	exit 1
fi
if ! rg -n "auto_sync_runtime_limit_reached" lib/Service/AutoSyncService.php >/dev/null; then
	echo "Automatic sync runtime-limit logging is missing" >&2
	exit 1
fi
if ! rg -n "pendingEventsSeen" lib/Service/AutoSyncService.php js/admin-settings.js >/dev/null; then
	echo "Automatic sync load-summary counters are missing" >&2
	exit 1
fi
if ! rg -n "affectedAutoSyncPath|affectedIncludePath|autoSyncQueuePaths" lib/Service/AutoSyncService.php >/dev/null; then
	echo "Automatic sync must collapse file events to the affected include root" >&2
	exit 1
fi
if ! rg -n "auto-sync/status" appinfo/routes.php js/admin-settings.js >/dev/null; then
	echo "Admin Auto-Sync status endpoint/UI is missing" >&2
	exit 1
fi
if ! rg -n "data-profile" js/admin-settings.js >/dev/null; then
	echo "Admin load-profile controls are missing" >&2
	exit 1
fi
if ! rg -n "allowedGroups" lib/Service/SettingsService.php js/admin-settings.js >/dev/null; then
	echo "Admin group rollout controls are missing" >&2
	exit 1
fi
if ! rg -n "adminSettings#groups" appinfo/routes.php >/dev/null; then
	echo "Admin group listing endpoint is missing" >&2
	exit 1
fi
if ! rg -n "adminGroupAllowed" lib/Service/SettingsService.php lib/Service/AutoSyncService.php js/personal-settings.js >/dev/null; then
	echo "Group rollout must affect effective user settings, auto-sync queueing, and personal UI" >&2
	exit 1
fi
if ! rg -n "autoSyncWindowStart|outside_auto_sync_window" lib/Service/SettingsService.php lib/Service/AutoSyncService.php js/admin-settings.js >/dev/null; then
	echo "Auto-Sync maintenance window controls are missing" >&2
	exit 1
fi
if ! rg -n "maxManagedAlbumsPerUser|maxManagedFilesPerUser|managed_album_quota_exceeded|managed_file_quota_exceeded" lib/Service/SettingsService.php lib/Service/AlbumSyncService.php js/personal-settings.js >/dev/null; then
	echo "Per-user managed album/media quota enforcement is missing" >&2
	exit 1
fi
if ! rg -n "admin-settings-${ASSET_SUFFIX}" lib/Settings/Admin.php >/dev/null; then
	echo "Admin settings must load the cache-busting versioned JavaScript asset" >&2
	exit 1
fi
if ! rg -n "personal-settings-${ASSET_SUFFIX}" lib/Settings/Personal.php >/dev/null; then
	echo "Personal settings must load the cache-busting versioned JavaScript asset" >&2
	exit 1
fi
if rg -n "OC\\.generateUrl\\([^)]*\\?" js >/dev/null; then
	echo "Do not pass query strings directly into OC.generateUrl; append them after URL generation" >&2
	exit 1
fi
if ! rg -n "autoSyncEnabled" lib/Service/SettingsService.php js/personal-settings.js >/dev/null; then
	echo "Personal automatic-update opt-in setting is missing" >&2
	exit 1
fi
if ! rg -n "autoSyncActive" lib/Service/SettingsService.php lib/Service/AutoSyncService.php >/dev/null; then
	echo "Automatic sync must require the user's effective opt-in state" >&2
	exit 1
fi
if ! rg -n "auto_sync_user_skipped_disabled" lib/Service/AutoSyncService.php docs/ADMIN_GUIDE.md >/dev/null; then
	echo "Automatic sync must skip already queued work after the user disables auto-sync" >&2
	exit 1
fi
if ! rg -n "sourceFolders" lib/Service/SettingsService.php lib/Service/AlbumPlanService.php js/personal-settings.js >/dev/null; then
	echo "Structured source folder rules are missing" >&2
	exit 1
fi
if ! rg -n "overlapping_source_paths" lib/Service/AlbumPlanService.php lib/Service/AlbumSyncService.php js/personal-settings.js >/dev/null; then
	echo "Overlapping source folder safety guard is missing" >&2
	exit 1
fi
if ! rg -n "FolderBrowserService" lib/Controller/FolderController.php lib/Service/FolderBrowserService.php >/dev/null; then
	echo "Folder picker backend is missing" >&2
	exit 1
fi
if ! rg -n "queueUserRefresh" lib/Service/AutoSyncService.php lib/Controller/UserSettingsController.php lib/Controller/SyncController.php >/dev/null; then
	echo "User background refresh queue is missing" >&2
	exit 1
fi
if ! rg -n "progressPercent" lib/Service/AlbumSyncService.php js/personal-settings.js >/dev/null; then
	echo "User sync progress reporting is missing" >&2
	exit 1
fi
if ! rg -n "sakuraalbum_sync_cursors" lib/Migration lib/Db >/dev/null; then
	echo "Resumable background sync cursor table is missing" >&2
	exit 1
fi
if ! rg -n "buildExecutionChunk" lib/Service/AlbumPlanService.php lib/Service/AlbumSyncService.php >/dev/null; then
	echo "Chunked execution planning is missing" >&2
	exit 1
fi
if ! rg -n "writeChunk" lib/Service/AlbumSyncService.php lib/Service/AutoSyncService.php >/dev/null; then
	echo "Automatic sync must use resumable chunk writes" >&2
	exit 1
fi
if ! rg -n "chunk_continue" lib/Service/AutoSyncService.php >/dev/null; then
	echo "Automatic sync must requeue incomplete chunks" >&2
	exit 1
fi
if ! rg -n "process-due" appinfo/routes.php js/admin-settings.js >/dev/null; then
	echo "Admin-controlled due automatic sync processing endpoint/UI is missing" >&2
	exit 1
fi
if ! rg -n "processAutoSync" lib/Controller/AdminSettingsController.php >/dev/null; then
	echo "Admin automatic sync process endpoint handler is missing" >&2
	exit 1
fi
if ! rg -n "estimatedProgressPercent" lib/Service/AlbumSyncService.php js/personal-settings.js >/dev/null; then
	echo "Chunk cursor estimated progress reporting is missing" >&2
	exit 1
fi
if ! rg -n "Erweiterte manuelle Testfunktionen|Automatik einschalten" js/personal-settings.js >/dev/null; then
	echo "Personal Auto-Sync UX polish is missing" >&2
	exit 1
fi
if ! rg -n "DiagnosticReportService" lib/Service/DiagnosticReportService.php lib/Controller/DiagnosticsController.php >/dev/null; then
	echo "Diagnostic report service/controller is missing" >&2
	exit 1
fi
if ! rg -n "diagnostics/report" appinfo/routes.php js/admin-settings.js js/personal-settings.js >/dev/null; then
	echo "Diagnostic report API/UI routes are missing" >&2
	exit 1
fi
if ! rg -n "sendMailReady.*false|sendMailLater.*true" lib/Service/DiagnosticReportService.php >/dev/null; then
	echo "Diagnostic report must clearly mark mail sending as future work" >&2
	exit 1
fi
if ! rg -n "Fehlerbericht vorbereiten|Diagnosebericht" js/personal-settings.js js/admin-settings.js >/dev/null; then
	echo "Diagnostic report UI actions are missing" >&2
	exit 1
fi
if ! rg -n "REASON_UNIQUE_CONSTRAINT_VIOLATION" lib/Service/PhotosAlbumAdapter.php >/dev/null; then
	echo "Photos duplicate-link DB unique constraint handling is missing" >&2
	exit 1
fi
if ! rg -n "albumContainsOwnedFile" lib/Service/PhotosAlbumAdapter.php >/dev/null; then
	echo "Photos duplicate-link handling must re-check the exact album/file/owner link" >&2
	exit 1
fi

echo "Scanning for stale app identifiers"
legacy_namespace="$(printf 'OCA\\%s' 'File2Album')"
legacy_app_id="file2album"
legacy_xml="<id>${legacy_app_id}</id>"
legacy_route="/apps/${legacy_app_id}"
for stale in "$legacy_namespace" "$legacy_xml" "$legacy_route"; do
	if rg -n -F "$stale" . >/dev/null; then
		echo "Stale File2Album identifier found: $stale" >&2
		exit 1
	fi
done

echo "Self-check passed"
