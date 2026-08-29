#!/usr/bin/env bash
#
# release.sh — Promote the Unreleased changelog section, bump versions, commit, and tag.
#
# Usage: ./scripts/release.sh <version>
#
# The script does not push. Review the commit and tag before pushing them.

set -euo pipefail

VERSION="${1:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_FILE="$ROOT_DIR/Plugin.php"
CHANGELOG_FILE="$ROOT_DIR/CHANGELOG.md"
README_FILE="$ROOT_DIR/README.md"

if [[ -z "$VERSION" ]]; then
    echo "Usage: $0 <version>"
    echo "Example: $0 1.23.0"
    exit 1
fi

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$ ]]; then
    echo "Error: '$VERSION' is not a valid semantic version" >&2
    exit 1
fi

cd "$ROOT_DIR"
TAG="v$VERSION"

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Error: tracked changes exist. Commit or stash them before releasing." >&2
    exit 1
fi

if git rev-parse "$TAG" >/dev/null 2>&1; then
    echo "Error: tag '$TAG' already exists." >&2
    exit 1
fi

CURRENT=$(awk '/function getPluginVersion/,/}/' "$PLUGIN_FILE" \
    | sed -n "s/.*return '\([^']*\)'.*/\1/p" \
    | head -1)

if [[ -z "$CURRENT" ]]; then
    echo "Error: could not find getPluginVersion() in Plugin.php" >&2
    exit 1
fi

if [[ "$CURRENT" == "$VERSION" ]]; then
    echo "Error: plugin version is already $VERSION" >&2
    exit 1
fi

if ! grep -Fq "version \`$CURRENT\`" "$README_FILE"; then
    echo "Error: README.md does not contain the current version $CURRENT" >&2
    exit 1
fi

if ! grep -Fqx '## [Unreleased]' "$CHANGELOG_FILE"; then
    echo "Error: CHANGELOG.md has no [Unreleased] section" >&2
    exit 1
fi

if ! awk '
    /^## \[Unreleased\]$/ { in_unreleased = 1; next }
    in_unreleased && /^## \[/ { exit }
    in_unreleased && /^### / { has_notes = 1 }
    END { exit has_notes ? 0 : 1 }
' "$CHANGELOG_FILE"; then
    echo "Error: CHANGELOG.md [Unreleased] section has no categorized release notes" >&2
    exit 1
fi

if grep -Fq "## [$VERSION] —" "$CHANGELOG_FILE"; then
    echo "Error: CHANGELOG.md already contains version $VERSION" >&2
    exit 1
fi

echo "Bumping version: $CURRENT → $VERSION"

sed -i.bak "/function getPluginVersion/,/}/ s/return '${CURRENT}'/return '${VERSION}'/" "$PLUGIN_FILE"
rm -f "$PLUGIN_FILE.bak"

sed -i.bak "/Confirm \"Portfolio\" appears/ s/version \`${CURRENT}\`/version \`${VERSION}\`/" "$README_FILE"
rm -f "$README_FILE.bak"

DATE=$(date +%Y-%m-%d)
sed -i.bak "s/^## \[Unreleased\]$/## [$VERSION] — $DATE/" "$CHANGELOG_FILE"
rm -f "$CHANGELOG_FILE.bak"
sed -i.bak "/^# Changelog$/a\\
\\
## [Unreleased]" "$CHANGELOG_FILE"
rm -f "$CHANGELOG_FILE.bak"

bash "$SCRIPT_DIR/check-release-consistency.sh" "$VERSION"

git add Plugin.php README.md CHANGELOG.md
git commit -m "release: $TAG"
git tag -a "$TAG" -m "Release $TAG"

echo
echo "Version bumped to $VERSION"
echo "Commit: $(git rev-parse --short HEAD)"
echo "Tag:    $TAG"
echo
echo "Review with: git show --stat && git tag -n1 '$TAG'"
echo "Push with:   git push origin main --follow-tags"
