#!/usr/bin/env python3
"""Inject FFT-detected effects into YDOM FCPXML timeline.

Reads audio-analysis.json and YDOM-Layered-Edit.fcpxml,
inserts flash overlays and markers at beat-synced positions.
"""

import json
import re
import xml.etree.ElementTree as ET

ANALYSIS_PATH = "/Users/stevehall/development/promptband/video-edit/Your Data or Mine/audio-analysis.json"
FCPXML_PATH = "/Users/stevehall/development/promptband/video-edit/Your Data or Mine/YDOM-Layered-Edit.fcpxml"
OUTPUT_PATH = "/Users/stevehall/development/promptband/video-edit/Your Data or Mine/YDOM-FFT-Edit.fcpxml"
FLASH_MP4 = "/Users/stevehall/development/promptband/video-edit/Your Data or Mine/flash-white.mp4"

# Load analysis
with open(ANALYSIS_PATH) as f:
    analysis = json.load(f)

# Parse FCPXML as text (ElementTree mangles DTD)
with open(FCPXML_PATH) as f:
    fcpxml_text = f.read()

# --- BUILD SPINE CLIP MAP ---
# Extract all spine clips with their timeline offsets and durations
# Pattern: <asset-clip ref="rXX" offset="XXXXX/2500s" name="LTX: ..." ... duration="XXXXX/2500s" ...>
spine_pattern = re.compile(
    r'(<asset-clip\s+ref="(r\d+)"\s+offset="(\d+)/2500s"\s+name="(LTX:[^"]+)"\s+start="(\d+)/2500s"\s+duration="(\d+)/2500s"[^>]*>)',
    re.DOTALL
)

spine_clips = []
for m in spine_pattern.finditer(fcpxml_text):
    spine_clips.append({
        'ref': m.group(2),
        'offset': int(m.group(3)),      # timeline offset in /2500s units
        'name': m.group(4),
        'start': int(m.group(5)),
        'duration': int(m.group(6)),
        'match_start': m.start(),
        'match_end': m.end(),
        'tag': m.group(1)
    })

print(f"Found {len(spine_clips)} spine clips")

# --- DETERMINE WHICH HITS TO PLACE ---

def seconds_to_fcpxml_units(seconds):
    """Convert seconds to /2500s units (25fps, 100 units per frame)."""
    frames = round(seconds * 25)
    return frames * 100

def fcpxml_to_tc(units):
    """Convert /2500s units to timecode string."""
    total_frames = units // 100
    ff = total_frames % 25
    total_seconds = total_frames // 25
    ss = total_seconds % 60
    mm = (total_seconds // 60) % 60
    hh = total_seconds // 3600
    return f"{hh:02d}:{mm:02d}:{ss:02d}:{ff:02d}"

# Select hits for different effect types
# 1. FLASH STROBES: Top transients (strength >= 0.8) - white flash overlays
flash_hits = [t for t in analysis['transients'] if t['strength'] >= 0.80]
print(f"\nFlash strobes: {len(flash_hits)} hits (transients >= 0.8 strength)")

# 2. KICK MARKERS: Strongest kick hits for manual glitch/pixel application
kick_markers = [k for k in analysis['kick_bass_hits'] if k['strength'] >= 0.9]
print(f"Kick markers: {len(kick_markers)} hits (kicks >= 0.9 strength)")

# 3. ENERGY MARKERS: Energy peaks for manual bloom application
energy_markers = analysis['energy_peaks']
print(f"Energy markers: {len(energy_markers)} peaks")

# --- FIND PARENT CLIP FOR EACH HIT ---

def find_parent_clip(timeline_units):
    """Find which spine clip contains this timeline position."""
    for clip in spine_clips:
        clip_start = clip['offset']
        clip_end = clip['offset'] + clip['duration']
        if clip_start <= timeline_units < clip_end:
            return clip
    return None

# --- BUILD FLASH INSERTIONS ---

flash_insertions = []  # (clip_index, offset_within_clip, flash_name)

for i, hit in enumerate(flash_hits):
    timeline_units = seconds_to_fcpxml_units(hit['time_seconds'])
    parent = find_parent_clip(timeline_units)
    if parent:
        offset_within = timeline_units - parent['offset']
        # Ensure offset + flash duration doesn't exceed clip
        flash_dur = 500  # 5 frames
        if offset_within + flash_dur > parent['duration']:
            offset_within = parent['duration'] - flash_dur
        if offset_within < 0:
            continue
        flash_insertions.append({
            'timeline_pos': timeline_units,
            'parent_name': parent['name'],
            'parent_offset': parent['offset'],
            'offset_within': offset_within,
            'strength': hit['strength'],
            'timecode': hit['timecode'],
            'flash_name': f"Flash {i+1}"
        })

print(f"\nPlacing {len(flash_insertions)} flash overlays:")
for fi in flash_insertions:
    print(f"  {fi['timecode']} ({fi['strength']:.2f}) -> {fi['parent_name']} +{fi['offset_within']}/2500s")

# --- BUILD MARKER INSERTIONS ---

kick_marker_insertions = []
for i, hit in enumerate(kick_markers):
    timeline_units = seconds_to_fcpxml_units(hit['time_seconds'])
    parent = find_parent_clip(timeline_units)
    if parent:
        offset_within = timeline_units - parent['offset']
        kick_marker_insertions.append({
            'parent_name': parent['name'],
            'parent_offset': parent['offset'],
            'offset_within': offset_within,
            'timecode': hit['timecode'],
            'value': f"KICK {hit['strength']:.2f}"
        })

energy_marker_insertions = []
for i, hit in enumerate(energy_markers):
    timeline_units = seconds_to_fcpxml_units(hit['time_seconds'])
    parent = find_parent_clip(timeline_units)
    if parent:
        offset_within = timeline_units - parent['offset']
        energy_marker_insertions.append({
            'parent_name': parent['name'],
            'parent_offset': parent['offset'],
            'offset_within': offset_within,
            'timecode': hit['timecode'],
            'value': f"ENERGY PEAK"
        })

print(f"\nPlacing {len(kick_marker_insertions)} kick markers")
print(f"Placing {len(energy_marker_insertions)} energy markers")

# --- GENERATE OUTPUT FCPXML ---

# Start with the original FCPXML
output = fcpxml_text

# 1. Add flash-white.mp4 asset to resources (use r200 like before)
flash_asset = '''        <asset id="r200" name="flash-white" hasVideo="1" format="r1" hasAudio="0" duration="2500/2500s">
            <media-rep kind="original-media" src="file:///Users/stevehall/development/promptband/video-edit/Your%20Data%20or%20Mine/flash-white.mp4"/>
        </asset>'''

# Insert before </resources>
output = output.replace('    </resources>', flash_asset + '\n    </resources>')

# 2. For each spine clip that needs effects, inject flash clips and markers
# Group insertions by parent clip offset
from collections import defaultdict

flashes_by_parent = defaultdict(list)
for fi in flash_insertions:
    flashes_by_parent[fi['parent_offset']].append(fi)

kicks_by_parent = defaultdict(list)
for ki in kick_marker_insertions:
    kicks_by_parent[ki['parent_offset']].append(ki)

energy_by_parent = defaultdict(list)
for ei in energy_marker_insertions:
    energy_by_parent[ei['parent_offset']].append(ei)

# Get all parent offsets that need modification
all_parent_offsets = set(
    list(flashes_by_parent.keys()) +
    list(kicks_by_parent.keys()) +
    list(energy_by_parent.keys())
)

print(f"\nModifying {len(all_parent_offsets)} spine clips")

# For each spine clip, find its closing </asset-clip> and inject before it
# We need to be careful about which </asset-clip> belongs to the spine clip
# Strategy: find each spine clip's opening tag, then find where to inject

# Work backwards through the file to avoid offset shifting
modifications = []

for clip in spine_clips:
    parent_offset = clip['offset']
    if parent_offset not in all_parent_offsets:
        continue

    # Find the position in the output text for this clip
    # Use offset + duration combo which is unique per spine clip
    clip_pattern = f'offset="{parent_offset}/2500s"'
    # Find all occurrences and pick the one that's a spine-level clip (has name="LTX:")
    search_pos = 0
    clip_pos = -1
    while True:
        idx = output.find(clip_pattern, search_pos)
        if idx < 0:
            break
        # Check if this is a spine-level LTX clip (look back for <asset-clip ref=)
        line_start = output.rfind('\n', 0, idx) + 1
        line_text = output[line_start:idx + len(clip_pattern) + 100]
        if 'name="LTX:' in line_text and '<asset-clip' in line_text:
            clip_pos = idx
            break
        search_pos = idx + 1

    if clip_pos < 0:
        print(f"  WARNING: Could not find clip {clip['name']} at offset {parent_offset}")
        continue

    # Find the closing tag for this spine clip
    # The spine clip's children are: lane="1" kling clip, markers, and now our lane="2" flashes
    # Find the first </asset-clip> that closes this spine clip
    # Count nesting: each <asset-clip opens, each </asset-clip> closes
    search_start = clip_pos
    # Find the > that closes the opening tag
    tag_end = output.find('>', search_start)

    # Check if self-closing (shouldn't be for spine clips with children)
    if output[tag_end-1] == '/':
        print(f"  WARNING: Self-closing clip {clip['name']}")
        continue

    # Scan forward to find the matching </asset-clip> for this spine clip
    # Child asset-clips are self-closing (end with "/>") so they don't affect depth
    # Markers are also self-closing. Only non-self-closing <asset-clip> tags change depth.
    pos = tag_end + 1
    depth = 1
    inject_pos = None
    while depth > 0 and pos < len(output):
        # Find next relevant tag
        next_open_ac = output.find('<asset-clip', pos)
        next_close_ac = output.find('</asset-clip>', pos)

        if next_close_ac < 0:
            break

        # Check if the next open tag is self-closing
        if next_open_ac >= 0 and next_open_ac < next_close_ac:
            # Find the end of this opening tag
            tag_close = output.find('>', next_open_ac)
            if tag_close >= 0 and output[tag_close - 1] == '/':
                # Self-closing - skip it, doesn't change depth
                pos = tag_close + 1
            else:
                # Opens a new nested asset-clip (non-self-closing)
                depth += 1
                pos = tag_close + 1 if tag_close >= 0 else next_open_ac + 11
        else:
            depth -= 1
            if depth == 0:
                inject_pos = next_close_ac
                break
            pos = next_close_ac + 13

    if inject_pos is None:
        print(f"  WARNING: Could not find closing tag for {clip['name']}")
        continue

    # Build injection content
    inject_lines = []

    # Add flash clips (lane 2) - BEFORE markers per DTD rules
    if parent_offset in flashes_by_parent:
        for fi in flashes_by_parent[parent_offset]:
            inject_lines.append(
                f'                            <asset-clip ref="r200" lane="2" '
                f'offset="{fi["offset_within"]}/2500s" name="{fi["flash_name"]}" '
                f'start="0/2500s" duration="500/2500s" format="r1" tcFormat="NDF"/>'
            )

    # Add kick markers
    if parent_offset in kicks_by_parent:
        for ki in kicks_by_parent[parent_offset]:
            inject_lines.append(
                f'                            <marker start="{ki["offset_within"]}/2500s" '
                f'duration="100/2500s" value="{ki["value"]}"/>'
            )

    # Add energy markers
    if parent_offset in energy_by_parent:
        for ei in energy_by_parent[parent_offset]:
            inject_lines.append(
                f'                            <marker start="{ei["offset_within"]}/2500s" '
                f'duration="100/2500s" value="{ei["value"]}"/>'
            )

    if inject_lines:
        inject_text = '\n'.join(inject_lines) + '\n'
        modifications.append((inject_pos, inject_text, clip['name']))

# Apply modifications in reverse order (so positions don't shift)
modifications.sort(key=lambda x: x[0], reverse=True)

for inject_pos, inject_text, clip_name in modifications:
    output = output[:inject_pos] + inject_text + '                        ' + output[inject_pos:]
    print(f"  Injected into: {clip_name}")

# Fix: existing markers that are now after flash clips need DTD reordering
# In FCPXML DTD: asset-clip children must come BEFORE marker children
# We need to ensure within each spine clip: lane clips first, then markers

# Simple approach: for each spine clip section, collect all child elements,
# sort them (asset-clips first, markers second), rewrite

# Actually let's do a targeted fix - find all spine clips and reorder children
def reorder_all_clip_children(xml_text):
    """Ensure asset-clip children come before marker children in every asset-clip block."""
    lines = xml_text.split('\n')
    result_lines = []
    i = 0
    while i < len(lines):
        line = lines[i]
        stripped = line.strip()

        # Detect opening of a non-self-closing asset-clip (spine clip with children)
        if stripped.startswith('<asset-clip') and not stripped.endswith('/>'):
            result_lines.append(line)
            i += 1

            # Collect all child lines until matching </asset-clip>
            child_asset_lines = []
            child_marker_lines = []
            depth = 1

            while i < len(lines) and depth > 0:
                child_line = lines[i]
                child_stripped = child_line.strip()

                if child_stripped == '</asset-clip>':
                    depth -= 1
                    if depth == 0:
                        # Output children in correct order: assets first, markers second
                        for cl in child_asset_lines:
                            result_lines.append(cl)
                        for cl in child_marker_lines:
                            result_lines.append(cl)
                        result_lines.append(child_line)
                        i += 1
                        break
                elif child_stripped.startswith('<asset-clip') and not child_stripped.endswith('/>'):
                    # Nested non-self-closing asset-clip — shouldn't happen at this level
                    # but handle it: treat as asset line
                    child_asset_lines.append(child_line)
                    depth += 1
                    i += 1
                elif child_stripped.startswith('<marker'):
                    child_marker_lines.append(child_line)
                    i += 1
                else:
                    # asset-clip self-closing or other content
                    child_asset_lines.append(child_line)
                    i += 1
        else:
            result_lines.append(line)
            i += 1

    return '\n'.join(result_lines)

output = reorder_all_clip_children(output)

# Write output
with open(OUTPUT_PATH, 'w') as f:
    f.write(output)

print(f"\n=== FCPXML GENERATED ===")
print(f"Output: {OUTPUT_PATH}")
print(f"Flash overlays: {len(flash_insertions)}")
print(f"Kick markers: {len(kick_marker_insertions)}")
print(f"Energy markers: {len(energy_marker_insertions)}")
print(f"\nEffect placement summary:")
print(f"  INTRO (0:00-0:16): {sum(1 for f in flash_insertions if f['timeline_pos'] < 40000)} flashes")
print(f"  VERSE 1 (0:16-0:52): {sum(1 for f in flash_insertions if 40000 <= f['timeline_pos'] < 130000)} flashes")
print(f"  CHORUS 1 (1:06-1:34): {sum(1 for f in flash_insertions if 165000 <= f['timeline_pos'] < 235000)} flashes")
print(f"  POST-CHORUS (1:34-2:05): {sum(1 for f in flash_insertions if 235000 <= f['timeline_pos'] < 312500)} flashes")
print(f"  VERSE 2 (2:05-2:35): {sum(1 for f in flash_insertions if 312500 <= f['timeline_pos'] < 387500)} flashes")
print(f"  OUTRO (2:47-3:03): {sum(1 for f in flash_insertions if f['timeline_pos'] >= 417500)} flashes")
