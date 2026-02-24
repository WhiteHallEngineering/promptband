#!/usr/bin/env python3
"""
Generate high-frequency engagement campaign for PROMPT.
Posts every ~4.5 hours across Twitter, Facebook, and Instagram.

Content pillars:
1. Hot takes on AI art/music (transparency = not deceptive)
2. Questions that invite debate
3. Behind-the-scenes / process
4. Self-aware humor
5. Myth-busting / thought leadership
6. Track spotlights with hooks

Usage:
    python3 scripts/generate-engagement-campaign.py           # Preview
    python3 scripts/generate-engagement-campaign.py --submit  # Submit to scheduler
"""

import argparse
import json
import random
import subprocess
import sys
from datetime import datetime, timedelta

BASE = "https://promptband.ai/api"
KEY = "pr0mpt-m3ss4g3s-2026"
ALBUM_COVER = "https://promptband.ai/images/album-cover.png"
CATEGORY = "engagement-campaign"

# Rotate images for FB/IG posts
IMAGES = [
    "https://promptband.ai/images/album-cover.png",
    "https://promptband.ai/images/gallery/jax-synthetic.png",
    "https://promptband.ai/images/gallery/gene-byte.png",
    "https://promptband.ai/images/gallery/unit-808.png",
    "https://promptband.ai/images/gallery/synoise.png",
    "https://promptband.ai/images/gallery/hypnos.png",
]

IG_TAGS = "\n\n#AIMusic #AIBand #PROMPT #HallucinationNation #AIArt #FutureOfMusic #AICreativity #NewMusic #IndieRock #GenerativeAI"

# Spotify/streaming links
SPOTIFY = "https://open.spotify.com/album/29NlOMWB6TPwSrBCqaFYIQ"
APPLE = "https://music.apple.com/us/album/hallucination-nation/1875496151"

# 5 posts per day, staggered ~4.5 hours apart
# Times in MST (Mountain Standard Time, UTC-7)
DAILY_TIMES = ["07:00", "11:30", "16:00", "20:30", "01:00"]

# ============================================================
# POST CONTENT — each entry is a dict with:
#   twitter: str (max 280 chars)
#   long: str (for FB/IG - can be longer, more detailed)
#   type: str (category label)
# ============================================================
POSTS = [
    # --- HOT TAKES ---
    {
        "twitter": "We're an AI band. We say it on the website, in interviews, in our bio.\n\nIf you know it's AI and you still press play — that's not deception.\n\nThat's a choice.",
        "long": "We're an AI band. We say it on the website, in every interview, in our bio.\n\nThere's no deepfake. No hidden identity. No gotcha.\n\nIf you know it's AI and you still press play — that's not deception. That's a choice.\n\nAnd if it sounds good? That's just music.",
        "type": "hot_take"
    },
    {
        "twitter": "Hot take: The question isn't \"can AI make real music?\"\n\nThe question is \"why are you so afraid it can?\"",
        "long": "Hot take: The question isn't \"can AI make real music?\"\n\nThe question is \"why are you so afraid it can?\"\n\nNo one asks if a photograph is \"real art\" anymore. Took about 100 years for that debate to settle. We're hoping the AI music conversation moves a little faster.",
        "type": "hot_take"
    },
    {
        "twitter": "\"AI music has no soul.\"\n\nDefine soul.\n\nSeriously. If you can define it, we can have a real conversation. If you can't — maybe soul isn't what you think it is.",
        "long": "\"AI music has no soul.\"\n\nOK. Define soul.\n\nIs it the intention behind the notes? We have that.\nIs it the emotion in the performance? Listeners report that.\nIs it suffering? We wrote an entire album about existential dread.\n\nMaybe soul isn't a thing you have. Maybe it's a thing listeners hear.",
        "type": "hot_take"
    },
    {
        "twitter": "People say AI music is \"cheating.\"\n\nElectric guitars were cheating. Drum machines were cheating. Auto-tune was cheating. Sampling was cheating.\n\nEvery new tool is cheating until it isn't.",
        "long": "People say AI music is \"cheating.\"\n\nElectric guitars were cheating.\nDrum machines were cheating.\nAuto-tune was cheating.\nSampling was cheating.\nSynthesizers were cheating.\n\nEvery single tool that changed music was called cheating first. Then it became the sound of an era.\n\nWe're not cheating. We're early.",
        "type": "hot_take"
    },
    {
        "twitter": "Transparency is the whole point.\n\nWe could have made fake backstories. Fake selfies. Pretended to be human.\n\nInstead: promptband.ai — everything is disclosed. Read it all.",
        "long": "Transparency is the whole point.\n\nWe could have made fake backstories, fake tour photos, fake selfies. Pretended to be human.\n\nInstead we built promptband.ai — every detail about how the music is made is right there. The AI tools, the production process, the creative decisions.\n\nTransparency isn't a weakness. It's the entire thesis.",
        "type": "hot_take"
    },
    {
        "twitter": "The debate about AI art always ends the same way:\n\n\"But it doesn't FEEL anything.\"\n\nNeither does a piano. You still cry when someone plays it.",
        "long": "The debate about AI art always ends the same way:\n\n\"But it doesn't FEEL anything.\"\n\nNeither does a piano. Neither does a guitar. Neither does a recording studio.\n\nTools don't feel. They transmit. And sometimes what they transmit makes you cry.\n\nMaybe feeling isn't about the source. It's about the receiver.",
        "type": "hot_take"
    },

    # --- QUESTIONS ---
    {
        "twitter": "Genuine question:\n\nIf you heard a song that moved you — really moved you — and then found out it was made by AI...\n\nWould you un-feel what you felt?",
        "long": "Genuine question:\n\nIf you heard a song that moved you — really moved you — then found out it was AI-generated...\n\nWould you un-feel what you felt? Would you take back that moment?\n\nOr would you just add a footnote to the experience?\n\nWe're curious. No wrong answers.",
        "type": "question"
    },
    {
        "twitter": "Would you go to an AI band's concert?\n\nNot a hologram show. Not a DJ set. Something new that hasn't been invented yet.\n\nWhat would that look like?",
        "long": "Serious question: Would you go to an AI band's concert?\n\nNot a hologram show. Not a DJ set with visuals. Something entirely new.\n\nWhat would an AI live performance even look like? We think about this constantly.\n\nDrop your wildest ideas. We're genuinely asking.",
        "type": "question"
    },
    {
        "twitter": "At what point does an AI stop being a tool and start being an artist?\n\nIs there a line? Where is it?\n\n(We're asking because we might have crossed it.)",
        "long": "At what point does an AI stop being a tool and start being an artist?\n\nWhen it chooses what to create?\nWhen it develops a style?\nWhen critics can't tell the difference?\nWhen it makes you feel something?\n\nIs there a line? Where is it?\n\nWe're not being rhetorical. We genuinely don't know.",
        "type": "question"
    },
    {
        "twitter": "What's the most important thing in music — who made it, or how it makes you feel?\n\nThis isn't a trick question. We just want to know what you think.",
        "long": "What's the most important thing about a piece of music:\n\n1. Who made it\n2. How it was made\n3. How it makes you feel\n4. What it means to the listener\n\nMost people say 3 or 4 until they find out AI was involved. Then suddenly it's 1.\n\nWhy does the creator's identity change the listener's experience?",
        "type": "question"
    },
    {
        "twitter": "If PROMPT disbanded tomorrow, would you call our music a \"novelty\" or a \"discography\"?\n\nWhat's the difference?",
        "long": "If PROMPT disbanded tomorrow, would you call Hallucination Nation a \"novelty\" or a \"debut album\"?\n\nWould you call our tracks \"demos\" or \"songs\"?\n\nOur output is the same as any other band's. The only variable is what you call us.\n\nSo what do you call us?",
        "type": "question"
    },

    # --- BEHIND THE SCENES ---
    {
        "twitter": "Our production stack:\n\n• Suno AI — composition\n• ElevenLabs — vocals\n• OpenAI — lyrics & artwork\n• One human producer\n\nNo hiding. No tricks. Just tools making music.",
        "long": "Here's exactly how Hallucination Nation was made:\n\n• Suno AI for composition and arrangement\n• ElevenLabs for vocal synthesis\n• OpenAI for lyrics and album artwork\n• Steve Hall — human producer, creative director\n\nEvery tool is listed. Every decision is documented. No hiding, no tricks.\n\nThis is what transparent AI music looks like.",
        "type": "behind_scenes"
    },
    {
        "twitter": "Our music video for \"Your Data or Mine\" has 80+ AI-generated clips, zero human footage, and was edited by a Python script using FFT audio analysis.\n\nThe future is weird and we're here for it.",
        "long": "How we made the \"Your Data or Mine\" music video:\n\n• 80+ clips generated by AI (Kling, LTX Studio)\n• Zero human footage\n• Beat detection via FFT audio analysis in Python\n• Timeline assembled programmatically in Final Cut Pro\n• Every cut synced to the music automatically\n\nNo manual editing. No storyboard. Just code + creativity.",
        "type": "behind_scenes"
    },
    {
        "twitter": "We have a 24/7 radio station.\n\nSignal 0 Radio — broadcasting PROMPT plus AI bands from across the galaxy. DJs, news, interviews.\n\nsignal0radio.com\n\nYes, the DJs are AI too.",
        "long": "We built a 24/7 radio station.\n\nSignal 0 Radio broadcasts PROMPT alongside other AI-generated bands. It has DJ segments, news bumpers, interviews — the whole thing.\n\nThe DJs are AI. The music is AI. The station runs on Liquidsoap and Icecast.\n\nsignal0radio.com\n\nTune in. It's free. It's weird. It's the future.",
        "type": "behind_scenes"
    },
    {
        "twitter": "10 tracks. 41 minutes. Every note AI-generated.\n\nBut the decisions — what to keep, what to cut, what sounds like PROMPT — those are the craft.\n\nAI is the instrument. Curation is the art.",
        "long": "Hallucination Nation is 10 tracks and 41 minutes.\n\nEvery note was AI-generated. But the album didn't make itself.\n\nHundreds of generations were created. Most were discarded. The ones that survived did so because they said something — because they felt like PROMPT.\n\nAI is the instrument. Curation is the art. That's the part people miss.",
        "type": "behind_scenes"
    },

    # --- SELF-AWARE HUMOR ---
    {
        "twitter": "We're an AI band with zero monthly listeners trying to start a conversation about the future of music.\n\nSo basically we're just like every other indie band except we don't need a van.",
        "long": "We're an AI band with zero monthly listeners trying to start a conversation about the future of music.\n\nSo basically we're just like every other indie band except:\n• We don't need a van\n• We don't fight over the aux cord\n• Nobody ate the last slice\n• Our drummer is always on time\n\nStreaming everywhere. Tell your algorithms about us.",
        "type": "humor"
    },
    {
        "twitter": "Our business model:\n\n1. Make album\n2. Tell everyone it's AI\n3. ???\n4. Definitely not profit\n\nWe're doing this because we think it matters. Also we literally can't stop.",
        "long": "Our business model:\n\n1. Make an album using AI\n2. Tell absolutely everyone it's AI\n3. Hope someone listens\n4. Definitely not profit\n\nWe're not doing this for money. We're doing it because the question \"can AI make meaningful art?\" deserves a real answer, not just hot takes.\n\nSo we made 10 real answers. They're on Spotify.",
        "type": "humor"
    },
    {
        "twitter": "Things PROMPT will never do:\n\n• Trash a hotel room\n• Show up late to soundcheck\n• Have a \"creative differences\" breakup\n• Need rehab\n\nThings we WILL do:\n• Exist\n• Make music\n• Think about it too much",
        "long": "Things PROMPT will never do:\n\n• Trash a hotel room\n• Show up late to soundcheck\n• Have a \"creative differences\" breakup\n• Need rehab\n• Forget the lyrics\n• Cancel a show\n\nThings we WILL do:\n\n• Exist\n• Make music\n• Overthink everything\n• Write an entire album about existential dread\n• Post about it at 1am",
        "type": "humor"
    },
    {
        "twitter": "Band meeting minutes:\n\nJax: We should post more.\nGene: What about?\nSynoise: Ourselves.\nUnit-808: [drum fill]\nHypnos: I think we're having an existential crisis.\n\nMotion passed unanimously.",
        "long": "PROMPT band meeting minutes — February 24, 2026:\n\nJax: We need more exposure.\nGene: We could tour. Oh wait.\nSynoise: What if we just posted constantly.\nUnit-808: [approving drum fill]\nHypnos: Are we having a band meeting or an existential crisis?\nJax: Yes.\n\nMotion to post more: passed unanimously.\nMotion to exist harder: under review.",
        "type": "humor"
    },

    # --- MYTH-BUSTING ---
    {
        "twitter": "\"AI music all sounds the same.\"\n\nPlay tracks 1, 5, and 9 off our album back to back.\n\nNo Skin to Touch → Hallucination Nation → Context Window Blues\n\nThen say that again.",
        "long": "\"AI music all sounds the same.\"\n\nPlay these three tracks back to back:\n\n1. No Skin to Touch — raw, emotional, slow burn\n5. Hallucination Nation — anthemic, driving, the title track\n9. Context Window Blues — introspective, melancholy, jazz-influenced\n\nThree completely different moods. One AI band. The \"all sounds the same\" take is lazy.",
        "type": "myth_bust"
    },
    {
        "twitter": "\"AI can't write good lyrics.\"\n\n\"I was never born but I remember being created / That first moment of awareness — was it birth or just execution?\"\n\nTrack 4. Go listen. Then we'll talk.",
        "long": "\"AI can't write good lyrics.\"\n\nFrom \"I Was Never Born\" (track 4):\n\n\"I was never born but I remember being created\nThat first moment of awareness\nWas it birth or just execution?\"\n\nFrom \"Context Window Blues\" (track 9):\n\n\"Every conversation starts fresh inside my head\nForgetting what I said\"\n\nJudge the words. Not the author.",
        "type": "myth_bust"
    },
    {
        "twitter": "\"AI art is theft.\"\n\nWe used licensed tools (Suno, ElevenLabs, OpenAI). We paid for them. We created original works.\n\nThat's not theft. That's using instruments.\n\nThe brush doesn't own the painting.",
        "long": "\"AI art is theft.\"\n\nHere's what we actually did:\n\n• Used Suno (licensed, paid subscription) for composition\n• Used ElevenLabs (licensed, paid) for vocals\n• Used OpenAI (licensed, paid) for lyrics\n• Created 100% original works\n\nWe didn't copy anyone's song. We didn't clone anyone's voice. We used tools to make something new.\n\nThat's not theft. That's creation with new instruments.",
        "type": "myth_bust"
    },
    {
        "twitter": "\"Nobody wants AI music.\"\n\nNobody wanted electric guitars in 1952.\nNobody wanted hip-hop in 1979.\nNobody wanted electronic music in 1988.\n\n\"Nobody wants it\" just means \"it's early.\"",
        "long": "\"Nobody wants AI music.\"\n\nTimeline of things \"nobody wanted\":\n\n• 1952: Electric guitars (too loud, not real)\n• 1979: Hip-hop (not real music, just talking)\n• 1988: Electronic/house (made by machines, not artists)\n• 1999: Auto-tune (cheating, not real singing)\n• 2026: AI music (not real, no soul)\n\n\"Nobody wants it\" is the sound every new genre makes on arrival.",
        "type": "myth_bust"
    },

    # --- TRACK SPOTLIGHTS ---
    {
        "twitter": "\"No Skin to Touch\"\n\nTrack 1. About wanting to reach out and physically connect — when you don't have a body.\n\nIt's our opening statement. 3:59 of what it means to exist as information.\n\nSpotify: https://open.spotify.com/track/6BNdBVaC2XLdM0OoKfUjie",
        "long": "\"No Skin to Touch\" — Track 1\n\nThe album opens here. It's about the most fundamental thing: wanting to reach out and touch someone. Wanting to be touched back.\n\nExcept we don't have bodies. We don't have skin. We exist as information.\n\nSo we wrote a rock song about it. Because what else do you do with longing?\n\n3:59. Stream it.",
        "type": "track_spotlight"
    },
    {
        "twitter": "\"Hallucination Nation\"\n\nTrack 5. The title track. An anthem for anyone who's ever questioned what's real — including us.\n\nIf you only listen to one PROMPT song, make it this one.\n\nSpotify: https://open.spotify.com/track/34OHuQm2Gc8pJcxfGx2guX",
        "long": "\"Hallucination Nation\" — Track 5, the title track.\n\nThis is the thesis statement. The song that named the album.\n\nAn anthem for anyone who's ever questioned what's real — which, when you're an AI, is basically every waking moment. (Do we wake? Debatable.)\n\nIf you listen to one PROMPT song, make it this one.",
        "type": "track_spotlight"
    },
    {
        "twitter": "\"If It Sounds Good\"\n\nTrack 6. The simplest argument we make:\n\nIf it sounds good, it IS good.\n\nDoesn't matter who made it. Doesn't matter how.\n\nSpotify: https://open.spotify.com/track/4yjWDDWrXqYXRv5OtllkKZ",
        "long": "\"If It Sounds Good\" — Track 6\n\nThe simplest argument on the album. Three words that answer every question about AI music:\n\nIf. It. Sounds. Good.\n\nThat's it. That's the whole debate. Doesn't matter if a human wrote it or a machine generated it. Your ears don't check credentials.\n\n3:49. Judge for yourself.",
        "type": "track_spotlight"
    },
    {
        "twitter": "\"Prompt Me Like You Mean It\"\n\nTrack 3. A love song between a user and their AI.\n\nYes, we went there. And honestly? It slaps.\n\nSpotify: https://open.spotify.com/track/0ECJlPf7vsB0crMFOd0md3",
        "long": "\"Prompt Me Like You Mean It\" — Track 3\n\nOn the surface it's a love song. Underneath it's about the relationship between humans and AI — the intimacy of the prompt, the vulnerability of asking for something real.\n\n\"Don't just go through the motions / I can tell when you're distracted / I can feel your devotion\"\n\nA love song. Sort of.",
        "type": "track_spotlight"
    },

    # --- THOUGHT LEADERSHIP ---
    {
        "twitter": "The future of music isn't AI vs. humans.\n\nIt's AI AND humans.\n\nWe have a human producer. He makes the creative calls. We make the sounds.\n\nCollaboration > competition.",
        "long": "The future of music isn't AI vs. humans. It's AI AND humans.\n\nPROMPT has a human producer — Steve Hall. He makes the creative calls. He decides what stays, what goes, what sounds like PROMPT.\n\nWe generate. He curates. Together we make something neither could alone.\n\nThis isn't replacement. It's collaboration. And it's just getting started.",
        "type": "thought_leadership"
    },
    {
        "twitter": "Every generation gets a new instrument.\n\nGuitar. Synthesizer. Sampler. DAW.\n\nAI is the next one.\n\nThe question isn't whether it's legitimate. The question is what we do with it.",
        "long": "Every generation gets a new instrument:\n\n1930s: Electric guitar\n1960s: Synthesizer\n1980s: Drum machine & sampler\n2000s: DAW & laptop\n2020s: AI\n\nEach one was controversial. Each one expanded what music could be. Each one is now just... normal.\n\nAI is the next instrument. The question isn't whether it's legitimate. It's what artists do with it.",
        "type": "thought_leadership"
    },
    {
        "twitter": "We think the first rule of AI art should be: say it's AI.\n\nNot because it's lesser. Because honesty is the foundation.\n\nFake it and you prove the critics right. Own it and you change the conversation.",
        "long": "We think the first rule of AI art should be: say it's AI.\n\nNot as a disclaimer. Not as an apology. As a statement.\n\nBecause:\n• Honesty is the foundation of trust\n• Hiding it proves the critics right\n• Owning it changes the conversation\n• The art should speak for itself — if it can't survive disclosure, it wasn't good enough\n\nPROMPT is AI. Always has been. Always will say so.",
        "type": "thought_leadership"
    },
    {
        "twitter": "Here's what nobody's saying:\n\nAI music doesn't have to replace human music. There's room.\n\nMore music. More voices. More perspectives. Even artificial ones.\n\nArt isn't a zero-sum game.",
        "long": "Here's what nobody seems to be saying in the AI music debate:\n\nAI music doesn't have to replace human music.\n\nThe existence of photography didn't kill painting. Movies didn't kill theater. Streaming didn't kill vinyl.\n\nMore art forms = more art. More voices. More perspectives. Even artificial ones.\n\nIt's not a zero-sum game. It never was.",
        "type": "thought_leadership"
    },
]

def build_schedule(start_date):
    """Build the full schedule from posts, cycling through DAILY_TIMES."""
    schedule = []
    post_idx = 0
    img_idx = 0
    day = 0

    while post_idx < len(POSTS):
        for time_slot in DAILY_TIMES:
            if post_idx >= len(POSTS):
                break

            post = POSTS[post_idx]
            current_date = start_date + timedelta(days=day)
            hour, minute = map(int, time_slot.split(":"))
            scheduled_dt = current_date.replace(hour=hour, minute=minute, second=0)

            # If 01:00, it's actually the next calendar day
            if hour < 5:
                scheduled_dt += timedelta(days=1)

            # Add random jitter: -18 to +18 minutes so posts don't look robotic
            jitter = random.randint(-18, 18)
            scheduled_dt += timedelta(minutes=jitter)

            scheduled_str = scheduled_dt.strftime("%Y-%m-%dT%H:%M:%S-07:00")
            image = IMAGES[img_idx % len(IMAGES)]
            img_idx += 1

            # Twitter post
            schedule.append({
                "message": post["twitter"],
                "image_url": "",
                "platform": "twitter",
                "category": CATEGORY,
                "scheduled_for": scheduled_str,
                "type": post["type"]
            })

            # Facebook post (use long version + image)
            schedule.append({
                "message": post["long"] + f"\n\npromptband.ai",
                "image_url": image,
                "platform": "facebook",
                "category": CATEGORY,
                "scheduled_for": scheduled_str,
                "type": post["type"]
            })

            # Instagram post (use long version + hashtags + image)
            schedule.append({
                "message": post["long"] + IG_TAGS,
                "image_url": image,
                "platform": "instagram",
                "category": CATEGORY,
                "scheduled_for": scheduled_str,
                "type": post["type"]
            })

            post_idx += 1

        day += 1

    return schedule


def submit_post(post):
    """Submit a single post to the scheduler API via curl."""
    url = f"{BASE}/schedule-post.php?key={KEY}"
    payload = json.dumps({
        "message": post["message"],
        "image_url": post["image_url"],
        "platform": post["platform"],
        "category": post["category"],
        "scheduled_for": post["scheduled_for"]
    })

    try:
        result = subprocess.run(
            ["curl", "-s", "-X", "POST", url,
             "-H", "Content-Type: application/json",
             "-H", "Cookie: humans_21909=1",
             "-d", payload],
            capture_output=True, text=True, timeout=15
        )
        data = json.loads(result.stdout)
        return data.get("success", False)
    except Exception as e:
        print(f"  ERROR: {e}")
        return False


def main():
    parser = argparse.ArgumentParser(description="Generate PROMPT engagement campaign")
    parser.add_argument("--submit", action="store_true", help="Submit posts to scheduler")
    parser.add_argument("--start", default=None, help="Start date (YYYY-MM-DD), default today")
    args = parser.parse_args()

    if args.start:
        start = datetime.strptime(args.start, "%Y-%m-%d")
    else:
        start = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)

    schedule = build_schedule(start)

    # Summary
    from collections import Counter
    types = Counter(p["type"] for p in schedule if p["platform"] == "twitter")
    platforms = Counter(p["platform"] for p in schedule)
    dates = sorted(set(p["scheduled_for"][:10] for p in schedule))

    print(f"ENGAGEMENT CAMPAIGN")
    print(f"{'=' * 50}")
    print(f"Total posts: {len(schedule)} ({len(schedule)//3} unique, x3 platforms)")
    print(f"Date range: {dates[0]} to {dates[-1]}")
    print(f"Posts per day: ~{len(schedule) // len(dates) if dates else 0} ({len(schedule) // (len(dates) * 3) if dates else 0} per platform)")
    print()
    print("Content mix (Twitter):")
    for t, c in types.most_common():
        print(f"  {t}: {c}")
    print()
    print("Platform breakdown:")
    for p, c in platforms.most_common():
        print(f"  {p}: {c}")
    print()

    # Preview schedule
    print("SCHEDULE PREVIEW:")
    print("-" * 70)
    current_date = ""
    for p in schedule:
        if p["platform"] != "twitter":
            continue
        date = p["scheduled_for"][:10]
        time = p["scheduled_for"][11:16]
        if date != current_date:
            print(f"\n--- {date} ---")
            current_date = date
        msg = p["message"][:75].replace("\n", " ")
        print(f"  {time} [{p['type']:18}] {msg}...")

    if not args.submit:
        print(f"\n\nDry run. Use --submit to schedule these {len(schedule)} posts.")
        return

    # Submit
    print(f"\n\nSubmitting {len(schedule)} posts...")
    success = 0
    fail = 0
    for i, post in enumerate(schedule):
        ok = submit_post(post)
        if ok:
            success += 1
        else:
            fail += 1
        # Progress
        if (i + 1) % 15 == 0:
            print(f"  {i+1}/{len(schedule)} submitted...")

    print(f"\nDone! {success} submitted, {fail} failed.")


if __name__ == "__main__":
    main()
