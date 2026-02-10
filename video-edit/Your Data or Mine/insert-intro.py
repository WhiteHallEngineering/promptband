#!/usr/bin/env python3
"""Insert PROMPT terminal intro into YDOM FCPXML timeline.

Reads YDOM-FFT-Edit.fcpxml, adds the intro video at the beginning,
shifts all existing clips forward, and outputs YDOM-Intro-Edit.fcpxml.
"""

import re
import os

INPUT_FCPXML = "/Users/stevehall/development/promptband/video-edit/Your Data or Mine/YDOM-FFT-Edit.fcpxml"
OUTPUT_FCPXML = "/Users/stevehall/development/promptband/video-edit/Your Data or Mine/YDOM-Intro-Edit.fcpxml"
INTRO_VIDEO = "/Users/stevehall/development/promptband/video-edit/Your Data or Mine/intro.mp4"

# Intro duration: 12 seconds at 25fps = 300 frames = 30000/2500s
INTRO_DURATION = 30000  # in /2500s units
INTRO_DURATION_SECONDS = 12.0

print(f"Inserting intro ({INTRO_DURATION_SECONDS}s) into FCPXML timeline...")

# Read input
with open(INPUT_FCPXML) as f:
    xml = f.read()

# --- 1. ADD INTRO ASSET TO RESOURCES ---
# Use r201 for the intro video
intro_asset = f'''        <asset id="r201" name="PROMPT Intro" hasVideo="1" format="r1" hasAudio="0" duration="{INTRO_DURATION}/2500s">
            <media-rep kind="original-media" src="file://{INTRO_VIDEO.replace(" ", "%20")}"/>
        </asset>'''

# Insert before the flash-white asset (r200) or before </resources>
xml = xml.replace('    </resources>', intro_asset + '\n    </resources>')

print(f"  Added intro asset (r201)")

# --- 2. RENAME EVENT/PROJECT ---
xml = xml.replace('name="YDOM FFT Edit"', 'name="YDOM Intro Edit"')
print(f"  Renamed event/project to 'YDOM Intro Edit'")

# --- 3. UPDATE SEQUENCE DURATION ---
# Original: duration="458200/2500s" -> add INTRO_DURATION
seq_dur_match = re.search(r'<sequence[^>]*duration="(\d+)/2500s"', xml)
if seq_dur_match:
    old_dur = int(seq_dur_match.group(1))
    new_dur = old_dur + INTRO_DURATION
    xml = xml.replace(
        f'duration="{old_dur}/2500s" tcStart=',
        f'duration="{new_dur}/2500s" tcStart='
    )
    print(f"  Updated sequence duration: {old_dur}/2500s -> {new_dur}/2500s")

# --- 4. SHIFT ALL SPINE CLIP OFFSETS ---
# Spine clips have: offset="XXXXX/2500s" name="LTX: ..."
# We need to add INTRO_DURATION to each spine clip's offset

def shift_spine_offsets(text):
    """Shift all spine-level clip offsets forward by INTRO_DURATION."""
    # Match spine-level asset-clips (those with name="LTX:")
    pattern = re.compile(r'offset="(\d+)/2500s"(\s+name="LTX:)')

    def replace_offset(m):
        old_offset = int(m.group(1))
        new_offset = old_offset + INTRO_DURATION
        return f'offset="{new_offset}/2500s"{m.group(2)}'

    return pattern.sub(replace_offset, text)

xml = shift_spine_offsets(xml)
print(f"  Shifted all spine clip offsets by +{INTRO_DURATION}/2500s ({INTRO_DURATION_SECONDS}s)")

# --- 5. INSERT INTRO CLIP AT BEGINNING OF SPINE ---
# Insert right after <spine>
intro_clip = f'''                        <asset-clip ref="r201" offset="0/2500s" name="PROMPT Intro" start="0/2500s" duration="{INTRO_DURATION}/2500s" format="r1" tcFormat="NDF">
                            <marker start="0/2500s" duration="100/2500s" value="INTRO SEQUENCE"/>
                        </asset-clip>'''

xml = xml.replace('<spine>\n', f'<spine>\n{intro_clip}\n')
print(f"  Inserted intro clip at spine offset 0")

# --- 6. VERIFY ---
# Count spine clips
spine_clips = re.findall(r'offset="\d+/2500s"\s+name="LTX:', xml)
intro_clips = re.findall(r'name="PROMPT Intro"', xml)
markers = re.findall(r'<marker\s', xml)
flashes = re.findall(r'name="Flash \d+"', xml)

print(f"\n=== VERIFICATION ===")
print(f"  Intro clips: {len(intro_clips)}")
print(f"  Spine clips (LTX): {len(spine_clips)}")
print(f"  Markers: {len(markers)}")
print(f"  Flash overlays: {len(flashes)}")

# Verify first spine clip starts at INTRO_DURATION
first_ltx = re.search(r'offset="(\d+)/2500s"\s+name="LTX:', xml)
if first_ltx:
    first_offset = int(first_ltx.group(1))
    print(f"  First LTX clip offset: {first_offset}/2500s (expected {INTRO_DURATION}/2500s)")
    if first_offset != INTRO_DURATION:
        print(f"  WARNING: First clip offset mismatch!")

# --- 7. WRITE OUTPUT ---
with open(OUTPUT_FCPXML, 'w') as f:
    f.write(xml)

size = os.path.getsize(OUTPUT_FCPXML)
print(f"\n=== FCPXML GENERATED ===")
print(f"Output: {OUTPUT_FCPXML}")
print(f"Size: {size / 1024:.0f} KB")
print(f"\nTimeline layout:")
print(f"  0:00 - 0:04  PROMPT Intro (terminal boot/compose sequence)")
print(f"  0:04 - 3:07  Original content (shifted +4s)")
print(f"  Total duration: {(old_dur + INTRO_DURATION) / 2500:.1f}s")
