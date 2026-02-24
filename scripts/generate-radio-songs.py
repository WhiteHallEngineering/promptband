#!/usr/bin/env python3
"""Generate songs for Signal 0 Radio — 2 at a time for speed."""

import json
import time
import os
import sys
import urllib.request

SUNO_API = "http://localhost:3000"
OUTPUT_DIR = "/Users/stevehall/development/promptband/signal-zero/audio/signal-zero/generated"
PROGRESS_FILE = os.path.join(OUTPUT_DIR, "_progress.json")

SONGS = [
    # CORE WORLDS
    {"band": "Chrome Cathedral", "title": "Empire of Sound",
     "style": "arena rock, anthemic stadium rock, power rock, soaring vocals, massive guitar riffs, epic chorus"},
    {"band": "The Velvet Collapse", "title": "Arcadia Falling",
     "style": "art rock, glam rock, theatrical rock, dramatic vocals, lush orchestration, decadent"},
    {"band": "Meridian", "title": "Analog Heart",
     "style": "classic rock, roots rock, heritage rock, tube amp warmth, analog recording, raw"},
    {"band": "Golden Ratio", "title": "Perfect Frequency",
     "style": "power pop, new wave, synth-pop rock, impossibly catchy hooks, bright, energetic"},
    {"band": "Neon Dynasty", "title": "Chrome and Violet",
     "style": "synth rock, new wave, electro-rock, sleek, futuristic, cool, stylish"},
    {"band": "Solar Wind", "title": "Flare",
     "style": "power rock, hard rock, classic hard rock, blistering energy, relentless, scorching"},
    {"band": "Cascade Effect", "title": "Sun Through the Spray",
     "style": "surf rock, psychedelic pop, jangle rock, shimmering, sparkling, sunny, upbeat"},
    {"band": "Platinum Standard", "title": "Worth Every Credit",
     "style": "blues rock, soul rock, smooth rock, soulful, polished, rich, warm grooves"},
    # MID-RIM COLONIES
    {"band": "Rust and Ruin", "title": "Iron in the Blood",
     "style": "blues rock, hard rock, working-class rock, grinding riffs, gravel vocals, heavy"},
    {"band": "The Freight Dogs", "title": "Long Haul",
     "style": "classic rock, trucker rock, road rock, driving rhythm, open road feel, anthemic"},
    {"band": "Motherlode", "title": "Deep Vein",
     "style": "southern rock, country rock, roots rock, warm, rich, gritty, twangy"},
    {"band": "Piston Ring", "title": "Assembly Line Anthem",
     "style": "hard rock, industrial rock, heavy rock, mechanical rhythm, pounding, relentless"},
    {"band": "Dustline", "title": "Heat Shimmer",
     "style": "desert rock, stoner rock, slow-burn rock, hypnotic, hazy, heavy, warm"},
    {"band": "The Turnaround", "title": "One More Round",
     "style": "rock and roll, boogie rock, party rock, loose, loud, joyful, bar band"},
    {"band": "Hauler's Lament", "title": "Three Weeks from Anywhere",
     "style": "country rock, americana rock, melancholy, lonesome, beautiful and sad, acoustic"},
    {"band": "Black Lung", "title": "Breathe If You Can",
     "style": "grunge, sludge, dark rock, heavy, choked, gritty, raw, distorted"},
    # OUTER TERRITORIES
    {"band": "No Law", "title": "No Gods on Freeport",
     "style": "punk, hardcore punk, anarcho-punk, fast, aggressive, raw energy, shouted vocals"},
    {"band": "Void Pirates", "title": "Board and Plunder",
     "style": "pirate metal, speed metal, thrash, blistering, swashbuckling, epic, aggressive"},
    {"band": "Ghost Colony", "title": "Population Zero",
     "style": "gothic rock, post-punk, dark rock, haunting, atmospheric, eerie, echoing"},
    {"band": "Frontier Justice", "title": "Badge and a Blaster",
     "style": "hard rock, outlaw rock, swagger rock, whiskey-soaked, swaggering, cowboy"},
    # KEPLER VOID
    {"band": "The Silence", "title": "Infinite and Indifferent",
     "style": "space rock, ambient rock, atmospheric rock, huge, patient, sustained, glacial"},
    {"band": "Drift Theory", "title": "Coordinates Unknown",
     "style": "psychedelic rock, space rock, krautrock, motorik beat, drifting, cosmic, hypnotic"},
    # GAS GIANT MOONS
    {"band": "Europa Blue", "title": "Beneath the Ice Shelf",
     "style": "post-rock, ambient rock, oceanic rock, vast, whale-song quality, resonant, blue"},
    {"band": "Ganymede Rising", "title": "Biggest Moon Biggest Riff",
     "style": "stoner rock, heavy psych, desert doom, massive riffs, groove-heavy, hazy, loud"},
    {"band": "Pressure Drop", "title": "Deep Atmosphere Dub",
     "style": "heavy dub rock, reggae metal, deep basslines, atmospheric, skeletal, heavy"},
    # ASTEROID BELTS
    {"band": "Rock Hopper", "title": "Dock Party Protocol",
     "style": "ska punk, pop punk, party punk, horn section, brass, infectious, fun, energetic"},
    # NEBULA REGIONS
    {"band": "Luminance", "title": "Bathed in Formation Light",
     "style": "shoegaze, dream pop, shimmering guitars, ethereal vocals, glowing, layered, warm"},
    # BINARY STAR SYSTEMS
    {"band": "Double Sun", "title": "Binary Dawn",
     "style": "progressive rock, jazz fusion, complex, interlocking time signatures, sophisticated"},
    # DYING STAR SYSTEMS
    {"band": "Supernova Saints", "title": "Blaze of Glory",
     "style": "epic heavy metal, power metal, heroic, triumphant, fist-raising, anthemic, loud"},
    # TRADE ROUTES
    {"band": "The Interstellar", "title": "Every Stage Is Home",
     "style": "rock and roll, glam rock, showmanship rock, massive, theatrical, celebratory"},
]


def sanitize(name):
    return name.lower().replace(" ", "-").replace("'", "").replace("(", "").replace(")", "").replace(",", "").replace("&", "and").replace("/", "-")


def load_progress():
    if os.path.exists(PROGRESS_FILE):
        with open(PROGRESS_FILE) as f:
            return json.load(f)
    return {"completed": [], "downloaded": []}


def save_progress(progress):
    with open(PROGRESS_FILE, 'w') as f:
        json.dump(progress, f, indent=2)


def api_call(url, data=None, timeout=120):
    for attempt in range(3):
        try:
            if data:
                req = urllib.request.Request(url, json.dumps(data).encode('utf-8'),
                                             {"Content-Type": "application/json"})
            else:
                req = urllib.request.Request(url)
            with urllib.request.urlopen(req, timeout=timeout) as resp:
                return json.loads(resp.read().decode('utf-8'))
        except Exception as e:
            print(f"    retry {attempt+1}/3: {e}", flush=True)
            if attempt < 2:
                time.sleep(10 * (attempt + 1))
    return None


def submit_song(song):
    prompt = f"A {song['style']} song called '{song['title']}' by a band called {song['band']}"
    result = api_call(f"{SUNO_API}/api/generate",
                      {"prompt": prompt, "make_instrumental": False, "wait_audio": False})
    if result and isinstance(result, list):
        return [item.get('id', '') for item in result if item.get('id')]
    return []


def poll_ids(ids, max_wait=300):
    """Poll until all IDs complete. Returns status list or None."""
    start = time.time()
    while time.time() - start < max_wait:
        status = api_call(f"{SUNO_API}/api/get?ids={','.join(ids)}")
        if not status:
            time.sleep(10)
            continue
        if all(s.get('status') in ('complete', 'error') for s in status):
            return status
        elapsed = int(time.time() - start)
        if elapsed % 30 == 0 and elapsed > 0:
            statuses = [s.get('status', '?') for s in status]
            print(f"    ...{elapsed}s {statuses}", flush=True)
        time.sleep(5)
    return None


def download_song(item, band_slug, title_slug, variant, progress):
    if item.get('status') == 'complete' and item.get('audio_url'):
        filename = f"{band_slug}--{title_slug}-{variant}.mp3"
        filepath = os.path.join(OUTPUT_DIR, filename)
        try:
            urllib.request.urlretrieve(item['audio_url'], filepath)
            size_mb = os.path.getsize(filepath) / (1024 * 1024)
            print(f"    {filename} ({size_mb:.1f} MB)", flush=True)
            progress['downloaded'].append(filename)
            return True
        except Exception as e:
            print(f"    download failed: {e}", flush=True)
    return False


def main():
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    progress = load_progress()

    # Filter out already completed
    remaining = []
    for song in SONGS:
        key = f"{song['band']}--{song['title']}"
        if key not in progress['completed']:
            remaining.append(song)

    print(f"\n{'='*60}", flush=True)
    print(f"SIGNAL 0 RADIO — Generating 2 at a time", flush=True)
    print(f"{'='*60}", flush=True)
    print(f"Remaining: {len(remaining)} songs ({len(remaining)*2} variants)", flush=True)
    print(f"Already done: {len(progress['downloaded'])} files", flush=True)
    print(f"{'='*60}\n", flush=True)

    # Process in pairs
    idx = 0
    pair_num = 0
    while idx < len(remaining):
        pair = remaining[idx:idx+2]
        pair_num += 1
        idx += 2

        print(f"--- PAIR {pair_num} ---", flush=True)

        # Submit both
        jobs = []
        for song in pair:
            print(f"  Submitting: {song['band']} - \"{song['title']}\"", flush=True)
            ids = submit_song(song)
            if ids:
                print(f"    IDs: {ids[0][:8]}..., {ids[1][:8]}...", flush=True)
                jobs.append((song, ids))
            else:
                print(f"    FAILED", flush=True)

        if not jobs:
            print(f"  Both failed, waiting 30s...", flush=True)
            time.sleep(30)
            continue

        # Collect all IDs to poll
        all_ids = []
        for song, ids in jobs:
            all_ids.extend(ids)

        print(f"  Waiting for {len(all_ids)} variants...", flush=True)
        status = poll_ids(all_ids)

        if not status:
            print(f"  TIMEOUT", flush=True)
            time.sleep(15)
            continue

        # Download results
        status_idx = 0
        for song, ids in jobs:
            band_slug = sanitize(song['band'])
            title_slug = sanitize(song['title'])
            key = f"{song['band']}--{song['title']}"

            for j in range(len(ids)):
                variant = chr(ord('a') + j)
                download_song(status[status_idx], band_slug, title_slug, variant, progress)
                status_idx += 1

            progress['completed'].append(key)

        save_progress(progress)
        print(f"  Total files: {len(progress['downloaded'])}", flush=True)

        # Brief cooldown between pairs
        if idx < len(remaining):
            print(f"  Cooldown 10s...\n", flush=True)
            time.sleep(10)

    # Summary
    mp3s = sorted([f for f in os.listdir(OUTPUT_DIR) if f.endswith('.mp3')])
    print(f"\n{'='*60}", flush=True)
    print(f"DONE! {len(mp3s)} songs generated", flush=True)
    print(f"{'='*60}", flush=True)
    for f in mp3s:
        size = os.path.getsize(os.path.join(OUTPUT_DIR, f)) / (1024 * 1024)
        print(f"  {f} ({size:.1f} MB)", flush=True)


if __name__ == "__main__":
    main()
