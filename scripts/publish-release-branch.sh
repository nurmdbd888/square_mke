#!/usr/bin/env bash
# Commits build/release-tree on top of the "release" branch and pushes it.
# Each release is a new commit, so the branch history lists every build.
#
#   scripts/publish-release-branch.sh <version> [remote] [branch]
set -euo pipefail

VERSION="${1:?usage: scripts/publish-release-branch.sh <version> [remote] [branch]}"
REMOTE="${2:-origin}"
BRANCH="${3:-release}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TREE="$ROOT/build/release-tree"
WORK="$ROOT/build/release-git"

cd "$ROOT"
[ -d "$TREE" ] || { echo "Run scripts/build-release.sh first." >&2; exit 1; }

SOURCE_COMMIT="$(git rev-parse HEAD)"
git worktree remove --force "$WORK" 2>/dev/null || rm -rf "$WORK"

if git ls-remote --exit-code --heads "$REMOTE" "$BRANCH" >/dev/null 2>&1; then
    git fetch --quiet --depth=1 "$REMOTE" "$BRANCH"
    git worktree add --quiet --detach "$WORK" FETCH_HEAD
else
    git worktree add --quiet --detach "$WORK"
    git -C "$WORK" checkout --quiet --orphan "sdm-release-$$"
fi

find "$WORK" -mindepth 1 -maxdepth 1 ! -name .git -exec rm -rf {} +
cp -a "$TREE/." "$WORK/"

git -C "$WORK" add --all --force .
if git -C "$WORK" diff --cached --quiet; then
    echo "Release branch already matches this build."
else
    git -C "$WORK" commit --quiet -m "Release $VERSION" -m "Built from $SOURCE_COMMIT."
fi

git -C "$WORK" push --quiet "$REMOTE" "HEAD:refs/heads/$BRANCH"
echo "Pushed $VERSION to $REMOTE/$BRANCH ($(git -C "$WORK" rev-parse --short HEAD))."

git worktree remove --force "$WORK"
