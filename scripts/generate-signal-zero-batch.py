#!/usr/bin/env python3
"""
Signal 0 Radio — Full Music Generation Pipeline

Generates music via Suno API and completes the ENTIRE workflow per song:
  1. Generate via Suno API (2 variants per song)
  2. Poll for completion
  3. Download mp3 from CDN
  4. Capture lyrics from Suno response
  5. Upload to Signal 0 server (Lightsail)
  6. Add to Liquidsoap songs.m3u
  7. Update signal-zero-songs.json (audioUrl, audioStatus, lyrics)
  8. Update signal-zero-bands.json (songStats)

Usage:
    python3 scripts/generate-signal-zero-batch.py --band SLUG [--dry-run]
    python3 scripts/generate-signal-zero-batch.py --all [--dry-run]

Reads band/song data from the admin DB on Bluehost, so no hardcoded song lists needed.
"""

import json
import os
import subprocess
import sys
import time
import urllib.request
import urllib.error

SUNO_API = "http://localhost:3000"
SUNO_MODEL = "chirp-crow"
DOWNLOAD_DIR = "/tmp/signal-zero-music"

# Server config
LIGHTSAIL_KEY = os.path.expanduser("~/development/hallmark-webdesign/LightsailDefaultKey-us-east-2.pem")
LIGHTSAIL_HOST = "ubuntu@13.58.35.202"
LIGHTSAIL_AUDIO_DIR = "/var/www/signal-zero/audio/signal-zero"

BLUEHOST_KEY = os.path.expanduser("~/.ssh/bluehost_promptband")
BLUEHOST_HOST = "hallmar3@162.241.225.117"
BLUEHOST_SONGS_PATH = "/home2/hallmar3/public_html/website_8b0f5c66/api/signal-zero-songs.json"
BLUEHOST_BANDS_PATH = "/home2/hallmar3/public_html/website_8b0f5c66/api/signal-zero-bands.json"


def slugify(title):
    """Convert song title to filename slug."""
    slug = title.lower()
    for ch in ["(", ")", ",", ".", "'", '"', ":", "\u2014", "\u2013"]:
        slug = slug.replace(ch, "")
    slug = slug.replace(" ", "-")
    while "--" in slug:
        slug = slug.replace("--", "-")
    return slug.strip("-")


def ssh_cmd(host, key, cmd):
    """Run a command on a remote server via SSH."""
    ssh_args = ["ssh"]
    if "lightsail" in key.lower() or "Lightsail" in key:
        ssh_args.extend(["-o", "PubkeyAcceptedAlgorithms=+ssh-rsa"])
    ssh_args.extend(["-i", key, host, cmd])
    result = subprocess.run(ssh_args, capture_output=True, text=True, timeout=30)
    return result.stdout.strip(), result.returncode


def scp_upload(local_path, remote_path, key, host):
    """Upload a file via SCP."""
    scp_args = ["scp"]
    if "lightsail" in key.lower() or "Lightsail" in key:
        scp_args.extend(["-o", "PubkeyAcceptedAlgorithms=+ssh-rsa"])
    scp_args.extend(["-i", key, local_path, host + ":" + remote_path])
    result = subprocess.run(scp_args, capture_output=True, text=True, timeout=120)
    return result.returncode == 0


def fetch_songs_db():
    """Fetch the songs DB from Bluehost."""
    out, rc = ssh_cmd(BLUEHOST_HOST, BLUEHOST_KEY,
                      "cat " + BLUEHOST_SONGS_PATH)
    if rc != 0:
        print("ERROR: Could not fetch songs DB")
        sys.exit(1)
    return json.loads(out)


def fetch_bands_db():
    """Fetch the bands DB from Bluehost."""
    out, rc = ssh_cmd(BLUEHOST_HOST, BLUEHOST_KEY,
                      "cat " + BLUEHOST_BANDS_PATH)
    if rc != 0:
        print("ERROR: Could not fetch bands DB")
        sys.exit(1)
    return json.loads(out)


def save_songs_db(songs):
    """Save the songs DB back to Bluehost."""
    local_tmp = "/tmp/signal-zero-songs-update.json"
    with open(local_tmp, "w") as f:
        json.dump(songs, f, indent=2, ensure_ascii=False)
    return scp_upload(local_tmp, BLUEHOST_SONGS_PATH, BLUEHOST_KEY, BLUEHOST_HOST)


def save_bands_db(bands):
    """Save the bands DB back to Bluehost."""
    local_tmp = "/tmp/signal-zero-bands-update.json"
    with open(local_tmp, "w") as f:
        json.dump(bands, f, indent=2, ensure_ascii=False)
    return scp_upload(local_tmp, BLUEHOST_BANDS_PATH, BLUEHOST_KEY, BLUEHOST_HOST)


def update_band_stats(bands, songs, band_slug):
    """Recalculate songStats for a specific band."""
    for b in bands:
        if b["slug"] == band_slug:
            bs = [s for s in songs if s.get("bandSlug") == band_slug]
            b["songStats"] = {
                "total": len(bs),
                "withLyrics": sum(1 for s in bs if s.get("lyrics")),
                "withAudio": sum(1 for s in bs if s.get("audioStatus") == "complete"),
                "inRotation": sum(1 for s in bs if s.get("rotation"))
            }
            return b["songStats"]
    return None


def suno_generate(title, style):
    """Submit a generation request to Suno API. Returns list of clip IDs."""
    payload = json.dumps({
        "prompt": title + " \u2014 " + style,
        "mv": SUNO_MODEL,
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
                return [clip["id"] for clip in data]
            elif isinstance(data, dict) and "clips" in data:
                return [clip["id"] for clip in data["clips"]]
            else:
                print("    Unexpected response:", json.dumps(data)[:200])
                return []
    except Exception as e:
        print("    ERROR generating:", e)
        return []


def poll_and_get_clip(ids, max_wait=300):
    """Poll until complete, return best clip data (with lyrics)."""
    id_str = ",".join(ids)
    start = time.time()

    while time.time() - start < max_wait:
        try:
            url = SUNO_API + "/api/get?ids=" + id_str
            with urllib.request.urlopen(url, timeout=30) as resp:
                clips = json.loads(resp.read())

            all_done = all(
                c.get("status") in ("complete", "error", "failed")
                for c in clips
            )
            if all_done:
                # Return first completed clip
                for clip in clips:
                    if clip.get("status") == "complete":
                        return clip
                return None
        except Exception as e:
            print("    Poll error:", e)

        time.sleep(10)

    print("    WARNING: Timed out waiting for generation")
    return None


def download_mp3(clip_id, band_slug, song_slug):
    """Download mp3 from Suno CDN. Returns local filepath or None."""
    band_dir = os.path.join(DOWNLOAD_DIR, band_slug)
    os.makedirs(band_dir, exist_ok=True)

    url = "https://cdn1.suno.ai/" + clip_id + ".mp3"
    filepath = os.path.join(band_dir, song_slug + ".mp3")

    try:
        urllib.request.urlretrieve(url, filepath)
        size_mb = os.path.getsize(filepath) / (1024 * 1024)
        print("    Downloaded: {:.1f} MB".format(size_mb))
        return filepath
    except Exception as e:
        print("    Download FAILED:", e)
        return None


def upload_to_signal0(local_path, band_slug, filename):
    """Upload mp3 to Signal 0 server. Returns True on success."""
    remote_dir = LIGHTSAIL_AUDIO_DIR + "/" + band_slug
    # Ensure dir exists
    ssh_cmd(LIGHTSAIL_HOST, LIGHTSAIL_KEY,
            "mkdir -p " + remote_dir + " && sudo chown ubuntu:ubuntu " + remote_dir)
    remote_path = remote_dir + "/" + filename
    return scp_upload(local_path, remote_path, LIGHTSAIL_KEY, LIGHTSAIL_HOST)


def add_to_playlist(band_slug, filename):
    """Add song to Liquidsoap songs.m3u if not already present."""
    filepath = LIGHTSAIL_AUDIO_DIR + "/" + band_slug + "/" + filename
    cmd = ('grep -qF "' + filepath + '" /etc/liquidsoap/songs.m3u || '
           'echo "' + filepath + '" | sudo tee -a /etc/liquidsoap/songs.m3u > /dev/null && echo ADDED')
    out, _ = ssh_cmd(LIGHTSAIL_HOST, LIGHTSAIL_KEY, cmd)
    return "ADDED" in out


def process_song(band_slug, style, song, songs_db):
    """
    Full pipeline for one song:
    Generate -> Poll -> Download -> Get Lyrics -> Upload -> Playlist -> Update DB

    Returns True if successful.
    """
    title = song["title"]
    song_id = song.get("id", "")
    song_slug = song_id.split("--", 1)[1] if "--" in song_id else slugify(title)
    filename = song_slug + ".mp3"

    print("\n  [" + title + "]")

    # Step 1: Generate
    print("    Generating...")
    ids = suno_generate(title, style)
    if not ids:
        print("    FAILED: Could not submit to Suno")
        return False

    print("    Submitted: " + ", ".join(ids))

    # Step 2: Poll for completion
    print("    Waiting for completion...")
    clip = poll_and_get_clip(ids)
    if not clip:
        print("    FAILED: No completed clip")
        return False

    # Step 3: Download
    print("    Downloading...")
    local_path = download_mp3(clip["id"], band_slug, song_slug)
    if not local_path:
        return False

    # Step 4: Capture lyrics
    lyrics = clip.get("lyric", "")
    if lyrics:
        print("    Lyrics: " + str(len(lyrics)) + " chars")
    else:
        print("    No lyrics in response")

    # Step 5: Upload to Signal 0
    print("    Uploading to Signal 0...")
    if not upload_to_signal0(local_path, band_slug, filename):
        print("    FAILED: Upload failed")
        return False
    print("    Uploaded.")

    # Step 6: Add to Liquidsoap playlist
    added = add_to_playlist(band_slug, filename)
    if added:
        print("    Added to playlist.")

    # Step 7: Update songs DB
    audio_url = "https://signal0radio.com/audio/signal-zero/" + band_slug + "/" + filename
    for s in songs_db:
        if s.get("id") == song_id:
            s["audioUrl"] = audio_url
            s["audioStatus"] = "complete"
            if lyrics:
                s["lyrics"] = lyrics
            s["sunoId"] = clip["id"]
            break

    print("    COMPLETE")
    return True


def get_pending_songs(songs_db, band_slug):
    """Get songs that need audio for a specific band."""
    return [
        s for s in songs_db
        if s.get("bandSlug") == band_slug
        and s.get("audioStatus") != "complete"
    ]


def main():
    args = sys.argv[1:]
    dry_run = "--dry-run" in args

    # Determine target bands
    if "--band" in args:
        idx = args.index("--band")
        if idx + 1 >= len(args):
            print("Usage: --band SLUG")
            sys.exit(1)
        target_slugs = [args[idx + 1]]
    elif "--all" in args:
        target_slugs = None  # Will be determined from DB
    else:
        print("Usage: python3 generate-signal-zero-batch.py --band SLUG [--dry-run]")
        print("       python3 generate-signal-zero-batch.py --all [--dry-run]")
        sys.exit(1)

    os.makedirs(DOWNLOAD_DIR, exist_ok=True)

    # Fetch current state from admin DB
    print("Fetching admin DB...")
    songs_db = fetch_songs_db()
    bands_db = fetch_bands_db()

    # Determine which bands need work
    if target_slugs is None:
        # Find all bands with pending songs
        pending_bands = {}
        for s in songs_db:
            if s.get("audioStatus") != "complete":
                slug = s.get("bandSlug", "")
                if slug not in pending_bands:
                    pending_bands[slug] = 0
                pending_bands[slug] += 1
        target_slugs = sorted(pending_bands.keys(),
                              key=lambda x: pending_bands[x], reverse=True)
        print("Bands with pending songs: " + str(len(target_slugs)))
        for slug in target_slugs[:20]:
            print("  " + slug + ": " + str(pending_bands[slug]) + " pending")

    total_generated = 0
    total_failed = 0

    for band_slug in target_slugs:
        band = next((b for b in bands_db if b["slug"] == band_slug), None)
        if not band:
            print("\nBand not found: " + band_slug)
            continue

        style = band.get("style", "rock")
        pending = get_pending_songs(songs_db, band_slug)

        if not pending:
            print("\n" + band["name"] + ": No pending songs, skipping.")
            continue

        print("\n" + "=" * 60)
        print(band["name"] + " (" + band_slug + ")")
        print("Style: " + style)
        print("Pending: " + str(len(pending)) + " songs")
        print("=" * 60)

        if dry_run:
            for s in pending:
                print("  Would generate: " + s["title"])
            continue

        band_success = 0
        for song in pending:
            if process_song(band_slug, style, song, songs_db):
                band_success += 1
                total_generated += 1
            else:
                total_failed += 1

            # Delay between songs
            time.sleep(3)

        # Save DBs after each band
        print("\n  Saving DBs...")
        stats = update_band_stats(bands_db, songs_db, band_slug)
        save_songs_db(songs_db)
        save_bands_db(bands_db)
        if stats:
            print("  " + band["name"] + ": " + str(stats["withAudio"]) + "/" + str(stats["total"]) + " audio, " + str(stats["withLyrics"]) + "/" + str(stats["total"]) + " lyrics")
        print("  Band done: " + str(band_success) + "/" + str(len(pending)) + " succeeded")

    print("\n" + "=" * 60)
    print("PIPELINE COMPLETE")
    print("Generated: " + str(total_generated))
    print("Failed: " + str(total_failed))
    print("=" * 60)


if __name__ == "__main__":
    main()
