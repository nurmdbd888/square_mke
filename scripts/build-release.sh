#!/usr/bin/env bash
# Builds a server-ready release from the committed tree (HEAD), so the server
# never needs Composer or Node.
#
#   scripts/build-release.sh <version>
#
# Output:
#   build/mke-<version>.zip  .htaccess + app/ + shared.example/ (File Manager upload method)
#   build/release-tree/      contents of the "release" branch (cPanel Git method)
set -euo pipefail

VERSION="${1:?usage: scripts/build-release.sh <version>}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

COMMIT="$(git rev-parse HEAD)"
BUILD="$ROOT/build"
STAGE="$BUILD/stage"
APP="$STAGE/app"

rm -rf "$BUILD"
mkdir -p "$APP"

# Tracked files only; export-ignore in .gitattributes drops tests, docs and CI files.
git archive --format=tar HEAD | tar -x -C "$APP"

composer install --working-dir="$APP" --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

if [ ! -s "$APP/public/css/app.css" ]; then
    echo "public/css/app.css is missing: run scripts/build-css.sh and commit the result." >&2
    exit 1
fi

rm -rf "$APP/storage/logs/"*.log "$APP/storage/framework/views/"*.php "$APP/bootstrap/cache/"*.php

printf '{"version": "%s", "commit": "%s", "built_at": "%s"}\n' \
    "$VERSION" "$COMMIT" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$APP/version.json"

cp -a "$ROOT/deploy/shared.example" "$STAGE/shared.example"
cp "$ROOT/deploy/root.htaccess" "$STAGE/.htaccess"

(cd "$STAGE" && zip -qr -X "$BUILD/mke-$VERSION.zip" .htaccess app shared.example)

mkdir -p "$BUILD/release-tree"
cp -a "$APP/." "$BUILD/release-tree/"
cp "$ROOT/deploy/cpanel/cpanel.yml" "$BUILD/release-tree/.cpanel.yml"

echo "Built $BUILD/mke-$VERSION.zip ($(du -h "$BUILD/mke-$VERSION.zip" | cut -f1)) and $BUILD/release-tree"
