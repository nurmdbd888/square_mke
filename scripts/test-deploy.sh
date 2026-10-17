#!/usr/bin/env bash
# Runs deploy/cpanel/deploy.sh from a release tree the way cPanel's "Deploy
# HEAD Commit" does, in a throwaway home folder, and checks the result:
# folder swap, shared/ kept, deny files, and the ~/mke/.htaccess safety net
# (including cPanel's PHP handler blocks) surviving repeated deploys.
#
#   scripts/test-deploy.sh build/release-tree
set -euo pipefail

TREE="$(cd "${1:?usage: scripts/test-deploy.sh <release-tree>}" && pwd)"
FAKE_HOME="$(mktemp -d)"
trap 'rm -rf "$FAKE_HOME"' EXIT

REPO="$FAKE_HOME/repositories/square_mke"
MKE="$FAKE_HOME/mke"
HANDLER=$'# BEGIN cPanel-generated php ini directives, do not edit\n<IfModule mime_module>\n  AddHandler application/x-httpd-ea-php84 .php .php8 .phtml\n</IfModule>\n# END cPanel-generated php ini directives, do not edit'

fail() { echo "FAIL: $*" >&2; exit 1; }
deploy() { (cd "$REPO" && HOME="$FAKE_HOME" /bin/bash deploy/cpanel/deploy.sh >/dev/null); }
count() { grep -c -- "$1" "$2" || true; }

mkdir -p "$(dirname "$REPO")"
cp -a "$TREE" "$REPO"
grep -q 'deploy/cpanel/deploy.sh' "$REPO/.cpanel.yml" || fail ".cpanel.yml does not run deploy.sh"

# First deploy on an empty account.
deploy
[ -f "$MKE/app/artisan" ] || fail "app/ not created"
[ -f "$MKE/shared/.env" ] || fail "shared/.env not created"
[ "$(stat -c %a "$MKE/shared/.env")" = 600 ] || fail "shared/.env is not 600"
cmp -s "$MKE/.htaccess" "$TREE/deploy/root.htaccess" || fail "~/mke/.htaccess is not deploy/root.htaccess"
cmp -s "$MKE/shared/.htaccess" "$TREE/deploy/shared.example/.htaccess" || fail "shared/.htaccess missing"
cmp -s "$MKE/shared/storage/.htaccess" "$TREE/deploy/shared.example/storage/.htaccess" || fail "shared/storage/.htaccess missing"

# cPanel writes its PHP handler into whichever folder is the document root;
# the owner fills in .env.
printf '%s\n\n%s' "$HANDLER" "$(cat "$MKE/.htaccess")" > "$MKE/.htaccess"
printf '%s\n\n%s' "$HANDLER" "$(cat "$MKE/app/public/.htaccess")" > "$MKE/app/public/.htaccess"
echo "DB_PASSWORD=owner-secret" >> "$MKE/shared/.env"
env_before="$(sha1sum < "$MKE/shared/.env")"

# Two updates in a row.
deploy
deploy
[ -d "$MKE/app-prev" ] || fail "app-prev not created"
[ ! -e "$MKE/app-new" ] || fail "app-new left behind"
[ "$(sha1sum < "$MKE/shared/.env")" = "$env_before" ] || fail "shared/.env was changed"
for f in "$MKE/.htaccess" "$MKE/app/public/.htaccess"; do
    [ "$(count 'BEGIN cPanel-generated' "$f")" = 1 ] || fail "$f should hold exactly one cPanel block"
    grep -q 'x-httpd-ea-php84' "$f" || fail "$f lost the PHP handler"
done
[ "$(count 'sdm-root-htaccess' "$MKE/.htaccess")" = 1 ] || fail "~/mke/.htaccess lost the safety net"

# An install from before the safety net existed (v0.0.0): nothing at ~/mke/.htaccess
# and no deny files in shared/.
rm "$MKE/.htaccess" "$MKE/shared/.htaccess" "$MKE/shared/storage/.htaccess"
deploy
cmp -s "$MKE/.htaccess" "$TREE/deploy/root.htaccess" || fail "~/mke/.htaccess not restored"
[ -f "$MKE/shared/.htaccess" ] && [ -f "$MKE/shared/storage/.htaccess" ] || fail "shared deny files not restored"
[ "$(sha1sum < "$MKE/shared/.env")" = "$env_before" ] || fail "shared/.env was changed"

echo "deploy.sh: all checks passed"
