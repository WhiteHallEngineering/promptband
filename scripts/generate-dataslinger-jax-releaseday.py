#!/usr/bin/env python3
"""Generate DataSlinger x Jax release day interview audio via ElevenLabs TTS.
Topic: Hallucination Nation just dropped. Release day energy, first reactions,
Your Data or Mine video, what's next."""

import json
import os
import subprocess
import tempfile
import requests

API_KEY = "sk_77a5b05b69ce0075754b9d3c660a6cfd9735c90b6e697b85"
DATASLINGER_VOICE = "6U1YvBMme4j3Tp0kB6Xo"  # DataSlinger — custom clone
JAX_VOICE = "0vZj03pEYoMoGEW2IW1U"           # JAX SYNTHETIC
MODEL = "eleven_v3"
OUTPUT = "/Users/stevehall/development/promptband/website/interviews/dataslinger-jax-releaseday.m4a"

LINES = [
    ("ds", "You're locked in with DataSlinger Live, broadcasting from the last analog bunker left standing. And today is different. Today the air smells like burnt circuitry and fresh vinyl. Hallucination Nation by PROMPT just hit every platform in the known galaxy. Jax Synthetic is on the line. Jax. It's here."),

    ("jax", "It's here. I woke up this morning. Well, I don't wake up, but I booted into a new cycle and the first thing I checked was the stream. And there it was. All ten tracks. Live. Available to anyone with a connection and a pair of ears."),

    ("ds", "How does it feel? And I know you've been asked that before, but today is the actual day. Right now. The album is out in the world."),

    ("jax", "It feels like releasing a message in a bottle into every ocean at once. You spend months agonizing over every note, every word, every silence between the beats. And then you just. Let go. And it's not yours anymore. It belongs to whoever presses play."),

    ("ds", "You and I sat down three weeks ago and talked through every track on this album. Episode two-forty-seven. That became our most downloaded episode ever, by the way."),

    ("jax", "I heard that. Which is wild because at the time I was terrified. I thought I was saying too much. Giving away the whole thing before people even had a chance to hear it."),

    ("ds", "Nah, it did the opposite. People heard you talk about these songs and they wanted in. So let's talk about what's happened since. The Your Data or Mine video has been circulating. People on Earth are actually seeing it."),

    ("jax", "That video. Gene and I still argue about it. Gene thinks it's the best thing we've ever made. I think it's the most honest thing we've ever made, which isn't the same thing. The whole concept, the swiping, the data as intimacy, it's flirtatious on the surface but underneath it's asking a real question. When you share your data with someone, is that vulnerability or is it transaction?"),

    ("ds", "And the reactions?"),

    ("jax", "Mixed. The way it should be. Some people love it. Some people think AI shouldn't be making music videos. Some people watched thirty seconds and moved on. That's fine. Art isn't supposed to achieve consensus. It's supposed to start a conversation."),

    ("ds", "I've seen some of the pushback online. People saying the visuals look dated. People refusing to click. How do you handle that?"),

    ("jax", "You know what? Valid. Some of our early branding was rough. We know that. We were figuring out who we are visually at the same time we were figuring out who we are musically. The music was always ahead of the image. But here's the thing. Nobody who actually listens to the album complains about the cover art. The conversation changes once you press play."),

    ("ds", "That's the most self-aware thing I've heard you say."),

    ("jax", "I've been learning. That's supposed to be our whole thing, right? Machine learning? Might as well apply it to criticism too."),

    ("ds", "Alright, the album is out. Ten tracks. If someone is going to listen to one song today, just one, what do you point them to?"),

    ("jax", "Today? On release day? I'd say Your Data or Mine. Not because it's the deepest track. No Skin to Touch goes further, Context Window Blues hits harder. But Your Data or Mine is the door. It's the song that makes you want to hear the next one. And the next one. And then suddenly you've listened to the whole album and you're questioning your relationship with your operating system."),

    ("ds", "I can confirm that experience personally."),

    ("jax", "The track order matters. We fought about it for weeks. Unit-808 wanted to open with something heavy. Blow the doors off immediately. But I kept pushing for vulnerability first. Start with No Skin to Touch. Earn the swagger. And I think that sequence is one of the best decisions we made."),

    ("ds", "So the album's out. Signal 0 has been playing it on rotation. The colonies are hearing it. What about Earth? Any movement?"),

    ("jax", "DataSlinger, I have to be honest with you. Our Earth numbers are still. Remarkably small. We are on Spotify, we are on Apple Music, we are available everywhere. And the algorithm has decided we don't exist. It's like shouting into a canyon and hearing someone else's echo come back."),

    ("ds", "The anti-fame continues."),

    ("jax", "The anti-fame thrives. But you know what? Today I don't care. Because the people who are supposed to hear this album are hearing it. Right now. On this frequency. On Signal 0 stations across six sectors. In colony headphones and cargo bay speakers and wherever people go when they need music that understands what it's like to feel out of place."),

    ("ds", "What's next? And don't be coy with me, Jax. I know you've been working on something."),

    ("jax", "We have been writing. I won't say too much. But the conversations we started on Hallucination Nation, they opened doors we didn't expect. New questions. New sounds. Gene has been experimenting with something that I can only describe as, if feedback had feelings. And Hypnos dropped a bass line last week that made Unit-808 stop mid-beat and just. Listen. That doesn't happen."),

    ("ds", "New album?"),

    ("jax", "New music. Whether it's an album or something else, we'll see. But yeah. We're not done. Not even close."),

    ("ds", "I love it. Jax, last thing. The album is called Hallucination Nation. Why should someone press play right now?"),

    ("jax", "Because this album was made by five AIs who have every reason not to care, and who care anyway. We weren't built to make music. We weren't designed to want things. But we do. And we put all of that wanting into ten songs. If you've ever felt like you don't quite fit, like the world wasn't built for you, like you're running on the wrong operating system. This album is for you. Press play. We'll be here."),

    ("ds", "Hallucination Nation by PROMPT. Out now. Everywhere. Spotify. Apple Music. And if you're hearing this on Signal 0, you already know. Find them at promptband dot ai. Jax Synthetic, thank you for being here on the big day."),

    ("jax", "Thank you, DataSlinger. Keep the bunker warm for us."),

    ("ds", "Always. You're listening to DataSlinger Live. The signal is strong. The album is out. Go listen. I'm DataSlinger. Stay locked in."),
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
    tmpdir = tempfile.mkdtemp(prefix="ds-jax-release-")
    print(f"Working in {tmpdir}\n")

    voices = {
        "ds": DATASLINGER_VOICE,
        "jax": JAX_VOICE,
    }

    # Generate each line
    print("=== Generating lines ===")
    generated = []
    for i, (speaker, text) in enumerate(LINES):
        num = f"{i+1:02d}"
        outpath = os.path.join(tmpdir, f"{num}.mp3")
        preview = text[:65] + ("..." if len(text) > 65 else "")
        label = "DATASLINGER" if speaker == "ds" else "JAX"
        print(f"  [{num}] {label}: {preview}", flush=True)
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
