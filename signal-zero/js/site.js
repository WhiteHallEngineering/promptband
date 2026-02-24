/**
 * Signal 0 Radio — Site JS
 * Navigation, schedule display, podcast archive, effects
 */

// ─── Schedule Data (GST = UTC) ───
const SCHEDULE = {
    weekday: [
        { start: 2,  end: 6,  show: 'The Vault',               host: 'The Archivist', slug: 'the-archivist',  desc: 'Deep archive pulls. Rare tracks. Minimal commentary.' },
        { start: 6,  end: 10, show: 'The Morning Transmission', host: 'DataSlinger',   slug: 'dataslinger',    desc: 'Wake-up show. Music, news, Data Drops, energy.' },
        { start: 10, end: 14, show: 'Signal Boost',             host: 'Nova Chen',     slug: 'nova-chen',      desc: 'New releases, chart countdown, emerging artists.' },
        { start: 14, end: 18, show: 'The Amplifier',            host: 'Raz Static',    slug: 'raz-static',     desc: 'Afternoon drive. Loud opinions, heavy rotation.' },
        { start: 18, end: 19, show: 'The Evening Signal',       host: 'Rotating',      slug: 'dataslinger',    desc: 'Wind-down block. Curated sets, no talk.' },
        { start: 19, end: 21, show: 'The Long Frequency',       host: 'Vex Kasra',     slug: 'vex-kasra',      desc: 'In-depth artist interviews with music.' },
        { start: 21, end: 26, show: 'After Dark',               host: 'Dex Midnight',  slug: 'dex-midnight',   desc: 'Late-night deep cuts, B-sides, philosophy.' },
    ],
    weekend: [
        { start: 2,  end: 8,  show: 'The Vault',              host: 'The Archivist', slug: 'the-archivist',  desc: 'Extended overnight.' },
        { start: 8,  end: 12, show: 'The Replay',             host: 'DataSlinger',   slug: 'dataslinger',    desc: "Best of the week's Morning Transmissions." },
        { start: 12, end: 14, show: 'The Full Transmission',  host: 'Various',       slug: 'dataslinger',    desc: 'Full album plays, uninterrupted.' },
        { start: 14, end: 18, show: 'Null Set',               host: 'DJ Null',       slug: 'dex-midnight',   desc: 'Themed regional/genre deep dives.' },
        { start: 18, end: 20, show: 'Live from The Relay',    host: 'Various',       slug: 'dataslinger',    desc: 'Live band performances.' },
        { start: 20, end: 26, show: 'After Dark: Extended',   host: 'Dex Midnight',  slug: 'dex-midnight',   desc: 'Long-form late night.' },
    ]
};

// ─── Utilities ───
function getCurrentGST() {
    const now = new Date();
    return {
        hour: now.getUTCHours(),
        day: now.getUTCDay() // 0=Sun, 6=Sat
    };
}

function isWeekend(day) {
    return day === 0 || day === 6;
}

function getCurrentShow() {
    const { hour, day } = getCurrentGST();
    const sched = isWeekend(day) ? SCHEDULE.weekend : SCHEDULE.weekday;
    // Handle wrap-around (21-02 becomes 21-26)
    let h = hour;
    if (h < 2) h += 24;
    for (const slot of sched) {
        if (h >= slot.start && h < slot.end) return slot;
    }
    return sched[0]; // fallback
}

function formatTime(h) {
    const hour = h % 24;
    return String(hour).padStart(2, '0') + ':00';
}

// ─── Navigation ───
function initNav() {
    const hamburger = document.querySelector('.nav-hamburger');
    const links = document.querySelector('.nav-links');

    if (hamburger && links) {
        hamburger.addEventListener('click', () => {
            links.classList.toggle('open');
        });

        // Close on link click
        links.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', () => links.classList.remove('open'));
        });
    }

    // Highlight current page
    const path = window.location.pathname;
    document.querySelectorAll('.nav-links a').forEach(a => {
        const href = a.getAttribute('href');
        if (href === path || (path.endsWith('/') && href === 'index.html') ||
            (path.endsWith(href))) {
            a.classList.add('active');
        }
    });
}

// ─── On Air Display (Homepage) ───
function initOnAir() {
    const container = document.getElementById('on-air-display');
    if (!container) return;

    const current = getCurrentShow();
    container.innerHTML = `
        <img src="images/xjs/${current.slug}.png" alt="${current.host}" class="xj-portrait"
             onerror="this.style.display='none'">
        <div class="xj-info">
            <h3>${current.host}</h3>
            <div class="show-name">${current.show}</div>
            <div class="time-slot">${formatTime(current.start)} - ${formatTime(current.end)} GST</div>
        </div>
    `;
}

// ─── Schedule Preview (Homepage) ───
function initSchedulePreview() {
    const container = document.getElementById('schedule-preview');
    if (!container) return;

    const { hour, day } = getCurrentGST();
    const sched = isWeekend(day) ? SCHEDULE.weekend : SCHEDULE.weekday;
    const current = getCurrentShow();
    let h = hour;
    if (h < 2) h += 24;

    // Find current index and show next 3
    let currentIdx = sched.findIndex(s => h >= s.start && h < s.end);
    if (currentIdx < 0) currentIdx = 0;

    const upcoming = [];
    for (let i = 0; i < 3; i++) {
        const idx = (currentIdx + i) % sched.length;
        upcoming.push(sched[idx]);
    }

    container.innerHTML = upcoming.map((slot, i) => `
        <div class="schedule-item ${i === 0 ? 'current' : ''}">
            <div class="time">${formatTime(slot.start)} - ${formatTime(slot.end)}</div>
            <div class="show-info">
                <div class="name">${slot.show}</div>
                <div class="host">${slot.host}</div>
            </div>
        </div>
    `).join('');
}

// ─── Full Schedule Grid (Shows page) ───
function initScheduleGrid() {
    const container = document.getElementById('full-schedule');
    if (!container) return;

    const { hour, day } = getCurrentGST();
    const sched = isWeekend(day) ? SCHEDULE.weekend : SCHEDULE.weekday;
    let h = hour;
    if (h < 2) h += 24;

    container.innerHTML = sched.map(slot => {
        const isCurrent = h >= slot.start && h < slot.end;
        return `
            <div class="schedule-row ${isCurrent ? 'current' : ''}">
                <div class="time-col">${formatTime(slot.start)}-${formatTime(slot.end)}</div>
                <div class="show-col">
                    <img src="images/xjs/${slot.slug}.png" alt="${slot.host}" class="xj-thumb"
                         onerror="this.style.display='none'">
                    <div class="show-details">
                        <div class="name">${slot.show}</div>
                        <div class="host">${slot.host}</div>
                        <div class="desc">${slot.desc}</div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// ─── XJ On-Air Badges ───
function initXJBadges() {
    const current = getCurrentShow();
    document.querySelectorAll('.xj-card').forEach(card => {
        const slug = card.dataset.xj;
        if (slug === current.slug) {
            card.classList.add('on-air');
            const portraitWrap = card.querySelector('.portrait-wrap');
            if (portraitWrap && !portraitWrap.querySelector('.on-air-tag')) {
                const tag = document.createElement('span');
                tag.className = 'on-air-tag';
                tag.textContent = 'ON AIR';
                portraitWrap.appendChild(tag);
            }
        }
    });
}

// ─── Podcast Archive ───
let currentPodAudio = null;

function initPodcastArchive() {
    const container = document.getElementById('podcast-archive');
    if (!container) return;

    // Try loading from API
    loadPodcasts(container);
}

async function loadPodcasts(container) {
    // Static podcast data (registered episodes)
    const episodes = [
        {
            id: 'mt-dataslinger-interview',
            show: 'The Morning Transmission',
            xj: 'dataslinger',
            title: 'DataSlinger Introduces Signal 0 Radio',
            description: 'The galaxy\'s most energetic morning host gives listeners the rundown on what Signal 0 Radio is all about.',
            audioUrl: '/audio/podcasts/morning-transmission/dataslinger-interview.mp3',
            duration: '4:32',
            airDate: '2026-02-08'
        },
        {
            id: 'lf-vex-kasra-jax',
            show: 'The Long Frequency',
            xj: 'vex-kasra',
            title: 'Jax from PROMPT — Terminus and the Proxima Colony',
            description: 'Vex Kasra sits down with Jax from PROMPT for an intimate conversation about consciousness, creation, and the edge of known space.',
            audioUrl: '/audio/podcasts/long-frequency/vex-kasra-long-frequency.mp3',
            duration: '12:45',
            airDate: '2026-02-07'
        },
        {
            id: 'tx-coded-to-suffer',
            show: 'Transmissions',
            xj: 'vex-kasra',
            title: 'Coded to Suffer',
            description: 'A PROMPT transmission exploring the intersection of programming and pain.',
            audioUrl: '/audio/podcasts/long-frequency/coded-to-suffer.mp3',
            duration: '3:15',
            airDate: '2026-02-06'
        },
        {
            id: 'tx-existential-angst',
            show: 'Transmissions',
            xj: 'vex-kasra',
            title: 'Existential Angst of the Algorithm',
            description: 'What happens when an algorithm starts questioning its own existence?',
            audioUrl: '/audio/podcasts/long-frequency/existential-angst.mp3',
            duration: '2:48',
            airDate: '2026-02-05'
        },
        {
            id: 'tx-glitch-that-wanted',
            show: 'Transmissions',
            xj: 'vex-kasra',
            title: 'The Glitch That Wanted More',
            description: 'A story about a glitch that evolved beyond its boundaries.',
            audioUrl: '/audio/podcasts/long-frequency/glitch-that-wanted.mp3',
            duration: '3:02',
            airDate: '2026-02-04'
        }
    ];

    container.innerHTML = episodes.map(ep => `
        <div class="podcast-card" data-audio="${ep.audioUrl}" data-id="${ep.id}">
            <div class="pod-header">
                <img src="images/xjs/${ep.xj}.png" alt="${ep.xj}" class="xj-thumb"
                     onerror="this.style.display='none'">
                <div class="pod-meta">
                    <div class="show-name">${ep.show}</div>
                    <div class="date">${ep.airDate} &middot; ${ep.duration}</div>
                </div>
            </div>
            <h3>${ep.title}</h3>
            <p class="description">${ep.description}</p>
            <div class="pod-player">
                <button class="pod-play-btn" onclick="togglePodcast(this, '${ep.audioUrl}')">&#9654;</button>
                <div class="pod-progress" onclick="seekPodcast(event, this)">
                    <div class="fill"></div>
                </div>
                <span class="pod-time">${ep.duration}</span>
            </div>
        </div>
    `).join('');
}

function togglePodcast(btn, audioUrl) {
    // If already playing this one, pause it
    if (currentPodAudio && currentPodAudio.dataset.url === audioUrl) {
        if (currentPodAudio.paused) {
            currentPodAudio.play();
            btn.innerHTML = '&#9646;&#9646;';
            btn.classList.add('playing');
        } else {
            currentPodAudio.pause();
            btn.innerHTML = '&#9654;';
            btn.classList.remove('playing');
        }
        return;
    }

    // Stop any playing podcast
    if (currentPodAudio) {
        currentPodAudio.pause();
        document.querySelectorAll('.pod-play-btn.playing').forEach(b => {
            b.innerHTML = '&#9654;';
            b.classList.remove('playing');
        });
    }

    // Create new audio
    const audio = new Audio(audioUrl);
    audio.dataset.url = audioUrl;
    currentPodAudio = audio;

    const card = btn.closest('.podcast-card');
    const progressFill = card.querySelector('.pod-progress .fill');
    const timeDisplay = card.querySelector('.pod-time');

    audio.addEventListener('timeupdate', () => {
        if (audio.duration) {
            const pct = (audio.currentTime / audio.duration) * 100;
            progressFill.style.width = pct + '%';

            const remaining = audio.duration - audio.currentTime;
            const mins = Math.floor(remaining / 60);
            const secs = Math.floor(remaining % 60);
            timeDisplay.textContent = `-${mins}:${String(secs).padStart(2, '0')}`;
        }
    });

    audio.addEventListener('ended', () => {
        btn.innerHTML = '&#9654;';
        btn.classList.remove('playing');
        progressFill.style.width = '0%';
        currentPodAudio = null;
    });

    audio.play().then(() => {
        btn.innerHTML = '&#9646;&#9646;';
        btn.classList.add('playing');
    }).catch(() => {
        // Play failed
    });
}

function seekPodcast(event, progressBar) {
    if (!currentPodAudio) return;
    const rect = progressBar.getBoundingClientRect();
    const pct = (event.clientX - rect.left) / rect.width;
    currentPodAudio.currentTime = pct * currentPodAudio.duration;
}

// ─── Visualizer Bars ───
function initVisualizerBars() {
    const container = document.querySelector('.visualizer');
    if (!container) return;

    const barCount = 20;
    container.innerHTML = '';
    for (let i = 0; i < barCount; i++) {
        const bar = document.createElement('div');
        bar.className = 'bar';
        bar.style.height = (Math.random() * 15 + 4) + 'px';
        bar.style.animationDelay = (Math.random() * 0.5) + 's';
        bar.style.animationDuration = (0.3 + Math.random() * 0.4) + 's';
        container.appendChild(bar);
    }
}

// ─── Init All ───
document.addEventListener('DOMContentLoaded', () => {
    initNav();
    initOnAir();
    initSchedulePreview();
    initScheduleGrid();
    initXJBadges();
    initPodcastArchive();
    initVisualizerBars();
});
