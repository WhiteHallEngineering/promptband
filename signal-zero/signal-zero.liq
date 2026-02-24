#!/usr/bin/liquidsoap

# Signal 0 Radio — Liquidsoap Automation
# Plays songs from M3U playlist, inserts bumpers, streams to Icecast
# Includes time-based podcast scheduling per show timeslot (GST = UTC)

# Settings
settings.log.file.path := "/var/log/liquidsoap/radio.log"
settings.log.level := 3

# ─── Music Rotation ───
songs = playlist(mode="randomize", reload=86400, "/etc/liquidsoap/songs.m3u")
bumpers = playlist(mode="randomize", reload=600, "/etc/liquidsoap/bumpers.m3u")

# Boost bumper volume (TTS output is quieter than music tracks)
bumpers = amplify(2.0, bumpers)

# Insert bumper every 3-4 songs
music = rotate(weights=[3,1], [songs, bumpers])

# ─── Podcast Sources (sequential play, watch for new files) ───
morning_pod = playlist(mode="normal", reload=14400,
  "/var/www/signal-zero/audio/podcasts/morning-transmission/")
signal_boost_pod = playlist(mode="normal", reload=14400,
  "/var/www/signal-zero/audio/podcasts/signal-boost/")
amplifier_pod = playlist(mode="normal", reload=14400,
  "/var/www/signal-zero/audio/podcasts/the-amplifier/")
long_freq_pod = playlist(mode="normal", reload=14400,
  "/var/www/signal-zero/audio/podcasts/long-frequency/")
after_dark_pod = playlist(mode="normal", reload=14400,
  "/var/www/signal-zero/audio/podcasts/after-dark/")
vault_pod = playlist(mode="normal", reload=14400,
  "/var/www/signal-zero/audio/podcasts/the-vault/")

# ─── Time-Based Scheduling (GST = UTC) ───
radio = switch(track_sensitive=false, [
  ({ 6h-10h }, fallback([morning_pod, music])),
  ({ 10h-14h }, fallback([signal_boost_pod, music])),
  ({ 14h-18h }, fallback([amplifier_pod, music])),
  ({ 19h-21h }, fallback([long_freq_pod, music])),
  ({ 21h-2h }, fallback([after_dark_pod, music])),
  ({ 2h-6h }, fallback([vault_pod, music])),
  ({ true }, music)
])

# Now-playing webhook on track change (must be before crossfade to capture metadata)
def on_new_track(m)
  title = m["title"]
  artist = m["artist"]
  if artist != "" and artist != "Signal 0 Radio" then
    cmd = "curl -s -X POST 'https://promptband.ai/api/signal-zero-nowplaying.php?key=pr0mpt-m3ss4g3s-2026' -H 'Content-Type: application/json' -H 'Cookie: humans_21909=1' -d '{\"title\":\"" ^ title ^ "\",\"band\":\"" ^ artist ^ "\"}'";
    ignore(process.run(cmd))
  end
end
radio.on_metadata(on_new_track)

# Smooth transitions between tracks
radio = crossfade(duration=3.0, radio)

# Safety: ensure stream never dies
radio = mksafe(radio)

# Output to Icecast
output.icecast(
  %mp3(bitrate=128, samplerate=44100),
  host="localhost",
  port=8000,
  password="s1gn4l-z3r0-src",
  mount="/stream",
  name="Signal 0 Radio",
  description="The frequency beneath all frequencies.",
  genre="Rock",
  url="https://signal0radio.com",
  public=true,
  radio
)
