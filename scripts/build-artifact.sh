#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
WORK_DIR="$(cd "$APP_DIR/.." && pwd)"
ARTIFACT_DIR="${1:-$WORK_DIR/artifacts}"

APP_ID="$(php -r '$xml = simplexml_load_file($argv[1]); echo (string)$xml->id;' "$APP_DIR/appinfo/info.xml")"
VERSION="$(php -r '$xml = simplexml_load_file($argv[1]); echo (string)$xml->version;' "$APP_DIR/appinfo/info.xml")"

if [[ -z "$APP_ID" || -z "$VERSION" ]]; then
	echo "Could not read app id/version from appinfo/info.xml" >&2
	exit 1
fi

mkdir -p "$ARTIFACT_DIR"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

mkdir -p "$TMP_DIR/$APP_ID"

tar -C "$APP_DIR" \
	--exclude='./.git' \
	--exclude='./.github' \
	--exclude='./.nextcloud-test-instance' \
	--exclude='./SOURCE_MANIFEST.txt' \
	--exclude='./build' \
	--exclude='./node_modules' \
	--exclude='./releases' \
	--exclude='./vendor' \
	--exclude='./*.log' \
	--exclude='./*.tar.gz' \
	--exclude='./*.zip' \
	-cf "$TMP_DIR/source.tar" .

tar -C "$TMP_DIR/$APP_ID" -xf "$TMP_DIR/source.tar"

OUT="$ARTIFACT_DIR/${APP_ID}-${VERSION}.tar.gz"
tar --sort=name \
	--mtime='UTC 2026-01-01' \
	--owner=0 \
	--group=0 \
	--numeric-owner \
	-C "$TMP_DIR" \
	-czf "$OUT" "$APP_ID"

if tar -tzf "$OUT" | rg -n '(^|/)(node_modules|vendor|build|releases|\.git|\.github|\.cache|\.nextcloud-test-instance)(/|$)|(^|/)SOURCE_MANIFEST\.txt$|\.(log|zip)$|\.tar\.gz$' >/dev/null; then
	echo "Artifact contains development-only files" >&2
	tar -tzf "$OUT" | rg -n '(^|/)(node_modules|vendor|build|releases|\.git|\.github|\.cache|\.nextcloud-test-instance)(/|$)|(^|/)SOURCE_MANIFEST\.txt$|\.(log|zip)$|\.tar\.gz$' >&2
	exit 1
fi

sha256sum "$OUT"
