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

echo "Running naming smoke tests"
php tests/Smoke/NamingSmokeTest.php >/dev/null

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
if ! rg -n "affectedIncludePath" lib/Service/AutoSyncService.php >/dev/null; then
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
if ! rg -n "admin-settings-020" lib/Settings/Admin.php >/dev/null; then
	echo "Admin settings must load the cache-busting versioned JavaScript asset" >&2
	exit 1
fi
if ! rg -n "personal-settings-020" lib/Settings/Personal.php >/dev/null; then
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
