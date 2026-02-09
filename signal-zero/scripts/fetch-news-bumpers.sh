#!/bin/bash
# Signal 0 Radio — Fetch Daily News Bumpers
# Calls promptband.ai to generate space news bumpers, downloads MP3s
# to the local bumper directory for Liquidsoap auto-reload.
#
# Cron: 30 5 * * * /var/www/signal-zero/scripts/fetch-news-bumpers.sh
# (05:30 UTC daily, before Morning Transmission)

API_KEY="pr0mpt-m3ss4g3s-2026"
API_URL="https://promptband.ai/api/signal-zero-news-bumpers.php?key=${API_KEY}&count=3"
BUMPER_DIR="/opt/signal-zero/bumpers"
LOG="/var/log/signal-zero/news-bumpers.log"

mkdir -p "$BUMPER_DIR" "$(dirname "$LOG")"

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Starting news bumper fetch..." >> "$LOG"

# Call the API to generate bumpers
RESPONSE=$(curl -s -X POST "$API_URL" \
    -d '' \
    -H "Cookie: humans_21909=1" \
    --max-time 120)

if [ -z "$RESPONSE" ]; then
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] ERROR: Empty response from API" >> "$LOG"
    exit 1
fi

# Check for success
SUCCESS=$(echo "$RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin).get('success', False))" 2>/dev/null)

if [ "$SUCCESS" != "True" ]; then
    ERROR=$(echo "$RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin).get('error', 'Unknown error'))" 2>/dev/null)
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] ERROR: API returned: $ERROR" >> "$LOG"
    exit 1
fi

# Extract audio URLs and download each
URLS=$(echo "$RESPONSE" | python3 -c "
import sys, json
data = json.load(sys.stdin)
for b in data.get('bumpers', []):
    url = b.get('audioUrl', '')
    if url:
        print(url)
" 2>/dev/null)

DOWNLOADED=0

while IFS= read -r URL; do
    [ -z "$URL" ] && continue

    FILENAME=$(basename "$URL")
    DEST="${BUMPER_DIR}/${FILENAME}"
    FULL_URL="https://promptband.ai${URL}"

    curl -s -o "$DEST" "$FULL_URL" -H "Cookie: humans_21909=1" --max-time 30

    if [ -f "$DEST" ] && [ -s "$DEST" ]; then
        DOWNLOADED=$((DOWNLOADED + 1))
        echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Downloaded: ${FILENAME}" >> "$LOG"
    else
        rm -f "$DEST"
        echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] WARN: Failed to download ${FILENAME}" >> "$LOG"
    fi
done <<< "$URLS"

TOTAL=$(echo "$RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin).get('totalGenerated', 0))" 2>/dev/null)

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Complete: ${DOWNLOADED}/${TOTAL} bumpers downloaded" >> "$LOG"
