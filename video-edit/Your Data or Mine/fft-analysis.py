#!/usr/bin/env python3
"""FFT-based audio analysis for YDOM music video effect placement."""

import wave
import struct
import json
import numpy as np
from scipy.signal import find_peaks
from scipy.ndimage import uniform_filter1d

WAV_PATH = "/Users/stevehall/development/promptband/video-edit/Your Data or Mine/Audio/Your Data or Mine.wav"
OUTPUT_PATH = "/Users/stevehall/development/promptband/video-edit/Your Data or Mine/audio-analysis.json"

# Read WAV file
with wave.open(WAV_PATH, 'r') as wf:
    n_channels = wf.getnchannels()
    sample_width = wf.getsampwidth()
    framerate = wf.getframerate()
    n_frames = wf.getnframes()
    raw = wf.readframes(n_frames)

print(f"Audio: {n_channels}ch, {sample_width*8}bit, {framerate}Hz, {n_frames} frames")
print(f"Duration: {n_frames/framerate:.2f}s")

# Convert to mono float array
if sample_width == 2:
    fmt = f"<{n_frames * n_channels}h"
    samples = np.array(struct.unpack(fmt, raw), dtype=np.float64) / 32768.0
elif sample_width == 3:
    # 24-bit: unpack manually
    samples = []
    for i in range(0, len(raw), 3):
        val = int.from_bytes(raw[i:i+3], byteorder='little', signed=True)
        samples.append(val / 8388608.0)
    samples = np.array(samples, dtype=np.float64)
else:
    fmt = f"<{n_frames * n_channels}i"
    samples = np.array(struct.unpack(fmt, raw), dtype=np.float64) / 2147483648.0

# Mix to mono
if n_channels == 2:
    samples = (samples[0::2] + samples[1::2]) / 2.0

# --- ANALYSIS PARAMETERS ---
hop_size = 512          # ~11.6ms at 44100Hz
fft_size = 2048         # frequency resolution
fps = 25                # video framerate

# --- 1. SPECTRAL FLUX (onset/transient detection) ---
print("\nComputing spectral flux for transient detection...")
n_hops = (len(samples) - fft_size) // hop_size
window = np.hanning(fft_size)
prev_spectrum = None
spectral_flux = []

for i in range(n_hops):
    start = i * hop_size
    frame = samples[start:start + fft_size] * window
    spectrum = np.abs(np.fft.rfft(frame))

    if prev_spectrum is not None:
        # Half-wave rectified spectral flux (only increases)
        diff = spectrum - prev_spectrum
        flux = np.sum(np.maximum(diff, 0))
        spectral_flux.append(flux)
    else:
        spectral_flux.append(0)

    prev_spectrum = spectrum

spectral_flux = np.array(spectral_flux)

# Normalize
flux_smooth = uniform_filter1d(spectral_flux, size=10)
flux_threshold = flux_smooth + np.std(spectral_flux) * 1.2

# Find transient peaks
transient_indices, transient_props = find_peaks(
    spectral_flux,
    height=flux_threshold,
    distance=int(0.1 * framerate / hop_size),  # min 100ms between transients
    prominence=np.std(spectral_flux) * 0.8
)

transient_times = transient_indices * hop_size / framerate
transient_strengths = spectral_flux[transient_indices]
# Normalize strengths 0-1
if len(transient_strengths) > 0:
    max_str = transient_strengths.max()
    transient_strengths = transient_strengths / max_str if max_str > 0 else transient_strengths

print(f"Found {len(transient_times)} transients")

# --- 2. RMS ENERGY ENVELOPE ---
print("Computing RMS energy envelope...")
rms_hop = hop_size
rms_window = fft_size
n_rms = (len(samples) - rms_window) // rms_hop
rms_energy = []

for i in range(n_rms):
    start = i * rms_hop
    frame = samples[start:start + rms_window]
    rms = np.sqrt(np.mean(frame ** 2))
    rms_energy.append(rms)

rms_energy = np.array(rms_energy)
rms_times = np.arange(len(rms_energy)) * rms_hop / framerate

# Find energy peaks (big moments)
rms_smooth = uniform_filter1d(rms_energy, size=50)
energy_peaks, _ = find_peaks(
    rms_smooth,
    distance=int(2.0 * framerate / rms_hop),  # min 2s between energy peaks
    prominence=np.std(rms_smooth) * 0.5
)

energy_peak_times = energy_peaks * rms_hop / framerate
print(f"Found {len(energy_peak_times)} energy peaks")

# --- 3. SUB-BAND ENERGY (kick detection via low freq) ---
print("Computing sub-band energy for kick/bass detection...")
low_flux = []
mid_flux = []
high_flux = []
prev_spectrum = None

for i in range(n_hops):
    start = i * hop_size
    frame = samples[start:start + fft_size] * window
    spectrum = np.abs(np.fft.rfft(frame))

    freq_bins = np.fft.rfftfreq(fft_size, 1.0/framerate)
    low_mask = freq_bins < 200       # sub-bass + bass
    mid_mask = (freq_bins >= 200) & (freq_bins < 4000)  # mids
    high_mask = freq_bins >= 4000    # highs (cymbals, hi-hats)

    if prev_spectrum is not None:
        diff = spectrum - prev_spectrum
        low_flux.append(np.sum(np.maximum(diff[low_mask], 0)))
        mid_flux.append(np.sum(np.maximum(diff[mid_mask], 0)))
        high_flux.append(np.sum(np.maximum(diff[high_mask], 0)))
    else:
        low_flux.append(0)
        mid_flux.append(0)
        high_flux.append(0)

    prev_spectrum = spectrum

low_flux = np.array(low_flux)
mid_flux = np.array(mid_flux)
high_flux = np.array(high_flux)

# Kick/bass hits
low_smooth = uniform_filter1d(low_flux, size=5)
kick_indices, _ = find_peaks(
    low_flux,
    height=uniform_filter1d(low_flux, size=10) + np.std(low_flux) * 1.0,
    distance=int(0.15 * framerate / hop_size),  # min 150ms
    prominence=np.std(low_flux) * 0.6
)
kick_times = kick_indices * hop_size / framerate

# Hi-hat/cymbal hits
high_smooth = uniform_filter1d(high_flux, size=5)
cymbal_indices, _ = find_peaks(
    high_flux,
    height=uniform_filter1d(high_flux, size=10) + np.std(high_flux) * 1.2,
    distance=int(0.1 * framerate / hop_size),
    prominence=np.std(high_flux) * 0.8
)
cymbal_times = cymbal_indices * hop_size / framerate

print(f"Found {len(kick_times)} kick/bass hits")
print(f"Found {len(cymbal_times)} cymbal/hi-hat hits")

# --- 4. ENERGY SECTIONS (quiet vs loud) ---
print("Detecting energy sections...")
# Smooth RMS over ~2 seconds
section_smooth = uniform_filter1d(rms_energy, size=int(2.0 * framerate / rms_hop))
median_energy = np.median(section_smooth)

# Classify as low/medium/high energy
sections = []
current_level = None
section_start = 0

for i, e in enumerate(section_smooth):
    t = i * rms_hop / framerate
    if e < median_energy * 0.6:
        level = "low"
    elif e < median_energy * 1.2:
        level = "medium"
    else:
        level = "high"

    if level != current_level:
        if current_level is not None:
            sections.append({
                "start": round(section_start, 3),
                "end": round(t, 3),
                "level": current_level
            })
        current_level = level
        section_start = t

# Final section
sections.append({
    "start": round(section_start, 3),
    "end": round(len(samples) / framerate, 3),
    "level": current_level
})

# Merge very short sections (<1s) into neighbors
merged = [sections[0]]
for s in sections[1:]:
    if s["end"] - s["start"] < 1.0:
        merged[-1]["end"] = s["end"]
    else:
        merged.append(s)
sections = merged

print(f"Found {len(sections)} energy sections")

# --- 5. FORMAT HELPERS ---
def sec_to_tc(seconds):
    """Convert seconds to HH:MM:SS:FF timecode at 25fps."""
    total_frames = int(round(seconds * fps))
    ff = total_frames % fps
    total_seconds = total_frames // fps
    ss = total_seconds % 60
    mm = (total_seconds // 60) % 60
    hh = total_seconds // 3600
    return f"{hh:02d}:{mm:02d}:{ss:02d}:{ff:02d}"

def sec_to_fcpxml(seconds):
    """Convert seconds to FCPXML time value (X/2500s at 25fps)."""
    frames = int(round(seconds * fps))
    return f"{frames * 100}/2500s"

# --- BUILD OUTPUT ---
# Top transients (strongest 30)
top_transients = sorted(
    zip(transient_times, transient_strengths),
    key=lambda x: x[1], reverse=True
)[:30]
top_transients.sort(key=lambda x: x[0])  # re-sort by time

# Top kicks (strongest 40)
kick_strengths = low_flux[kick_indices]
if len(kick_strengths) > 0:
    kick_strengths = kick_strengths / kick_strengths.max()
top_kicks = sorted(
    zip(kick_times, kick_strengths),
    key=lambda x: x[1], reverse=True
)[:40]
top_kicks.sort(key=lambda x: x[0])

output = {
    "audio_info": {
        "file": WAV_PATH,
        "duration_seconds": round(len(samples) / framerate, 3),
        "duration_timecode": sec_to_tc(len(samples) / framerate),
        "sample_rate": framerate,
        "channels": n_channels,
        "fps": fps
    },
    "transients": [
        {
            "time_seconds": round(float(t), 3),
            "timecode": sec_to_tc(t),
            "fcpxml_time": sec_to_fcpxml(t),
            "strength": round(float(s), 3)
        }
        for t, s in top_transients
    ],
    "kick_bass_hits": [
        {
            "time_seconds": round(float(t), 3),
            "timecode": sec_to_tc(t),
            "fcpxml_time": sec_to_fcpxml(t),
            "strength": round(float(s), 3)
        }
        for t, s in top_kicks
    ],
    "cymbal_hits": [
        {
            "time_seconds": round(float(t), 3),
            "timecode": sec_to_tc(t),
            "fcpxml_time": sec_to_fcpxml(t),
            "strength": round(float(s), 3)
        }
        for t, s in sorted(zip(cymbal_times[:50], [1.0]*min(50, len(cymbal_times))), key=lambda x: x[0])
    ],
    "energy_peaks": [
        {
            "time_seconds": round(float(t), 3),
            "timecode": sec_to_tc(t),
            "fcpxml_time": sec_to_fcpxml(t)
        }
        for t in energy_peak_times
    ],
    "energy_sections": sections,
    "summary": {
        "total_transients": len(transient_times),
        "total_kick_hits": len(kick_times),
        "total_cymbal_hits": len(cymbal_times),
        "total_energy_peaks": len(energy_peak_times),
        "high_energy_sections": len([s for s in sections if s["level"] == "high"])
    }
}

with open(OUTPUT_PATH, 'w') as f:
    json.dump(output, f, indent=2)

print(f"\n=== ANALYSIS COMPLETE ===")
print(f"Output: {OUTPUT_PATH}")
print(f"\nSummary:")
print(f"  Top 30 transients (strongest hits)")
print(f"  Top 40 kick/bass hits")
print(f"  {len(cymbal_times)} cymbal hits")
print(f"  {len(energy_peak_times)} energy peaks")
print(f"  {len(sections)} energy sections")
print(f"\nTop 10 strongest transients:")
for t, s in top_transients[:10]:
    print(f"  {sec_to_tc(t)} ({t:.3f}s) - strength {s:.3f}")
