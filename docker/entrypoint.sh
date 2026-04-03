#!/bin/sh
set -e

# Fix ownership on mounted volumes
# Only changes files not already owned by www-data (faster than chown -R on every start)
for dir in content storage uploads; do
    if [ -d "$dir" ]; then
        find "$dir" ! -user www-data -exec chown www-data:www-data {} + 2>/dev/null \
            || echo "Warning: could not fix ownership for $dir" >&2
    fi
done

exec "$@"
