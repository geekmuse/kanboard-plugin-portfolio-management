#!/usr/bin/env bash
# Verify that release-facing version references agree before publishing a tag.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-${GITHUB_REF_NAME:-}}"
VERSION="${VERSION#v}"

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$ ]]; then
    echo "Error: provide a release version such as 1.2.3 or v1.2.3" >&2
    exit 1
fi

PLUGIN_VERSION=$(awk '/function getPluginVersion/,/}/' "$ROOT_DIR/Plugin.php" \
    | sed -n "s/.*return '\([^']*\)'.*/\1/p" \
    | head -1)

if [[ "$PLUGIN_VERSION" != "$VERSION" ]]; then
    echo "Error: Plugin.php reports $PLUGIN_VERSION, expected $VERSION" >&2
    exit 1
fi

if ! grep -Fq "## [$VERSION] —" "$ROOT_DIR/CHANGELOG.md"; then
    echo "Error: CHANGELOG.md has no release heading for $VERSION" >&2
    exit 1
fi

if ! grep -Fq "version \`$VERSION\`" "$ROOT_DIR/README.md"; then
    echo "Error: README.md install verification does not reference version $VERSION" >&2
    exit 1
fi

echo "Release metadata is consistent for v$VERSION."
