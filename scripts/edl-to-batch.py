#!/usr/bin/env python3
"""
EDL → OpenAI Batch JSONL Generator

Parses a PROMPT music video EDL markdown file and generates a .jsonl file
suitable for the OpenAI Batch API (/v1/images/generations).

Each unique clip in the EDL becomes one image generation request.
Repeated clips (e.g. "Clip 10 (repeat)") are deduplicated.

Usage:
    python3 scripts/edl-to-batch.py edl/02-your-data-or-mine-edl.md
    python3 scripts/edl-to-batch.py edl/02-your-data-or-mine-edl.md --model gpt-image-1 --quality medium
    python3 scripts/edl-to-batch.py edl/02-your-data-or-mine-edl.md --dry-run

Output:
    batch/{track-slug}-storyboard.jsonl
"""

import argparse
import json
import os
import re
import sys

# ── Defaults ──────────────────────────────────────────────────────────────

DEFAULT_MODEL = "gpt-image-1.5"
DEFAULT_QUALITY = "high"        # Storyboards are reference frames for video gen
DEFAULT_SIZE = "1536x1024"      # Landscape for video
DEFAULT_OUTPUT_FORMAT = "png"


# ── EDL Parser ────────────────────────────────────────────────────────────

def parse_edl(filepath):
    """Parse an EDL markdown file and extract metadata + unique clips."""
    with open(filepath) as f:
        content = f.read()

    # Extract track info
    track_info = {}
    title_match = re.search(r'^# "(.+?)"', content, re.MULTILINE)
    if title_match:
        track_info["title"] = title_match.group(1)

    track_match = re.search(r'\*\*Track Number:\*\*\s*(\d+)', content)
    if track_match:
        track_info["track_number"] = track_match.group(1).zfill(2)

    duration_match = re.search(r'\*\*Duration:\*\*\s*(.+?)(?:\n|$)', content)
    if duration_match:
        track_info["duration"] = duration_match.group(1).strip()

    # Extract creative vision for prompt context
    vision = {}
    directors_match = re.search(
        r"\*\*Director's Statement:\*\*\s*\n(.+?)(?=\n###|\n---|\n##)",
        content, re.DOTALL
    )
    if directors_match:
        vision["directors_statement"] = directors_match.group(1).strip()

    # Color palette
    colors = re.findall(r'\*\*(.+?)\*\*\s*-\s*(.+)', content[content.find("Color Palette"):content.find("Core Motifs")] if "Color Palette" in content else "")
    if colors:
        vision["colors"] = {c[0]: c[1] for c in colors}

    # Core motifs
    motifs = re.findall(r'\*\*(.+?)\*\*\s*-\s*(.+)', content[content.find("Core Motifs"):content.find("MASTER TIMING")] if "Core Motifs" in content else "")
    if motifs:
        vision["motifs"] = [f"{m[0]}: {m[1]}" for m in motifs]

    # Extract sections and clips
    sections = []
    current_section = None

    for line in content.split("\n"):
        # Section header: ### VERSE 1 (0:08 - 0:32) = 24 sec
        section_match = re.match(r'^### (.+?) \((.+?)\)', line)
        if section_match:
            current_section = {
                "name": section_match.group(1),
                "time_range": section_match.group(2),
                "clips": [],
            }
            sections.append(current_section)
            continue

        # Clip line: 0:08-0:13 (5s) --- Clip 3: Love or Illusion "Is this love or an illusion?"
        clip_match = re.match(
            r'^(\d+:\d+)-(\d+:\d+)\s+\(\d+s\)\s+---\s+Clip\s+(\d+):\s+(.+)',
            line
        )
        if clip_match and current_section is not None:
            clip_name = clip_match.group(4).strip()
            clip_num = int(clip_match.group(3))

            # Separate lyric quote if present
            lyric = ""
            lyric_match = re.search(r'"(.+?)"', clip_name)
            if lyric_match:
                lyric = lyric_match.group(1)
                clip_name = clip_name[:clip_name.index('"')].strip()

            # Skip reuse/repeat markers for deduplication
            is_reuse = "(repeat)" in clip_name.lower() or "(reuse)" in clip_name.lower()

            current_section["clips"].append({
                "clip_num": clip_num,
                "name": re.sub(r'\s*\((?:repeat|reuse)\)', '', clip_name, flags=re.IGNORECASE).strip(),
                "start": clip_match.group(1),
                "end": clip_match.group(2),
                "lyric": lyric,
                "is_reuse": is_reuse,
                "section": current_section["name"],
            })

    return track_info, vision, sections


def deduplicate_clips(sections):
    """Return unique clips only (skip repeats/reuses)."""
    seen = set()
    unique = []
    for section in sections:
        for clip in section["clips"]:
            if clip["is_reuse"]:
                continue
            key = clip["clip_num"]
            if key not in seen:
                seen.add(key)
                unique.append(clip)
    return unique


# ── Prompt Builder ────────────────────────────────────────────────────────

def build_style_context(vision):
    """Build a style/context prefix from the creative vision."""
    parts = []

    if vision.get("directors_statement"):
        # Take first sentence for brevity
        stmt = vision["directors_statement"].split(".")[0] + "."
        parts.append(stmt)

    if vision.get("colors"):
        color_str = ", ".join(f"{k} ({v})" for k, v in list(vision["colors"].items())[:3])
        parts.append(f"Color palette: {color_str}.")

    return " ".join(parts)


def build_clip_prompt(clip, style_context, track_title):
    """Build an image generation prompt for a single clip."""
    prompt_parts = [
        f"Storyboard frame for the music video \"{track_title}\".",
        style_context,
        f"Scene: {clip['name']}.",
    ]

    if clip.get("lyric"):
        prompt_parts.append(f"The lyric at this moment: \"{clip['lyric']}\".")

    prompt_parts.append(f"Section: {clip['section']}.")
    prompt_parts.append(
        "Cinematic composition, high detail, suitable as a reference frame "
        "for AI video generation. Dark atmosphere with neon lighting."
    )

    return " ".join(prompt_parts)


# ── JSONL Generator ───────────────────────────────────────────────────────

def generate_jsonl(clips, track_info, vision, model, quality, size):
    """Generate JSONL lines for the OpenAI Batch API."""
    style_context = build_style_context(vision)
    track_title = track_info.get("title", "Unknown")
    track_num = track_info.get("track_number", "00")

    lines = []
    for clip in clips:
        slug = re.sub(r'[^a-z0-9]+', '-', clip["name"].lower()).strip('-')
        custom_id = f"t{track_num}-clip{clip['clip_num']:02d}-{slug}"

        prompt = build_clip_prompt(clip, style_context, track_title)

        request = {
            "custom_id": custom_id,
            "method": "POST",
            "url": "/v1/images/generations",
            "body": {
                "model": model,
                "prompt": prompt,
                "size": size,
                "quality": quality,
                "n": 1,
            },
        }
        lines.append(json.dumps(request))

    return lines


# ── Main ──────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="Convert a PROMPT EDL file to an OpenAI Batch API .jsonl file"
    )
    parser.add_argument("edl_file", help="Path to EDL markdown file")
    parser.add_argument("--model", default=DEFAULT_MODEL,
                        help=f"Image model (default: {DEFAULT_MODEL})")
    parser.add_argument("--quality", default=DEFAULT_QUALITY,
                        choices=["low", "medium", "high"],
                        help=f"Image quality (default: {DEFAULT_QUALITY})")
    parser.add_argument("--size", default=DEFAULT_SIZE,
                        help=f"Image size (default: {DEFAULT_SIZE})")
    parser.add_argument("--output", help="Output .jsonl path (default: batch/<slug>-storyboard.jsonl)")
    parser.add_argument("--dry-run", action="store_true",
                        help="Print summary without writing file")

    args = parser.parse_args()

    if not os.path.exists(args.edl_file):
        print(f"Error: {args.edl_file} not found")
        sys.exit(1)

    # Parse
    track_info, vision, sections = parse_edl(args.edl_file)
    clips = deduplicate_clips(sections)

    title = track_info.get("title", "unknown")
    track_num = track_info.get("track_number", "00")

    print(f"Track: \"{title}\" (#{track_num})")
    print(f"Sections: {len(sections)}")
    print(f"Total clip entries: {sum(len(s['clips']) for s in sections)}")
    print(f"Unique clips: {len(clips)}")
    print(f"Model: {args.model} | Quality: {args.quality} | Size: {args.size}")
    print()

    # Generate JSONL
    lines = generate_jsonl(clips, track_info, vision, args.model, args.quality, args.size)

    if args.dry_run:
        print("=== DRY RUN — First 3 requests ===\n")
        for line in lines[:3]:
            obj = json.loads(line)
            print(f"  {obj['custom_id']}")
            print(f"  Prompt: {obj['body']['prompt'][:120]}...")
            print()
        print(f"Total requests: {len(lines)}")

        # Estimate cost
        if args.model == "gpt-image-1.5":
            costs = {"low": 0.009, "medium": 0.034, "high": 0.10}
        elif args.model == "gpt-image-1":
            costs = {"low": 0.011, "medium": 0.052, "high": 0.10}
        elif args.model == "gpt-image-1-mini":
            costs = {"low": 0.005, "medium": 0.015, "high": 0.035}
        else:
            costs = {"low": 0.04, "medium": 0.06, "high": 0.08}

        sync_cost = len(lines) * costs.get(args.quality, 0.10)
        batch_cost = sync_cost * 0.5
        print(f"Estimated cost (sync):  ${sync_cost:.2f}")
        print(f"Estimated cost (batch): ${batch_cost:.2f}  ← 50% off")
        return

    # Write output
    if args.output:
        output_path = args.output
    else:
        slug = re.sub(r'[^a-z0-9]+', '-', title.lower()).strip('-')
        os.makedirs("batch", exist_ok=True)
        output_path = f"batch/{slug}-storyboard.jsonl"

    with open(output_path, "w") as f:
        f.write("\n".join(lines) + "\n")

    file_size = os.path.getsize(output_path)
    print(f"Written: {output_path}")
    print(f"Requests: {len(lines)}")
    print(f"File size: {file_size:,} bytes")


if __name__ == "__main__":
    main()
