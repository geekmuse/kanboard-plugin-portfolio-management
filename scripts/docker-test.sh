#!/usr/bin/env bash
# Run the PHPUnit test suite via Docker.
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
docker run --rm \
  -v "${REPO_ROOT}:/app" \
  -w /app \
  php:8.3-cli-alpine \
  ./vendor/bin/phpunit --configuration phpunit.xml
