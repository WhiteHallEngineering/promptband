/**
 * Signal 0 Radio — Stream Player
 * Connects to Icecast stream, displays now-playing, visualizer bars
 */

const SignalPlayer = (() => {
    const STREAM_URL = 'https://signal0radio.com/stream';
    const NOW_PLAYING_URL = 'https://promptband.ai/api/signal-zero-nowplaying.php';
    const POLL_INTERVAL = 10000; // 10s

    let audio = null;
    let isPlaying = false;
    let pollTimer = null;

    function init() {
        audio = new Audio();
        audio.crossOrigin = 'anonymous';
        audio.preload = 'none';

        const playBtn = document.getElementById('stream-play-btn');
        const volSlider = document.getElementById('stream-volume');
        const volIcon = document.getElementById('stream-vol-icon');

        if (playBtn) {
            playBtn.addEventListener('click', togglePlay);
        }

        if (volSlider) {
            audio.volume = parseFloat(volSlider.value);
            volSlider.addEventListener('input', (e) => {
                audio.volume = parseFloat(e.target.value);
                updateVolIcon();
            });
        }

        if (volIcon) {
            volIcon.addEventListener('click', () => {
                audio.muted = !audio.muted;
                updateVolIcon();
            });
        }

        audio.addEventListener('playing', () => {
            isPlaying = true;
            updateUI();
        });

        audio.addEventListener('pause', () => {
            isPlaying = false;
            updateUI();
        });

        audio.addEventListener('error', () => {
            isPlaying = false;
            updateUI();
            showOffline();
        });

        // Start polling now-playing
        pollNowPlaying();
        pollTimer = setInterval(pollNowPlaying, POLL_INTERVAL);
    }

    function togglePlay() {
        if (isPlaying) {
            audio.pause();
            audio.src = '';
        } else {
            audio.src = STREAM_URL;
            audio.play().catch(() => {
                showOffline();
            });
        }
    }

    function updateUI() {
        const playBtn = document.getElementById('stream-play-btn');
        const visualizer = document.querySelector('.visualizer');

        if (playBtn) {
            playBtn.classList.toggle('playing', isPlaying);
            playBtn.innerHTML = isPlaying ? '&#9646;&#9646;' : '&#9654;';
        }

        if (visualizer) {
            visualizer.classList.toggle('active', isPlaying);
        }
    }

    function updateVolIcon() {
        const icon = document.getElementById('stream-vol-icon');
        if (!icon) return;
        if (audio.muted || audio.volume === 0) {
            icon.textContent = '\u{1F507}';
        } else if (audio.volume < 0.5) {
            icon.textContent = '\u{1F509}';
        } else {
            icon.textContent = '\u{1F50A}';
        }
    }

    function showOffline() {
        const npTitle = document.getElementById('np-title');
        const npArtist = document.getElementById('np-artist');
        if (npTitle) npTitle.textContent = 'Signal Lost';
        if (npArtist) npArtist.textContent = 'Stream offline';
    }

    async function pollNowPlaying() {
        try {
            const res = await fetch(NOW_PLAYING_URL);
            const data = await res.json();

            if (data.success && data.nowPlaying) {
                const np = data.nowPlaying;
                const npTitle = document.getElementById('np-title');
                const npArtist = document.getElementById('np-artist');

                if (npTitle) npTitle.textContent = np.title || 'Unknown Track';
                if (npArtist) npArtist.textContent = np.band || 'Unknown Artist';
            }
        } catch (e) {
            // Silently fail — don't break player
        }
    }

    function destroy() {
        if (audio) {
            audio.pause();
            audio.src = '';
        }
        if (pollTimer) clearInterval(pollTimer);
    }

    return { init, togglePlay, destroy };
})();

// Init when DOM ready
document.addEventListener('DOMContentLoaded', () => {
    SignalPlayer.init();
});
