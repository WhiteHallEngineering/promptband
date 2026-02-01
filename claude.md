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

**Note:** Consider changing these keys in production for better security.

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
