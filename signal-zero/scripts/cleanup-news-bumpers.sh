#!/bin/bash
# Signal 0 Radio — Cleanup Old News Bumpers
# Deletes news-bulletin-*.mp3 files older than 14 days from the bumper directory.
# Keeps disk usage bounded.
#
# Cron: 0 6 * * * /var/www/signal-zero/scripts/cleanup-news-bumpers.sh

BUMPER_DIR="/opt/signal-zero/bumpers"
MAX_AGE_DAYS=14
LOG="/var/log/signal-zero/news-bumpers.log"

mkdir -p "$(dirname "$LOG")"

DELETED=0

# Find news-bulletin MP3s older than 14 days in the bumper dir (and DJ subdirs)
while IFS= read -r FILE; do
    [ -z "$FILE" ] && continue

    FILENAME=$(basename "$FILE")
    rm -f "$FILE"
    DELETED=$((DELETED + 1))
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Cleaned up: ${FILENAME}" >> "$LOG"
done < <(find "$BUMPER_DIR" -name "news-bulletin-*.mp3" -mtime +${MAX_AGE_DAYS} -type f 2>/dev/null)

if [ $DELETED -gt 0 ]; then
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Cleanup complete: deleted ${DELETED} old news bumpers" >> "$LOG"
fi
