#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
NEXTCLOUD_ROOT="${NEXTCLOUD_ROOT:-/var/www/nextcloud}"
LIVE_APP_DIR="${LIVE_APP_DIR:-$NEXTCLOUD_ROOT/apps/sakuraalbum}"
BACKUP_ROOT="${BACKUP_ROOT:-/home/cloud/sakuraalbum-backups}"
MODE="${1:---preflight}"

cd "$APP_DIR"

usage() {
	cat <<'USAGE'
Usage:
  scripts/production-update.sh --preflight
  SAKURAALBUM_PRODUCTION_UPDATE=1 scripts/production-update.sh --deploy

Environment:
  NEXTCLOUD_ROOT   Defaults to /var/www/nextcloud
  LIVE_APP_DIR     Defaults to $NEXTCLOUD_ROOT/apps/sakuraalbum
  BACKUP_ROOT      Defaults to /home/cloud/sakuraalbum-backups
USAGE
}

read_xml_value() {
	php -r '$xml = simplexml_load_file($argv[1]); echo (string)$xml->{$argv[2]};' "$1" "$2"
}

require_command() {
	if ! command -v "$1" >/dev/null 2>&1; then
		echo "Missing required command: $1" >&2
		exit 1
	fi
}

require_clean_token_scan() {
	if rg -n "ghp_[A-Za-z0-9_]{20,}" . >/dev/null; then
		echo "Potential GitHub personal access token found in source tree." >&2
		exit 1
	fi
}

require_clean_worktree() {
	if [[ -n "$(git status --porcelain)" ]]; then
		echo "Git worktree is not clean. Commit or intentionally stash changes before a production update." >&2
		git status --short >&2
		exit 1
	fi
}

preflight() {
	require_command php
	require_command node
	require_command rg
	require_command git

	local app_id
	local version
	local package_version
	app_id="$(read_xml_value appinfo/info.xml id)"
	version="$(read_xml_value appinfo/info.xml version)"
	package_version="$(php -r '$data = json_decode(file_get_contents("package.json"), true); echo (string)($data["version"] ?? "");')"

	if [[ "$app_id" != "sakuraalbum" ]]; then
		echo "Unexpected app id: $app_id" >&2
		exit 1
	fi
	if [[ -z "$version" || "$version" != "$package_version" ]]; then
		echo "Version mismatch: appinfo=$version package.json=$package_version" >&2
		exit 1
	fi

	./scripts/self-check.sh
	./scripts/build-artifact.sh >/tmp/sakuraalbum-build-artifact.log
	require_clean_token_scan
	require_clean_worktree

	if [[ -x "$NEXTCLOUD_ROOT/occ" || -f "$NEXTCLOUD_ROOT/occ" ]]; then
		sudo -u www-data php "$NEXTCLOUD_ROOT/occ" status
	else
		echo "Nextcloud occ not found at $NEXTCLOUD_ROOT/occ; skipped live status preflight." >&2
	fi

	echo "Production preflight passed for SakuraAlbum $version."
	echo "Artifact output:"
	cat /tmp/sakuraalbum-build-artifact.log
}

backup_live_app() {
	local version
	local stamp
	local backup_dir
	version="$(read_xml_value appinfo/info.xml version)"
	stamp="$(date +%Y%m%d-%H%M%S)"
	backup_dir="$BACKUP_ROOT/sakuraalbum-pre-update-${version}-${stamp}"

	if [[ ! -d "$LIVE_APP_DIR" ]]; then
		echo "Live app directory does not exist: $LIVE_APP_DIR" >&2
		exit 1
	fi

	sudo mkdir -p "$backup_dir"
	sudo cp -a "$LIVE_APP_DIR" "$backup_dir/app"
	sudo chown -R "$(id -u):$(id -g)" "$backup_dir"
	printf '%s\n' "$backup_dir"
}

deploy() {
	if [[ "${SAKURAALBUM_PRODUCTION_UPDATE:-}" != "1" ]]; then
		echo "Refusing deploy. Set SAKURAALBUM_PRODUCTION_UPDATE=1 for an intentional production update." >&2
		exit 1
	fi

	preflight

	local backup_dir
	backup_dir="$(backup_live_app)"
	echo "Backup created: $backup_dir"

	sudo rsync -a --delete \
		--exclude='.git' \
		--exclude='.github' \
		--exclude='node_modules' \
		--exclude='vendor' \
		--exclude='releases' \
		--exclude='SOURCE_MANIFEST.txt' \
		--exclude='*.log' \
		"$APP_DIR/" "$LIVE_APP_DIR/"
	sudo chown -R www-data:www-data "$LIVE_APP_DIR"

	local status
	status="$(sudo -u www-data php "$NEXTCLOUD_ROOT/occ" status)"
	printf '%s\n' "$status"
	if printf '%s\n' "$status" | rg -n "needsDbUpgrade:\s*true" >/dev/null; then
		sudo -u www-data php "$NEXTCLOUD_ROOT/occ" upgrade
	fi
	sudo -u www-data php "$NEXTCLOUD_ROOT/occ" status

	cat <<RESTORE
Restore prompt:
Stelle SakuraAlbum aus '$backup_dir/app' nach '$LIVE_APP_DIR' wieder her, setze Eigentümer 'www-data:www-data', pruefe danach 'sudo -u www-data php $NEXTCLOUD_ROOT/occ status' und stelle sicher, dass maintenance false und needsDbUpgrade false sind.
RESTORE
}

case "$MODE" in
	--preflight)
		preflight
		;;
	--deploy)
		deploy
		;;
	--help|-h)
		usage
		;;
	*)
		usage >&2
		exit 1
		;;
esac
