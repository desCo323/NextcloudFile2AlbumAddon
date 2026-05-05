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

echo "Checking write/delete fingerprint freshness guards"
if ! rg -n "assertRecentDryRunFingerprint" lib/Service/AlbumSyncService.php >/dev/null; then
	echo "Album write service must verify a recent server-recorded dry-run fingerprint" >&2
	exit 1
fi
if ! rg -n "assertRecentDeleteDryRunFingerprint" lib/Service/ManagedAlbumDeletionService.php >/dev/null; then
	echo "Managed delete service must verify a recent server-recorded delete dry-run fingerprint" >&2
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
