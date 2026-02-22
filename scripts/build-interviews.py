#!/usr/bin/env python3
"""
Build script for PROMPT interview rotation.

Reads website/api/interviews.json and regenerates:
- Main page (website/index.html): featured card + top 3 grid cards
- Interviews page (website/interviews/index.html): all interviews

Usage:
    python3 scripts/build-interviews.py           # Build only
    python3 scripts/build-interviews.py --deploy   # Build + deploy
"""

import json
import os
import re
import subprocess
import sys
from html import escape

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(SCRIPT_DIR)
WEBSITE_DIR = os.path.join(PROJECT_ROOT, 'website')
MANIFEST_PATH = os.path.join(WEBSITE_DIR, 'api', 'interviews.json')
INDEX_PATH = os.path.join(WEBSITE_DIR, 'index.html')
INTERVIEWS_PATH = os.path.join(WEBSITE_DIR, 'interviews', 'index.html')

BEGIN_MARKER = '<!-- BEGIN TRANSMISSIONS -->'
END_MARKER = '<!-- END TRANSMISSIONS -->'


def load_manifest():
    with open(MANIFEST_PATH, 'r') as f:
        return json.load(f)


def freq_html(freq):
    """Generate frequency badge HTML for main page grid cards."""
    if freq == 'SIGNAL 0':
        return '<a href="https://signal0radio.com" target="_blank" rel="noopener" style="color: inherit; text-decoration: none;">SIGNAL 0</a>'
    return escape(freq)


def build_featured_card(interview):
    """Generate the featured broadcast card HTML for the main page."""
    img_html = ''
    css_class = 'transmission-featured reveal'
    if interview.get('image'):
        css_class = 'transmission-featured transmission-featured--with-image reveal'
        alt_text = escape(' & '.join(interview.get('guests', [])) or interview['title'])
        img_html = f'''
            <div class="transmission-featured__image">
              <img src="{escape(interview['image'])}" alt="{alt_text}" loading="lazy">
            </div>'''

    show_name = escape(interview.get('show') or 'Featured Broadcast')
    # Strip show name prefix from title to avoid redundancy
    title = interview['title']
    show_raw = interview.get('show') or ''
    if show_raw and title.startswith(show_raw + ':'):
        episode_text = escape(title[len(show_raw) + 1:].strip())
    else:
        episode_text = escape(title)

    # Build description
    desc = interview['description']

    # Transcript button only if interview has transcript
    transcript_btn = ''
    if interview.get('transcript'):
        transcript_btn = '''
                <button type="button" class="btn btn--secondary" id="transcript-trigger">
                  Read Transcript
                </button>'''

    return f'''          {BEGIN_MARKER}
          <!-- Featured Interview -->
          <div class="{css_class}">
            <div class="transmission-featured__static"></div>{img_html}
            <div class="transmission-featured__content">
              <div class="transmission-featured__badge">
                <span class="badge-live"></span>
                FEATURED BROADCAST
              </div>
              <h3 class="transmission-featured__show">{show_name}</h3>
              <p class="transmission-featured__episode">{episode_text}</p>
              <p class="transmission-featured__desc">{desc}</p>
              <div class="transmission-featured__actions">
                <button type="button" class="btn btn--primary" id="interview-play-btn">
                  <span class="btn-icon play-icon">&#9654;</span>
                  <span class="btn-icon pause-icon" style="display:none;">&#10074;&#10074;</span>
                  Listen to Interview
                </button>{transcript_btn}
              </div>
              <audio id="interview-audio" preload="metadata">
                <source src="interviews/{escape(interview['audioFile'])}" type="{escape(interview.get('audioType', 'audio/mp4'))}">
              </audio>
            </div>
            <div class="transmission-featured__waveform">
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
              <div class="waveform-bar"></div>
            </div>
          </div>'''


def build_grid_card(interview, index):
    """Generate a podcast grid card for the main page."""
    delay_class = f' reveal--delay-{index}' if index > 0 else ''
    podcast_id = f'podcast-{index + 1}'
    freq = freq_html(interview['frequency'])

    return f'''
            <div class="transmission-card reveal{delay_class}" id="{escape(interview['id'])}">
              <div class="transmission-card__header">
                <div class="transmission-card__frequency">{freq}</div>
                <div class="transmission-card__signal">
                  <span></span><span></span><span></span>
                </div>
              </div>
              <h5 class="transmission-card__title">{escape(interview['title'])}</h5>
              <p class="transmission-card__duration">{escape(interview['description'])}</p>
              <div class="transmission-card__player">
                <audio id="{podcast_id}" preload="metadata">
                  <source src="interviews/{escape(interview['audioFile'])}" type="{escape(interview.get('audioType', 'audio/mp4'))}">
                </audio>
                <button class="podcast-play-btn" data-audio="{podcast_id}" aria-label="Play podcast">
                  <span class="play-icon">&#9654;</span>
                  <span class="pause-icon">&#10074;&#10074;</span>
                </button>
                <div class="podcast-progress">
                  <div class="podcast-progress__bar"></div>
                </div>
                <span class="podcast-time">0:00</span>
              </div>
            </div>'''


def build_main_page_html(interviews):
    """Generate full transmissions section for the main page."""
    featured = next((i for i in interviews if i.get('featured')), None)
    non_featured = [i for i in interviews if not i.get('featured')]
    grid_interviews = non_featured[:3]

    parts = []

    # Featured card
    if featured:
        parts.append(build_featured_card(featured))
    parts.append('')

    # Grid
    parts.append('          <!-- Podcast Grid -->')
    parts.append('          <div class="transmissions-grid">')
    parts.append('            <h4 class="transmissions-grid__title">')
    parts.append('              <span class="signal-icon"></span>')
    parts.append('              Audio Transmissions')
    parts.append('            </h4>')

    for idx, interview in enumerate(grid_interviews):
        parts.append(build_grid_card(interview, idx))

    parts.append('            <div class="transmissions-grid__more reveal">')
    parts.append('              <a href="/interviews/" class="btn btn--secondary">View All Transmissions &rarr;</a>')
    parts.append('            </div>')
    parts.append('          </div>')
    parts.append(f'          {END_MARKER}')

    return '\n'.join(parts)


def build_interviews_card(interview, is_newest=False):
    """Generate a card for the interviews page."""
    freq_class = ' transmission__freq--signal0' if interview['frequency'] == 'SIGNAL 0' else ''
    freq_text = escape(interview['frequency'])

    # Format date as YYYY.MM.DD
    date_display = interview['date'].replace('-', '.')

    new_badge = ''
    if is_newest:
        new_badge = '\n        <span class="transmission__new">NEW</span>'

    guests_html = ''
    if interview.get('guests'):
        guest_spans = ' &amp; '.join(f'<span>{escape(g)}</span>' for g in interview['guests'])
        guests_html = f'\n      <div class="transmission__guests">Featuring: {guest_spans}</div>'

    return f'''
    <div class="transmission" id="{escape(interview['id'])}">
      <div class="transmission__meta">
        <span class="transmission__freq{freq_class}">{freq_text}</span>
        <span class="transmission__date">{date_display}</span>
        <span class="transmission__duration">{escape(interview['duration'])}</span>{new_badge}
      </div>
      <div class="transmission__title">{escape(interview['title'])}</div>
      <div class="transmission__desc">{escape(interview['description'])}</div>{guests_html}
      <div class="player">
        <button class="player__btn" data-src="{escape(interview['audioFile'])}" aria-label="Play">
          <span class="play">&#9654;</span>
          <span class="pause">&#10074;&#10074;</span>
        </button>
        <div class="player__bar">
          <div class="player__progress"></div>
        </div>
        <span class="player__time">0:00 / 0:00</span>
      </div>
    </div>'''


def build_interviews_page_html(interviews):
    """Generate all cards for the interviews page."""
    parts = [f'    {BEGIN_MARKER}']
    for idx, interview in enumerate(interviews):
        parts.append(build_interviews_card(interview, is_newest=(idx == 0)))
    parts.append(f'    {END_MARKER}')
    return '\n'.join(parts)


def replace_between_markers(html, new_content):
    """Replace content between BEGIN/END TRANSMISSIONS markers."""
    pattern = re.compile(
        r'(\s*)' + re.escape(BEGIN_MARKER) + r'.*?' + re.escape(END_MARKER),
        re.DOTALL
    )
    match = pattern.search(html)
    if not match:
        print(f'  ERROR: Could not find markers in file')
        return None
    return html[:match.start()] + '\n' + new_content + '\n' + html[match.end():]


def deploy():
    """Deploy updated files to server."""
    ssh_key = os.path.expanduser('~/.ssh/bluehost_promptband')
    server = 'hallmar3@162.241.225.117'
    remote_path = '~/public_html/website_8b0f5c66/'

    files = [
        ('website/index.html', remote_path),
        ('website/interviews/index.html', f'{remote_path}interviews/'),
        ('website/api/interviews.json', f'{remote_path}api/'),
        ('website/api/character-profiles.json', f'{remote_path}api/'),
    ]

    # Deploy XJ images
    xjs_dir = os.path.join(WEBSITE_DIR, 'images', 'xjs')
    if os.path.isdir(xjs_dir):
        print('\nDeploying XJ portraits...')
        subprocess.run([
            'ssh', '-i', ssh_key, server,
            f'mkdir -p {remote_path}images/xjs'
        ], check=True)
        subprocess.run([
            'scp', '-i', ssh_key,
            *[os.path.join(xjs_dir, f) for f in os.listdir(xjs_dir) if f.endswith('.png')],
            f'{server}:{remote_path}images/xjs/'
        ], check=True)

    for local_rel, remote in files:
        local_path = os.path.join(PROJECT_ROOT, local_rel)
        print(f'  Deploying {local_rel}...')
        subprocess.run([
            'scp', '-i', ssh_key, local_path, f'{server}:{remote}'
        ], check=True)

    print('\nDeployment complete.')


def main():
    do_deploy = '--deploy' in sys.argv

    print('Loading interviews manifest...')
    interviews = load_manifest()
    print(f'  Found {len(interviews)} interviews')

    featured = [i for i in interviews if i.get('featured')]
    non_featured = [i for i in interviews if not i.get('featured')]
    print(f'  Featured: {featured[0]["title"] if featured else "NONE"}')
    print(f'  Grid (top 3): {", ".join(i["id"] for i in non_featured[:3])}')

    # Build main page
    print('\nBuilding main page transmissions...')
    with open(INDEX_PATH, 'r') as f:
        index_html = f.read()

    main_content = build_main_page_html(interviews)
    new_index = replace_between_markers(index_html, main_content)
    if new_index is None:
        print('FAILED: Could not find markers in index.html')
        sys.exit(1)

    with open(INDEX_PATH, 'w') as f:
        f.write(new_index)
    print('  Updated website/index.html')

    # Build interviews page
    print('\nBuilding interviews page...')
    with open(INTERVIEWS_PATH, 'r') as f:
        interviews_html = f.read()

    interviews_content = build_interviews_page_html(interviews)
    new_interviews = replace_between_markers(interviews_html, interviews_content)
    if new_interviews is None:
        print('FAILED: Could not find markers in interviews/index.html')
        sys.exit(1)

    with open(INTERVIEWS_PATH, 'w') as f:
        f.write(new_interviews)
    print('  Updated website/interviews/index.html')

    print('\nDone! Summary:')
    print(f'  Main page: 1 featured + {min(3, len(non_featured))} grid cards')
    print(f'  Interviews page: {len(interviews)} total cards')

    if do_deploy:
        print('\nDeploying to server...')
        deploy()


if __name__ == '__main__':
    main()
