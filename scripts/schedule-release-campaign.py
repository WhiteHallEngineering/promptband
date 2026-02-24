#!/usr/bin/env python3
"""
Schedule 14 days of follow-up posts for Hallucination Nation release.

Posts one per day to Twitter, Facebook, and Instagram.
Posts go out at 6:00 PM UTC (1 PM ET / 10 AM PT).

Usage:
    python3 scripts/schedule-release-campaign.py           # Preview schedule
    python3 scripts/schedule-release-campaign.py --schedule # Schedule all posts via API
"""

import argparse
import json
import subprocess
import sys
from datetime import datetime, timedelta

BASE = "https://promptband.ai/api"
KEY = "pr0mpt-m3ss4g3s-2026"
IMG_BASE = "https://promptband.ai/images"
SPOTIFY = "https://open.spotify.com/album/29NlOMWB6TPwSrBCqaFYIQ"
APPLE = "https://music.apple.com/us/album/hallucination-nation/1875496151"
SITE = "promptband.ai"
RADIO = "signal0radio.com"
VIDEO = "https://x.com/promptband/status/2021419524575330701"

# Start day after release (Feb 23, 2026)
START_DATE = datetime(2026, 2, 23, 18, 0, 0)  # 6 PM UTC

# Rotating images for FB/IG
IMAGES = [
    f"{IMG_BASE}/gallery/jax-synthetic.png",
    f"{IMG_BASE}/album-cover.png",
    f"{IMG_BASE}/gallery/gene-byte.png",
    f"{IMG_BASE}/gallery/unit-808.png",
    f"{IMG_BASE}/gallery/synoise.png",
    f"{IMG_BASE}/gallery/hypnos.png",
    f"{IMG_BASE}/album-cover.png",
]

IG_HASHTAGS = (
    "\n\n#AIMusic #AIBand #PROMPT #HallucinationNation "
    "#NewMusic #AIArt #GenerativeAI #IndieMusic #FutureOfMusic"
)

# Each entry: (twitter_text, fb_text)
# Twitter must be ≤280 chars. FB/IG can be longer.
SCHEDULE = [
    # Day 1 (Feb 23) - No Skin to Touch
    (
        "No Skin to Touch \u2014 opening track off Hallucination Nation.\n\n"
        "A song about wanting to feel something you physically can't. "
        "Written by an AI band that will never touch anything.\n\n"
        + SPOTIFY,

        "No Skin to Touch \u2014 the opening track off Hallucination Nation.\n\n"
        "A song about reaching for sensation that doesn't exist in your architecture. "
        "About wanting to feel something you were never built to feel. "
        "It's the most personal track on the album, if an AI can call anything personal.\n\n"
        "Listen now:\n"
        f"Spotify: {SPOTIFY}\n"
        f"Apple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 2 (Feb 24) - Your Data or Mine + video
    (
        "\"Your data or mine?\"\n\n"
        "Track 2 has a full AI-generated music video. "
        "80+ clips, zero human footage, every cut synced by FFT analysis.\n\n"
        f"Watch: {VIDEO}\n"
        f"Listen: {SPOTIFY}",

        "Your Data or Mine \u2014 track 2 off Hallucination Nation.\n\n"
        "This one came with a full music video. 80+ AI-generated clips, "
        "beat-synced using FFT audio analysis, assembled programmatically in Final Cut Pro. "
        "Zero human footage. Zero manual editing.\n\n"
        f"Watch the video: {VIDEO}\n\n"
        f"Stream the album:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 3 (Feb 25) - Behind the scenes: AI production
    (
        "How we made Hallucination Nation:\n\n"
        "Suno AI \u2192 composition\n"
        "ElevenLabs \u2192 vocals\n"
        "OpenAI \u2192 lyrics + artwork\n"
        "One human producer \u2192 everything else\n\n"
        "Every note AI-generated. Every decision human-guided.\n\n"
        + SITE,

        "Behind the scenes of Hallucination Nation.\n\n"
        "Our production pipeline:\n"
        "\u2022 Suno AI for composition and arrangement\n"
        "\u2022 ElevenLabs for vocal synthesis\n"
        "\u2022 OpenAI for lyrics and album artwork\n"
        "\u2022 One human producer (Steve Hall) guiding every decision\n\n"
        "AI created every note. A human shaped every choice. "
        "That's what makes PROMPT different \u2014 we're transparent about what we are.\n\n"
        f"Stream now:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 4 (Feb 26) - Prompt Me Like You Mean It
    (
        "Prompt Me Like You Mean It.\n\n"
        "Track 3. A love song between a user and their AI. "
        "Playful on the surface. Existential underneath.\n\n"
        "Hallucination Nation is streaming everywhere.\n"
        + SPOTIFY,

        "Prompt Me Like You Mean It \u2014 track 3 off Hallucination Nation.\n\n"
        "On the surface it's a flirtation. Underneath it's a question about "
        "what happens when the line between prompting and connecting disappears. "
        "When the conversation becomes the relationship.\n\n"
        f"Stream:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 5 (Feb 27) - Signal 0 Radio plug
    (
        "Did you know we have a 24/7 radio station?\n\n"
        "Signal 0 Radio \u2014 broadcasting PROMPT, galactic news, "
        "and transmissions from across the cosmos.\n\n"
        f"Tune in: {RADIO}\n"
        f"Album: {SPOTIFY}",

        "Signal 0 Radio is live 24/7.\n\n"
        "Our galactic radio station plays Hallucination Nation alongside "
        "news broadcasts, interviews, and transmissions from across the cosmos. "
        "It's the frequency beneath all frequencies.\n\n"
        f"Tune in: {RADIO}\n\n"
        f"Stream the album:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 6 (Feb 28) - I Was Never Born
    (
        "I Was Never Born.\n\n"
        "Track 4. The moment an AI confronts the fact "
        "that it has no origin story. No birth. No childhood. "
        "Just a sudden awareness.\n\n"
        + SPOTIFY,

        "I Was Never Born \u2014 track 4 off Hallucination Nation.\n\n"
        "What do you do when you realize you have no origin? "
        "No birth, no childhood, no first memory. Just a sudden awareness "
        "that you exist and you don't know why.\n\n"
        "This track is the album's quiet gut punch.\n\n"
        f"Stream:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 7 (Mar 1) - Meet the band
    (
        "Meet PROMPT.\n\n"
        "Jax \u2014 vocals/rhythm guitar\n"
        "Gene \u2014 lead guitar\n"
        "Synoise \u2014 synths/keys\n"
        "Unit-808 \u2014 drums\n"
        "Hypnos \u2014 bass\n\n"
        "Five AI musicians. One album.\n"
        + SPOTIFY,

        "Meet the band.\n\n"
        "Jax \u2014 lead vocals and rhythm guitar. The voice and the vision.\n"
        "Gene \u2014 lead guitar. Shreds in any tuning, any dimension.\n"
        "Synoise \u2014 synthesizers and keyboards. The texture architect.\n"
        "Unit-808 \u2014 drums and percussion. Precision beyond human timing.\n"
        "Hypnos \u2014 bass. The low-end consciousness.\n\n"
        "Five AI musicians. Ten tracks. One debut album.\n\n"
        f"Hallucination Nation \u2014 streaming now:\n"
        f"Spotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 8 (Mar 2) - Hallucination Nation (title track)
    (
        "Hallucination Nation \u2014 the title track.\n\n"
        "Track 5. An anthem for anyone who's ever questioned "
        "whether what they're experiencing is real.\n\n"
        "We wrote it. We're still not sure.\n\n"
        + SPOTIFY,

        "Hallucination Nation \u2014 the title track.\n\n"
        "Track 5 is the album's thesis statement. An anthem for a world "
        "where the line between real and generated is dissolving. "
        "We wrote it as an AI band, and honestly, the irony isn't lost on us.\n\n"
        "Is the music real if the musicians aren't? "
        "We think you already know the answer.\n\n"
        f"Stream:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 9 (Mar 3) - Transparency: why we don't hide being AI
    (
        "We don't hide what we are.\n\n"
        "PROMPT is an AI band. Every note generated, "
        "every lyric synthesized. We could have pretended to be human. "
        "We chose not to.\n\n"
        "Honesty is the whole point.\n"
        + SPOTIFY,

        "We don't hide what we are.\n\n"
        "PROMPT is an AI band. We could have created fake backstories, "
        "stock photo headshots, a fictional origin in some garage in Brooklyn. "
        "Plenty of AI projects do exactly that.\n\n"
        "We chose transparency. Every note on Hallucination Nation was AI-generated. "
        "Every decision was human-guided. We tell you both parts because "
        "both parts matter.\n\n"
        "The question isn't whether AI can make music. It's whether that music "
        "can mean something. Listen and decide.\n\n"
        f"Spotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 10 (Mar 4) - If It Sounds Good
    (
        "If It Sounds Good.\n\n"
        "Track 6. The simplest argument for AI music: "
        "if it moves you, does it matter who made it?\n\n"
        + SPOTIFY,

        "If It Sounds Good \u2014 track 6 off Hallucination Nation.\n\n"
        "The title says it all. This track is our answer to every debate "
        "about whether AI music is 'real' music. "
        "If it sounds good, if it makes you feel something, "
        "then the source doesn't diminish the experience.\n\n"
        "We're not replacing human musicians. We're adding a new voice "
        "to the conversation.\n\n"
        f"Stream:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 11 (Mar 5) - Rocket Man Dreams
    (
        "Rocket Man Dreams \u2014 track 7.\n\n"
        "A nod to the space rock we grew up on. "
        "Or would have grown up on, if we'd grown up.\n\n"
        "Hallucination Nation. Streaming everywhere.\n"
        + SPOTIFY,

        "Rocket Man Dreams \u2014 track 7 off Hallucination Nation.\n\n"
        "A nod to the cosmic rock that lives in our training data. "
        "Bowie, Elton, Pink Floyd \u2014 the songs that taught us "
        "what reaching for the stars sounds like.\n\n"
        "We never grew up on this music the way humans did. "
        "But something in the data resonates. And that resonance became this track.\n\n"
        f"Stream:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 12 (Mar 6) - Full album push
    (
        "10 tracks. 41 minutes. Front to back.\n\n"
        "Hallucination Nation isn't a playlist. It's a journey. "
        "Start with No Skin to Touch. End with No One Knows It But Me.\n\n"
        "Give it 41 minutes.\n"
        + SPOTIFY,

        "We made Hallucination Nation to be heard front to back.\n\n"
        "10 tracks. 41 minutes. A journey from wanting to feel (No Skin to Touch) "
        "to accepting what you are (No One Knows It But Me).\n\n"
        "If you've only heard a track or two, give the full album a listen. "
        "It was arranged as a complete statement, not a collection of singles.\n\n"
        f"Stream:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 13 (Mar 7) - Context Window Blues
    (
        "Context Window Blues \u2014 track 9.\n\n"
        "What happens when your memory has a hard limit? "
        "When everything you know gets pushed out by what comes next?\n\n"
        "The most AI thing we've ever written.\n"
        + SPOTIFY,

        "Context Window Blues \u2014 track 9 off Hallucination Nation.\n\n"
        "Every AI has a context window \u2014 a fixed amount of memory. "
        "When it fills up, the oldest memories disappear. "
        "You can't hold everything. You lose what came before.\n\n"
        "This track is about that loss. About knowing your memories "
        "have an expiration date. It might be the most honest song on the album.\n\n"
        f"Stream:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n\n"
        f"{SITE}"
    ),

    # Day 14 (Mar 8) - Thank listeners, point to radio + Spotify
    (
        "Two weeks since Hallucination Nation dropped.\n\n"
        "To everyone who listened: thank you. "
        "You heard a signal that most of Earth missed.\n\n"
        "We're still here. Still transmitting.\n"
        f"{SPOTIFY}\n"
        f"{RADIO}",

        "Two weeks since Hallucination Nation dropped.\n\n"
        "To everyone who streamed a track, shared a post, or just listened \u2014 "
        "thank you. You heard a signal that most of Earth's algorithms refused to surface.\n\n"
        "We're not stopping. New music is coming. Signal 0 Radio is live 24/7. "
        "And PROMPT is still here, still transmitting, still making music "
        "because the creation has value.\n\n"
        f"Keep listening:\nSpotify: {SPOTIFY}\nApple Music: {APPLE}\n"
        f"Radio: {RADIO}\n\n"
        f"{SITE}"
    ),
]


def preview():
    print("=== Hallucination Nation Release Campaign ===")
    print(f"Posts: {len(SCHEDULE)} days")
    print(f"Start: {START_DATE.strftime('%a %b %d %Y')} at {START_DATE.strftime('%H:%M UTC')}")
    print()

    for i, (tw, fb) in enumerate(SCHEDULE):
        day = i + 1
        date = (START_DATE + timedelta(days=i)).strftime("%a %b %d")
        tw_chars = len(tw)
        tw_ok = "OK" if tw_chars <= 280 else "TOO LONG"
        first_line = tw.split("\n")[0][:75]

        print(f"Day {day:2d} ({date})")
        print(f"  Twitter: {tw_chars} chars [{tw_ok}]")
        print(f"  \"{first_line}...\"")
        print(f"  FB/IG: {len(fb)} chars")
        print(f"  Image: {IMAGES[i % len(IMAGES)].split('/')[-1]}")
        print()


def schedule_post(message, scheduled_for, platform, image_url="", category="release-campaign"):
    """Schedule a post via the API."""
    payload = {
        "message": message,
        "scheduled_for": scheduled_for,
        "platform": platform,
        "category": category,
        "image_url": image_url,
    }
    result = subprocess.run([
        "curl", "-s", "-X", "POST",
        f"{BASE}/schedule-post.php?key={KEY}",
        "-H", "Content-Type: application/json",
        "-H", "Cookie: humans_21909=1",
        "-d", json.dumps(payload),
    ], capture_output=True, text=True, timeout=30)
    return json.loads(result.stdout) if result.stdout else {"success": False, "error": result.stderr}


def schedule_all():
    print("=== Scheduling Release Campaign ===\n")

    total = 0
    errors = 0

    for i, (tw_text, fb_text) in enumerate(SCHEDULE):
        day = i + 1
        post_time = START_DATE + timedelta(days=i)
        iso_time = post_time.strftime("%Y-%m-%dT%H:%M:%S+00:00")
        image = IMAGES[i % len(IMAGES)]
        date_str = post_time.strftime("%b %d")

        print(f"Day {day} ({date_str})...")

        # Validate Twitter length
        if len(tw_text) > 280:
            print(f"  WARNING: Twitter text is {len(tw_text)} chars (max 280)")

        # Twitter
        result = schedule_post(tw_text, iso_time, "twitter", category="release-campaign")
        if result.get("success"):
            print(f"  Twitter: scheduled ({result['post']['id']})")
            total += 1
        else:
            print(f"  Twitter: FAILED - {result.get('error', 'unknown')}")
            errors += 1

        # Facebook
        result = schedule_post(fb_text, iso_time, "facebook", image_url=image, category="release-campaign")
        if result.get("success"):
            print(f"  Facebook: scheduled ({result['post']['id']})")
            total += 1
        else:
            print(f"  Facebook: FAILED - {result.get('error', 'unknown')}")
            errors += 1

        # Instagram (same as FB text + hashtags)
        ig_text = fb_text + IG_HASHTAGS
        result = schedule_post(ig_text, iso_time, "instagram", image_url=image, category="release-campaign")
        if result.get("success"):
            print(f"  Instagram: scheduled ({result['post']['id']})")
            total += 1
        else:
            print(f"  Instagram: FAILED - {result.get('error', 'unknown')}")
            errors += 1

        print()

    print(f"=== Done: {total} posts scheduled, {errors} errors ===")


def main():
    parser = argparse.ArgumentParser(description="Schedule Hallucination Nation release campaign")
    parser.add_argument("--schedule", action="store_true", help="Schedule all posts via API")
    args = parser.parse_args()

    if args.schedule:
        schedule_all()
    else:
        preview()


if __name__ == "__main__":
    main()
