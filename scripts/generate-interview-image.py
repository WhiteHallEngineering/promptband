#!/usr/bin/env python3
"""
Generate interview images for PROMPT transmissions.

Takes an interview ID, looks up the guests in interviews.json,
pulls their visual descriptions + portrait images from character-profiles.json,
combines portraits into a reference image, and generates a new interview
scene image via OpenAI's gpt-image-1 API.

Usage:
    python3 scripts/generate-interview-image.py nova-jax-ghost-colony
    python3 scripts/generate-interview-image.py nova-jax-ghost-colony --model gpt-image-1.5
    python3 scripts/generate-interview-image.py nova-jax-ghost-colony --dry-run

Requires:
    OPENAI_API_KEY environment variable
"""

import argparse
import base64
import io
import json
import os
import sys

from PIL import Image
import requests

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(SCRIPT_DIR)
WEBSITE_DIR = os.path.join(PROJECT_ROOT, 'website')
MANIFEST_PATH = os.path.join(WEBSITE_DIR, 'api', 'interviews.json')
PROFILES_PATH = os.path.join(WEBSITE_DIR, 'api', 'character-profiles.json')
OUTPUT_DIR = os.path.join(WEBSITE_DIR, 'images', 'press')

DEFAULT_MODEL = 'gpt-image-1'
DEFAULT_QUALITY = 'medium'
DEFAULT_SIZE = '1536x1024'


def load_json(path):
    with open(path, 'r') as f:
        return json.load(f)


def find_interview(interviews, interview_id):
    for i in interviews:
        if i['id'] == interview_id:
            return i
    return None


def resolve_character(profiles, guest_name):
    """Find a character profile by guest name (fuzzy match)."""
    name_lower = guest_name.lower().strip()

    # Check XJs
    for key, xj in profiles.get('xjs', {}).items():
        if name_lower in xj['name'].lower() or xj['name'].lower() in name_lower:
            return xj, 'xj'

    # Check band members
    for key, member in profiles.get('band', {}).items():
        if name_lower in member['name'].lower() or member['name'].lower() in name_lower:
            return member, 'band'
        # Also match on just the first name (e.g. "Jax" matches "Jax Synthetic")
        if name_lower == key.lower():
            return member, 'band'

    return None, None


def load_portrait(portrait_path):
    """Load a portrait image from the website directory."""
    full_path = os.path.join(WEBSITE_DIR, portrait_path)
    if not os.path.exists(full_path):
        print(f'  WARNING: Portrait not found: {full_path}')
        return None
    return Image.open(full_path)


def combine_portraits(images, target_height=1024):
    """Combine portrait images side by side into a single reference image."""
    if not images:
        return None

    # Resize all to same height
    resized = []
    for img in images:
        ratio = target_height / img.height
        new_width = int(img.width * ratio)
        resized.append(img.resize((new_width, target_height), Image.LANCZOS))

    # Combine side by side with a small gap
    gap = 20
    total_width = sum(img.width for img in resized) + gap * (len(resized) - 1)
    combined = Image.new('RGB', (total_width, target_height), (10, 10, 15))

    x_offset = 0
    for img in resized:
        combined.paste(img, (x_offset, 0))
        x_offset += img.width + gap

    return combined


def image_to_png_bytes(img):
    """Convert PIL Image to PNG bytes."""
    buf = io.BytesIO()
    img.save(buf, format='PNG')
    buf.seek(0)
    return buf


def build_prompt(interview, characters):
    """Build the image generation prompt from interview + character data."""
    char_descriptions = []
    for char, char_type in characters:
        label = 'Radio DJ' if char_type == 'xj' else 'Band member guest'
        char_descriptions.append(f'{label} "{char["name"]}": {char["visual"]}')

    chars_text = '\n'.join(char_descriptions)

    prompt = f"""Generate a cinematic interview scene for a sci-fi radio station called Signal 0.

The scene shows these two characters sitting across from each other in a neon-lit radio studio:

{chars_text}

Setting: A futuristic cyberpunk radio studio with professional broadcast microphones, holographic mixing consoles, and neon lighting in blues, purples, and magentas. The Signal 0 Radio logo glows faintly on a monitor in the background. Dark atmospheric lighting with dramatic rim lighting on the characters.

Style: Photorealistic digital art with cinematic lighting. The two characters should be clearly recognizable from the reference portraits provided. Radio interview composition — both figures visible, one slightly turned toward the other.

IMPORTANT: Match the characters' appearances precisely to the reference image provided. The left figure in the reference is the DJ, the right figure is the guest."""

    return prompt


def generate_image(prompt, reference_image, model, quality, size, api_key):
    """Call OpenAI Images API to generate the interview image."""
    url = 'https://api.openai.com/v1/images/edits'

    headers = {
        'Authorization': f'Bearer {api_key}',
    }

    # Convert reference image to bytes
    img_bytes = image_to_png_bytes(reference_image)

    files = {
        'image': ('reference.png', img_bytes, 'image/png'),
    }

    data = {
        'prompt': prompt,
        'model': model,
        'n': 1,
        'size': size,
        'quality': quality,
    }

    print(f'  Calling OpenAI {model} (quality={quality}, size={size})...')
    response = requests.post(url, headers=headers, files=files, data=data, timeout=120)

    if response.status_code != 200:
        print(f'  ERROR: API returned {response.status_code}')
        print(f'  {response.text}')
        return None

    result = response.json()

    # Extract image data (base64)
    if 'data' in result and len(result['data']) > 0:
        img_data = result['data'][0]
        if 'b64_json' in img_data:
            return base64.b64decode(img_data['b64_json'])
        elif 'url' in img_data:
            # Download from URL
            img_response = requests.get(img_data['url'], timeout=60)
            if img_response.status_code == 200:
                return img_response.content
            print(f'  ERROR: Could not download image from URL')
            return None

    print(f'  ERROR: Unexpected API response format')
    print(f'  {json.dumps(result, indent=2)[:500]}')
    return None


def main():
    parser = argparse.ArgumentParser(
        description='Generate interview images for PROMPT transmissions'
    )
    parser.add_argument('interview_id', help='Interview ID from interviews.json')
    parser.add_argument('--model', default=DEFAULT_MODEL, help=f'OpenAI model (default: {DEFAULT_MODEL})')
    parser.add_argument('--quality', default=DEFAULT_QUALITY, help=f'Image quality (default: {DEFAULT_QUALITY})')
    parser.add_argument('--size', default=DEFAULT_SIZE, help=f'Image size (default: {DEFAULT_SIZE})')
    parser.add_argument('--dry-run', action='store_true', help='Show prompt without generating')
    parser.add_argument('--output', help='Custom output path (default: website/images/press/{id}.png)')
    args = parser.parse_args()

    # API key
    api_key = os.environ.get('OPENAI_API_KEY')
    if not api_key and not args.dry_run:
        print('ERROR: Set OPENAI_API_KEY environment variable')
        sys.exit(1)

    # Load data
    print('Loading manifest and profiles...')
    interviews = load_json(MANIFEST_PATH)
    profiles = load_json(PROFILES_PATH)

    # Find interview
    interview = find_interview(interviews, args.interview_id)
    if not interview:
        print(f'ERROR: Interview "{args.interview_id}" not found')
        print(f'Available: {", ".join(i["id"] for i in interviews)}')
        sys.exit(1)

    print(f'  Interview: {interview["title"]}')
    print(f'  Guests: {", ".join(interview.get("guests", []))}')

    # Resolve characters
    characters = []
    portraits = []
    for guest in interview.get('guests', []):
        char, char_type = resolve_character(profiles, guest)
        if char:
            characters.append((char, char_type))
            portrait = load_portrait(char['portrait'])
            if portrait:
                portraits.append(portrait)
            print(f'  Found profile: {char["name"]} ({char_type})')
        else:
            print(f'  WARNING: No profile found for "{guest}"')

    if len(characters) < 2:
        print('WARNING: Less than 2 characters found, image may not match expectations')

    # Build prompt
    prompt = build_prompt(interview, characters)

    if args.dry_run:
        print(f'\n--- PROMPT ---\n{prompt}\n--- END ---')
        if portraits:
            combined = combine_portraits(portraits)
            ref_path = os.path.join(OUTPUT_DIR, f'{args.interview_id}-reference.png')
            combined.save(ref_path)
            print(f'\nReference image saved: {ref_path}')
        return

    # Combine portraits into reference image
    if portraits:
        print(f'\nCombining {len(portraits)} portraits into reference image...')
        reference = combine_portraits(portraits)
    else:
        print('ERROR: No portrait images found for characters')
        sys.exit(1)

    # Generate image
    print('\nGenerating interview image...')
    image_data = generate_image(prompt, reference, args.model, args.quality, args.size, api_key)

    if not image_data:
        print('FAILED: Image generation failed')
        sys.exit(1)

    # Save output
    output_path = args.output or os.path.join(OUTPUT_DIR, f'{args.interview_id}.png')
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    with open(output_path, 'wb') as f:
        f.write(image_data)

    print(f'\nImage saved: {output_path}')
    print(f'Size: {len(image_data) / 1024:.0f} KB')

    # Update interviews.json if image path changed
    for i in interviews:
        if i['id'] == args.interview_id:
            rel_path = os.path.relpath(output_path, WEBSITE_DIR)
            if i.get('image') != rel_path:
                i['image'] = rel_path
                with open(MANIFEST_PATH, 'w') as f:
                    json.dump(interviews, f, indent=2)
                    f.write('\n')
                print(f'Updated interviews.json: image = {rel_path}')
            break


if __name__ == '__main__':
    main()
