# Signal 0 Radio — Full Implementation Plan

## Status

- **Phase 1: Band Catalog & Admin** — COMPLETE
- **Phase 2: Songs & Lyrics Generation** — COMPLETE
- **Phase 3: Audio Generation (Suno)** — COMPLETE (code built, needs Suno API running)
- **Phase 4: Rotation & DJ Bumpers** — COMPLETE (code built, needs audio to test)
- **Phase 5: Station Player & Stream** — COMPLETE (code built, needs rotation songs)
- **Phase 6: Internet Radio Stream (Icecast)** — CONFIG READY (needs server setup)

## Current State

- 112 bands, 1,120 songs imported into JSON
- 10 approved bands (all Core Worlds so far)
- Chrome Cathedral: 10 songs with approved-ready lyrics (pending status)
- Admin UI: Bands tab (review/approve) + Songs tab (lyrics generation/editing)
- Self-hosted Suno API running locally at localhost:3000
- Suno proxy already exists at `/api/suno-proxy.php`
- ElevenLabs TTS already configured with DJ voice IDs

---

## Phase 3: Audio Generation (Suno)

### Goal
Generate audio for songs with approved lyrics via the self-hosted Suno API. Review, rate, and manage generated audio. NOT generating all 1,120 songs yet — just building the infrastructure and testing with a handful.

### New API Endpoints

| File | Purpose |
|------|---------|
| `website/api/signal-zero-generate.php` | Generate audio for a single song via suno-proxy |
| `website/api/signal-zero-audio.php` | Save/update audio status, rating, download URL |

### How Generation Works

1. Song must have `lyricsStatus: approved` before generating audio
2. POST to `signal-zero-generate.php` with `songId` + `bandSlug`
3. Endpoint reads song lyrics + band style from JSON
4. Calls `suno-proxy.php?action=generate` with:
   - `lyrics`: the approved lyrics text
   - `prompt`: band's style tags (from band data) as Suno style prompt
   - `title`: song title
   - `persona_id`: Suno persona if applicable (or omit for default voice)
5. Returns Suno job ID
6. Poll `suno-proxy.php?action=status&ids=JOB_ID` until complete
7. When complete, download audio via `suno-proxy.php?action=download`
8. Save MP3 to `/audio/signal-zero/{band-slug}/{song-slug}.mp3`
9. Update song JSON: `audioUrl`, `audioStatus: complete`, `sunoJobId`

### Song JSON Updates (new fields)

```json
{
  "audioUrl": "/audio/signal-zero/chrome-cathedral/empire-of-sound.mp3",
  "audioStatus": "none|generating|complete|rejected",
  "sunoJobId": "abc123",
  "sunoAudioUrl": "https://cdn.suno.ai/...",
  "audioRating": null,
  "audioGeneratedAt": null
}
```

### UI — Generation Tab (signal-zero.html)

**Layout:** Similar to Songs tab — band tree on left, generation panel on right.

**Left panel:** Same band tree, but filtered to bands with approved lyrics. Progress shows audio completion (e.g., "3/10 audio").

**Right panel — Generation Queue:**
- Song list with columns: #, Title, Lyrics Status, Audio Status, Rating, Actions
- Actions per song: Generate | Play | Rate (1-5 stars) | Regenerate | Reject
- "Generate All" button (batch) with rate limiting (~120s between calls)
- Batch progress bar with pause/cancel
- Suno quota display at top (calls `suno-proxy.php?action=quota`)

**Audio Player:**
- Inline play button per song row
- Simple HTML5 audio element, swap src on play
- Shows waveform or just progress bar

**Rate limiting:**
- Suno generates 2 clips per call
- ~120 second wait between generation calls recommended
- Batch generation respects this with built-in delay
- Queue state persists so you can close browser and resume

### Audio Storage

```
website/audio/signal-zero/
  chrome-cathedral/
    empire-of-sound.mp3
    cathedral.mp3
    ...
  the-velvet-collapse/
    ...
```

Server directory needs to be created: `ssh ... "mkdir -p ~/public_html/website_8b0f5c66/audio/signal-zero"`

### Suno Style Prompt Construction

Built from band data, NOT the lyrics. Example for Chrome Cathedral:
- Band style: "Arena rock, power pop, anthemic"
- Region flavor: Core Worlds = polished, big production
- Suno prompt: `"arena rock, power pop, anthemic, polished production, big vocals"`

Map band `style` field directly as Suno tags. Keep under 200 chars.

---

## Phase 4: Rotation & DJ Bumpers

### Goal
Build the playlist rotation system and generate DJ voice bumpers via ElevenLabs TTS.

### 4A: Rotation System

**New API Endpoints:**

| File | Purpose |
|------|---------|
| `website/api/signal-zero-rotation.php` | Get/set rotation assignments |

**Rotation Tiers:**
- **Heavy** (20%) — ~120 songs, played most often (4x weight)
- **Medium** (50%) — ~300 songs, regular rotation (2x weight)
- **Light** (30%) — ~180 songs, occasional plays (1x weight)
- Target: 600 songs total in rotation

**Assignment Logic:**
- Auto-assign based on: audio rating (higher = heavier rotation) + region balance
- Region balance: each region should have proportional representation
- Manual override: drag songs between tiers in UI

**UI — Rotation Tab:**
- Region balance bars (visual — how much of each region is in rotation)
- Song table with rotation tier dropdown (Heavy/Medium/Light/None)
- Auto-balance button: "Fill rotation to 600 songs"
- Stats: songs per tier, region breakdown

**Song JSON Updates:**
```json
{
  "rotation": "heavy|medium|light|null",
  "rotationAssignedAt": null
}
```

### 4B: DJ Bumpers (ElevenLabs TTS)

**New API Endpoints:**

| File | Purpose |
|------|---------|
| `website/api/signal-zero-bumper.php` | Generate TTS bumper, save audio |
| `website/api/signal-zero-bumpers.php` | List/delete bumpers |

**Bumper Types:**
1. **Station ID** — "You're listening to Signal 0 Radio. The frequency beneath all frequencies."
2. **Show Intro** — "This is The Morning Transmission with DataSlinger..."
3. **Band Intro** — "Coming up next, Chrome Cathedral from Novus Prime..."
4. **Song Outro** — "That was Empire of Sound by Chrome Cathedral..."
5. **Ad Read** — Sponsor reads (Void Walker Guitars, NovaBrew, etc.)
6. **Time Check** — "It's 14:00 Galactic Standard Time..."

**DJ Voices (from station config):**
- DataSlinger: voice ID `6U1YvBMme4j3Tp0kB6Xo` (already configured)
- Vex Kasra: voice ID `CwhRBWXzGAHq8TQ4Fs17` (already configured)
- Other DJs: need voice IDs assigned (clone or select from ElevenLabs library)

**Generation Flow:**
1. Select DJ, bumper type, enter text (or auto-generate from templates)
2. POST to ElevenLabs TTS API (`eleven_v3` model)
3. Save MP3 to `/audio/signal-zero/bumpers/{dj-slug}/{bumper-type}-{id}.mp3`
4. Track in `signal-zero-bumpers.json`

**Bumper Storage:**
```
website/audio/signal-zero/bumpers/
  dataslinger/
    station-id-001.mp3
    band-intro-chrome-cathedral.mp3
  vex-kasra/
    show-intro-001.mp3
  ...
```

**UI — DJs Tab:**
- DJ cards (from station config): name, show, time slot, voice ID, bumper count
- Click DJ → bumper management panel
- Bumper generator: select type, enter/generate text, preview, save
- Bumper library: list with play/delete per bumper
- Template system: auto-fill text for band intros ("Coming up next, {band} from {origin}...")

---

## Phase 5: Station Player (Admin Preview)

### Goal
Build a functional radio player in the admin that plays songs in rotation with DJ bumpers between tracks. This is the preview/test version before the public stream.

### How It Works

**Playlist Logic (client-side JavaScript):**
1. Load rotation songs + bumpers on init
2. Pick next song using weighted random:
   - Heavy = 4x chance, Medium = 2x, Light = 1x
3. No immediate repeats: track last N songs played (N=50)
4. Region variety: don't play same region back-to-back
5. Insert DJ bumper every 3-4 songs:
   - Band intro before the song
   - Occasional station ID
   - Time-appropriate DJ voice (match schedule)
6. Crossfade between tracks (Web Audio API or simple overlap)

**UI — Player Tab:**
- Large "now playing" display: song title, band name, region badge, album art placeholder
- Progress bar with elapsed/total time
- Up Next preview (next 3 songs + any bumpers)
- Play/Pause/Skip controls
- Volume control
- Like/Skip tracking (POST to analytics)
- "On Air" indicator

**Technical:**
- HTML5 Audio API for playback
- Preload next track while current plays
- Bumper audio elements preloaded
- Playlist state in memory (no server calls during playback except analytics)
- Falls back gracefully if audio files missing

---

## Phase 6: Internet Radio Stream (Icecast)

### Goal
Deploy a real internet radio stream that the Varo speaker (and any internet radio app) can tune into.

### Architecture

**Option A: Server-Side Stream (Icecast + Liquidsoap)**

This is the standard approach for internet radio:

```
Liquidsoap (playlist automation)
    ↓ generates continuous audio stream
Icecast2 (streaming server)
    ↓ serves stream to listeners
Varo Speaker / TuneIn / browser
```

**Liquidsoap** = programmable radio automation software
- Reads playlist from JSON/API
- Handles crossfading, bumper insertion, scheduling
- Outputs continuous audio stream to Icecast

**Icecast2** = streaming server
- Accepts source connections from Liquidsoap
- Serves MP3/OGG stream to listeners
- Provides listener stats, metadata (now playing)
- Standard protocol — works with all internet radio clients

### Server Requirements

Icecast + Liquidsoap need a server that can run background processes (not Bluehost shared hosting). Options:

1. **AWS Lightsail** (already have the REC instance at 18.220.30.151)
   - Could run Icecast there alongside WordPress
   - Need to install: `apt install icecast2 liquidsoap`
   - Low bandwidth for small listener count
   - Cost: already paying for instance

2. **Dedicated cheap VPS** (DigitalOcean $6/mo, Vultr, etc.)
   - Clean install, dedicated to streaming
   - More control, no conflict with other services

3. **Managed internet radio service** (Radio.co, Airtime/LibreTime)
   - Easier setup, handles infrastructure
   - Monthly cost but less maintenance

### Recommended: AWS Lightsail (REC Instance)

Since we already have the server, use it. Steps:

1. Install Icecast2 + Liquidsoap on the Lightsail instance
2. Upload song MP3s + bumper MP3s to the server
3. Configure Liquidsoap playlist script:
   - Reads rotation data from JSON (synced from Bluehost)
   - Weighted random selection matching Phase 5 logic
   - DJ bumper insertion every 3-4 songs
   - Crossfade between tracks
   - Now-playing metadata updates
4. Configure Icecast2:
   - Mount point: `/signal-zero`
   - Format: MP3 128kbps (widely compatible)
   - Max listeners: start with 32
   - Stream URL: `http://roanokecontrols.com:8000/signal-zero`
5. Open port 8000 in Lightsail firewall

### Varo Speaker Compatibility

Varo internet speakers typically support:
- **TuneIn** — register stream on TuneIn directory
- **Direct URL** — enter Icecast stream URL in app
- **M3U/PLS playlist files** — point speaker at playlist URL

**To add to Varo:**
1. Get stream URL working: `http://roanokecontrols.com:8000/signal-zero`
2. Create M3U file at `https://promptband.ai/signal-zero-stream.m3u`:
   ```
   #EXTM3U
   #EXTINF:-1,Signal 0 Radio
   http://roanokecontrols.com:8000/signal-zero
   ```
3. Register on TuneIn (free for stations): https://tunein.com/broadcasters/
4. Use TuneIn on Varo to find "Signal 0 Radio"

### Now-Playing Integration

Liquidsoap can call a webhook on track change:
- POST to `https://promptband.ai/api/signal-zero-nowplaying.php`
- Body: `{ "song": "Empire of Sound", "band": "Chrome Cathedral", "region": "Core Worlds" }`
- Endpoint updates `signal-zero-nowplaying.json`
- Admin dashboard shows current now-playing
- Could display on promptband.ai public page too

### Liquidsoap Script (Rough Outline)

```liquidsoap
# Playlist from directory
songs = playlist("/opt/signal-zero/songs/", mode="randomize", reload=3600)

# Bumpers
bumpers = playlist("/opt/signal-zero/bumpers/", mode="randomize")

# Insert bumper every 4 songs
radio = rotate(weights=[4,1], [songs, bumpers])

# Crossfade
radio = crossfade(radio)

# Metadata hook
def on_track(m)
  system("curl -X POST https://promptband.ai/api/signal-zero-nowplaying.php ...")
end
radio = on_track(on_track, radio)

# Output to Icecast
output.icecast(
  %mp3(bitrate=128),
  host="localhost", port=8000,
  password="hackme", mount="/signal-zero",
  name="Signal 0 Radio",
  description="The frequency beneath all frequencies.",
  genre="Rock",
  url="https://promptband.ai",
  radio
)
```

---

## Phase Order & Dependencies

```
Phase 3: Audio Generation
  └─ Requires: approved lyrics (Phase 2 ✅)
  └─ Produces: MP3 files for songs

Phase 4: Rotation & DJ Bumpers
  └─ Requires: audio files (Phase 3)
  └─ Produces: rotation assignments + bumper audio files

Phase 5: Station Player (Admin Preview)
  └─ Requires: rotation + bumpers (Phase 4)
  └─ Produces: working in-browser radio player

Phase 6: Internet Radio Stream
  └─ Requires: audio files + rotation logic (Phase 3-4)
  └─ Produces: Icecast stream URL for Varo speaker
  └─ Note: Can be built in parallel with Phase 5
```

---

## Key Infrastructure Notes

- **Suno API** must be running locally for Phase 3: `cd /Users/stevehall/development/suno-api && npm run dev`
- **Suno cookie** expires — refresh from suno.com devtools before batch generation
- **ElevenLabs** API key in MEMORY.md, model `eleven_v3`
- **Audio storage** on Bluehost: `/audio/signal-zero/` directory
- **Stream server** on Lightsail: needs Icecast2 + Liquidsoap installed
- **Audio sync**: need to sync MP3s from Bluehost to Lightsail for streaming (rsync cron or generate directly on Lightsail)

## Estimated Audio Budget

- **Suno**: 10,000 credits/month (Premier), ~2,000 songs. Each song = 5 credits (2 clips generated per call). 600 rotation songs = ~1,500 credits minimum (plus regenerations).
- **ElevenLabs**: Bumpers are short (5-15 seconds each). ~50-100 bumpers needed. Well within free/starter tier.
- **OpenAI**: Lyrics already generated. No additional cost for audio phase.
