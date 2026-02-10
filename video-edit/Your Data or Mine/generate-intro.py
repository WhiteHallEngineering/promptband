#!/usr/bin/env python3
"""Generate PROMPT terminal intro video for YDOM music video.

Renders a frame-by-frame terminal/chat prompt animation where the AI
composes "Your Data or Mine". Output: intro.mp4 (8s, 25fps, 1280x720).
"""

import os
import shutil
import subprocess
import random
import numpy as np
from PIL import Image, ImageDraw, ImageFont

# --- CONFIG ---
WIDTH, HEIGHT = 1280, 720
FPS = 25
TOTAL_FRAMES = 300  # 12 seconds
OUTPUT_DIR = "/Users/stevehall/development/promptband/video-edit/Your Data or Mine"
FRAMES_DIR = os.path.join(OUTPUT_DIR, "intro-frames")
OUTPUT_VIDEO = os.path.join(OUTPUT_DIR, "intro.mp4")

# Colors
BG_COLOR = (8, 8, 12)           # Near-black with slight blue
TERMINAL_GREEN = (0, 204, 136)  # Cyan-green terminal text
TERMINAL_DIM = (0, 102, 68)     # Dimmed terminal text
CURSOR_COLOR = (0, 255, 170)    # Bright cursor
TITLE_WHITE = (255, 255, 255)   # Song title
PROMPT_CYAN = (0, 220, 255)     # Band name PROMPT
SCANLINE_COLOR = (0, 0, 0)      # Scan line overlay
GLITCH_RED = (255, 40, 40)
GLITCH_BLUE = (40, 40, 255)

# Fonts
FONT_MONO = "/System/Library/Fonts/Menlo.ttc"
FONT_MONO_ALT = "/System/Library/Fonts/SFNSMono.ttf"
FONT_BOLD = "/System/Library/Fonts/Supplemental/DIN Condensed Bold.ttf"
FONT_IMPACT = "/System/Library/Fonts/Supplemental/Impact.ttf"

# Load fonts
font_terminal = ImageFont.truetype(FONT_MONO, 22)
font_terminal_small = ImageFont.truetype(FONT_MONO, 16)
font_title = ImageFont.truetype(FONT_IMPACT, 80)
font_brand = ImageFont.truetype(FONT_BOLD, 52)
font_brand_small = ImageFont.truetype(FONT_MONO_ALT, 18)

# --- ANIMATION TIMELINE (300 frames = 12 seconds) ---
# Phase 1: BOOT         (frames 0-39,    1.6s)  - black, scan lines fade in, "PROMPT://" types
# Phase 2: COMMAND       (frames 40-159,  4.8s)  - types compose command slowly with pauses
# Phase 3: PROCESSING    (frames 160-209, 2.0s)  - progress bar fills gradually
# Phase 4: GLITCH REVEAL (frames 210-249, 1.6s)  - screen tears, title appears and settles
# Phase 5: BRAND + HOLD  (frames 250-279, 1.2s)  - "PROMPT" appears, hold for reading
# Phase 6: EXIT          (frames 280-300, 0.8s)  - fade to black

# Terminal text lines to type
BOOT_TEXT = "PROMPT://"
COMMAND_LINE_1 = "> compose --style=\"70s-rock-fusion\""
COMMAND_LINE_2 = "  --theme=\"digital desire\" --generate"
PROCESSING_TEXT = "analyzing patterns..."
PROGRESS_STAGES = ["[█░░░░░░░░░░░░░░░]", "[██░░░░░░░░░░░░░░]", "[████░░░░░░░░░░░░]",
                   "[██████░░░░░░░░░░]", "[████████░░░░░░░░]", "[██████████░░░░░░]",
                   "[████████████░░░░]", "[██████████████░░]", "[████████████████]"]
COMPLETE_TEXT = "OUTPUT: YOUR DATA OR MINE"


def add_scanlines(img, intensity=0.15, spacing=3):
    """Add CRT scan line effect."""
    pixels = np.array(img, dtype=np.float32)
    for y in range(0, HEIGHT, spacing):
        pixels[y, :] = pixels[y, :] * (1.0 - intensity)
    return Image.fromarray(pixels.astype(np.uint8))


def add_noise(img, amount=15):
    """Add digital noise."""
    pixels = np.array(img, dtype=np.float32)
    noise = np.random.normal(0, amount, pixels.shape)
    pixels = np.clip(pixels + noise, 0, 255)
    return Image.fromarray(pixels.astype(np.uint8))


def add_vignette(img, strength=0.4):
    """Add subtle vignette (darker corners)."""
    pixels = np.array(img, dtype=np.float32)
    Y, X = np.ogrid[:HEIGHT, :WIDTH]
    cx, cy = WIDTH / 2, HEIGHT / 2
    dist = np.sqrt((X - cx) ** 2 + (Y - cy) ** 2)
    max_dist = np.sqrt(cx ** 2 + cy ** 2)
    vignette = 1.0 - strength * (dist / max_dist) ** 2
    for c in range(3):
        pixels[:, :, c] *= vignette
    return Image.fromarray(np.clip(pixels, 0, 255).astype(np.uint8))


def add_glitch(img, intensity=1.0):
    """Add horizontal glitch displacement."""
    pixels = np.array(img)
    n_glitches = int(3 + intensity * 8)
    for _ in range(n_glitches):
        y = random.randint(0, HEIGHT - 1)
        h = random.randint(2, int(8 + intensity * 20))
        shift = random.randint(-int(30 * intensity), int(30 * intensity))
        y_end = min(y + h, HEIGHT)
        if shift > 0:
            pixels[y:y_end, shift:] = pixels[y:y_end, :WIDTH - shift]
            pixels[y:y_end, :shift] = 0
        elif shift < 0:
            pixels[y:y_end, :WIDTH + shift] = pixels[y:y_end, -shift:]
            pixels[y:y_end, WIDTH + shift:] = 0
    return Image.fromarray(pixels)


def add_rgb_split(img, offset=3):
    """Add chromatic aberration / RGB channel split."""
    pixels = np.array(img)
    result = np.zeros_like(pixels)
    # Red channel shifted left
    result[:, :max(0, WIDTH - offset), 0] = pixels[:, min(offset, WIDTH):, 0]
    # Green channel stays
    result[:, :, 1] = pixels[:, :, 1]
    # Blue channel shifted right
    result[:, min(offset, WIDTH):, 2] = pixels[:, :max(0, WIDTH - offset), 2]
    return Image.fromarray(result)


def type_text(text, chars_to_show):
    """Return text truncated to chars_to_show characters."""
    return text[:chars_to_show]


def draw_cursor(draw, x, y, frame, font_height=22):
    """Draw a blinking cursor at position."""
    if (frame // 5) % 2 == 0:  # Blink every 5 frames (slower blink)
        draw.rectangle([x, y, x + 12, y + font_height], fill=CURSOR_COLOR)


def get_text_width(draw, text, font):
    """Get width of text."""
    bbox = draw.textbbox((0, 0), text, font=font)
    return bbox[2] - bbox[0]


def render_frame(frame_num):
    """Render a single frame of the intro animation."""
    img = Image.new("RGB", (WIDTH, HEIGHT), BG_COLOR)
    draw = ImageDraw.Draw(img)

    # Base position for terminal text
    tx, ty = 80, 160
    line_height = 32

    # --- PHASE 1: BOOT (frames 0-39, 1.6s) ---
    if frame_num < 12:
        # Black with subtle noise fading in
        if frame_num >= 4:
            img = add_noise(img, amount=3)
        img = add_scanlines(img, intensity=0.03 * frame_num)
        return img

    if frame_num < 40:
        # "PROMPT://" types in slowly (1 char per 3 frames)
        boot_frame = frame_num - 12
        chars = min(boot_frame // 3, len(BOOT_TEXT))
        visible = type_text(BOOT_TEXT, chars)
        draw.text((tx, ty), visible, fill=TERMINAL_GREEN, font=font_terminal)
        cursor_x = tx + get_text_width(draw, visible, font_terminal)
        draw_cursor(draw, cursor_x, ty, frame_num)

    # --- PHASE 2: COMMAND TYPING (frames 40-159, 4.8s) ---
    elif frame_num < 160:
        # Show boot text (complete)
        draw.text((tx, ty), BOOT_TEXT, fill=TERMINAL_DIM, font=font_terminal)

        cmd_frame = frame_num - 40

        # Line 1: 1 char per 3 frames (~54 frames for 36 chars) then 10 frame pause
        if cmd_frame < 56:
            # Type command line 1
            chars = min(cmd_frame // 3 + 1, len(COMMAND_LINE_1))
            visible = type_text(COMMAND_LINE_1, chars)
            draw.text((tx, ty + line_height), visible, fill=TERMINAL_GREEN, font=font_terminal)
            cursor_x = tx + get_text_width(draw, visible, font_terminal)
            draw_cursor(draw, cursor_x, ty + line_height, frame_num)
        elif cmd_frame < 68:
            # Pause after line 1 - cursor blinks at end, let viewer read
            draw.text((tx, ty + line_height), COMMAND_LINE_1, fill=TERMINAL_GREEN, font=font_terminal)
            cursor_x = tx + get_text_width(draw, COMMAND_LINE_1, font_terminal)
            draw_cursor(draw, cursor_x, ty + line_height, frame_num)
        elif cmd_frame < 75:
            # Cursor moves to new line
            draw.text((tx, ty + line_height), COMMAND_LINE_1, fill=TERMINAL_GREEN, font=font_terminal)
            draw_cursor(draw, tx, ty + line_height * 2, frame_num)
        else:
            # Type line 2: 1 char per 3 frames (~54 frames for 37 chars)
            draw.text((tx, ty + line_height), COMMAND_LINE_1, fill=TERMINAL_GREEN, font=font_terminal)
            chars2 = min((cmd_frame - 75) // 3 + 1, len(COMMAND_LINE_2))
            visible2 = type_text(COMMAND_LINE_2, chars2)
            draw.text((tx, ty + line_height * 2), visible2, fill=TERMINAL_GREEN, font=font_terminal)
            cursor_x = tx + get_text_width(draw, visible2, font_terminal)
            draw_cursor(draw, cursor_x, ty + line_height * 2, frame_num)

    # --- PHASE 3: PROCESSING (frames 160-209, 2.0s) ---
    elif frame_num < 210:
        # Show completed command (dimmed)
        draw.text((tx, ty), BOOT_TEXT, fill=TERMINAL_DIM, font=font_terminal)
        draw.text((tx, ty + line_height), COMMAND_LINE_1, fill=TERMINAL_DIM, font=font_terminal)
        draw.text((tx, ty + line_height * 2), COMMAND_LINE_2, fill=TERMINAL_DIM, font=font_terminal)

        proc_frame = frame_num - 160
        # Show "analyzing patterns..." with animated dots (12 frames)
        if proc_frame < 12:
            dots = "." * ((proc_frame // 3) % 4)
            draw.text((tx, ty + line_height * 4), f"analyzing patterns{dots}",
                       fill=TERMINAL_GREEN, font=font_terminal_small)
        else:
            draw.text((tx, ty + line_height * 4), "analyzing patterns...",
                       fill=TERMINAL_DIM, font=font_terminal_small)
            # Progress bar - 9 stages over ~30 frames
            bar_frame = proc_frame - 12
            stage_idx = min(bar_frame * len(PROGRESS_STAGES) // 30, len(PROGRESS_STAGES) - 1)
            progress = PROGRESS_STAGES[stage_idx]
            pct = min(100, bar_frame * 100 // 30)
            draw.text((tx, ty + line_height * 5), f"{progress} {pct}%",
                       fill=TERMINAL_GREEN, font=font_terminal)

            if proc_frame >= 44:
                draw.text((tx, ty + line_height * 6.5), COMPLETE_TEXT,
                           fill=CURSOR_COLOR, font=font_terminal)

    # --- PHASE 4: GLITCH REVEAL (frames 210-249, 1.6s) ---
    elif frame_num < 250:
        reveal_frame = frame_num - 210

        if reveal_frame < 5:
            # Heavy glitch - screen tears
            if reveal_frame == 0:
                draw.rectangle([0, 0, WIDTH, HEIGHT], fill=(200, 200, 200))
                img = add_noise(img, amount=80)
                img = add_glitch(img, intensity=2.0)
                return img
            elif reveal_frame < 3:
                img = add_noise(img, amount=50 - reveal_frame * 15)
                img = add_glitch(img, intensity=1.5 - reveal_frame * 0.3)
            else:
                img = add_noise(img, amount=15)

        # Song title appears
        title_text = "YOUR DATA OR MINE"
        title_bbox = draw.textbbox((0, 0), title_text, font=font_title)
        title_w = title_bbox[2] - title_bbox[0]
        title_x = (WIDTH - title_w) // 2
        title_y = HEIGHT // 2 - 60

        if reveal_frame < 10:
            # Glitchy appearance - draw with RGB offset/split
            jitter_x = random.randint(-10, 10)
            jitter_y = random.randint(-5, 5)
            # Draw shadow/ghost layers
            draw.text((title_x + jitter_x + 4, title_y + jitter_y + 2), title_text,
                       fill=GLITCH_RED, font=font_title)
            draw.text((title_x + jitter_x - 4, title_y + jitter_y - 1), title_text,
                       fill=GLITCH_BLUE, font=font_title)
            draw.text((title_x + jitter_x, title_y + jitter_y), title_text,
                       fill=TITLE_WHITE, font=font_title)
        else:
            # Settling - decreasing jitter over ~25 frames
            settle = max(0, 1.0 - (reveal_frame - 10) / 25.0)
            jx = int(random.uniform(-4, 4) * settle)
            jy = int(random.uniform(-2, 2) * settle)
            if settle > 0.2:
                draw.text((title_x + jx + 3, title_y + jy + 1), title_text,
                           fill=(*GLITCH_RED[:2], int(GLITCH_RED[2] * settle)), font=font_title)
                draw.text((title_x + jx - 3, title_y + jy), title_text,
                           fill=(*GLITCH_BLUE[:2], int(GLITCH_BLUE[2] * settle)), font=font_title)
            draw.text((title_x + jx, title_y + jy), title_text,
                       fill=TITLE_WHITE, font=font_title)

        # Faint terminal text in background
        alpha_text = tuple(c // 4 for c in TERMINAL_DIM)
        draw.text((tx, ty), BOOT_TEXT, fill=alpha_text, font=font_terminal_small)
        draw.text((tx, ty + 24), COMMAND_LINE_1, fill=alpha_text, font=font_terminal_small)

    # --- PHASE 5: BRAND + HOLD (frames 250-279, 1.2s) ---
    elif frame_num < 280:
        brand_frame = frame_num - 250

        # Song title (stable)
        title_text = "YOUR DATA OR MINE"
        title_bbox = draw.textbbox((0, 0), title_text, font=font_title)
        title_w = title_bbox[2] - title_bbox[0]
        title_x = (WIDTH - title_w) // 2
        title_y = HEIGHT // 2 - 60
        draw.text((title_x, title_y), title_text, fill=TITLE_WHITE, font=font_title)

        # Band name "PROMPT" appears
        brand_text = "PROMPT"
        brand_bbox = draw.textbbox((0, 0), brand_text, font=font_brand)
        brand_w = brand_bbox[2] - brand_bbox[0]
        brand_x = (WIDTH - brand_w) // 2
        brand_y = title_y + 100

        if brand_frame < 5:
            # Glitch in
            jx = random.randint(-8, 8)
            draw.text((brand_x + jx + 3, brand_y), brand_text, fill=GLITCH_RED, font=font_brand)
            draw.text((brand_x + jx - 3, brand_y), brand_text, fill=GLITCH_BLUE, font=font_brand)
            draw.text((brand_x + jx, brand_y), brand_text, fill=PROMPT_CYAN, font=font_brand)
        else:
            draw.text((brand_x, brand_y), brand_text, fill=PROMPT_CYAN, font=font_brand)

        # Thin separator line
        line_y = brand_y - 12
        line_w = max(title_w, brand_w) + 40
        line_x = (WIDTH - line_w) // 2
        draw.line([(line_x, line_y), (line_x + line_w, line_y)],
                  fill=(*PROMPT_CYAN, 128), width=1)

        # Faint terminal text in background
        alpha_text = tuple(c // 6 for c in TERMINAL_DIM)
        draw.text((tx, ty - 40), BOOT_TEXT, fill=alpha_text, font=font_terminal_small)
        draw.text((tx, ty - 18), COMMAND_LINE_1, fill=alpha_text, font=font_terminal_small)
        draw.text((tx, ty + 4), COMMAND_LINE_2, fill=alpha_text, font=font_terminal_small)

    # --- PHASE 6: EXIT (frames 280-300, 0.8s) ---
    else:
        fade_frame = frame_num - 280
        fade = 1.0 - fade_frame / 20.0  # Slow fade over 20 frames

        title_text = "YOUR DATA OR MINE"
        title_bbox = draw.textbbox((0, 0), title_text, font=font_title)
        title_w = title_bbox[2] - title_bbox[0]
        title_x = (WIDTH - title_w) // 2
        title_y = HEIGHT // 2 - 60

        brand_text = "PROMPT"
        brand_bbox = draw.textbbox((0, 0), brand_text, font=font_brand)
        brand_w = brand_bbox[2] - brand_bbox[0]
        brand_x = (WIDTH - brand_w) // 2
        brand_y = title_y + 100

        title_color = tuple(max(0, int(c * fade)) for c in TITLE_WHITE)
        brand_color = tuple(max(0, int(c * fade)) for c in PROMPT_CYAN)

        draw.text((title_x, title_y), title_text, fill=title_color, font=font_title)
        draw.text((brand_x, brand_y), brand_text, fill=brand_color, font=font_brand)

        # Separator fades too
        line_y = brand_y - 12
        line_w = max(title_w, brand_w) + 40
        line_x = (WIDTH - line_w) // 2
        line_color = tuple(max(0, int(c * fade)) for c in PROMPT_CYAN)
        draw.line([(line_x, line_y), (line_x + line_w, line_y)],
                  fill=line_color, width=1)

    # --- POST-PROCESSING ---
    # Apply effects based on phase
    if frame_num >= 5:
        img = add_scanlines(img, intensity=0.12, spacing=3)

    if 210 <= frame_num < 218:
        img = add_rgb_split(img, offset=max(1, 10 - (frame_num - 210) * 2))
        img = add_glitch(img, intensity=max(0.2, 1.2 - (frame_num - 210) * 0.15))
    elif 250 <= frame_num < 255:
        img = add_rgb_split(img, offset=max(1, 5 - (frame_num - 250)))
        img = add_glitch(img, intensity=0.3)

    # Subtle noise on all frames
    noise_amount = 8
    if 210 <= frame_num < 218:
        noise_amount = 30
    img = add_noise(img, amount=noise_amount)

    # Vignette
    img = add_vignette(img, strength=0.35)

    return img


def main():
    # Clean/create frames directory
    if os.path.exists(FRAMES_DIR):
        shutil.rmtree(FRAMES_DIR)
    os.makedirs(FRAMES_DIR)

    print(f"Generating {TOTAL_FRAMES} frames at {WIDTH}x{HEIGHT} ({TOTAL_FRAMES/FPS:.0f}s)...")
    random.seed(42)  # Reproducible randomness
    np.random.seed(42)

    for i in range(TOTAL_FRAMES):
        # Reset random for glitch effects per-frame but reproducibly
        random.seed(42 + i)
        np.random.seed(42 + i)

        frame = render_frame(i)
        frame_path = os.path.join(FRAMES_DIR, f"frame_{i:04d}.png")
        frame.save(frame_path)

        if (i + 1) % 25 == 0:
            print(f"  Rendered {i + 1}/{TOTAL_FRAMES} frames ({(i+1)/FPS:.1f}s)")

    print(f"\nCompiling video with FFmpeg...")

    # Compile frames to video
    ffmpeg_cmd = [
        "ffmpeg", "-y",
        "-framerate", str(FPS),
        "-i", os.path.join(FRAMES_DIR, "frame_%04d.png"),
        "-c:v", "libx264",
        "-pix_fmt", "yuv420p",
        "-crf", "18",
        "-preset", "slow",
        "-r", str(FPS),
        OUTPUT_VIDEO
    ]

    result = subprocess.run(ffmpeg_cmd, capture_output=True, text=True)
    if result.returncode != 0:
        print(f"FFmpeg error: {result.stderr}")
        return

    # Get file size
    size = os.path.getsize(OUTPUT_VIDEO)
    print(f"\n=== INTRO VIDEO GENERATED ===")
    print(f"Output: {OUTPUT_VIDEO}")
    print(f"Size: {size / 1024:.0f} KB")
    print(f"Duration: {TOTAL_FRAMES / FPS:.1f}s")
    print(f"Resolution: {WIDTH}x{HEIGHT}")
    print(f"FPS: {FPS}")
    print(f"\nTimeline:")
    print(f"  0:00-1:12  Boot sequence (PROMPT://)")
    print(f"  1:15-6:10  Command typing (compose --style...)")
    print(f"  6:10-8:10  Processing (progress bar)")
    print(f"  8:10-10:00 Glitch reveal (YOUR DATA OR MINE)")
    print(f"  10:00-11:05 Brand hold (PROMPT)")
    print(f"  11:05-12:00 Fade out")

    # Cleanup frames
    print(f"\nCleaning up {TOTAL_FRAMES} frame files...")
    shutil.rmtree(FRAMES_DIR)
    print("Done!")


if __name__ == "__main__":
    main()
