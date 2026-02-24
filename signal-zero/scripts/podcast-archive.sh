#!/bin/bash
# Signal 0 Radio — Podcast Auto-Archive
# Moves aired podcast episodes from show directories to archive,
# and registers them in the podcast API.
#
# Uses find to locate files at any depth (prevents nested played/ buildup).
#
# Run via cron after each show's timeslot ends:
#   1 10 * * * /var/www/signal-zero/scripts/podcast-archive.sh morning-transmission dataslinger "The Morning Transmission"
#   1 14 * * * /var/www/signal-zero/scripts/podcast-archive.sh signal-boost nova-chen "Signal Boost"
#   1 18 * * * /var/www/signal-zero/scripts/podcast-archive.sh the-amplifier raz-static "The Amplifier"
#   1 21 * * * /var/www/signal-zero/scripts/podcast-archive.sh long-frequency vex-kasra "The Long Frequency"
#   1  2 * * * /var/www/signal-zero/scripts/podcast-archive.sh after-dark dex-midnight "After Dark"
#   1  6 * * * /var/www/signal-zero/scripts/podcast-archive.sh the-vault the-archivist "The Vault"

SHOW_DIR="$1"
XJ_SLUG="$2"
SHOW_NAME="$3"
API_KEY="pr0mpt-m3ss4g3s-2026"
API_URL="https://promptband.ai/api/signal-zero-podcasts.php?key=${API_KEY}"
PODCAST_BASE="/var/www/signal-zero/audio/podcasts"
ARCHIVE_DIR="${PODCAST_BASE}/archive"
LOG="/var/log/signal-zero/podcast-archive.log"

if [ -z "$SHOW_DIR" ] || [ -z "$XJ_SLUG" ] || [ -z "$SHOW_NAME" ]; then
    echo "Usage: $0 <show-dir> <xj-slug> <show-name>"
    exit 1
fi

SOURCE="${PODCAST_BASE}/${SHOW_DIR}"

# Find all audio files at any depth within the show directory
FILES=$(find "$SOURCE" \( -name "*.mp3" -o -name "*.m4a" \) -type f 2>/dev/null)

if [ -z "$FILES" ]; then
    exit 0  # Nothing to archive
fi

mkdir -p "$ARCHIVE_DIR" "$(dirname "$LOG")"

echo "$FILES" | while IFS= read -r FILE; do
    [ -z "$FILE" ] && continue

    FILENAME=$(basename "$FILE")
    EXT="${FILENAME##*.}"
    DATE=$(date -u +%Y-%m-%d)
    BASENAME="${FILENAME%.*}"

    # Get duration via ffprobe
    DURATION=$(ffprobe -v quiet -show_entries format=duration -of csv=p=0 "$FILE" 2>/dev/null)
    if [ -n "$DURATION" ]; then
        MINS=$(echo "$DURATION" | awk '{printf "%d", $1/60}')
        SECS=$(echo "$DURATION" | awk '{printf "%02d", $1%60}')
        DURATION_FMT="${MINS}:${SECS}"
    else
        DURATION_FMT="unknown"
    fi

    # Generate episode ID
    EP_ID="${SHOW_DIR}-${DATE}-${BASENAME}"

    # Move to archive
    mv "$FILE" "${ARCHIVE_DIR}/${FILENAME}"

    # Build archive URL (served by nginx)
    AUDIO_URL="https://signal0radio.com/audio/podcasts/archive/${FILENAME}"

    # Register in podcast API
    curl -s -X POST "$API_URL" \
        -H "Content-Type: application/json" \
        -H "Cookie: humans_21909=1" \
        -d "{
            \"action\": \"register\",
            \"id\": \"${EP_ID}\",
            \"show\": \"${SHOW_DIR}\",
            \"xj\": \"${XJ_SLUG}\",
            \"title\": \"${SHOW_NAME} — ${BASENAME}\",
            \"description\": \"Aired ${DATE} on Signal 0 Radio.\",
            \"audioUrl\": \"${AUDIO_URL}\",
            \"duration\": \"${DURATION_FMT}\",
            \"airDate\": \"${DATE}\"
        }" > /dev/null 2>&1

    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Archived: ${FILENAME} -> ${ARCHIVE_DIR}/ (${DURATION_FMT}, registered as ${EP_ID})" >> "$LOG"
done

# Clean up any empty played/ directories left behind
find "$SOURCE" -name "played" -type d -empty -delete 2>/dev/null
