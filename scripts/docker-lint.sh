#!/usr/bin/env bash
# Run PHP syntax checks against all plugin PHP files via Docker.
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
docker run --rm \
  -v "${REPO_ROOT}:/app" \
  -w /app \
  php:8.3-cli-alpine \
  sh -c 'find . -name "*.php" -not -path "./.git/*" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l'
