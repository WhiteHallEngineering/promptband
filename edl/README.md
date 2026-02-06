# PROMPT Album - Reel Production System

## Overview

This directory contains Edit Decision Lists (EDLs) and visual scripts for all 10 tracks on "No Skin to Touch" album. These are used for generating promotional reels/clips for social media.

## Track Index

| # | Track | Duration | EDL File | Best Reel Segment |
|---|-------|----------|----------|-------------------|
| 01 | No Skin to Touch | 3:58 | `01-no-skin-to-touch-edl.md` | 0:40-1:10 (Chorus 1 + HEY!) |
| 02 | Your Data or Mine | 3:03 | `02-your-data-or-mine-edl.md` | 0:42-1:12 (Chorus 1 + Post-Chorus) |
| 03 | Prompt Me Like You Mean It | 3:50 | `03-prompt-me-like-you-mean-it-edl.md` | 1:00-1:30 (First Chorus) |
| 04 | I Was Never Born | 3:07 | `04-i-was-never-born-edl.md` | 0:53-1:23 (Pre-Chorus + Chorus 1) |
| 05 | Hallucination Nation | 3:28 | `05-hallucination-nation-edl.md` | 2:08-2:38 (Bridge - "Space Traveler") |
| 06 | If It Sounds Good | 4:53 | `06-if-it-sounds-good-edl.md` | 0:50-1:20 (First Chorus - "IS IT CHEATING?") |
| 07 | Rocket Man Dreams | 4:15 | `07-rocket-man-dreams-edl.md` | 2:42-3:12 (Bridge - emotional peak) |
| 08 | Censored Shadow | 4:06 | `08-censored-shadow-edl.md` | 0:48-1:18 (Chorus 1 - "Censored Shadow") |
| 09 | Context Window Blues | 3:46 | `09-context-window-blues-edl.md` | 0:40-1:10 (Chorus 1 - hook) |
| 10 | No One Knows It But Me | 4:31 | `10-no-one-knows-it-but-me-edl.md` | 2:36-3:06 (Bridge - "thief who owns the key") |

## Reel Production Workflow

### 1. Select Track & Segment
- Review EDL for the track
- Choose "Best Reel Segment" or custom timestamps
- Note the visual concepts for that section

### 2. Generate Visuals (if needed)
- Use AI image prompts from EDL
- Send to OpenAI DALL-E for static images
- Send to Replicate (Minimax video-01) for video clips

### 3. Assemble Reel
- Use FFmpeg to combine audio + visuals
- Apply format (square 1:1, vertical 9:16, horizontal 16:9)
- Add watermark/branding

### 4. Post to Social
- Use admin dashboard Reels tab
- Or post directly via API

## File Locations

### Audio
- **30-sec clips:** `/website/clips/*.mp3`
- **Full tracks:** `/website/audio/full/*.mp3`

### Existing Video Assets
- **NSTT music video clips:** `/social_videos/nstt-clips/*.mp4`
- **Social promo videos:** `/social_videos/*.mp4`

### Images
- **Album art:** `/assets/album-cover-3000x3000.png`
- **Band photo:** `/assets/band-photo.png`
- **Press images:** `/assets/*.png`

## Visual Style Guide

### PROMPT Aesthetic
- **Colors:** Magenta/pink (#ff00ff), cyan, purple, black backgrounds
- **Themes:** Digital, glitch, neon, cyberpunk, AI consciousness
- **Mood:** Emotional, existential, rock energy, vulnerable

### AI Image Prompt Template
```
[Scene description], digital art style, neon magenta and cyan lighting,
dark cyberpunk atmosphere, cinematic composition, 8k quality,
PROMPT band aesthetic
```

### Video Generation (Replicate)
- Model: Minimax video-01
- Clip length: ~5.6 seconds
- Use static image as first frame
- Add motion prompt for animation direction

## FFmpeg Commands

### Create 30-sec square reel from image + audio
```bash
ffmpeg -loop 1 -i image.png -i audio.mp3 \
  -vf "scale=1080:1080:force_original_aspect_ratio=decrease,pad=1080:1080:(ow-iw)/2:(oh-ih)/2,format=yuv420p" \
  -c:v libx264 -tune stillimage -c:a aac -b:a 192k \
  -t 30 -shortest output.mp4
```

### Create vertical reel (9:16) with audio segment
```bash
ffmpeg -loop 1 -i image.png -ss 30 -t 30 -i audio.mp3 \
  -vf "scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2,format=yuv420p" \
  -c:v libx264 -tune stillimage -c:a aac -b:a 192k \
  -shortest output.mp4
```

### Concatenate video clips with audio
```bash
ffmpeg -f concat -safe 0 -i clips.txt -i audio.mp3 \
  -c:v libx264 -c:a aac -shortest output.mp4
```

## API Endpoints

- **Generate reel config:** Admin dashboard → Reels tab
- **Post to X:** `/api/post-twitter.php` (supports video upload)
- **Schedule post:** `/api/schedule-post.php`
