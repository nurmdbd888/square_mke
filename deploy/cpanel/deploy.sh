#!/bin/bash
# Run by cPanel > Git Version Control > "Deploy HEAD Commit" (see .cpanel.yml
# at the root of the "release" branch). Produces exactly the same folders as
# the zip method in DEPLOY.md:
#
#   ~/mke/app       live code (subdomain document root = ~/mke/app/public)
#   ~/mke/app-prev  the previous release, for rollback
#   ~/mke/shared    .env and storage, created on the first deploy, never replaced
#
# Only bash and coreutils are used: no rsync, composer, node or php.
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ROOT="${SDM_ROOT:-$HOME/mke}"
NEW="$ROOT/app-new"
CUR="$ROOT/app"
PREV="$ROOT/app-prev"
SHARED="$ROOT/shared"

log() { echo "[sdm-deploy] $*"; }

if [ ! -f "$SRC/artisan" ] || [ ! -f "$SRC/vendor/autoload.php" ]; then
    log "ERROR: $SRC is not a release build (vendor/ is missing). In Git Version Control, check out the 'release' branch."
    exit 1
fi

mkdir -p "$ROOT"
rm -rf "$NEW"
mkdir "$NEW"

shopt -s dotglob nullglob
for item in "$SRC"/*; do
    case "$(basename "$item")" in
        .git | .cpanel.yml) continue ;;
    esac
    cp -a "$item" "$NEW/"
done
shopt -u dotglob nullglob

# Keep what cPanel itself wrote into the live document root: the PHP version
# handler from MultiPHP Manager, MultiPHP INI settings and AutoSSL challenges.
if [ -d "$CUR/public" ]; then
    for f in .user.ini php.ini; do
        if [ -f "$CUR/public/$f" ]; then cp -a "$CUR/public/$f" "$NEW/public/$f"; fi
    done
    if [ -d "$CUR/public/.well-known" ]; then cp -a "$CUR/public/.well-known" "$NEW/public/"; fi
    if [ -f "$CUR/public/.htaccess" ]; then
        blocks="$(awk '/BEGIN cPanel-generated/{p=1} p{print} /END cPanel-generated/{p=0}' "$CUR/public/.htaccess")"
        if [ -n "$blocks" ]; then
            { printf '%s\n\n' "$blocks"; cat "$NEW/public/.htaccess"; } > "$NEW/public/.htaccess.tmp"
            mv "$NEW/public/.htaccess.tmp" "$NEW/public/.htaccess"
            log "Kept the cPanel-generated PHP settings in public/.htaccess."
        fi
    fi
fi

# Same safety net as the zip's ~/mke/.htaccess, in case the subdomain's
# document root is ~/mke. A cPanel PHP handler block in it is kept.
root_blocks=""
if [ -f "$ROOT/.htaccess" ]; then
    root_blocks="$(awk '/BEGIN cPanel-generated/{p=1} p{print} /END cPanel-generated/{p=0}' "$ROOT/.htaccess")"
fi
{
    if [ -n "$root_blocks" ]; then printf '%s\n\n' "$root_blocks"; fi
    cat "$NEW/deploy/root.htaccess"
} > "$ROOT/.htaccess.tmp"
mv "$ROOT/.htaccess.tmp" "$ROOT/.htaccess"

if [ ! -d "$SHARED" ]; then
    cp -a "$NEW/deploy/shared.example" "$SHARED"
    log "Created $SHARED."
fi
(cd "$NEW/deploy/shared.example" && find storage -type d) | while read -r dir; do
    mkdir -p "$SHARED/$dir"
done
for f in .htaccess storage/.htaccess; do
    if [ ! -f "$SHARED/$f" ]; then cp "$NEW/deploy/shared.example/$f" "$SHARED/$f"; fi
done
if [ ! -f "$SHARED/.env" ]; then
    cp "$SHARED/.env.example" "$SHARED/.env"
    chmod 600 "$SHARED/.env"
    log "Created $SHARED/.env from the example. Fill it in with File Manager (see DEPLOY.md)."
fi

rm -rf "$PREV"
if [ -d "$CUR" ]; then mv "$CUR" "$PREV"; fi
mv "$NEW" "$CUR"

log "Deployed $(cat "$CUR/version.json" 2>/dev/null || echo 'unknown version') to $CUR."
log "Next: open /maintenance and press 'Run migrations' if this release has new ones."
