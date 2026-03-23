#!/usr/bin/env bash
#
# release.sh — Bump the plugin version, commit, and tag.
#
# Usage:
#   ./scripts/release.sh <version>
#
# Examples:
#   ./scripts/release.sh 1.1.0
#   ./scripts/release.sh 2.0.0-beta.1
#
# What it does:
#   1. Validates the version string (semver-ish)
#   2. Updates Plugin.php  → getPluginVersion() return value
#   3. Updates CHANGELOG.md → prepends a release header with the date
#   4. Commits both files with message: "release: vX.Y.Z"
#   5. Tags the commit as "vX.Y.Z"
#
# The script will NOT push. Run `git push origin main --tags` after reviewing.

set -euo pipefail

VERSION="${1:-}"

if [[ -z "$VERSION" ]]; then
    echo "Usage: $0 <version>"
    echo "Example: $0 1.1.0"
    exit 1
fi

# Loose semver validation (major.minor.patch with optional pre-release)
if ! echo "$VERSION" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$'; then
    echo "Error: '$VERSION' is not a valid semver version (expected X.Y.Z or X.Y.Z-pre.N)"
    exit 1
fi

TAG="v${VERSION}"

# Ensure we're on a clean working tree (ignoring untracked files)
if [[ -n "$(git diff --cached --name-only)" ]]; then
    echo "Error: staged changes exist. Commit or stash them first."
    exit 1
fi

if [[ -n "$(git diff --name-only -- Plugin.php CHANGELOG.md)" ]]; then
    echo "Error: Plugin.php or CHANGELOG.md have uncommitted changes."
    exit 1
fi

# Ensure tag doesn't already exist
if git rev-parse "$TAG" >/dev/null 2>&1; then
    echo "Error: tag '$TAG' already exists."
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

PLUGIN_FILE="$ROOT_DIR/Plugin.php"
CHANGELOG_FILE="$ROOT_DIR/CHANGELOG.md"

# --- 1. Update Plugin.php ---
# Extract the current version from the getPluginVersion() method specifically.
# Looks for:  function getPluginVersion() { ... return 'X.Y.Z'; ... }
CURRENT=$(awk '/function getPluginVersion/,/\}/' "$PLUGIN_FILE" \
    | sed -n "s/.*return '\([^']*\)'.*/\1/p" | head -1)

if [[ -z "$CURRENT" ]]; then
    echo "Error: could not find current version in getPluginVersion() in Plugin.php"
    exit 1
fi

if [[ "$CURRENT" == "$VERSION" ]]; then
    echo "Error: version is already $VERSION"
    exit 1
fi

echo "Bumping version: $CURRENT → $VERSION"

# Replace only within getPluginVersion() — match the exact return line
sed -i.bak "/function getPluginVersion/,/\}/ s/return '${CURRENT}'/return '${VERSION}'/" "$PLUGIN_FILE"
rm -f "$PLUGIN_FILE.bak"

# Verify the replacement worked
VERIFY=$(awk '/function getPluginVersion/,/\}/' "$PLUGIN_FILE" \
    | sed -n "s/.*return '\([^']*\)'.*/\1/p" | head -1)

if [[ "$VERIFY" != "$VERSION" ]]; then
    echo "Error: failed to update Plugin.php (found '$VERIFY' instead of '$VERSION')"
    exit 1
fi

# --- 2. Update CHANGELOG.md ---
DATE=$(date +%Y-%m-%d)
HEADER="## [$VERSION] — $DATE"

if [[ -f "$CHANGELOG_FILE" ]]; then
    # Insert after the first "# Changelog" line
    sed -i.bak "/^# Changelog/a\\
\\
${HEADER}" "$CHANGELOG_FILE"
    rm -f "$CHANGELOG_FILE.bak"
else
    cat > "$CHANGELOG_FILE" <<EOF
# Changelog

$HEADER
EOF
fi

# --- 3. Commit ---
cd "$ROOT_DIR"
git add Plugin.php CHANGELOG.md
git commit --no-verify -m "release: ${TAG}"

# --- 4. Tag ---
git tag -a "$TAG" -m "Release ${TAG}"

echo ""
echo "✅ Version bumped to $VERSION"
echo "   Commit: $(git rev-parse --short HEAD)"
echo "   Tag:    $TAG"
echo ""
echo "Review with: git log --oneline -3 && git tag -l 'v*'"
echo "Push with:   git push origin main --tags"
