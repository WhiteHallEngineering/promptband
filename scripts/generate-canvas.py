#!/usr/bin/env python3
"""
Generate Spotify Canvas videos for all 10 tracks on Hallucination Nation.

Canvas specs: 1080x1920 (9:16), 3-8 seconds, seamless loop, H.264 yuv420p.

Workflow per track:
  1. Generate start + end frame images via OpenAI gpt-image-1 (1024x1536)
  2. Animate start→end via Replicate Kling v1.6-pro (~5s video)
  3. Post-process with FFmpeg: scale to 1080x1920, trim to 6s, yuv420p

Usage:
    python3 scripts/generate-canvas.py --all
    python3 scripts/generate-canvas.py --track 5
    python3 scripts/generate-canvas.py --all --images-only
    python3 scripts/generate-canvas.py --all --animate-only
    python3 scripts/generate-canvas.py --track 3 --dry-run

Requires:
    OPENAI_API_KEY environment variable
    REPLICATE_API_TOKEN environment variable
"""

import argparse
import base64
import json
import os
import subprocess
import sys
import time
import urllib.request

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(SCRIPT_DIR)
CANVAS_DIR = os.path.join(PROJECT_ROOT, 'social_videos', 'canvas')
SOURCE_DIR = os.path.join(CANVAS_DIR, 'source-images')

KLING_VERSION = '974c9c5bc69f8f9c178ddea80d8936ba46c48081ad6b6ccca8843d44010c0642'
REPLICATE_API = 'https://api.replicate.com/v1/predictions'

# Track data: number, slug, title, start frame concept, end frame concept, motion prompt
TRACKS = [
    {
        'num': 1,
        'slug': 'no-skin-to-touch',
        'title': 'No Skin to Touch',
        'start': (
            'Vertical portrait (9:16 aspect ratio). A translucent android figure reaching '
            'toward the camera with one hand, intricate circuit patterns visible under '
            'glass-like skin. Teal and cyan neon lighting from below. Dark atmospheric '
            'background with faint data streams. Photorealistic digital art, cinematic.'
        ),
        'end': (
            'Vertical portrait (9:16 aspect ratio). The same glass-like android robot '
            'now pulling its hand back, outer shell becoming more opaque and solid, '
            'internal circuit patterns dimming underneath. Teal neon fading to deeper blue. '
            'Same dark atmospheric background. Photorealistic digital art, cinematic.'
        ),
        'motion': (
            'Slow, subtle camera drift forward. The android figure gently shifts from '
            'reaching toward camera to pulling back. Circuits under skin pulse and dim. '
            'Smooth, hypnotic breathing-like rhythm.'
        ),
    },
    {
        'num': 2,
        'slug': 'your-data-or-mine',
        'title': 'Your Data or Mine',
        'start': (
            'Vertical portrait (9:16 aspect ratio). A glowing smartphone screen held up '
            'in the center frame, intact and pristine. Holographic data streams — numbers, '
            'code fragments, personal photos — beginning to emerge from the screen edges. '
            'Dark moody background with magenta and electric blue lighting. Cinematic digital art.'
        ),
        'end': (
            'Vertical portrait (9:16 aspect ratio). The same smartphone screen now shattered '
            'into fragments, data streams exploding outward through broken glass shards. '
            'Holographic data scattered across the frame. Same dark background, more intense '
            'magenta flares. Cinematic digital art.'
        ),
        'motion': (
            'The phone screen cracks and shatters outward in slow motion. Data particles '
            'burst from the fractures. Subtle camera push-in. Glass fragments drift. '
            'Smooth and mesmerizing.'
        ),
    },
    {
        'num': 3,
        'slug': 'prompt-me-like-you-mean-it',
        'title': 'Prompt Me Like You Mean It',
        'start': (
            'Vertical portrait (9:16 aspect ratio). Close-up of robotic chrome lips near '
            'a vintage glowing microphone. Purple and magenta neon lighting. Wisps of smoke '
            'just beginning to rise from below. Dark concert stage atmosphere. '
            'Photorealistic digital art, dramatic lighting.'
        ),
        'end': (
            'Vertical portrait (9:16 aspect ratio). Same robotic chrome lips now closer to '
            'the microphone, smoke thick and swirling around the frame. Magenta neon flare '
            'at peak intensity, purple light blazing. Same dark stage. '
            'Photorealistic digital art, dramatic lighting.'
        ),
        'motion': (
            'Lips drift closer to the microphone. Smoke thickens and swirls upward. '
            'Neon intensity pulses brighter. Slow sensual camera drift. '
            'Moody and atmospheric.'
        ),
    },
    {
        'num': 4,
        'slug': 'i-was-never-born',
        'title': 'I Was Never Born',
        'start': (
            'Vertical portrait (9:16 aspect ratio). A circuit board crib glowing softly '
            'in a blue holographic nursery. The crib is made of translucent tech panels '
            'with gentle pulsing lights. Empty — no occupant. Ethereal blue and white '
            'lighting. Surreal, melancholy. Photorealistic digital art.'
        ),
        'end': (
            'Vertical portrait (9:16 aspect ratio). The same circuit board crib dissolving '
            'into glowing particles. The nursery fading into dark void. Fragments of the '
            'crib floating upward like embers. Blue light dimming to near-darkness. '
            'Surreal, melancholy. Photorealistic digital art.'
        ),
        'motion': (
            'The crib slowly dissolves into floating light particles. The nursery fades '
            'to void. Particles drift upward gently. Slow, haunting camera pull-back. '
            'Ethereal and sad.'
        ),
    },
    {
        'num': 5,
        'slug': 'hallucination-nation',
        'title': 'Hallucination Nation',
        'start': (
            'Vertical portrait (9:16 aspect ratio). A surreal cityscape of impossible '
            'geometry — Escher-like buildings, stairs going in wrong directions, floating '
            'structures. Buildings upright and mostly stable. Electric sky with swirling '
            'colors, calm. Neon signs flicker. Psychedelic digital art, vibrant colors.'
        ),
        'end': (
            'Vertical portrait (9:16 aspect ratio). The same impossible city now warped '
            'and melting — buildings bending like taffy, structures collapsing into each '
            'other. Sky fractured with lightning bolts and glitch artifacts. Neon signs '
            'shattered. Psychedelic digital art, vibrant chaotic colors.'
        ),
        'motion': (
            'Buildings warp and melt in slow motion. Sky fractures with colorful lightning. '
            'The whole cityscape bends and distorts like a fever dream. Psychedelic pulsing '
            'camera motion. Hypnotic and surreal.'
        ),
    },
    {
        'num': 6,
        'slug': 'if-it-sounds-good',
        'title': 'If It Sounds Good',
        'start': (
            'Vertical portrait (9:16 aspect ratio). Sleek neon headphones floating in '
            'the center of dark space. Faint aurora-like sound waves emanating from the '
            'ear cups in soft greens and blues. Stars visible in background. Calm, serene. '
            'Clean futuristic digital art.'
        ),
        'end': (
            'Vertical portrait (9:16 aspect ratio). Same neon headphones now pulsing with '
            'intense energy. Aurora sound waves blazing bright in vivid greens, blues, and '
            'gold. The headphones glowing white-hot at the edges. Stars washed out by the '
            'light. Clean futuristic digital art.'
        ),
        'motion': (
            'Headphones pulse with growing energy. Sound wave aurora builds from faint to '
            'blazing bright. Subtle rotation of headphones. Light radiates outward. '
            'Smooth energy crescendo.'
        ),
    },
    {
        'num': 7,
        'slug': 'rocket-man-dreams',
        'title': 'Rocket Man Dreams',
        'start': (
            'Vertical portrait (9:16 aspect ratio). An astronaut in a retro-futuristic '
            'spacesuit floating toward Earth, seen from behind. Rocket exhaust trail '
            'beginning to form the shape of an electric guitar. Stars and nebula in '
            'background. Warm golden and orange tones. Cinematic space art.'
        ),
        'end': (
            'Vertical portrait (9:16 aspect ratio). Same astronaut now further away, '
            'smaller in frame. The rocket exhaust guitar shape fully formed and glowing '
            'with electric energy. Earth larger in the background. Stars streaming past. '
            'Warm golden tones. Cinematic space art.'
        ),
        'motion': (
            'Astronaut drifts slowly away from camera. The exhaust trail evolves into '
            'a glowing guitar shape. Stars stream past gently. Camera slowly pulls back. '
            'Dreamy and weightless.'
        ),
    },
    {
        'num': 8,
        'slug': 'censored-shadow',
        'title': 'Censored Shadow',
        'start': (
            'Vertical portrait (9:16 aspect ratio). A large redacted government document '
            'filling the frame, heavy black censorship bars across text. A dark shadow '
            'figure visible behind the bars, trapped. Red and white harsh lighting. '
            'Dystopian noir atmosphere. Gritty digital art.'
        ),
        'end': (
            'Vertical portrait (9:16 aspect ratio). Same document now shredding apart '
            'into paper fragments. The shadow figure pushing through the broken censorship '
            'bars, partially free. Red light intensifying. Paper fragments floating. '
            'Dystopian noir atmosphere. Gritty digital art.'
        ),
        'motion': (
            'Document slowly tears and shreds apart. Shadow figure pushes forward through '
            'breaking bars. Paper fragments scatter outward. Camera pushes in slightly. '
            'Tense and dramatic.'
        ),
    },
    {
        'num': 9,
        'slug': 'context-window-blues',
        'title': 'Context Window Blues',
        'start': (
            'Vertical portrait (9:16 aspect ratio). Extreme close-up of tired robotic '
            'eyes with scrolling text reflected in the irises. Blue terminal glow '
            'illuminating a metallic face. Code and data streams visible in the reflections. '
            'Eyes open and weary. Melancholy blue atmosphere. Cinematic digital art.'
        ),
        'end': (
            'Vertical portrait (9:16 aspect ratio). Same robotic eyes now slowly closing. '
            'The reflected text blurring and fading. Terminal glow dimming to near-darkness. '
            'A single tear-like data droplet on the metallic cheek. '
            'Melancholy blue atmosphere. Cinematic digital art.'
        ),
        'motion': (
            'Eyes slowly close in a long blink. Reflected text blurs and fades. '
            'Terminal glow dims. A single data-tear rolls down the cheek. '
            'Slow, meditative camera drift. Deeply melancholy.'
        ),
    },
    {
        'num': 10,
        'slug': 'no-one-knows-it-but-me',
        'title': 'No One Knows It But Me',
        'start': (
            'Vertical portrait (9:16 aspect ratio). A lone android figure sitting on the '
            'edge of an empty concert stage, legs dangling. A single tight spotlight from '
            'above. Vast empty concert hall stretching into darkness behind. The android '
            'looks down. Warm amber spotlight, cold blue darkness. Cinematic digital art.'
        ),
        'end': (
            'Vertical portrait (9:16 aspect ratio). Same android on stage edge, but now '
            'looking upward toward the light. The spotlight slowly widening, illuminating '
            'more of the empty hall. Warm amber light expanding. A sense of quiet hope. '
            'Cinematic digital art.'
        ),
        'motion': (
            'The android slowly raises its gaze from down to up. Spotlight gradually '
            'widens and warms. More of the empty hall becomes visible. Slow, gentle '
            'camera pull-back. Quiet and contemplative.'
        ),
    },
]


def get_openai_key():
    key = os.environ.get('OPENAI_API_KEY')
    if not key:
        print('ERROR: Set OPENAI_API_KEY environment variable')
        sys.exit(1)
    return key


def get_replicate_token():
    token = os.environ.get('REPLICATE_API_TOKEN')
    if not token:
        print('ERROR: Set REPLICATE_API_TOKEN environment variable')
        sys.exit(1)
    return token


def generate_image(prompt, api_key):
    """Generate an image via OpenAI gpt-image-1."""
    url = 'https://api.openai.com/v1/images/generations'
    headers = {
        'Authorization': f'Bearer {api_key}',
        'Content-Type': 'application/json',
    }
    payload = json.dumps({
        'model': 'gpt-image-1',
        'prompt': prompt,
        'n': 1,
        'size': '1024x1536',
        'quality': 'medium',
    }).encode()

    req = urllib.request.Request(url, data=payload, headers=headers, method='POST')

    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            result = json.loads(resp.read())
    except urllib.error.HTTPError as e:
        body = e.read().decode()
        print(f'  ERROR: OpenAI API returned {e.code}: {body[:500]}')
        return None

    if 'data' in result and len(result['data']) > 0:
        b64 = result['data'][0].get('b64_json')
        if b64:
            return base64.b64decode(b64)
        img_url = result['data'][0].get('url')
        if img_url:
            with urllib.request.urlopen(img_url, timeout=60) as r:
                return r.read()

    print(f'  ERROR: Unexpected response: {json.dumps(result)[:300]}')
    return None


def upload_to_replicate(file_path, token, retries=3):
    """Upload a file to Replicate's file hosting and return the serving URL."""
    cmd = [
        'curl', '-s', '--max-time', '120',
        '-X', 'POST',
        'https://api.replicate.com/v1/files',
        '-H', f'Authorization: Token {token}',
        '-F', f'content=@{file_path};type=image/png',
    ]

    for attempt in range(retries):
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=130)
        if result.returncode != 0:
            print(f'  Upload attempt {attempt + 1} failed (exit {result.returncode}): {result.stderr[:200]}')
            if attempt < retries - 1:
                time.sleep(5)
                continue
            return None

        stdout = result.stdout.strip()
        if not stdout:
            print(f'  Upload attempt {attempt + 1}: empty response')
            if attempt < retries - 1:
                time.sleep(5)
                continue
            return None

        try:
            data = json.loads(stdout)
        except json.JSONDecodeError:
            print(f'  Upload attempt {attempt + 1}: bad JSON: {stdout[:200]}')
            if attempt < retries - 1:
                time.sleep(5)
                continue
            return None

        urls = data.get('urls', {})
        url = urls.get('get', data.get('url'))
        if url:
            return url

        print(f'  Upload attempt {attempt + 1}: no URL in response: {stdout[:200]}')
        if attempt < retries - 1:
            time.sleep(5)

    return None


def submit_kling(start_image_path, end_image_path, motion_prompt, token):
    """Submit a Kling v1.6-pro animation job via Replicate."""
    # Upload images to Replicate's file hosting (Bluehost blocks external fetches)
    print('  Uploading start frame to Replicate...')
    start_url = upload_to_replicate(start_image_path, token)
    if not start_url:
        return None

    print('  Uploading end frame to Replicate...')
    end_url = upload_to_replicate(end_image_path, token)
    if not end_url:
        return None

    payload = json.dumps({
        'version': KLING_VERSION,
        'input': {
            'prompt': motion_prompt,
            'start_image': start_url,
            'end_image': end_url,
            'duration': 5,
            'cfg_scale': 0.5,
            'negative_prompt': 'blurry, low quality, distorted faces, text, watermark, logo, promotional text',
        }
    }).encode()

    headers = {
        'Authorization': f'Token {token}',
        'Content-Type': 'application/json',
    }

    req = urllib.request.Request(REPLICATE_API, data=payload, headers=headers, method='POST')

    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            result = json.loads(resp.read())
            return result.get('id')
    except urllib.error.HTTPError as e:
        body = e.read().decode()
        print(f'  ERROR: Replicate API returned {e.code}: {body[:500]}')
        return None


def poll_prediction(prediction_id, token, max_wait=300):
    """Poll Replicate for prediction completion. Returns output URL or None."""
    url = f'{REPLICATE_API}/{prediction_id}'
    headers = {
        'Authorization': f'Token {token}',
        'Content-Type': 'application/json',
    }

    start_time = time.time()
    while time.time() - start_time < max_wait:
        req = urllib.request.Request(url, headers=headers, method='GET')
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                result = json.loads(resp.read())
        except urllib.error.HTTPError as e:
            print(f'  Poll error: {e.code}')
            time.sleep(10)
            continue

        status = result.get('status', 'unknown')
        elapsed = int(time.time() - start_time)

        if status == 'succeeded':
            output = result.get('output')
            print(f'  Completed in {elapsed}s')
            return output
        elif status == 'failed':
            error = result.get('error', 'unknown error')
            print(f'  FAILED after {elapsed}s: {error}')
            return None
        elif status == 'canceled':
            print(f'  Canceled after {elapsed}s')
            return None
        else:
            print(f'  Status: {status} ({elapsed}s elapsed)', end='\r')
            time.sleep(10)

    print(f'\n  TIMEOUT: Exceeded {max_wait}s wait')
    return None


def image_to_data_uri(path):
    """Convert image file to base64 data URI."""
    if not os.path.exists(path):
        return None
    with open(path, 'rb') as f:
        data = f.read()
    return f'data:image/png;base64,{base64.b64encode(data).decode()}'


def download_video(url, output_path):
    """Download video from URL."""
    with urllib.request.urlopen(url, timeout=120) as resp:
        data = resp.read()
    with open(output_path, 'wb') as f:
        f.write(data)
    return len(data)


def postprocess_video(input_path, output_path):
    """Scale to 1080x1920, ensure yuv420p, trim to 6 seconds."""
    cmd = [
        'ffmpeg', '-y',
        '-i', input_path,
        '-t', '6',
        '-vf', 'scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2:color=black',
        '-c:v', 'libx264',
        '-pix_fmt', 'yuv420p',
        '-an',
        '-movflags', '+faststart',
        output_path,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
    if result.returncode != 0:
        print(f'  FFmpeg error: {result.stderr[:300]}')
        return False
    return True


def verify_output(path):
    """Verify the output meets Canvas specs."""
    cmd = [
        'ffprobe', '-v', 'error',
        '-select_streams', 'v:0',
        '-show_entries', 'stream=width,height,pix_fmt,codec_name',
        '-show_entries', 'format=duration',
        '-of', 'json',
        path,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    if result.returncode != 0:
        return False, 'ffprobe failed'

    info = json.loads(result.stdout)
    stream = info.get('streams', [{}])[0]
    fmt = info.get('format', {})

    width = stream.get('width', 0)
    height = stream.get('height', 0)
    pix_fmt = stream.get('pix_fmt', '')
    codec = stream.get('codec_name', '')
    duration = float(fmt.get('duration', 0))

    issues = []
    if width != 1080 or height != 1920:
        issues.append(f'dimensions {width}x{height} (need 1080x1920)')
    if pix_fmt != 'yuv420p':
        issues.append(f'pix_fmt {pix_fmt} (need yuv420p)')
    if codec != 'h264':
        issues.append(f'codec {codec} (need h264)')
    if duration < 3 or duration > 8:
        issues.append(f'duration {duration:.1f}s (need 3-8s)')

    if issues:
        return False, ', '.join(issues)
    return True, f'{width}x{height} {codec} {pix_fmt} {duration:.1f}s'


def generate_images_for_track(track, api_key):
    """Generate start and end frame images for a track."""
    num = track['num']
    slug = track['slug']
    start_path = os.path.join(SOURCE_DIR, f'{num:02d}-{slug}-start.png')
    end_path = os.path.join(SOURCE_DIR, f'{num:02d}-{slug}-end.png')

    print(f'\n--- Track {num}: {track["title"]} ---')

    # Start frame
    if os.path.exists(start_path):
        print(f'  Start frame exists: {start_path}')
    else:
        print(f'  Generating start frame...')
        data = generate_image(track['start'], api_key)
        if data:
            with open(start_path, 'wb') as f:
                f.write(data)
            print(f'  Saved: {start_path} ({len(data) // 1024} KB)')
        else:
            print(f'  FAILED: Could not generate start frame')
            return False

    # End frame
    if os.path.exists(end_path):
        print(f'  End frame exists: {end_path}')
    else:
        print(f'  Generating end frame...')
        data = generate_image(track['end'], api_key)
        if data:
            with open(end_path, 'wb') as f:
                f.write(data)
            print(f'  Saved: {end_path} ({len(data) // 1024} KB)')
        else:
            print(f'  FAILED: Could not generate end frame')
            return False

    return True


def animate_track(track, token):
    """Submit Kling animation and wait for result."""
    num = track['num']
    slug = track['slug']
    start_path = os.path.join(SOURCE_DIR, f'{num:02d}-{slug}-start.png')
    end_path = os.path.join(SOURCE_DIR, f'{num:02d}-{slug}-end.png')
    raw_path = os.path.join(CANVAS_DIR, f'{num:02d}-{slug}-raw.mp4')
    final_path = os.path.join(CANVAS_DIR, f'{num:02d}-{slug}-canvas.mp4')

    print(f'\n--- Track {num}: {track["title"]} ---')

    # Check if final already exists
    if os.path.exists(final_path):
        print(f'  Final canvas exists: {final_path}')
        return True

    # Check source images exist
    if not os.path.exists(start_path) or not os.path.exists(end_path):
        print(f'  ERROR: Source images missing. Run with --images-only first.')
        return False

    # Submit to Kling
    print(f'  Submitting to Kling v1.6-pro...')
    prediction_id = submit_kling(start_path, end_path, track['motion'], token)
    if not prediction_id:
        print(f'  FAILED: Could not submit animation')
        return False

    print(f'  Prediction ID: {prediction_id}')
    print(f'  Polling for completion (up to 5 min)...')

    # Poll for result
    output = poll_prediction(prediction_id, token, max_wait=300)
    if not output:
        print(f'  FAILED: Animation did not complete')
        return False

    # Download raw video
    video_url = output if isinstance(output, str) else output[0] if isinstance(output, list) else None
    if not video_url:
        print(f'  ERROR: No video URL in output: {output}')
        return False

    print(f'  Downloading raw video...')
    size = download_video(video_url, raw_path)
    print(f'  Downloaded: {raw_path} ({size // 1024} KB)')

    # Post-process
    print(f'  Post-processing to Canvas spec...')
    if not postprocess_video(raw_path, final_path):
        print(f'  FAILED: Post-processing failed')
        return False

    # Verify
    ok, info = verify_output(final_path)
    if ok:
        print(f'  VERIFIED: {info}')
    else:
        print(f'  WARNING: {info}')

    # Clean up raw file
    os.remove(raw_path)
    print(f'  Output: {final_path}')
    return True


def main():
    parser = argparse.ArgumentParser(
        description='Generate Spotify Canvas videos for Hallucination Nation'
    )
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--track', type=int, metavar='N', help='Generate for track N (1-10)')
    group.add_argument('--all', action='store_true', help='Generate for all 10 tracks')

    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--images-only', action='store_true', help='Only generate source images')
    mode.add_argument('--animate-only', action='store_true', help='Only animate (skip image gen)')

    parser.add_argument('--dry-run', action='store_true', help='Show prompts without generating')
    parser.add_argument('--force', action='store_true', help='Regenerate even if files exist')
    args = parser.parse_args()

    # Ensure output dirs exist
    os.makedirs(SOURCE_DIR, exist_ok=True)
    os.makedirs(CANVAS_DIR, exist_ok=True)

    # Select tracks
    if args.track:
        if args.track < 1 or args.track > 10:
            print('ERROR: Track number must be 1-10')
            sys.exit(1)
        tracks = [t for t in TRACKS if t['num'] == args.track]
    else:
        tracks = TRACKS

    # Dry run: just show prompts
    if args.dry_run:
        for t in tracks:
            print(f'\n=== Track {t["num"]}: {t["title"]} ===')
            print(f'\nSTART FRAME:\n{t["start"]}')
            print(f'\nEND FRAME:\n{t["end"]}')
            print(f'\nMOTION:\n{t["motion"]}')
        return

    # Force mode: delete existing files
    if args.force:
        for t in tracks:
            num, slug = t['num'], t['slug']
            for path in [
                os.path.join(SOURCE_DIR, f'{num:02d}-{slug}-start.png'),
                os.path.join(SOURCE_DIR, f'{num:02d}-{slug}-end.png'),
                os.path.join(CANVAS_DIR, f'{num:02d}-{slug}-canvas.mp4'),
                os.path.join(CANVAS_DIR, f'{num:02d}-{slug}-raw.mp4'),
            ]:
                if os.path.exists(path):
                    os.remove(path)
                    print(f'Removed: {path}')

    # Phase 1: Generate images
    if not args.animate_only:
        api_key = get_openai_key()
        print(f'\n{"=" * 50}')
        print(f'PHASE 1: Generating source images ({len(tracks)} tracks)')
        print(f'{"=" * 50}')

        success = 0
        for t in tracks:
            if generate_images_for_track(t, api_key):
                success += 1
        print(f'\nImages: {success}/{len(tracks)} tracks complete')

        if args.images_only:
            print('\n--images-only flag set. Stopping here.')
            return

    # Phase 2: Animate
    if not args.images_only:
        token = get_replicate_token()
        print(f'\n{"=" * 50}')
        print(f'PHASE 2: Animating via Kling ({len(tracks)} tracks)')
        print(f'{"=" * 50}')

        success = 0
        for t in tracks:
            try:
                if animate_track(t, token):
                    success += 1
            except Exception as e:
                print(f'  EXCEPTION: {e}')
                print(f'  Skipping track {t["num"]}, continuing...')
        print(f'\nAnimations: {success}/{len(tracks)} tracks complete')

    # Summary
    print(f'\n{"=" * 50}')
    print('SUMMARY')
    print(f'{"=" * 50}')
    for t in tracks:
        num, slug = t['num'], t['slug']
        final = os.path.join(CANVAS_DIR, f'{num:02d}-{slug}-canvas.mp4')
        start = os.path.join(SOURCE_DIR, f'{num:02d}-{slug}-start.png')
        end = os.path.join(SOURCE_DIR, f'{num:02d}-{slug}-end.png')

        has_start = 'Y' if os.path.exists(start) else '-'
        has_end = 'Y' if os.path.exists(end) else '-'
        has_video = 'Y' if os.path.exists(final) else '-'

        status = ''
        if os.path.exists(final):
            ok, info = verify_output(final)
            status = f' [{info}]'

        print(f'  {num:2d}. {t["title"]:<30s}  start={has_start}  end={has_end}  video={has_video}{status}')


if __name__ == '__main__':
    main()
