#!/bin/bash
# Signal 0 Radio — Fetch Daily News Bumpers
# Calls promptband.ai to generate space news bumpers, downloads MP3s
# to the local bumper directory for Liquidsoap auto-reload.
#
# Cron: 30 5 * * * /var/www/signal-zero/scripts/fetch-news-bumpers.sh
# (05:30 UTC daily, before Morning Transmission)

API_KEY="pr0mpt-m3ss4g3s-2026"
API_URL="https://promptband.ai/api/signal-zero-news-bumpers.php?key=${API_KEY}&count=3"
BUMPER_DIR="/var/www/signal-zero/audio/signal-zero/bumpers"
M3U_FILE="/etc/liquidsoap/bumpers.m3u"
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

# Check for audio generation failures
AUDIO_STATUS=$(echo "$RESPONSE" | python3 -c "
import sys, json
data = json.load(sys.stdin)
bumpers = data.get('bumpers', [])
total = len(bumpers)
with_audio = sum(1 for b in bumpers if b.get('audioGenerated'))
failed = [b for b in bumpers if not b.get('audioGenerated')]
print(f'{with_audio}/{total}')
for b in failed:
    title = b.get('originalTitle', 'unknown')[:60]
    err = b.get('audioError', 'no audioUrl')
    print(f'AUDIO_FAIL: {title} — {err}')
" 2>/dev/null)

# Log any audio generation failures prominently
AUDIO_FAILS=$(echo "$AUDIO_STATUS" | grep "^AUDIO_FAIL:" || true)
if [ -n "$AUDIO_FAILS" ]; then
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] WARNING: Some bumpers failed audio generation:" >> "$LOG"
    echo "$AUDIO_FAILS" | while IFS= read -r line; do
        echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)]   $line" >> "$LOG"
    done
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
URL_COUNT=0

while IFS= read -r URL; do
    [ -z "$URL" ] && continue
    URL_COUNT=$((URL_COUNT + 1))

    FILENAME=$(basename "$URL")
    DEST="${BUMPER_DIR}/${FILENAME}"
    FULL_URL="https://promptband.ai${URL}"

    curl -s -o "$DEST" "$FULL_URL" -H "Cookie: humans_21909=1" --max-time 30

    if [ -f "$DEST" ] && [ -s "$DEST" ]; then
        DOWNLOADED=$((DOWNLOADED + 1))
        # Add to Liquidsoap bumper playlist if not already present
        if ! grep -qF "$DEST" "$M3U_FILE" 2>/dev/null; then
            echo "$DEST" >> "$M3U_FILE"
        fi
        echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Downloaded: ${FILENAME}" >> "$LOG"
    else
        rm -f "$DEST"
        echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] WARN: Failed to download ${FILENAME} from ${FULL_URL}" >> "$LOG"
    fi
done <<< "$URLS"

TOTAL=$(echo "$RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin).get('totalGenerated', 0))" 2>/dev/null)
TOTAL_NEW=$(echo "$RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin).get('totalNew', '?'))" 2>/dev/null)

if [ "$DOWNLOADED" -eq 0 ] && [ "$TOTAL" -gt 0 ]; then
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] ERROR: 0 downloads but ${TOTAL} generated — likely ElevenLabs credit/API issue" >> "$LOG"
elif [ "$DOWNLOADED" -eq 0 ] && [ "$TOTAL" -eq 0 ] && [ "$TOTAL_NEW" -eq 0 ]; then
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] INFO: No new headlines to process (all previously used)" >> "$LOG"
fi

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Complete: ${DOWNLOADED}/${TOTAL} bumpers downloaded (${URL_COUNT} URLs found, ${TOTAL_NEW} new headlines)" >> "$LOG"
