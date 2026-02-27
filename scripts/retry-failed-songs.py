#!/usr/bin/env python3
"""Retry failed song generations."""

import json
import os
import sys
import time
import urllib.request

SUNO_API = "http://localhost:3000"
DOWNLOAD_DIR = "/tmp/signal-zero-music"
MODEL = "chirp-crow"

FAILED = [
    ("the-velvet-collapse", "Curtain Call at the End of the World", "art rock, glam rock, theatrical rock"),
    ("the-velvet-collapse", "Velvet, Darling", "art rock, glam rock, theatrical rock"),
    ("starless", "Planet Without a Dawn", "Black metal, dark ambient rock, void metal"),
    ("recursive", "The Loop That Knows It Loops", "Glitch rock, experimental electronic, fractal rock"),
    ("double-shifts", "Borrowed Gear", "garage rock, punk rock, raw rock"),
    ("dead-reckoning", "The Navigator's Last Entry", "Doom rock, heavy blues, dark blues"),
    ("dead-reckoning", "Rust Belt of the Void", "Doom rock, heavy blues, dark blues"),
    ("dead-reckoning", "Engines Cold for Centuries", "Doom rock, heavy blues, dark blues"),
    ("color-theory", "The Canvas Breathes", "Art rock, post-punk, visual rock"),
    ("absolute-zero", "Cold Beyond Measurement", "Post-metal, drone rock, cold rock"),
    ("echo-cartridge", "Transmitted Through Everything", "Lo-fi rock, tape-hiss rock, transmission rock"),
    ("port-call", "Port Call (One More Round)", "yacht rock, tropical rock, smooth rock, soft rock"),
]

def slugify(title):
    slug = title.lower()
    for ch in ["(", ")", ",", ".", "'", '"', ":", "\u2014", "\u2013"]:
        slug = slug.replace(ch, "")
    slug = slug.replace(" ", "-")
    while "--" in slug:
        slug = slug.replace("--", "-")
    return slug.strip("-")

def generate_and_download(band_slug, title, style):
    song_slug = slugify(title)
    print("[" + band_slug + "] " + title)

    payload = json.dumps({
        "prompt": title + " \u2014 " + style,
        "mv": MODEL,
        "title": title,
        "tags": style,
    }).encode()

    req = urllib.request.Request(
        SUNO_API + "/api/generate",
        data=payload,
        headers={"Content-Type": "application/json"},
    )

    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            data = json.loads(resp.read())
            if isinstance(data, list):
                ids = [clip["id"] for clip in data]
            elif isinstance(data, dict) and "clips" in data:
                ids = [clip["id"] for clip in data["clips"]]
            else:
                print("  Unexpected response")
                return False
            print("  Submitted: " + ", ".join(ids))
    except Exception as e:
        print("  FAILED: " + str(e))
        return False

    # Poll
    id_str = ",".join(ids)
    for _ in range(30):
        time.sleep(10)
        try:
            url = SUNO_API + "/api/get?ids=" + id_str
            with urllib.request.urlopen(url, timeout=30) as resp:
                clips = json.loads(resp.read())
            if all(c.get("status") in ("complete", "error", "failed") for c in clips):
                break
        except:
            pass

    # Download first complete clip
    try:
        url = SUNO_API + "/api/get?ids=" + id_str
        with urllib.request.urlopen(url, timeout=30) as resp:
            clips = json.loads(resp.read())
        for clip in clips:
            if clip.get("status") == "complete":
                band_dir = os.path.join(DOWNLOAD_DIR, band_slug)
                os.makedirs(band_dir, exist_ok=True)
                filepath = os.path.join(band_dir, song_slug + ".mp3")
                cdn_url = "https://cdn1.suno.ai/" + clip["id"] + ".mp3"
                urllib.request.urlretrieve(cdn_url, filepath)
                size = os.path.getsize(filepath) / (1024*1024)
                print("  Downloaded: {:.1f} MB".format(size))
                return True
    except Exception as e:
        print("  Download error: " + str(e))

    print("  No completed clip")
    return False

success = 0
for band_slug, title, style in FAILED:
    if generate_and_download(band_slug, title, style):
        success += 1
    time.sleep(3)

print("\nRetried: " + str(success) + "/" + str(len(FAILED)) + " succeeded")
