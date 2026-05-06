#!/usr/bin/env bash
set -euo pipefail

if [[ "${SAKURAALBUM_LIVE_SMOKE:-}" != "1" ]]; then
	echo "Refusing live smoke test without SAKURAALBUM_LIVE_SMOKE=1." >&2
	exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
NEXTCLOUD_ROOT="${NEXTCLOUD_ROOT:-/var/www/nextcloud}"
cd "$NEXTCLOUD_ROOT"
php -d display_errors=1 -r 'require $argv[1];' "$SCRIPT_DIR/live-smoke.php"
