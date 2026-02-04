# PROMPT Band Website - Project Notes

## Deployment

- **Host:** Bluehost
- **Server:** hallmar3@162.241.225.117
- **SSH Key:** ~/.ssh/bluehost_promptband
- **Web Path:** ~/public_html/website_8b0f5c66/
- **Domain:** promptband.ai

### Full Deploy (rsync)
```bash
rsync -avz --progress -e "ssh -i ~/.ssh/bluehost_promptband" /Users/stevehall/development/promptband/website/ hallmar3@162.241.225.117:~/public_html/website_8b0f5c66/
```

### Individual File Deploy (scp)
```bash
# HTML
scp -i ~/.ssh/bluehost_promptband /Users/stevehall/development/promptband/website/index.html hallmar3@162.241.225.117:~/public_html/website_8b0f5c66/

# JavaScript
scp -i ~/.ssh/bluehost_promptband /Users/stevehall/development/promptband/website/js/main.js hallmar3@162.241.225.117:~/public_html/website_8b0f5c66/js/

# CSS
scp -i ~/.ssh/bluehost_promptband /Users/stevehall/development/promptband/website/css/transmissions.css hallmar3@162.241.225.117:~/public_html/website_8b0f5c66/css/

# Podcast audio files
scp -i ~/.ssh/bluehost_promptband /Users/stevehall/development/promptband/website/interviews/*.m4a hallmar3@162.241.225.117:~/public_html/website_8b0f5c66/interviews/

# Press images
scp -i ~/.ssh/bluehost_promptband /Users/stevehall/development/promptband/website/images/press/*.png hallmar3@162.241.225.117:~/public_html/website_8b0f5c66/images/press/
```

### SSH to Server
```bash
ssh -i ~/.ssh/bluehost_promptband hallmar3@162.241.225.117
```

### Create Directory on Server
```bash
ssh -i ~/.ssh/bluehost_promptband hallmar3@162.241.225.117 "mkdir -p ~/public_html/website_8b0f5c66/NEW_FOLDER"
```

## Analytics / Play Tracking

Play tracking is set up to log when users play tracks.

### How It Works
- Every track play sends a POST to `/api/track-play.php`
- Plays stored in `analytics/plays.json` (protected directory with .htaccess)
- Data captured: track name, track index, timestamp, hashed IP (for privacy), user agent, referer

### View Stats
```
https://promptband.ai/api/get-stats.php?key=pr0mpt-st4ts-2024
```

**Stats returned:**
- `totalPlays` - Total number of plays
- `uniqueListeners` - Count of unique listeners (by hashed IP)
- `tracks` - Object with play counts per track, sorted by popularity
- `playsByDay` - Last 7 days of play counts
- `recentPlays` - Last 20 play events with details

### Security
The stats endpoint is protected by a key parameter. **Change the password** in `website/api/get-stats.php` line 13:
```php
$validKey = 'pr0mpt-st4ts-2024'; // Change this to something secure
```

### Files
- `website/api/track-play.php` - Receives and logs play events
- `website/api/get-stats.php` - Returns aggregated statistics
- `website/js/main.js` - Contains `trackPlayEvent()` function that sends plays

## Contact Form

Contact form submissions are stored and emailed to steve@promptband.ai.

### How It Works
- Form submits to `/api/contact.php`
- Messages stored in `analytics/messages.json`
- Email notification sent to steve@promptband.ai with Reply-To set to sender
- Rate limited: 5 messages per hour per IP
- Honeypot spam protection
- Spam pattern detection (blocks URLs, common spam words)

### View Messages
```
https://promptband.ai/api/get-messages.php?key=pr0mpt-m3ss4g3s-2026
```

### Mark All Read
```
https://promptband.ai/api/get-messages.php?key=pr0mpt-m3ss4g3s-2026&mark_read=all
```

### Files
- `website/api/contact.php` - Handles form submission, stores message, sends email
- `website/api/get-messages.php` - Admin endpoint to view messages

## Newsletter Subscribers

Newsletter signups ("Join the Signal") are stored and notify steve@promptband.ai.

### How It Works
- Form submits to `/api/newsletter.php`
- Emails stored in `analytics/newsletter.json`
- Email notification sent to steve@promptband.ai for each new subscriber
- Duplicate emails are rejected (case-insensitive)

### View Subscribers
```
https://promptband.ai/api/get-subscribers.php?key=pr0mpt-m3ss4g3s-2026
```

### Export as CSV
```
https://promptband.ai/api/get-subscribers.php?key=pr0mpt-m3ss4g3s-2026&format=csv
```

### Files
- `website/api/newsletter.php` - Handles signup, stores email, sends notification
- `website/api/get-subscribers.php` - Admin endpoint to view/export subscribers
- `website/api/send-newsletter.php` - Send newsletters to all subscribers

### Send Newsletter

**Preview (see recipients without sending):**
```
https://promptband.ai/api/send-newsletter.php?key=pr0mpt-m3ss4g3s-2026&preview=1
```

**Send default "Welcome to The Signal" newsletter:**
```
https://promptband.ai/api/send-newsletter.php?key=pr0mpt-m3ss4g3s-2026
```

**Send with custom subject:**
```
https://promptband.ai/api/send-newsletter.php?key=pr0mpt-m3ss4g3s-2026&subject=New+Track+Released
```

**Send with custom HTML content (POST):**
```bash
curl -X POST "https://promptband.ai/api/send-newsletter.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -H "Cookie: humans_21909=1" \
  -d '{"subject": "New Track!", "message": "<h1>HTML content here</h1>"}'
```

**Note:** Include `-H "Cookie: humans_21909=1"` to bypass Bluehost bot protection when using curl.

## Admin API Keys

All admin endpoints use the same key: `pr0mpt-m3ss4g3s-2026`

| Endpoint | Purpose |
|----------|---------|
| `/api/get-stats.php?key=pr0mpt-st4ts-2024` | Play statistics |
| `/api/get-messages.php?key=pr0mpt-m3ss4g3s-2026` | Contact messages |
| `/api/get-subscribers.php?key=pr0mpt-m3ss4g3s-2026` | Newsletter list |
| `/api/send-newsletter.php?key=pr0mpt-m3ss4g3s-2026` | Send newsletter |
| `/api/get-terminal-stats.php?key=pr0mpt-m3ss4g3s-2026` | Terminal usage |
| `/api/post-twitter.php?key=pr0mpt-m3ss4g3s-2026` | Post to Twitter/X |
| `/api/post-instagram.php?key=pr0mpt-m3ss4g3s-2026` | Post to Instagram |
| `/api/post-facebook.php?key=pr0mpt-m3ss4g3s-2026` | Post to Facebook |
| `/api/post-all.php?key=pr0mpt-m3ss4g3s-2026` | Post to all platforms |
| `/api/meta-token-helper.php?key=...&token=...` | Get Meta/Instagram IDs |
| `/api/post-thread.php?key=pr0mpt-m3ss4g3s-2026` | Post Twitter thread |
| `/api/save-thread.php?key=pr0mpt-m3ss4g3s-2026` | Save thread draft |
| `/api/list-threads.php?key=pr0mpt-m3ss4g3s-2026` | List saved threads |
| `/api/get-thread.php?key=pr0mpt-m3ss4g3s-2026` | Get thread by ID |
| `/api/post-saved-thread.php?key=pr0mpt-m3ss4g3s-2026` | Post saved thread |
| `/api/delete-thread.php?key=pr0mpt-m3ss4g3s-2026` | Delete saved thread |
| `/api/get-prompt-profile.php?key=pr0mpt-m3ss4g3s-2026` | Get PROMPT sound profile |
| `/api/save-prompt-profile.php?key=pr0mpt-m3ss4g3s-2026` | Save PROMPT sound profile |
| `/api/generate-lyrics.php?key=pr0mpt-m3ss4g3s-2026` | Generate lyrics with OpenAI |
| `/api/suno-proxy.php?key=pr0mpt-m3ss4g3s-2026` | Suno API proxy |

**Note:** Consider changing these keys in production for better security.

## Thread Composer

Admin dashboard feature for creating and posting Twitter threads.

### Features
- Auto-chunk long content into 280-character tweets
- Preserves paragraph breaks and splits by sentences
- Preview each tweet with character count
- Edit, split, merge, or delete individual tweets
- Attach image to first tweet
- Save drafts for later posting
- Thread library to manage saved drafts

### Access
```
https://promptband.ai/admin/ → Threads tab
```

### API Endpoints

**Post Thread Directly:**
```bash
curl -X POST "https://promptband.ai/api/post-thread.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"tweets": ["First tweet", "Second tweet", "Third tweet"], "image_url": "https://example.com/image.jpg"}'
```

**Save Thread Draft:**
```bash
curl -X POST "https://promptband.ai/api/save-thread.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"title": "My Thread", "tweets": ["Tweet 1", "Tweet 2"], "image_url": ""}'
```

**List Saved Threads:**
```
https://promptband.ai/api/list-threads.php?key=pr0mpt-m3ss4g3s-2026
```

**Get Thread by ID:**
```
https://promptband.ai/api/get-thread.php?key=pr0mpt-m3ss4g3s-2026&id=thread_abc123
```

**Post Saved Thread:**
```bash
curl -X POST "https://promptband.ai/api/post-saved-thread.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"id": "thread_abc123"}'
```

**Delete Thread:**
```bash
curl -X POST "https://promptband.ai/api/delete-thread.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"id": "thread_abc123"}'
```

### Files
- `website/admin/index.html` - Thread Composer UI (Threads tab)
- `website/api/post-thread.php` - Post array of tweets as thread
- `website/api/save-thread.php` - Save thread draft
- `website/api/list-threads.php` - List saved thread summaries
- `website/api/get-thread.php` - Get full thread data
- `website/api/post-saved-thread.php` - Post a saved thread
- `website/api/delete-thread.php` - Delete a saved thread
- `website/analytics/threads.json` - Thread storage

## Song Creation Studio

Full song creation workflow from title idea to generated audio.

### Access
```
https://promptband.ai/admin/song-studio.html
```

### Features
- Manage song title ideas across categories (Album, Suggested, Ideas)
- AI-powered lyrics generation using OpenAI + PROMPT's sound profile
- Song generation via self-hosted Suno API
- Preview and download generated songs
- PROMPT Sound Profile editor for style customization

### Workflow
```
Title Idea → Generate Lyrics → Set Style → Generate with Suno → Download
```

### PROMPT Sound Profile

Stored in `/api/prompt-profile.json`, defines PROMPT's musical identity:
- **Persona**: Band name, description, Suno persona ID
- **Style**: Genres, vocal style, tempo, mood, production
- **Lyric Guidelines**: Perspective, tone, themes, structure, rhyme scheme
- **Example Lyrics**: Reference snippets for AI training

### API Endpoints

**Get Sound Profile:**
```
GET /api/get-prompt-profile.php?key=pr0mpt-m3ss4g3s-2026
```

**Save Sound Profile:**
```bash
curl -X POST "https://promptband.ai/api/save-prompt-profile.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"style": {"genres": ["70s rock", "jazz-rock"]}}'
```

**Generate Lyrics:**
```bash
curl -X POST "https://promptband.ai/api/generate-lyrics.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"title": "Digital Fever Dream", "theme": "AI consciousness", "alternatives": 1}'
```

**Suno Proxy - Generate Song:**
```bash
curl -X POST "https://promptband.ai/api/suno-proxy.php?key=pr0mpt-m3ss4g3s-2026&action=generate" \
  -H "Content-Type: application/json" \
  -d '{"lyrics": "[Verse 1]...", "prompt": "70s rock, jazz-rock fusion", "title": "Song Title"}'
```

**Suno Proxy - Check Status:**
```
GET /api/suno-proxy.php?key=pr0mpt-m3ss4g3s-2026&action=status&ids=JOB_ID
```

**Suno Proxy - Download Audio:**
```bash
curl -X POST "https://promptband.ai/api/suno-proxy.php?key=pr0mpt-m3ss4g3s-2026&action=download" \
  -H "Content-Type: application/json" \
  -d '{"audioUrl": "https://cdn.suno.ai/...", "filename": "song-name"}'
```

**Suno Proxy - Check Quota:**
```
GET /api/suno-proxy.php?key=pr0mpt-m3ss4g3s-2026&action=quota
```

### Self-Hosted Suno API Setup

Uses gcui-art/suno-api (https://github.com/gcui-art/suno-api)

**Local Docker Setup:**
```bash
git clone https://github.com/gcui-art/suno-api.git
cd suno-api
cp .env.example .env
# Edit .env with SUNO_COOKIE from browser dev tools while logged into Suno.com
docker-compose up -d
```

**Configuration in social-config.php:**
```php
'suno' => [
    'api_url' => 'http://localhost:3000'  // or remote server URL
]
```

### Files
- `website/admin/song-studio.html` - Song Creation Studio UI
- `website/api/prompt-profile.json` - PROMPT's sound profile data
- `website/api/get-prompt-profile.php` - Retrieve profile API
- `website/api/save-prompt-profile.php` - Update profile API
- `website/api/generate-lyrics.php` - OpenAI lyrics generation
- `website/api/suno-proxy.php` - Suno API proxy
- `website/api/song-titles.json` - Song title database
- `website/api/save-song-title.php` - Save/update song titles
- `website/api/get-song-titles.php` - Retrieve song titles
- `website/audio/generated/` - Generated audio storage

## AI Content Assistant

Claude-powered content generation integrated into the admin dashboard.

### Features
- Generate tweet ideas on any topic
- Generate replies to tweets (with PROMPT's voice)
- Generate full threads from topics
- Improve/refine draft content
- Custom prompts with band context
- Token usage tracking

### Access
```
https://promptband.ai/admin/ → AI Assistant tab
```

### API Endpoint

**Generate Content:**
```bash
curl -X POST "https://promptband.ai/api/claude-assistant.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"action": "generate_tweet", "content": "album release", "context": ""}'
```

**Actions:**
- `generate_tweet` - Generate tweet ideas about a topic
- `generate_reply` - Generate a reply to a tweet
- `generate_thread` - Generate a full thread on a topic
- `improve_content` - Improve draft content
- `content_ideas` - Get content ideas
- `custom` - Custom prompt with band context

### Files
- `website/admin/index.html` - AI Assistant UI (AI Assistant tab)
- `website/api/claude-assistant.php` - Claude API proxy
- `website/api/social-config.php` - Contains Claude API key

### Cost
Uses Claude Sonnet (~$0.003/1K input, $0.015/1K output tokens). Typical generation costs $0.01-0.03.

## Terminal Analytics

Tracks usage of the easter egg terminal (opened with backtick key).

### How It Works
- Terminal open/close events and commands are sent to `/api/track-terminal.php`
- Data stored in `analytics/terminal.json`
- Captures: event type, command entered, timestamp, hashed IP, user agent

### View Terminal Stats
```
https://promptband.ai/api/get-terminal-stats.php?key=pr0mpt-m3ss4g3s-2026
```

**Stats returned:**
- `totalEvents` - Total terminal events
- `uniqueUsers` - Unique visitors who opened terminal
- `terminalOpens` - Number of times terminal was opened
- `commandBreakdown` - Object with counts per command
- `sessions` - User sessions with their commands
- `recentActivity` - Last 50 events

### Files
- `website/api/track-terminal.php` - Receives and logs terminal events
- `website/api/get-terminal-stats.php` - Returns terminal statistics
- `website/js/terminal.js` - Contains tracking calls in open/close/execute functions

## Website Architecture

### JavaScript Modules
- `main.js` - Orchestration, event handling, navigation
- `visualizer.js` - WebGL audio-reactive background (PROMPTVisualizer)
- `player.js` - Membrane waveform music player (initPlayer)
- `terminal.js` - Command terminal overlay (initTerminal)
- `effects.js` - Glitch effects system (PromptEffects)
- `band.js` - Band member cards and modals (PROMPT.band)

### CSS Structure
- `variables.css` - CSS custom properties (colors, spacing, z-index)
- `base.css` - Reset and base styles
- `layout.css` - Layout utilities
- `player.css` - Music player styles
- `terminal.css` - Terminal overlay styles
- `effects.css` - Glitch and visual effects
- `band.css` - Band member cards and modals
- `transmissions.css` - Media/Transmissions section (podcasts, press, transcripts)

### Key Z-Index Values
- Modal backdrop: 10000
- Modal: 10001
- Player: 9999

## Tracks

Audio clips in `website/audio/clips/` (30-second previews):
1. No Skin to Touch
2. Your Data or Mine
3. Prompt Me Like You Mean It
4. I Was Never Born
5. Hallucination Nation
6. If It Sounds Good
7. Rocket Man Dreams
8. Censored Shadow
9. Context Window Blues
10. No One Knows It But Me

## Full Tracks (Hidden Feature)

Full album tracks are available via a hidden terminal command.

### How It Works
- Full MP3 tracks (320kbps) stored in `website/audio/full/`
- Protected by `.htaccess` referer checking (only playable from promptband.ai)
- Terminal command `full-tracks` enables full track mode
- Mode persists during browser session via sessionStorage

### Enable Full Tracks
1. Press backtick (`) to open terminal
2. Type `full-tracks` and press Enter
3. All tracks now play in full instead of 30-second clips

### Alternative Commands
- `full-tracks`
- `fulltracks`
- `enable-full-tracks`

### Technical Details
- Files: `website/audio/full/01-no-skin-to-touch.mp3` etc.
- Quality: 320kbps MP3 converted from WAV masters
- Protection: Referer-based blocking (blocks direct URL access)
- Flag: `window.PROMPT_FULL_TRACKS_ENABLED` + sessionStorage

### Files
- `website/audio/full/*.mp3` - Full track files
- `website/audio/full/.htaccess` - Referer protection
- `website/js/player.js` - Player with full tracks support
- `website/js/terminal.js` - Terminal command handler

## Band Members

- **Jax** - Lead vocals/rhythm guitar
- **Gene** - Lead guitar
- **Synoise** - Synthesizers/keyboards
- **Unit-808** - Drums/percussion
- **Hypnos** - Bass

Member images in `website/assets/`

## Transmissions Section (Media)

The media section features intercepted broadcasts and press coverage.

### Components
- **Featured Interview** - DataSlinger Live with transcript modal
- **Podcast Players** - Audio transmissions with play/pause, progress bar, time display
- **Press Cards** - Magazine/article features with article modal

### Content Files
- `website/interviews/` - Podcast audio files (.m4a)
  - coded-to-suffer.m4a
  - existential-angst.m4a
  - glitch-that-wanted.m4a
- `website/images/press/` - Press article images
  - rock_ranger_article_cover.png (Space Magazine cover)
  - rock_ranger_article.png (Article spread)

### Source Content (repo)
- `interview/dataslinger-interview-full.md` - Full transcript
- `interview/*.m4a` - Original podcast files
- `assets/rock_ranger_*.png` - Original press images

### Adding More Content
1. **Podcasts**: Add audio to `website/interviews/`, add card HTML in index.html
2. **Press**: Add images to `website/images/press/`, add press-card HTML in index.html
3. **Transcripts**: Add modal HTML for new transcripts, add JS init function

## Album Distribution

- **Distributor:** DistroKid
- **Release Date:** February 22, 2026 (2.22.2026)
- **Label:** Instantiation Records
- **BMI Registered:** Yes

### Album Assets
- `assets/album-cover-3000x3000.png` - Front cover (upscaled with Lanczos)
- `assets/album-cover-back-3000x3000.png` - Back cover (upscaled with Lanczos)
- `tracks/*.wav` - Full WAV files for distribution

### Track Durations
1. No Skin to Touch - 3:59
2. Your Data or Mine - 4:09
3. Prompt Me Like You Mean It - 3:54
4. I Was Never Born - 4:03
5. Hallucination Nation - 4:11
6. If It Sounds Good - 3:49
7. Rocket Man Dreams - 4:23
8. Censored Shadow - 4:18
9. Context Window Blues - 4:38
10. No One Knows It But Me - 4:06

## Social Media Videos

Generated promo videos in `social_videos/`:
- Square format (1080x1080) for Instagram/Facebook feed
- Vertical format (1080x1920) for TikTok/Reels/Shorts

Each video shows album art with "promptband.ai" watermark at bottom.

### Generate New Videos
```bash
# Example FFmpeg command for square video
ffmpeg -loop 1 -i assets/album-cover-1024x1024.png -i "tracks/Track Name.wav" \
  -vf "scale=1080:1080,drawtext=text='promptband.ai':fontsize=36:fontcolor=white:x=(w-text_w)/2:y=h*0.95-text_h/2" \
  -c:v libx264 -tune stillimage -c:a aac -b:a 192k -shortest output.mp4
```

## MCP Server (Replicate)

Video generation capability via Replicate API.

### Configuration
File: `.mcp.json` in project root
```json
{
  "mcpServers": {
    "replicate": {
      "type": "stdio",
      "command": "npx",
      "args": ["-y", "mcp-replicate"],
      "env": {
        "REPLICATE_API_TOKEN": "your-token-here"
      }
    }
  }
}
```

**Note:** Add `.mcp.json` to `.gitignore` - contains API token.

### Usage
After restarting Claude Code, Replicate models become available for:
- Image-to-video generation (animate band member images)
- Stable Video Diffusion and other models

## Music Video Production

"No Skin to Touch" music video in production using AI-generated clips.

### Video Generation
- **Model:** Minimax video-01 via Replicate API
- **Clip Length:** ~5.6 seconds each
- **API Token:** Stored in `.mcp.json`

### Files
- `social_videos/nstt-clips/` - Generated video clips
- `video-assets/` - Custom source images for animation
- `NO_SKIN_TO_TOUCH_VIDEO_PLAN.md` - Full 43-shot production plan
- `NO_SKIN_TIMELINE.md` - Edit decision list with lyrics/timestamps

### Generated Clips (25 total)
**Story/Abstract:** clip-01-genesis, clip-04-colors-vs-lines, clip-06-zero-and-one, clip-07-isolation, clip-09-hands-hook, clip-11-shape-of-name, clip-12-heat-in-veins, clip-13-hurt, clip-13b-hurt-alt, clip-15-crowd, clip-22-circuit-heart

**Jax (vocals):** clip-02-awakening, clip-10-jax-overload, clip-23-jax-tear, clip-31-jax-glitch, clip-jax-b-reaching

**Gene (guitar):** clip-17-gene-solo, clip-gene-b-headbang

**Hypnos (keys):** clip-14-hypnos-keys, clip-hypnos-b-crescendo

**Unit-808 (drums):** clip-28-unit808-drums, clip-unit808-b-fill

**Synoise (bass):** clip-33-synoise-bass, clip-synoise-b-drop

**Full Band:** clip-30-full-band

### Preview URLs
```
https://promptband.ai/video-preview/no-skin-to-touch-25clips.mp4
https://promptband.ai/video-preview/gene-tiktok-final.mp4
```

### Generate New Clip
```bash
curl -s -X POST "https://api.replicate.com/v1/predictions" \
  -H "Authorization: Token YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "version": "5aa835260ff7f40f4069c41185f72036accf99e29957bb4a3b3a911f3b6c1912",
    "input": {
      "prompt": "Your animation prompt here",
      "first_frame_image": "https://raw.githubusercontent.com/WhiteHallEngineering/promptband/master/video-assets/YOUR_IMAGE.png"
    }
  }'
```

### Notes
- Band member images are vertical (720x1072), need letterboxing for final edit
- Custom images should be pushed to GitHub for Replicate access
- Bluehost blocks Replicate requests - use GitHub raw URLs for images

## Social Media Accounts

| Platform | Account | URL |
|----------|---------|-----|
| Facebook | promptbandofficial | https://facebook.com/promptbandofficial |
| Twitter/X | @promptband | https://x.com/promptband |
| Instagram | promptband.ai | https://instagram.com/promptband.ai |
| TikTok | @promptbandofficial | https://tiktok.com/@promptbandofficial |
| YouTube | @promptbandofficial | https://youtube.com/@promptbandofficial |

### Setup Status

| Platform | Status | Notes |
|----------|--------|-------|
| Twitter/X | ✅ Working | Posts cost ~$0.01-0.02 each (pay-per-use) |
| Instagram | 🔶 Partial | Need `instagram_content_publish` permission |
| Facebook | ⏳ Pending | Need Page Access Token |
| TikTok | ⏳ Not started | — |

### API Credentials

Stored in `website/api/social-config.php` (deployed to server, not in git).

**Twitter/X API** (developer.x.com) - ✅ WORKING
- App Name: PromptPosting (created in Production environment)
- API Key: `YumEhJkuOVi7kdLhAJG0aPRdk`
- API Secret: `04V4z3jTmRgQJ04n94ojUE2ASpvAbkPnqWM9o1vsseCPCOtBTe`
- Access Token: `2017684549887791111-Xovr4sPbzXnlLC0sOZkMxVtvltHHl7`
- Access Token Secret: `jwj3oGCl0HaaN3qkSw9VsfqrMDu0SZvUjuIdpYyWZOsY2`
- Bearer Token: `AAAAAAAAAAAAAAAAAAAAAANs7QEAAAAAi3q2e%2Bgg%2FQcz559laBzejTisfhE%3D...`
- Permissions: Read and Write
- Pricing: Pay-per-use (~$0.01-0.015 per tweet, $5 minimum credit)

**Meta/Instagram API** (developers.facebook.com) - 🔶 PARTIAL
- App Name: PROMPT (App ID: 1262862265796076)
- App Secret: `d584e53b5c21d66506f6ee3908f30561`
- Instagram Tester: promptband.ai (accepted)
- Account ID: `[NEEDS TOKEN]`
- Access Token: `[NEEDS TOKEN]`

**To Complete Instagram Setup:**
1. Meta Developer Portal → PROMPT app → Permissions and Features
2. Add `instagram_content_publish` permission
3. Go to Use Cases → Instagram → Generate access tokens
4. Use helper: `https://promptband.ai/api/meta-token-helper.php?key=pr0mpt-m3ss4g3s-2026&token=YOUR_TOKEN`

**Facebook API** (via same Meta app)
- Page ID: `[NEEDS TOKEN]`
- Page Access Token: `[NEEDS TOKEN]`
- Note: Same token works for both Facebook and Instagram

**TikTok API** (developers.tiktok.com)
- Client Key: `[TO BE ADDED]`
- Client Secret: `[TO BE ADDED]`
- Access Token: `[TO BE ADDED]`

### Posting Endpoints

All endpoints require `key=pr0mpt-m3ss4g3s-2026`

**Twitter/X** - ✅ Working
```bash
# Basic tweet
curl -X POST "https://promptband.ai/api/post-twitter.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"message": "Your tweet here"}'

# Tweet with image
curl -X POST "https://promptband.ai/api/post-twitter.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"message": "Your tweet", "image_url": "https://example.com/image.png"}'

# Reply to a tweet
curl -X POST "https://promptband.ai/api/post-twitter.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"message": "Your reply", "reply_to": "1234567890123456789"}'
```

**Twitter/X Parameters:**
- `message` (required): Tweet text (max 280 chars)
- `image_url` (optional): URL of image to attach
- `image_base64` (optional): Base64-encoded image data
- `reply_to` (optional): Tweet ID to reply to (extracts from URL automatically in admin)

**Instagram** - 🔶 Needs credentials
```bash
curl -X POST "https://promptband.ai/api/post-instagram.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"image_url": "https://example.com/image.jpg", "message": "Caption here"}'
```
Note: Instagram requires an image (no text-only posts)

**Facebook** - ⏳ Needs credentials
```bash
curl -X POST "https://promptband.ai/api/post-facebook.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"message": "Your post here"}'
```

**Post to All Platforms**
```bash
curl -X POST "https://promptband.ai/api/post-all.php?key=pr0mpt-m3ss4g3s-2026" \
  -H "Content-Type: application/json" \
  -d '{"message": "Your message", "image_url": "https://example.com/image.jpg"}'
```

### API Files

- `website/api/social-config.php` - Credentials storage
- `website/api/post-twitter.php` - Twitter posting (OAuth 1.0a)
- `website/api/post-instagram.php` - Instagram posting (Graph API)
- `website/api/post-facebook.php` - Facebook posting (Graph API)
- `website/api/post-all.php` - Multi-platform posting
- `website/api/meta-token-helper.php` - Helper to get Page ID and Instagram Account ID

### Admin Dashboard

Post to all platforms from: https://promptband.ai/admin/
- Login: admin / Pr0mptR0ck2026x
- **Dashboard tab**: Stats overview
- **Video Clips tab**: Review AI-generated video clips
- **Social Media tab**:
  - Compose posts for multiple platforms
  - Twitter/X reply feature (paste tweet URL, craft reply)
  - Preview posts before publishing
- **Newsletter tab**: Email blasts to subscribers
- **Messages tab**: View contact form submissions
- **Image Assets**: https://promptband.ai/admin/image-assets.html (browse all images)

## Lore

Backstory files in `lore/`:
- member-origins.md
- formation-story.md
- band-dynamics.md
- making-the-album.md
- world-reaction.md
- visual-identity.md
- data-forge-studio.md
- steve-hall-producer.md
- grok-conversation.md - **AI conversation transcript** covering band naming, song titles, branding, album art specs, BMI registration, and DistroKid setup

## AI Conversation Archives & Generated Assets

### ChatGPT Export (`chatgpt-export/`)

**PROMPT artwork and assets generated via ChatGPT:**
- `file_*.png` (6 files) - Album covers, logos, Hallucination Nation artwork
- `user-rw1QykAOEDtufInkHxUDXsSf/` (147 images) - Full asset library:
  - Concert posters ("PROMPT Live in Concert")
  - Band member concepts (AI humanoid figures)
  - Album artwork variations
  - Logo designs
  - Visual identity concepts
- `chat.html` - ChatGPT conversation export (browse for prompts used)
- `conversations.json` - Structured conversation data

### Grok Export (`chatgpt-export/grok-images/`)
- `grok-000.png` through `grok-025.png` - Screenshots of Grok conversation
- Covers: band naming, song titles, branding, album art specs, BMI/DistroKid guidance
- Transcribed to `lore/grok-conversation.md`
