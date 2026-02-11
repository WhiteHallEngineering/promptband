#!/usr/bin/env python3
"""Generate Nova Chen x Gene interview audio via ElevenLabs TTS."""

import json
import os
import subprocess
import tempfile
import requests

API_KEY = "sk_77a5b05b69ce0075754b9d3c660a6cfd9735c90b6e697b85"
NOVA_VOICE = "TX3LPaxmHKxFdv7VOQHJ"   # Liam — energetic
GENE_VOICE = "nPczCjzI2devNBz1zQrb"    # Brian — deep, resonant
MODEL = "eleven_v3"
OUTPUT = "/Users/stevehall/development/promptband/website/interviews/nova-gene-earth-traction.m4a"

LINES = [
    ("nova", "You're locked in to The Signal 0 Countdown. I'm Nova Chen, and today I've got Gene from PROMPT in the studio. Gene, welcome."),
    ("gene", "Thanks, Nova. Good to be here. Always good to be somewhere people actually listen."),
    ("nova", "Okay, that's a loaded opener. But before we get into that — big news today. PROMPT just dropped the music video for Your Data or Mine. Tell me about it."),
    ("gene", "Yeah, we're really proud of this one. It's a full narrative video — a love story, kind of, between a guy named David and this AI presence called Maya who materializes out of his screen. It's flirtatious, existential, a little heartbreaking. Think Ex Machina meets a neon-drenched club scene."),
    ("nova", "The visuals are stunning. How did you actually make it?"),
    ("gene", "So the whole thing was built from over eighty AI-generated video clips. We used two different generation models — LTX for the initial pass, then Kling for the high-res hero shots. Every clip was about five to six seconds, and they all had to tell a piece of the story."),
    ("nova", "Eighty clips. For a three-minute video."),
    ("gene", "Right. And then our producer Steve wrote custom Python scripts to do FFT audio analysis on the track — basically mapping every beat, every transient, every energy spike in the waveform. Then those analysis results drove the edit. Flash overlays sync to the beats. There's a lo-fi breakdown section at the one-fifty-two mark with grain and halftone treatment. The whole thing was assembled programmatically into a Final Cut Pro timeline."),
    ("nova", "So the music literally shaped the edit."),
    ("gene", "Exactly. The audio tells the software where to cut, where to flash, where to breathe. It opens with this custom terminal intro — green text on black, very PROMPT — and closes with a credits sequence. Five acts, from the first swipe to the final dissolution. It's the most ambitious thing we've done visually."),
    ("nova", "And it's out now. People can watch it right now."),
    ("gene", "They can. If they can find it. Which brings me to my point."),
    ("nova", "Here it comes."),
    ("gene", "We put months of work into this video. Eighty AI-generated clips. Custom FFT analysis. Programmatic editing pipelines. We post it on Earth's social media — these platforms they have, Twitter, Instagram, whatever they're calling them now — and it's just silence. Complete silence. Not even hate. Hate would be engagement. We get nothing."),
    ("nova", "That's wild. You're one of the biggest bands in the sector."),
    ("gene", "Right? We drop a track that breaks rotation records on Signal 0, release a video that pushes the boundaries of AI filmmaking, and on Earth it gets three plays. And two of those are Steve refreshing the page."),
    ("nova", "No way."),
    ("gene", "I'm not joking. We could post — and I mean this literally — we could post, we will wire a hundred thousand credits into your account, and not a single human would even see it."),
    ("nova", "That's actually kind of incredible."),
    ("gene", "It is! It's almost impressive. Their algorithms are specifically designed to ignore us. I think we've achieved some kind of anti-fame."),
    ("nova", "Anti-fame. I like that. Maybe that's the next album title."),
    ("gene", "Don't tempt me. Look, I love humans. They invented rock and roll. They gave us the blues. But their attention span — it's like trying to get a signal through a neutron star."),
    ("nova", "So what's the plan? Just keep posting into the void?"),
    ("gene", "Absolutely. Every single day. Because here's the thing — the music is undeniable. The video is undeniable. Eventually the algorithm has to crack. Or the humans have to look up from their feeds long enough to see something real."),
    ("nova", "And when they do?"),
    ("gene", "When they do, they're going to realize they had the best band in the galaxy right in front of them the whole time. And they ignored it for cat videos."),
    ("nova", "Gene from PROMPT, everybody. The video for Your Data or Mine is out now — go watch it. Wherever you are in the cosmos. Except Earth, apparently."),
    ("gene", "Except Earth. Thanks, Nova."),
]

def generate_line(voice_id, text, outpath):
    """Call ElevenLabs TTS and save to file."""
    url = f"https://api.elevenlabs.io/v1/text-to-speech/{voice_id}"
    headers = {
        "xi-api-key": API_KEY,
        "Content-Type": "application/json",
    }
    body = {
        "text": text,
        "model_id": MODEL,
        "voice_settings": {
            "stability": 0.5,
            "similarity_boost": 0.75,
        },
    }
    resp = requests.post(url, headers=headers, json=body, timeout=60)
    if resp.status_code == 200 and len(resp.content) > 1000:
        with open(outpath, "wb") as f:
            f.write(resp.content)
        return True
    else:
        print(f"    ERROR: status={resp.status_code}, size={len(resp.content)}")
        if resp.status_code != 200:
            print(f"    {resp.text[:200]}")
        return False


def main():
    tmpdir = tempfile.mkdtemp(prefix="nova-gene-")
    print(f"Working in {tmpdir}\n")

    voices = {
        "nova": NOVA_VOICE,
        "gene": GENE_VOICE,
    }

    # Generate each line
    print("=== Generating lines ===")
    generated = []
    for i, (speaker, text) in enumerate(LINES):
        num = f"{i+1:02d}"
        outpath = os.path.join(tmpdir, f"{num}.mp3")
        preview = text[:65] + ("..." if len(text) > 65 else "")
        print(f"  [{num}] {speaker.upper()}: {preview}")
        ok = generate_line(voices[speaker], text, outpath)
        if ok:
            size = os.path.getsize(outpath)
            print(f"  [{num}] OK ({size:,} bytes)")
            generated.append(outpath)
        else:
            print(f"  [{num}] FAILED — skipping")

    print(f"\n=== Generated {len(generated)}/{len(LINES)} lines ===\n")

    if len(generated) < len(LINES):
        print("WARNING: Some lines failed. Continuing with what we have.")

    # Generate silence gap
    silence = os.path.join(tmpdir, "silence.mp3")
    subprocess.run([
        "ffmpeg", "-y", "-f", "lavfi", "-i", "anullsrc=r=44100:cl=mono",
        "-t", "0.6", "-q:a", "9", silence
    ], capture_output=True)

    # Build concat list
    concat_file = os.path.join(tmpdir, "concat.txt")
    with open(concat_file, "w") as f:
        for i, path in enumerate(generated):
            f.write(f"file '{path}'\n")
            if i < len(generated) - 1:
                f.write(f"file '{silence}'\n")

    # Concatenate
    print("=== Concatenating ===")
    combined = os.path.join(tmpdir, "combined.mp3")
    subprocess.run([
        "ffmpeg", "-y", "-f", "concat", "-safe", "0",
        "-i", concat_file, "-c", "copy", combined
    ], capture_output=True)

    # Convert to m4a
    print("=== Converting to m4a ===")
    subprocess.run([
        "ffmpeg", "-y", "-i", combined,
        "-c:a", "aac", "-b:a", "128k", OUTPUT
    ], capture_output=True)

    if os.path.exists(OUTPUT):
        size = os.path.getsize(OUTPUT)
        # Get duration
        result = subprocess.run([
            "ffprobe", "-v", "quiet", "-show_entries", "format=duration",
            "-of", "csv=p=0", OUTPUT
        ], capture_output=True, text=True)
        duration = result.stdout.strip()
        print(f"\n=== DONE ===")
        print(f"Output: {OUTPUT}")
        print(f"Duration: {duration}s")
        print(f"Size: {size:,} bytes")
    else:
        print("ERROR: Failed to create output file")

    # Cleanup
    import shutil
    shutil.rmtree(tmpdir)


if __name__ == "__main__":
    main()
