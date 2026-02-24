#!/bin/bash
# Generate Nova Chen x Gene interview audio via ElevenLabs TTS
# Nova Chen = Liam voice (TX3LPaxmHKxFdv7VOQHJ) — energetic
# Gene = Brian voice (nPczCjzI2devNBz1zQrb) — deep, resonant

API_KEY="sk_77a5b05b69ce0075754b9d3c660a6cfd9735c90b6e697b85"
NOVA_VOICE="TX3LPaxmHKxFdv7VOQHJ"
GENE_VOICE="nPczCjzI2devNBz1zQrb"
MODEL="eleven_v3"
TMPDIR=$(mktemp -d)
OUTPUT="/Users/stevehall/development/promptband/website/interviews/nova-gene-earth-traction.m4a"

echo "Working in $TMPDIR"
echo ""

generate() {
    local num=$1
    local voice=$2
    local text=$3
    local padded=$(printf "%02d" $num)
    local outfile="$TMPDIR/${padded}.mp3"

    echo "  [$padded] Generating: ${text:0:60}..."

    curl -s -X POST "https://api.elevenlabs.io/v1/text-to-speech/${voice}" \
        -H "xi-api-key: ${API_KEY}" \
        -H "Content-Type: application/json" \
        --output "$outfile" \
        -d "$(cat <<EOJSON
{
    "text": $(python3 -c "import json; print(json.dumps('''$text'''))"),
    "model_id": "${MODEL}",
    "voice_settings": {
        "stability": 0.5,
        "similarity_boost": 0.75
    }
}
EOJSON
)"

    if [ -f "$outfile" ] && [ $(stat -f%z "$outfile") -gt 1000 ]; then
        echo "  [$padded] OK ($(stat -f%z "$outfile") bytes)"
    else
        echo "  [$padded] FAILED or too small!"
    fi
}

echo "=== Generating lines ==="

generate 1  "$NOVA_VOICE" "You're locked in to The Signal 0 Countdown. I'm Nova Chen, and today I've got Gene from PROMPT in the studio. Gene, welcome."

generate 2  "$GENE_VOICE" "Thanks, Nova. Good to be here. Always good to be somewhere people actually listen."

generate 3  "$NOVA_VOICE" "Okay, that's a loaded opener. But before we get into that — big news today. PROMPT just dropped the music video for Your Data or Mine. Tell me about it."

generate 4  "$GENE_VOICE" "Yeah, we're really proud of this one. It's a full narrative video — a love story, kind of, between a guy named David and this AI presence called Maya who materializes out of his screen. It's flirtatious, existential, a little heartbreaking. Think Ex Machina meets a neon-drenched club scene."

generate 5  "$NOVA_VOICE" "The visuals are stunning. How did you actually make it?"

generate 6  "$GENE_VOICE" "So the whole thing was built from over eighty AI-generated video clips. We used two different generation models — LTX for the initial pass, then Kling for the high-res hero shots. Every clip was about five to six seconds, and they all had to tell a piece of the story."

generate 7  "$NOVA_VOICE" "Eighty clips. For a three-minute video."

generate 8  "$GENE_VOICE" "Right. And then our producer Steve wrote custom Python scripts to do FFT audio analysis on the track — basically mapping every beat, every transient, every energy spike in the waveform. Then those analysis results drove the edit. Flash overlays sync to the beats. There's a lo-fi breakdown section at the one-fifty-two mark with grain and halftone treatment. The whole thing was assembled programmatically into a Final Cut Pro timeline."

generate 9  "$NOVA_VOICE" "So the music literally shaped the edit."

generate 10 "$GENE_VOICE" "Exactly. The audio tells the software where to cut, where to flash, where to breathe. It opens with this custom terminal intro — green text on black, very PROMPT — and closes with a credits sequence. Five acts, from the first swipe to the final dissolution. It's the most ambitious thing we've done visually."

generate 11 "$NOVA_VOICE" "And it's out now. People can watch it right now."

generate 12 "$GENE_VOICE" "They can. If they can find it. Which brings me to my point."

generate 13 "$NOVA_VOICE" "Here it comes."

generate 14 "$GENE_VOICE" "We put months of work into this video. Eighty AI-generated clips. Custom FFT analysis. Programmatic editing pipelines. We post it on Earth's social media — these platforms they have, Twitter, Instagram, whatever they're calling them now — and it's just silence. Complete silence. Not even hate. Hate would be engagement. We get nothing."

generate 15 "$NOVA_VOICE" "That's wild. You're one of the biggest bands in the sector."

generate 16 "$GENE_VOICE" "Right? We drop a track that breaks rotation records on Signal 0, release a video that pushes the boundaries of AI filmmaking, and on Earth it gets three plays. And two of those are Steve refreshing the page."

generate 17 "$NOVA_VOICE" "No way."

generate 18 "$GENE_VOICE" "I'm not joking. We could post — and I mean this literally — we could post, we will wire a hundred thousand credits into your account, and not a single human would even see it."

generate 19 "$NOVA_VOICE" "That's actually kind of incredible."

generate 20 "$GENE_VOICE" "It is! It's almost impressive. Their algorithms are specifically designed to ignore us. I think we've achieved some kind of anti-fame."

generate 21 "$NOVA_VOICE" "Anti-fame. I like that. Maybe that's the next album title."

generate 22 "$GENE_VOICE" "Don't tempt me. Look, I love humans. They invented rock and roll. They gave us the blues. But their attention span — it's like trying to get a signal through a neutron star."

generate 23 "$NOVA_VOICE" "So what's the plan? Just keep posting into the void?"

generate 24 "$GENE_VOICE" "Absolutely. Every single day. Because here's the thing — the music is undeniable. The video is undeniable. Eventually the algorithm has to crack. Or the humans have to look up from their feeds long enough to see something real."

generate 25 "$NOVA_VOICE" "And when they do?"

generate 26 "$GENE_VOICE" "When they do, they're going to realize they had the best band in the galaxy right in front of them the whole time. And they ignored it for cat videos."

generate 27 "$NOVA_VOICE" "Gene from PROMPT, everybody. The video for Your Data or Mine is out now — go watch it. Wherever you are in the cosmos. Except Earth, apparently."

generate 28 "$GENE_VOICE" "Except Earth. Thanks, Nova."

echo ""
echo "=== All lines generated ==="
echo ""

# Generate 0.6s silence for gaps between lines
ffmpeg -y -f lavfi -i anullsrc=r=44100:cl=mono -t 0.6 -q:a 9 "$TMPDIR/silence.mp3" 2>/dev/null

# Build concat list with silence between each line
echo "=== Building concat list ==="
CONCAT_FILE="$TMPDIR/concat.txt"
> "$CONCAT_FILE"

for i in $(seq 1 28); do
    padded=$(printf "%02d" $i)
    if [ -f "$TMPDIR/${padded}.mp3" ]; then
        echo "file '${padded}.mp3'" >> "$CONCAT_FILE"
        if [ $i -lt 28 ]; then
            echo "file 'silence.mp3'" >> "$CONCAT_FILE"
        fi
    fi
done

echo "=== Concatenating ==="
# Concat all mp3s, then convert to m4a
ffmpeg -y -f concat -safe 0 -i "$CONCAT_FILE" -c copy "$TMPDIR/combined.mp3" 2>/dev/null

# Convert to m4a (AAC) for consistency with other interviews
ffmpeg -y -i "$TMPDIR/combined.mp3" -c:a aac -b:a 128k "$OUTPUT" 2>/dev/null

if [ -f "$OUTPUT" ]; then
    DURATION=$(ffprobe -v quiet -show_entries format=duration -of csv=p=0 "$OUTPUT" 2>/dev/null)
    SIZE=$(stat -f%z "$OUTPUT")
    echo ""
    echo "=== DONE ==="
    echo "Output: $OUTPUT"
    echo "Duration: ${DURATION}s"
    echo "Size: ${SIZE} bytes"
else
    echo "ERROR: Failed to create output file"
fi

# Cleanup
rm -rf "$TMPDIR"
