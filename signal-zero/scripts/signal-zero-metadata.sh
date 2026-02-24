#!/bin/bash
# Signal 0 Radio — Metadata updater
# Watches Liquidsoap log for track changes and updates Icecast metadata
# Runs as a systemd service alongside Liquidsoap

LOGFILE="/var/log/liquidsoap/radio.log"
ICECAST_ADMIN="http://admin:s1gn4l-z3r0-adm1n@127.0.0.1:8000/admin/metadata"
NOWPLAYING_API="https://promptband.ai/api/signal-zero-nowplaying.php?key=pr0mpt-m3ss4g3s-2026"
LAST_SONG=""

slug_to_title() {
    echo "$1" | sed 's/-/ /g' | awk '{for(i=1;i<=NF;i++) $i=toupper(substr($i,1,1)) substr($i,2)}1'
}

update_metadata() {
    local song="$1"
    local title="$2"
    local artist="$3"
    if [ "$song" != "$LAST_SONG" ] && [ -n "$song" ]; then
        LAST_SONG="$song"
        encoded=$(python3 -c "import urllib.parse; print(urllib.parse.quote('$song'))")
        curl -s "$ICECAST_ADMIN?mount=/stream&mode=updinfo&song=$encoded" > /dev/null 2>&1
        # Also update the now-playing API
        if [ -n "$title" ] && [ -n "$artist" ]; then
            curl -s -X POST "$NOWPLAYING_API" \
                -H "Content-Type: application/json" \
                -H "Cookie: humans_21909=1" \
                -d "{\"title\":\"$title\",\"band\":\"$artist\"}" > /dev/null 2>&1
        fi
        echo "$(date '+%Y-%m-%d %H:%M:%S') Updated: $song"
    fi
}

echo "Signal 0 Radio metadata updater started"

# Wait for log file to exist
while [ ! -f "$LOGFILE" ]; do
    sleep 1
done

# Monitor log file for actual track playback events
# "Prepared" from songs_m3u/bumpers_m3u fires when a track is queued for
# immediate playback (after previous track ends), NOT during pre-buffering.
tail -F "$LOGFILE" 2>/dev/null | while read -r line; do
    # Match: [songs_m3u:3] Prepared "/path/to/song.mp3" (RID N).
    if echo "$line" | grep -qE '\[songs_m3u:[0-9]\] Prepared '; then
        filepath=$(echo "$line" | grep -oP '(?<=")/var/www/signal-zero/audio/signal-zero/[^"]+')
        if [ -n "$filepath" ]; then
            filename=$(basename "$filepath" .mp3)
            dirname=$(basename $(dirname "$filepath"))
            artist=$(slug_to_title "$dirname")
            # Strip leading track numbers (e.g. "08-censored-shadow" -> "censored-shadow")
            clean_name=$(echo "$filename" | sed 's/^[0-9]*-//')
            title=$(slug_to_title "$clean_name")
            update_metadata "$artist - $title" "$title" "$artist"
        fi
    # Match: [bumpers_m3u:3] Prepared "/path/to/bumper.mp3" (RID N).
    elif echo "$line" | grep -qE '\[bumpers_m3u:[0-9]\] Prepared '; then
        update_metadata "Signal 0 Radio" "" ""
    fi
done
