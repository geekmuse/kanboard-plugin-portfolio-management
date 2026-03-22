#!/usr/bin/env bash
# Run PHP_CodeSniffer against the plugin source via Docker.
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
docker run --rm \
  -v "${REPO_ROOT}:/app" \
  -w /app \
  php:8.3-cli-alpine \
  ./vendor/bin/phpcs --standard=.phpcs.xml
