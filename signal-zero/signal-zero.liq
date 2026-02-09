#!/usr/bin/liquidsoap

# Signal 0 Radio — Liquidsoap Automation
# Reads songs from rotation directory, inserts bumpers, streams to Icecast
# Includes time-based podcast scheduling per show timeslot (GST = UTC)

# Settings
set("log.file.path", "/var/log/liquidsoap/signal-zero.log")
set("log.level", 3)

# ─── Song Playlists by Rotation Tier ───
heavy = playlist(mode="randomize", reload=600, "/opt/signal-zero/rotation/heavy/")
medium = playlist(mode="randomize", reload=600, "/opt/signal-zero/rotation/medium/")
light = playlist(mode="randomize", reload=600, "/opt/signal-zero/rotation/light/")

# Weighted mix: heavy 4x, medium 2x, light 1x
songs = rotate(weights=[4,2,1], [heavy, medium, light])

# DJ Bumpers
bumpers = playlist(mode="randomize", reload=600, "/opt/signal-zero/bumpers/")

# Strip metadata from bumpers so they never overwrite now-playing display
bumpers = drop_metadata(bumpers)

# Insert bumper every 3-4 songs
music = rotate(weights=[3,1], [songs, bumpers])

# ─── Podcast Sources (sequential play, watch for new files) ───
morning_pod = playlist(mode="normal", reload=60,
  "/var/www/signal-zero/audio/podcasts/morning-transmission/")
signal_boost_pod = playlist(mode="normal", reload=60,
  "/var/www/signal-zero/audio/podcasts/signal-boost/")
amplifier_pod = playlist(mode="normal", reload=60,
  "/var/www/signal-zero/audio/podcasts/the-amplifier/")
long_freq_pod = playlist(mode="normal", reload=60,
  "/var/www/signal-zero/audio/podcasts/long-frequency/")
after_dark_pod = playlist(mode="normal", reload=60,
  "/var/www/signal-zero/audio/podcasts/after-dark/")
vault_pod = playlist(mode="normal", reload=60,
  "/var/www/signal-zero/audio/podcasts/the-vault/")

# ─── Time-Based Scheduling (GST = UTC) ───
# When a podcast directory has files, they play during that show's timeslot.
# When empty/unavailable, falls through to regular music rotation.
radio = switch(track_sensitive=false, [
  ({ 6h-10h }, fallback([morning_pod, music])),
  ({ 10h-14h }, fallback([signal_boost_pod, music])),
  ({ 14h-18h }, fallback([amplifier_pod, music])),
  ({ 19h-21h }, fallback([long_freq_pod, music])),
  ({ 21h-2h }, fallback([after_dark_pod, music])),
  ({ 2h-6h }, fallback([vault_pod, music])),
  ({ true }, music)
])

# Crossfade between tracks
radio = crossfade(duration=3.0, radio)

# Now-playing webhook on track change
def on_new_track(m)
  title = m["title"]
  artist = m["artist"]
  # Call webhook to update now-playing
  ignore(process.run("curl -s -X POST 'https://promptband.ai/api/signal-zero-nowplaying.php?key=pr0mpt-m3ss4g3s-2026' -H 'Content-Type: application/json' -d '{\"title\":\"" ^ title ^ "\",\"band\":\"" ^ artist ^ "\"}'"))
end

radio = on_metadata(on_new_track, radio)

# Safety: ensure stream never dies (mksafe instead of fallback to blank)
radio = mksafe(radio)

# Output to Icecast
output.icecast(
  %mp3(bitrate=128, samplerate=44100),
  host="localhost",
  port=8000,
  password="s1gnal0-src-2026",
  mount="/signal-zero",
  name="Signal 0 Radio",
  description="The frequency beneath all frequencies. Broadcasting from nowhere. Received everywhere.",
  genre="Rock",
  url="https://signal0radio.com",
  public=true,
  radio
)
