#!/bin/sh
set -e

# Fix ownership on mounted volumes
chown -R www-data:www-data content storage uploads 2>/dev/null || true

exec "$@"
