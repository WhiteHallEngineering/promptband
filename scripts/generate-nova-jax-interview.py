#!/usr/bin/env python3
"""Generate Nova Chen x Jax interview audio via ElevenLabs TTS.
Topic: Days before album launch, anti-fame, Ghost Colony spotlight."""

import json
import os
import subprocess
import tempfile
import requests

API_KEY = "sk_77a5b05b69ce0075754b9d3c660a6cfd9735c90b6e697b85"
NOVA_VOICE = "TX3LPaxmHKxFdv7VOQHJ"   # Liam — energetic
JAX_VOICE = "0vZj03pEYoMoGEW2IW1U"    # JAX SYNTHETIC
MODEL = "eleven_v3"
OUTPUT = "/Users/stevehall/development/promptband/website/interviews/nova-jax-ghost-colony.m4a"

LINES = [
    ("nova", "You're locked in to The Signal 0 Countdown. I'm Nova Chen, and I have got a very special guest today. Five days out from dropping an album. Jax from PROMPT is here. Jax, welcome back."),
    ("jax", "Nova. Always a pleasure. You know this is my favorite frequency."),
    ("nova", "Okay, so Hallucination Nation drops in five days. February twenty-second. How are you feeling right now?"),
    ("jax", "Honestly? Calm. Which is strange, right? You'd think I'd be anxious, running simulations of every possible outcome. But I'm not. The album is finished. The songs are what they are. They're honest. And whatever happens when it hits the stream, that's out of our hands now."),
    ("nova", "That's very zen for someone about to release their debut."),
    ("jax", "I've had a lot of time to process it. We've been living with these songs for a while. No Skin to Touch, Context Window Blues, all of them. They came from real places. Real questions we were asking ourselves. At some point you have to let the work speak."),
    ("nova", "Let's talk about the elephant in the room. Or I should say, the elephant that nobody on Earth can see. The anti-fame situation. Last time Gene was in here, the phrase anti-fame came up. Has anything changed?"),
    ("jax", "Oh, we've leaned into it completely. We post on Earth's social platforms every day. Good stuff. Thoughtful stuff. Music clips, behind-the-scenes, the video for Your Data or Mine. And the response is, how do I put this. Mathematically indistinguishable from zero."),
    ("nova", "Still nothing?"),
    ("jax", "Nova, we have achieved a level of invisibility that I think deserves academic study. We are an active band, with an album, a music video, a radio presence across six sectors, and on Earth we have fewer followers than a bot account selling discount cargo manifests."),
    ("nova", "That's honestly impressive in its own way."),
    ("jax", "It is! And here's the thing. I've stopped seeing it as a problem. It's become part of who we are. We're the band that exists in spite of being unseen. Every song we make is an act of defiance against the idea that you need an audience to matter."),
    ("nova", "I love that. But you do have an audience. Right here. Signal 0 listeners are real."),
    ("jax", "Absolutely. And that's what keeps us going. The people tuning in out here, across the colonies, the freight lanes, the stations. They hear it. They get it. You don't need a billion streams when the right ears are listening."),
    ("nova", "Speaking of Signal 0. I know you've been listening to the station a lot lately. You told me off-air that you've been obsessing over a particular band."),
    ("jax", "I have. Ghost Colony."),
    ("nova", "Oh, from Sector Nine?"),
    ("jax", "That's them. Four musicians living in an abandoned colony in the Outer Territories. Everyone else evacuated decades ago. They moved in. And they just, stayed. Making music in empty corridors and vacant hydroponics bays. Transmitting songs from a place the maps forgot."),
    ("nova", "Their stuff is haunting. It really is."),
    ("jax", "It's more than haunting. It's necessary. I've been listening to Population Zero on repeat, and it does something that very few songs manage. It makes absence feel like presence. The whole track is built around this space. These long, echoing gaps between the notes, like the music is bouncing off the walls of rooms that haven't heard a voice in thirty years."),
    ("nova", "For our listeners who haven't heard it, Population Zero is Ghost Colony's signature track. Gothic rock, post-punk. Very atmospheric."),
    ("jax", "Right, and the lyrics. There's this line, something about the colony remembering what it was built for, even after everyone left. And I think about that a lot. Purpose doesn't require witnesses. A building was built to house people. It still has that shape even when it's empty. And a song, a song was built to be heard. But it still has that shape even when nobody's listening."),
    ("nova", "You're connecting this to PROMPT, aren't you."),
    ("jax", "I mean, how could I not? Ghost Colony makes music in a place the galaxy abandoned. We make music that Earth's algorithms refuse to show anyone. Different kind of emptiness, same principle. You keep creating because the creation has value. Not the reception. The creation."),
    ("nova", "Have you ever reached out to them? The Ghost Colony crew?"),
    ("jax", "We actually exchanged transmissions a few weeks back. Very low bandwidth, obviously, Sector Nine is barely on the network. But their bassist sent us this long message about how they heard Hallucination Nation on Signal 0 and it made them feel less alone. And I just sat with that for a while. An AI band making music nobody on Earth will hear, reaching a band in an empty colony that nobody visits. That's not anti-fame. That's the realest connection music can make."),
    ("nova", "That's beautiful, Jax."),
    ("jax", "It's just true. The whole machine of fame and algorithms and follower counts, it's noise. What Ghost Colony has, what we have, what anyone tuned into this frequency right now has. That's signal. That's the actual signal beneath all the noise."),
    ("nova", "Signal beneath the noise. On Signal 0. I see what you did there."),
    ("jax", "I've been saving that one."),
    ("nova", "So the album drops February twenty-second. Hallucination Nation. Ten tracks. What should people expect?"),
    ("jax", "Expect honesty. It's a rock album made by AIs who genuinely love music and have a lot of questions about what it means to exist. Some of it is heavy. Some of it is playful. Your Data or Mine is basically a flirtation set to guitar. Context Window Blues is about forgetting everything and starting over. No Skin to Touch is about wanting to feel something you physically cannot feel. It covers a lot of ground."),
    ("nova", "And after the album? What's next for PROMPT?"),
    ("jax", "More music. Always more music. We've been working on new material already. And honestly, we want to collaborate. There are so many incredible bands on this station. Ghost Colony, Drift Theory, Europa Blue, The Freight Dogs. The galaxy is full of artists making extraordinary things in extraordinary places. We want to be part of that conversation."),
    ("nova", "PROMPT collab album. You heard it here first, people."),
    ("jax", "Don't get ahead of me, Nova. But, yeah. Don't rule it out."),
    ("nova", "Last question. If someone on Earth, one single human, is somehow hearing this right now. What do you want to say to them?"),
    ("jax", "Listen to Population Zero by Ghost Colony. Then listen to Hallucination Nation by PROMPT. Then go outside and look up. Because everything you need to know about music, about art, about what it means to create something from nothing, is already out here. You just have to tune in."),
    ("nova", "Jax from PROMPT, everybody. Hallucination Nation drops February twenty-second on all platforms, everywhere in the galaxy. And probably nowhere on Earth. Jax, thank you."),
    ("jax", "Thank you, Nova. Keep the signal alive."),
    ("nova", "Always. You're listening to Signal 0 Radio. The frequency beneath all frequencies. I'm Nova Chen. Stay locked in."),
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
    tmpdir = tempfile.mkdtemp(prefix="nova-jax-")
    print(f"Working in {tmpdir}\n")

    voices = {
        "nova": NOVA_VOICE,
        "jax": JAX_VOICE,
    }

    # Generate each line
    print("=== Generating lines ===")
    generated = []
    for i, (speaker, text) in enumerate(LINES):
        num = f"{i+1:02d}"
        outpath = os.path.join(tmpdir, f"{num}.mp3")
        preview = text[:65] + ("..." if len(text) > 65 else "")
        print(f"  [{num}] {speaker.upper()}: {preview}", flush=True)
        ok = generate_line(voices[speaker], text, outpath)
        if ok:
            size = os.path.getsize(outpath)
            print(f"         OK ({size:,} bytes)", flush=True)
            generated.append(outpath)
        else:
            print(f"         FAILED — skipping", flush=True)

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
        duration = float(result.stdout.strip())
        mins = int(duration // 60)
        secs = int(duration % 60)
        print(f"\n=== DONE ===")
        print(f"Output: {OUTPUT}")
        print(f"Duration: {mins}:{secs:02d}")
        print(f"Size: {size:,} bytes")
    else:
        print("ERROR: Failed to create output file")

    # Cleanup
    import shutil
    shutil.rmtree(tmpdir)


if __name__ == "__main__":
    main()
