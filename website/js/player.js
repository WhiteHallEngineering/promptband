/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * MEMBRANE PLAYER - PROMPT AI Rock Band
 * "Touch the sound. Feel the void."
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * A revolutionary audio player that transforms sound into a tactile visual experience.
 * Built with Web Audio API for waveform analysis and Canvas for organic rendering.
 *
 * @author PROMPT Engineering Team
 * @version 1.0.0
 */

(function (global) {
  'use strict';

  // ═══════════════════════════════════════════════════════════════════════════
  // CONSTANTS & CONFIGURATION
  // ═══════════════════════════════════════════════════════════════════════════

  const WAVEFORM_SAMPLES = 200;
  const ANIMATION_FPS = 60;
  const GLITCH_DURATION = 300;

  // Track accent colors - matching the CSS variables
  const TRACK_COLORS = [
    '#ff3366', // 1. No Skin to Touch
    '#00ffcc', // 2. Your Data or Mine
    '#ff9900', // 3. Prompt Me Like You Mean It
    '#9966ff', // 4. I Was Never Born
    '#ff00ff', // 5. Hallucination Nation
    '#00ff66', // 6. If It Sounds Good, Is It Cheating?
    '#ffcc00', // 7. Rocket Man Dreams
    '#ff0000', // 8. Censored Shadow
    '#0099ff', // 9. Context Window Blues
    '#ffffff', // 10. No One Knows It But Me
  ];

  // Default tracks configuration
  const DEFAULT_TRACKS = [
    { title: 'No Skin to Touch', file: 'clips/01-no-skin-to-touch-clip.mp3', fullFile: 'full/01-no-skin-to-touch.mp3' },
    { title: 'Your Data or Mine', file: 'clips/02-your-data-or-mine-clip.mp3', fullFile: 'full/02-your-data-or-mine.mp3' },
    { title: 'Prompt Me Like You Mean It', file: 'clips/03-prompt-me-like-you-mean-it-clip.mp3', fullFile: 'full/03-prompt-me-like-you-mean-it.mp3' },
    { title: 'I Was Never Born', file: 'clips/04-i-was-never-born-clip.mp3', fullFile: 'full/04-i-was-never-born.mp3' },
    { title: 'Hallucination Nation', file: 'clips/05-hallucination-nation-clip.mp3', fullFile: 'full/05-hallucination-nation.mp3' },
    { title: 'If It Sounds Good, Is It Cheating?', file: 'clips/06-if-it-sounds-good-clip.mp3', fullFile: 'full/06-if-it-sounds-good.mp3' },
    { title: 'Rocket Man Dreams', file: 'clips/07-rocket-man-dreams-clip.mp3', fullFile: 'full/07-rocket-man-dreams.mp3' },
    { title: 'Censored Shadow', file: 'clips/08-censored-shadow-clip.mp3', fullFile: 'full/08-censored-shadow.mp3' },
    { title: 'Context Window Blues', file: 'clips/09-context-window-blues-clip.mp3', fullFile: 'full/09-context-window-blues.mp3' },
    { title: 'No One Knows It But Me', file: 'clips/10-no-one-knows-it-but-me-clip.mp3', fullFile: 'full/10-no-one-knows-it-but-me.mp3' },
  ];

  // ═══════════════════════════════════════════════════════════════════════════
  // UTILITY FUNCTIONS
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Format seconds to MM:SS display
   */
  function formatTime(seconds) {
    if (!isFinite(seconds) || seconds < 0) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }

  /**
   * Clamp a value between min and max
   */
  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  /**
   * Create an element with attributes and children
   */
  function createElement(tag, attrs = {}, children = []) {
    const el = document.createElement(tag);
    Object.entries(attrs).forEach(([key, value]) => {
      if (key === 'className') {
        el.className = value;
      } else if (key === 'innerHTML') {
        el.innerHTML = value;
      } else if (key.startsWith('data')) {
        el.setAttribute(key.replace(/([A-Z])/g, '-$1').toLowerCase(), value);
      } else {
        el.setAttribute(key, value);
      }
    });
    children.forEach((child) => {
      if (typeof child === 'string') {
        el.appendChild(document.createTextNode(child));
      } else if (child) {
        el.appendChild(child);
      }
    });
    return el;
  }

  /**
   * Debounce function calls
   */
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // SVG ICONS
  // ═══════════════════════════════════════════════════════════════════════════

  const ICONS = {
    play: '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>',
    pause: '<svg viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>',
    prev: '<svg viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>',
    next: '<svg viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>',
    volumeHigh: '<svg viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>',
    volumeLow: '<svg viewBox="0 0 24 24"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>',
    volumeMute: '<svg viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>',
    chevronDown: '<svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>',
    minimize: '<svg viewBox="0 0 24 24"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>',
  };

  /**
   * Detect if device is mobile
   */
  function isMobile() {
    return window.innerWidth <= 768 ||
           /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // WAVEFORM ANALYZER CLASS
  // ═══════════════════════════════════════════════════════════════════════════

  class WaveformAnalyzer {
    constructor() {
      this.audioContext = null;
      this.cache = new Map();
    }

    /**
     * Initialize the Web Audio API context
     */
    async init() {
      if (!this.audioContext) {
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
      }
      if (this.audioContext.state === 'suspended') {
        await this.audioContext.resume();
      }
    }

    /**
     * Analyze an audio file and extract waveform data
     */
    async analyze(url, samples = WAVEFORM_SAMPLES) {
      // Check cache first
      if (this.cache.has(url)) {
        return this.cache.get(url);
      }

      await this.init();

      try {
        const response = await fetch(url);
        const arrayBuffer = await response.arrayBuffer();
        const audioBuffer = await this.audioContext.decodeAudioData(arrayBuffer);

        const waveformData = this.extractWaveform(audioBuffer, samples);
        this.cache.set(url, waveformData);

        return waveformData;
      } catch (error) {
        console.error('Waveform analysis failed:', error);
        // Return synthetic waveform on error
        return this.generateSyntheticWaveform(samples);
      }
    }

    /**
     * Extract waveform peaks from audio buffer
     */
    extractWaveform(audioBuffer, samples) {
      const channelData = audioBuffer.getChannelData(0);
      const blockSize = Math.floor(channelData.length / samples);
      const waveform = [];

      for (let i = 0; i < samples; i++) {
        const start = i * blockSize;
        let sum = 0;
        let max = 0;

        for (let j = 0; j < blockSize; j++) {
          const value = Math.abs(channelData[start + j] || 0);
          sum += value;
          if (value > max) max = value;
        }

        // Combine average and peak for more organic look
        const avg = sum / blockSize;
        waveform.push((avg + max) / 2);
      }

      // Normalize
      const maxValue = Math.max(...waveform) || 1;
      return waveform.map((v) => v / maxValue);
    }

    /**
     * Generate synthetic waveform for fallback
     */
    generateSyntheticWaveform(samples) {
      const waveform = [];
      for (let i = 0; i < samples; i++) {
        const base = 0.3 + Math.random() * 0.4;
        const wave = Math.sin((i / samples) * Math.PI * 4) * 0.2;
        waveform.push(clamp(base + wave, 0.1, 1));
      }
      return waveform;
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // WAVEFORM RENDERER CLASS
  // ═══════════════════════════════════════════════════════════════════════════

  class WaveformRenderer {
    constructor(inactiveCanvas, activeCanvas, options = {}) {
      this.inactiveCanvas = inactiveCanvas;
      this.activeCanvas = activeCanvas;
      this.inactiveCtx = inactiveCanvas.getContext('2d');
      this.activeCtx = activeCanvas.getContext('2d');

      this.options = {
        barWidth: 3,
        barGap: 2,
        inactiveColor: '#2a2a35',
        activeColor: '#00ffff',
        glowIntensity: 15,
        organicVariation: 0.1,
        ...options,
      };

      this.waveformData = null;
      this.progress = 0;
      this.animationPhase = 0;
      this.isPlaying = false;

      this.setupCanvas();
    }

    /**
     * Set up canvas for high-DPI displays
     */
    setupCanvas() {
      const dpr = window.devicePixelRatio || 1;
      const rect = this.inactiveCanvas.getBoundingClientRect();

      [this.inactiveCanvas, this.activeCanvas].forEach((canvas) => {
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        canvas.style.width = `${rect.width}px`;
        canvas.style.height = `${rect.height}px`;
        canvas.getContext('2d').scale(dpr, dpr);
      });

      this.width = rect.width;
      this.height = rect.height;
    }

    /**
     * Set waveform data and render
     */
    setWaveform(data) {
      this.waveformData = data;
      this.render();
    }

    /**
     * Set the active color
     */
    setActiveColor(color) {
      this.options.activeColor = color;
      this.render();
    }

    /**
     * Set playback progress (0-1)
     */
    setProgress(progress) {
      this.progress = clamp(progress, 0, 1);
      this.updateActiveClip();
    }

    /**
     * Update the clip path for active canvas
     */
    updateActiveClip() {
      const clipWidth = this.progress * 100;
      this.activeCanvas.style.clipPath = `inset(0 ${100 - clipWidth}% 0 0)`;
    }

    /**
     * Start animation loop
     */
    startAnimation() {
      this.isPlaying = true;
      this.animate();
    }

    /**
     * Stop animation loop
     */
    stopAnimation() {
      this.isPlaying = false;
    }

    /**
     * Animation frame
     */
    animate() {
      if (!this.isPlaying) return;

      this.animationPhase += 0.05;
      this.render();

      requestAnimationFrame(() => this.animate());
    }

    /**
     * Render waveform to canvases
     */
    render() {
      if (!this.waveformData) return;

      const { barWidth, barGap, organicVariation, glowIntensity } = this.options;
      const totalBarWidth = barWidth + barGap;
      const numBars = Math.floor(this.width / totalBarWidth);
      const dataStep = this.waveformData.length / numBars;

      // Clear canvases
      this.inactiveCtx.clearRect(0, 0, this.width, this.height);
      this.activeCtx.clearRect(0, 0, this.width, this.height);

      // Draw to both canvases
      for (let i = 0; i < numBars; i++) {
        const dataIndex = Math.floor(i * dataStep);
        let amplitude = this.waveformData[dataIndex] || 0.1;

        // Add organic breathing variation when playing
        if (this.isPlaying) {
          const variation =
            Math.sin(this.animationPhase + i * 0.3) * organicVariation;
          amplitude = clamp(amplitude + variation, 0.05, 1);
        }

        const barHeight = amplitude * (this.height * 0.8);
        const x = i * totalBarWidth + barGap / 2;
        const y = (this.height - barHeight) / 2;

        // Draw inactive bar
        this.drawBar(this.inactiveCtx, x, y, barWidth, barHeight, this.options.inactiveColor, 0);

        // Draw active bar with glow
        this.drawBar(this.activeCtx, x, y, barWidth, barHeight, this.options.activeColor, glowIntensity);
      }
    }

    /**
     * Draw a single waveform bar
     */
    drawBar(ctx, x, y, width, height, color, glowIntensity) {
      ctx.save();

      if (glowIntensity > 0) {
        ctx.shadowColor = color;
        ctx.shadowBlur = glowIntensity;
      }

      // Rounded bar
      const radius = width / 2;
      ctx.fillStyle = color;
      ctx.beginPath();
      ctx.roundRect(x, y, width, height, radius);
      ctx.fill();

      ctx.restore();
    }

    /**
     * Handle resize
     */
    resize() {
      this.setupCanvas();
      this.render();
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // MEMBRANE PLAYER CLASS
  // ═══════════════════════════════════════════════════════════════════════════

  class MembranePlayer {
    constructor(container, tracks) {
      this.container = typeof container === 'string'
        ? document.querySelector(container)
        : container;

      if (!this.container) {
        throw new Error('Membrane Player: Container not found');
      }

      this.tracks = tracks || DEFAULT_TRACKS;
      this.currentTrackIndex = 0;
      this.isPlaying = false;
      this.isExpanded = true;
      this.isDragging = false;
      this.volume = 1;

      // Components
      this.audio = null;
      this.analyzer = new WaveformAnalyzer();
      this.renderer = null;
      this.waveformData = new Map();

      // DOM elements (populated in build)
      this.elements = {};

      // EQ values (0-100, 50 is neutral) - must be defined before buildUI
      this.eqValues = {
        volume: 100,
        balance: 50,
        bass: 50,
        treble: 50
      };

      // Bind methods
      this.handleTimeUpdate = this.handleTimeUpdate.bind(this);
      this.handleTrackEnd = this.handleTrackEnd.bind(this);
      this.handleWaveformClick = this.handleWaveformClick.bind(this);
      this.handleWaveformMove = this.handleWaveformMove.bind(this);
      this.handleWaveformDown = this.handleWaveformDown.bind(this);
      this.handleWaveformUp = this.handleWaveformUp.bind(this);
      this.handleResize = debounce(this.handleResize.bind(this), 250);

      this.init();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ═══════════════════════════════════════════════════════════════════════

    async init() {
      // Check for full tracks mode from sessionStorage
      try {
        if (sessionStorage.getItem('prompt_full_tracks') === 'true') {
          window.PROMPT_FULL_TRACKS_ENABLED = true;
        }
      } catch (e) {
        // Storage not available
      }

      this.buildUI();
      this.setupAudio();
      this.bindEvents();
      await this.loadTrack(0);

      // Listen for full tracks mode being enabled
      window.addEventListener('prompt-full-tracks-enabled', () => {
        // Reload current track to switch to full version
        const currentIndex = this.currentTrackIndex;
        const wasPlaying = this.isPlaying;
        this.waveformData.clear(); // Clear cached waveforms
        this.loadTrack(currentIndex).then(() => {
          if (wasPlaying) this.play();
        });
      });

      // Emit ready event
      this.emit('ready', { player: this });
    }

    /**
     * Build the player UI
     */
    buildUI() {
      // Always start collapsed
      this.isExpanded = false;

      const playerEl = createElement('div', {
        className: 'membrane-player collapsed'
      });

      // Mobile minimize button (floating above player)
      const minimizeBtn = createElement('button', {
        className: 'membrane-minimize-btn',
        innerHTML: ICONS.minimize,
        'aria-label': 'Minimize player',
      });
      playerEl.appendChild(minimizeBtn);
      this.elements.minimizeBtn = minimizeBtn;

      // Toggle bar (collapsed view)
      const toggle = this.buildToggle();

      // Main content
      const content = createElement('div', { className: 'membrane-content' });

      // Track list
      const tracklist = this.buildTracklist();

      // Waveform area
      const waveformArea = this.buildWaveformArea();

      content.appendChild(tracklist);
      content.appendChild(waveformArea);
      playerEl.appendChild(toggle);
      playerEl.appendChild(content);

      // Add redaction bars container for Censored Shadow
      const redactionBars = createElement('div', { className: 'membrane-redaction-bars' });
      for (let i = 0; i < 5; i++) {
        const bar = createElement('div', { className: 'membrane-redaction-bar' });
        bar.style.top = `${20 + i * 20}%`;
        bar.style.width = `${30 + Math.random() * 40}%`;
        bar.style.left = `${Math.random() * 30}%`;
        bar.style.animationDelay = `${i * 0.5}s`;
        redactionBars.appendChild(bar);
      }
      playerEl.appendChild(redactionBars);

      this.container.appendChild(playerEl);
      this.elements.player = playerEl;
      this.elements.content = content;
      this.elements.redactionBars = redactionBars;

      // Initialize renderer after DOM is ready
      requestAnimationFrame(() => {
        this.renderer = new WaveformRenderer(
          this.elements.waveformInactive,
          this.elements.waveformActive
        );
      });
    }

    /**
     * Build toggle bar
     */
    buildToggle() {
      const toggle = createElement('div', { className: 'membrane-toggle' });

      const left = createElement('div', { className: 'membrane-toggle-left' });

      // Mini visualizer
      const miniViz = createElement('div', { className: 'membrane-mini-viz' });
      for (let i = 0; i < 5; i++) {
        miniViz.appendChild(createElement('div', { className: 'membrane-mini-bar' }));
      }
      this.elements.miniViz = miniViz;

      // Now playing info
      const nowPlaying = createElement('div', { className: 'membrane-now-playing' });
      const label = createElement('span', { className: 'membrane-now-playing-label' }, ['Now Playing']);
      const title = createElement('span', { className: 'membrane-now-playing-title' }, [this.tracks[0]?.title || '']);
      nowPlaying.appendChild(label);
      nowPlaying.appendChild(title);
      this.elements.nowPlayingTitle = title;

      left.appendChild(miniViz);
      left.appendChild(nowPlaying);

      const right = createElement('div', { className: 'membrane-toggle-right' });

      const hint = createElement('span', { className: 'membrane-toggle-hint' }, ['tap to open']);
      this.elements.toggleHint = hint;

      const arrow = createElement('span', {
        className: 'membrane-toggle-arrow',
        innerHTML: ICONS.chevronDown,
      });

      right.appendChild(hint);
      right.appendChild(arrow);

      toggle.appendChild(left);
      toggle.appendChild(right);
      this.elements.toggle = toggle;

      return toggle;
    }

    /**
     * Build track list
     */
    buildTracklist() {
      const list = createElement('div', { className: 'membrane-tracklist' });

      this.tracks.forEach((track, index) => {
        const trackEl = createElement('div', {
          className: `membrane-track${index === 0 ? ' active' : ''}`,
          dataIndex: index,
          tabindex: '0',
          role: 'button',
          'aria-label': `Play ${track.title}`,
        });

        // Set accent color
        trackEl.style.setProperty('--track-color', TRACK_COLORS[index] || TRACK_COLORS[0]);

        const number = createElement('span', { className: 'membrane-track-number' }, [
          `${(index + 1).toString().padStart(2, '0')}`,
        ]);

        const title = createElement('span', { className: 'membrane-track-title' }, [track.title]);

        const duration = createElement('span', { className: 'membrane-track-duration' }, ['--:--']);

        trackEl.appendChild(number);
        trackEl.appendChild(title);
        trackEl.appendChild(duration);
        list.appendChild(trackEl);
      });

      this.elements.tracklist = list;
      return list;
    }

    /**
     * Build waveform area
     */
    buildWaveformArea() {
      const area = createElement('div', { className: 'membrane-waveform-area' });

      // Waveform container
      const waveformContainer = createElement('div', { className: 'membrane-waveform-container' });

      // Canvas layers
      const canvasInactive = createElement('canvas', {
        className: 'membrane-waveform-canvas membrane-waveform-inactive',
      });
      const canvasActive = createElement('canvas', {
        className: 'membrane-waveform-canvas membrane-waveform-active',
      });

      // Cursor
      const cursor = createElement('div', { className: 'membrane-cursor' });
      cursor.style.left = '0%';

      // Hover time indicator
      const hoverTime = createElement('div', { className: 'membrane-hover-time' }, ['0:00']);

      // Organic overlay
      const organicOverlay = createElement('div', { className: 'membrane-organic-overlay' });

      // Loading indicator
      const loading = createElement('div', { className: 'membrane-loading' }, ['Analyzing']);

      waveformContainer.appendChild(canvasInactive);
      waveformContainer.appendChild(canvasActive);
      waveformContainer.appendChild(cursor);
      waveformContainer.appendChild(hoverTime);
      waveformContainer.appendChild(organicOverlay);
      waveformContainer.appendChild(loading);

      this.elements.waveformContainer = waveformContainer;
      this.elements.waveformInactive = canvasInactive;
      this.elements.waveformActive = canvasActive;
      this.elements.cursor = cursor;
      this.elements.hoverTime = hoverTime;
      this.elements.loading = loading;

      // Controls
      const controls = this.buildControls();

      area.appendChild(waveformContainer);
      area.appendChild(controls);

      return area;
    }

    /**
     * Build playback controls
     */
    buildControls() {
      const controls = createElement('div', { className: 'membrane-controls' });

      // Transport controls
      const transport = createElement('div', { className: 'membrane-transport' });

      const prevBtn = createElement('button', {
        className: 'membrane-btn membrane-btn-prev',
        innerHTML: ICONS.prev,
        'aria-label': 'Previous track',
      });

      const playBtn = createElement('button', {
        className: 'membrane-btn membrane-btn-play',
        innerHTML: ICONS.play,
        'aria-label': 'Play',
      });

      const nextBtn = createElement('button', {
        className: 'membrane-btn membrane-btn-next',
        innerHTML: ICONS.next,
        'aria-label': 'Next track',
      });

      transport.appendChild(prevBtn);
      transport.appendChild(playBtn);
      transport.appendChild(nextBtn);

      this.elements.prevBtn = prevBtn;
      this.elements.playBtn = playBtn;
      this.elements.nextBtn = nextBtn;

      // Time display
      const time = createElement('div', { className: 'membrane-time' });
      const currentTime = createElement('span', { className: 'membrane-time-current' }, ['0:00']);
      const separator = createElement('span', { className: 'membrane-time-separator' }, ['/']);
      const totalTime = createElement('span', { className: 'membrane-time-total' }, ['0:00']);

      time.appendChild(currentTime);
      time.appendChild(separator);
      time.appendChild(totalTime);

      this.elements.currentTime = currentTime;
      this.elements.totalTime = totalTime;

      // Rotary knobs container
      // Load saved settings before building knobs
      this.loadSavedSettings();

      const knobs = createElement('div', { className: 'membrane-knobs' });

      // Volume knob (use saved value or default)
      const volumeKnob = this.buildKnob('volume', 'VOL', this.eqValues.volume, 0, 100);
      knobs.appendChild(volumeKnob);

      // Balance knob
      const balanceKnob = this.buildKnob('balance', 'BAL', this.eqValues.balance, 0, 100);
      knobs.appendChild(balanceKnob);

      // Bass knob
      const bassKnob = this.buildKnob('bass', 'BASS', this.eqValues.bass, 0, 100);
      knobs.appendChild(bassKnob);

      // Treble knob
      const trebleKnob = this.buildKnob('treble', 'TREB', this.eqValues.treble, 0, 100);
      knobs.appendChild(trebleKnob);

      this.elements.knobs = knobs;

      controls.appendChild(transport);
      controls.appendChild(time);
      controls.appendChild(knobs);

      return controls;
    }

    /**
     * Build a rotary knob control
     */
    buildKnob(name, label, defaultValue, min, max) {
      const container = createElement('div', {
        className: 'membrane-knob',
        dataKnob: name,
      });

      const knobOuter = createElement('div', { className: 'membrane-knob-outer' });
      const knobInner = createElement('div', { className: 'membrane-knob-inner' });
      const indicator = createElement('div', { className: 'membrane-knob-indicator' });

      knobInner.appendChild(indicator);
      knobOuter.appendChild(knobInner);

      const labelEl = createElement('span', { className: 'membrane-knob-label' }, [label]);

      container.appendChild(knobOuter);
      container.appendChild(labelEl);

      // Store knob data
      container.knobData = {
        name,
        value: defaultValue,
        min,
        max,
        rotation: this.valueToRotation(defaultValue, min, max),
      };

      // Set initial rotation
      knobInner.style.transform = `rotate(${container.knobData.rotation}deg)`;

      // Store element reference
      this.elements[`${name}Knob`] = container;
      this.elements[`${name}KnobInner`] = knobInner;

      return container;
    }

    /**
     * Convert value to rotation degrees (-135 to 135)
     */
    valueToRotation(value, min, max) {
      const percent = (value - min) / (max - min);
      return -135 + (percent * 270);
    }

    /**
     * Convert rotation to value
     */
    rotationToValue(rotation, min, max) {
      const percent = (rotation + 135) / 270;
      return min + (percent * (max - min));
    }

    /**
     * Set up audio element with Web Audio API for EQ
     */
    setupAudio() {
      this.audio = new Audio();
      this.audio.preload = 'metadata';
      this.audio.volume = this.volume;

      // Web Audio API setup for EQ
      this.audioContext = null;
      this.sourceNode = null;
      this.gainNode = null;
      this.bassFilter = null;
      this.trebleFilter = null;
      this.pannerNode = null;
      this.audioConnected = false;
    }

    /**
     * Connect Web Audio API nodes (called on first play)
     */
    connectWebAudio() {
      if (this.audioConnected) return;

      try {
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        this.sourceNode = this.audioContext.createMediaElementSource(this.audio);

        // Create gain node for volume
        this.gainNode = this.audioContext.createGain();

        // Create stereo panner for balance
        this.pannerNode = this.audioContext.createStereoPanner();

        // Create bass filter (low shelf)
        this.bassFilter = this.audioContext.createBiquadFilter();
        this.bassFilter.type = 'lowshelf';
        this.bassFilter.frequency.value = 200;
        this.bassFilter.gain.value = 0;

        // Create treble filter (high shelf)
        this.trebleFilter = this.audioContext.createBiquadFilter();
        this.trebleFilter.type = 'highshelf';
        this.trebleFilter.frequency.value = 3000;
        this.trebleFilter.gain.value = 0;

        // Connect: source -> bass -> treble -> panner -> gain -> destination
        this.sourceNode.connect(this.bassFilter);
        this.bassFilter.connect(this.trebleFilter);
        this.trebleFilter.connect(this.pannerNode);
        this.pannerNode.connect(this.gainNode);
        this.gainNode.connect(this.audioContext.destination);

        this.audioConnected = true;
        console.log('[Player] Web Audio API connected');

        // Apply saved EQ settings
        this.applyEQSettings();
      } catch (e) {
        console.warn('[Player] Web Audio API not available:', e);
      }
    }

    /**
     * Apply current EQ settings to Web Audio nodes
     */
    applyEQSettings() {
      if (this.gainNode) {
        this.gainNode.gain.value = this.eqValues.volume / 100;
      }
      if (this.pannerNode) {
        this.pannerNode.pan.value = (this.eqValues.balance - 50) / 50;
      }
      if (this.bassFilter) {
        this.bassFilter.gain.value = (this.eqValues.bass - 50) * 0.24;
      }
      if (this.trebleFilter) {
        this.trebleFilter.gain.value = (this.eqValues.treble - 50) * 0.24;
      }
    }

    /**
     * Load saved EQ settings from localStorage
     */
    loadSavedSettings() {
      try {
        const saved = localStorage.getItem('prompt-player-eq');
        if (saved) {
          const settings = JSON.parse(saved);
          this.eqValues = { ...this.eqValues, ...settings };
          console.log('[Player] Loaded saved EQ settings:', this.eqValues);
        }
      } catch (e) {
        console.warn('[Player] Could not load saved settings:', e);
      }
    }

    /**
     * Save EQ settings to localStorage
     */
    saveSettings() {
      try {
        localStorage.setItem('prompt-player-eq', JSON.stringify(this.eqValues));
      } catch (e) {
        console.warn('[Player] Could not save settings:', e);
      }
    }

    /**
     * Update EQ value from knob
     */
    updateEQ(name, value) {
      this.eqValues[name] = value;

      // Save to localStorage
      this.saveSettings();

      switch (name) {
        case 'volume':
          // 0-100 -> 0-1
          const vol = value / 100;
          if (this.gainNode) {
            this.gainNode.gain.value = vol;
          } else {
            this.audio.volume = vol;
          }
          this.volume = vol;
          break;

        case 'balance':
          // 0-100 -> -1 to 1 (50 = center)
          if (this.pannerNode) {
            this.pannerNode.pan.value = (value - 50) / 50;
          }
          break;

        case 'bass':
          // 0-100 -> -12dB to +12dB (50 = 0)
          if (this.bassFilter) {
            this.bassFilter.gain.value = (value - 50) * 0.24;
          }
          break;

        case 'treble':
          // 0-100 -> -12dB to +12dB (50 = 0)
          if (this.trebleFilter) {
            this.trebleFilter.gain.value = (value - 50) * 0.24;
          }
          break;
      }
    }

    /**
     * Set up knob drag events
     */
    setupKnobEvents() {
      const knobNames = ['volume', 'balance', 'bass', 'treble'];

      knobNames.forEach(name => {
        const container = this.elements[`${name}Knob`];
        const knobInner = this.elements[`${name}KnobInner`];
        if (!container || !knobInner) return;

        let isDragging = false;
        let startY = 0;
        let startRotation = 0;

        const handleStart = (e) => {
          e.preventDefault();
          isDragging = true;
          startY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;
          startRotation = container.knobData.rotation;
          container.classList.add('active');
        };

        const handleMove = (e) => {
          if (!isDragging) return;
          e.preventDefault();

          const currentY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;
          const deltaY = startY - currentY;
          let newRotation = startRotation + (deltaY * 1.5);

          // Clamp rotation to -135 to 135 degrees
          newRotation = Math.max(-135, Math.min(135, newRotation));

          container.knobData.rotation = newRotation;
          knobInner.style.transform = `rotate(${newRotation}deg)`;

          // Calculate and apply value
          const value = this.rotationToValue(newRotation, container.knobData.min, container.knobData.max);
          container.knobData.value = value;
          this.updateEQ(name, value);
        };

        const handleEnd = () => {
          isDragging = false;
          container.classList.remove('active');
        };

        // Mouse events
        container.addEventListener('mousedown', handleStart);
        document.addEventListener('mousemove', handleMove);
        document.addEventListener('mouseup', handleEnd);

        // Touch events
        container.addEventListener('touchstart', handleStart, { passive: false });
        document.addEventListener('touchmove', handleMove, { passive: false });
        document.addEventListener('touchend', handleEnd);

        // Double-click to reset to center
        container.addEventListener('dblclick', () => {
          const defaultVal = name === 'volume' ? 100 : 50;
          container.knobData.value = defaultVal;
          container.knobData.rotation = this.valueToRotation(defaultVal, container.knobData.min, container.knobData.max);
          knobInner.style.transform = `rotate(${container.knobData.rotation}deg)`;
          this.updateEQ(name, defaultVal);
        });
      });
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
      // Audio events
      this.audio.addEventListener('timeupdate', this.handleTimeUpdate);
      this.audio.addEventListener('ended', this.handleTrackEnd);
      this.audio.addEventListener('loadedmetadata', () => {
        console.log('[Player] Audio loaded, duration:', this.audio.duration);
        this.elements.totalTime.textContent = formatTime(this.audio.duration);
        this.updateTrackDuration(this.currentTrackIndex, this.audio.duration);
      });
      this.audio.addEventListener('error', (e) => {
        console.error('[Player] Audio error:', this.audio.error);
      });
      this.audio.addEventListener('canplay', () => {
        console.log('[Player] Audio can play');
      });

      // Toggle collapse/expand
      this.elements.toggle.addEventListener('click', () => this.toggleExpand());

      // Mobile minimize button
      if (this.elements.minimizeBtn) {
        this.elements.minimizeBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          this.toggleExpand();
        });
      }

      // Transport controls
      this.elements.playBtn.addEventListener('click', () => this.togglePlay());
      this.elements.prevBtn.addEventListener('click', () => this.prev());
      this.elements.nextBtn.addEventListener('click', () => this.next());

      // Knob controls
      this.setupKnobEvents();

      // Waveform interactions
      const waveformEl = this.elements.waveformContainer;
      waveformEl.addEventListener('click', this.handleWaveformClick);
      waveformEl.addEventListener('mousemove', this.handleWaveformMove);
      waveformEl.addEventListener('mousedown', this.handleWaveformDown);
      waveformEl.addEventListener('mouseleave', () => {
        this.elements.hoverTime.style.opacity = '0';
      });

      // Touch support
      waveformEl.addEventListener('touchstart', this.handleWaveformDown, { passive: false });
      waveformEl.addEventListener('touchmove', this.handleWaveformMove, { passive: false });
      waveformEl.addEventListener('touchend', this.handleWaveformUp);

      document.addEventListener('mouseup', this.handleWaveformUp);
      document.addEventListener('mousemove', (e) => {
        if (this.isDragging) this.handleWaveformMove(e);
      });

      // Track list
      this.elements.tracklist.addEventListener('click', (e) => {
        const track = e.target.closest('.membrane-track');
        if (track) {
          const index = parseInt(track.dataset.index, 10);
          this.loadTrack(index);
          this.play();
        }
      });

      // Keyboard support for track list
      this.elements.tracklist.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          const track = e.target.closest('.membrane-track');
          if (track) {
            e.preventDefault();
            const index = parseInt(track.dataset.index, 10);
            this.loadTrack(index);
            this.play();
          }
        }
      });

      // Resize handler
      window.addEventListener('resize', this.handleResize);

      // Keyboard shortcuts
      document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        switch (e.key) {
          case ' ':
            e.preventDefault();
            this.togglePlay();
            break;
          case 'ArrowLeft':
            this.seek(this.audio.currentTime - 5);
            break;
          case 'ArrowRight':
            this.seek(this.audio.currentTime + 5);
            break;
          case 'ArrowUp':
            e.preventDefault();
            this.setVolume(Math.min(1, this.volume + 0.1));
            break;
          case 'ArrowDown':
            e.preventDefault();
            this.setVolume(Math.max(0, this.volume - 0.1));
            break;
        }
      });

      // Terminal integration - listen for play/pause commands from terminal
      window.addEventListener('terminal:play', (e) => {
        const { index, name } = e.detail;
        if (typeof index === 'number' && index >= 0 && index < this.tracks.length) {
          this.loadTrack(index);
          this.play();
          console.log('[Player] Terminal requested track:', name);
        }
      });

      window.addEventListener('terminal:pause', () => {
        this.pause();
        console.log('[Player] Terminal requested pause');
      });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TRACK MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Load a track by index
     */
    async loadTrack(index) {
      if (index < 0 || index >= this.tracks.length) return;

      const wasPlaying = this.isPlaying;

      // Pause current
      this.pause();

      // Trigger glitch effect
      this.triggerGlitch();

      const prevIndex = this.currentTrackIndex;
      this.currentTrackIndex = index;
      const track = this.tracks[index];

      // Update UI
      this.updateActiveTrack(index);
      this.elements.nowPlayingTitle.textContent = track.title;

      // Special mode for Censored Shadow
      const isCensored = track.title === 'Censored Shadow';
      this.elements.player.classList.toggle('censored-mode', isCensored);

      // Update renderer color
      if (this.renderer) {
        this.renderer.setActiveColor(TRACK_COLORS[index] || TRACK_COLORS[0]);
      }

      // Load audio - check for full tracks mode
      const audioFile = (window.PROMPT_FULL_TRACKS_ENABLED && track.fullFile) ? track.fullFile : track.file;
      this.audio.src = audioFile;
      this.audio.load();

      // Show loading
      this.elements.loading.style.display = 'block';

      // Analyze waveform
      try {
        // Use different cache key for full tracks
        const cacheKey = window.PROMPT_FULL_TRACKS_ENABLED ? `full-${index}` : index;
        let waveformData = this.waveformData.get(cacheKey);

        if (!waveformData) {
          waveformData = await this.analyzer.analyze(audioFile);
          this.waveformData.set(cacheKey, waveformData);
        }

        if (this.renderer) {
          this.renderer.setWaveform(waveformData);
          this.renderer.setProgress(0);
        }
      } catch (error) {
        console.error('Failed to analyze waveform:', error);
      }

      // Hide loading
      this.elements.loading.style.display = 'none';

      // Reset progress
      this.updateProgress(0);

      // Emit event
      this.emit('trackchange', {
        index,
        prevIndex,
        track,
      });

      // Resume if was playing
      if (wasPlaying) {
        this.play();
      }
    }

    /**
     * Update active track in list
     */
    updateActiveTrack(index) {
      const tracks = this.elements.tracklist.querySelectorAll('.membrane-track');
      tracks.forEach((el, i) => {
        el.classList.toggle('active', i === index);
        el.style.color = i === index ? TRACK_COLORS[i] : '';
      });
    }

    /**
     * Update track duration in list
     */
    updateTrackDuration(index, duration) {
      const tracks = this.elements.tracklist.querySelectorAll('.membrane-track');
      const durationEl = tracks[index]?.querySelector('.membrane-track-duration');
      if (durationEl) {
        durationEl.textContent = formatTime(duration);
      }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PLAYBACK CONTROLS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Play current track
     */
    async play() {
      try {
        console.log('[Player] Play called, audio src:', this.audio.src);
        console.log('[Player] Audio volume:', this.audio.volume, 'muted:', this.audio.muted);
        console.log('[Player] Audio readyState:', this.audio.readyState);

        // Connect Web Audio API on first play
        this.connectWebAudio();

        await this.analyzer.init(); // Ensure audio context is running
        await this.audio.play();
        this.isPlaying = true;

        console.log('[Player] Audio playing, currentTime:', this.audio.currentTime);

        this.elements.playBtn.innerHTML = ICONS.pause;
        this.elements.playBtn.setAttribute('aria-label', 'Pause');

        if (this.renderer) {
          this.renderer.startAnimation();
        }

        this.updateMiniViz();
        this.emit('play', { track: this.tracks[this.currentTrackIndex] });
      } catch (error) {
        console.error('Playback failed:', error);
      }
    }

    /**
     * Pause playback
     */
    pause() {
      this.audio.pause();
      this.isPlaying = false;

      this.elements.playBtn.innerHTML = ICONS.play;
      this.elements.playBtn.setAttribute('aria-label', 'Play');

      if (this.renderer) {
        this.renderer.stopAnimation();
      }

      this.emit('pause', { track: this.tracks[this.currentTrackIndex] });
    }

    /**
     * Toggle play/pause
     */
    togglePlay() {
      if (this.isPlaying) {
        this.pause();
      } else {
        this.play();
      }
    }

    /**
     * Go to next track
     */
    next() {
      const nextIndex = (this.currentTrackIndex + 1) % this.tracks.length;
      this.loadTrack(nextIndex);
      if (this.isPlaying) this.play();
    }

    /**
     * Go to previous track
     */
    prev() {
      // If more than 3 seconds in, restart current track
      if (this.audio.currentTime > 3) {
        this.seek(0);
        return;
      }

      const prevIndex = (this.currentTrackIndex - 1 + this.tracks.length) % this.tracks.length;
      this.loadTrack(prevIndex);
      if (this.isPlaying) this.play();
    }

    /**
     * Seek to time
     */
    seek(time) {
      this.audio.currentTime = clamp(time, 0, this.audio.duration || 0);
    }

    /**
     * Set volume
     */
    setVolume(value) {
      this.volume = clamp(value, 0, 1);
      this.audio.volume = this.volume;
      this.elements.volumeSlider.value = this.volume;

      // Update icon
      if (this.volume === 0) {
        this.elements.volumeBtn.innerHTML = ICONS.volumeMute;
      } else if (this.volume < 0.5) {
        this.elements.volumeBtn.innerHTML = ICONS.volumeLow;
      } else {
        this.elements.volumeBtn.innerHTML = ICONS.volumeHigh;
      }
    }

    /**
     * Toggle mute
     */
    toggleMute() {
      if (this.audio.volume > 0) {
        this._lastVolume = this.volume;
        this.setVolume(0);
      } else {
        this.setVolume(this._lastVolume || 1);
      }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EVENT HANDLERS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Handle time update
     */
    handleTimeUpdate() {
      const progress = this.audio.currentTime / (this.audio.duration || 1);
      this.updateProgress(progress);

      this.elements.currentTime.textContent = formatTime(this.audio.currentTime);

      this.emit('timeupdate', {
        currentTime: this.audio.currentTime,
        duration: this.audio.duration,
        progress,
      });
    }

    /**
     * Handle track end
     */
    handleTrackEnd() {
      this.next();
    }

    /**
     * Handle waveform click
     */
    handleWaveformClick(e) {
      if (this.isDragging) return;
      const rect = this.elements.waveformContainer.getBoundingClientRect();
      const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
      const progress = x / rect.width;
      this.seek(progress * (this.audio.duration || 0));
    }

    /**
     * Handle waveform mouse move (hover preview)
     */
    handleWaveformMove(e) {
      const rect = this.elements.waveformContainer.getBoundingClientRect();
      const clientX = e.touches ? e.touches[0].clientX : e.clientX;
      const x = clientX - rect.left;
      const progress = clamp(x / rect.width, 0, 1);

      // Update hover time indicator
      const time = progress * (this.audio.duration || 0);
      this.elements.hoverTime.textContent = formatTime(time);
      this.elements.hoverTime.style.left = `${x}px`;
      this.elements.hoverTime.style.opacity = '1';

      // If dragging, seek
      if (this.isDragging) {
        e.preventDefault();
        this.seek(time);
      }
    }

    /**
     * Handle waveform mouse/touch down
     */
    handleWaveformDown(e) {
      this.isDragging = true;
      this.handleWaveformMove(e);
    }

    /**
     * Handle waveform mouse/touch up
     */
    handleWaveformUp() {
      this.isDragging = false;
    }

    /**
     * Handle resize
     */
    handleResize() {
      if (this.renderer) {
        this.renderer.resize();
        this.renderer.setWaveform(this.waveformData.get(this.currentTrackIndex));
      }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UI UPDATES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Update progress display
     */
    updateProgress(progress) {
      // Update cursor position
      this.elements.cursor.style.left = `${progress * 100}%`;

      // Update waveform fill
      if (this.renderer) {
        this.renderer.setProgress(progress);
      }
    }

    /**
     * Toggle expand/collapse
     */
    toggleExpand() {
      this.isExpanded = !this.isExpanded;
      this.elements.player.classList.toggle('expanded', this.isExpanded);
      this.elements.player.classList.toggle('collapsed', !this.isExpanded);

      // Update hint text
      if (this.elements.toggleHint) {
        this.elements.toggleHint.textContent = this.isExpanded ? 'tap to close' : 'tap to open';
      }

      // Resize renderer after animation
      if (this.isExpanded) {
        setTimeout(() => this.handleResize(), 400);
      }
    }

    /**
     * Trigger glitch effect
     */
    triggerGlitch() {
      this.elements.player.classList.add('glitching');
      setTimeout(() => {
        this.elements.player.classList.remove('glitching');
      }, GLITCH_DURATION);
    }

    /**
     * Update mini visualizer
     */
    updateMiniViz() {
      if (!this.isPlaying) return;

      const bars = this.elements.miniViz.querySelectorAll('.membrane-mini-bar');
      bars.forEach((bar) => {
        const height = 0.2 + Math.random() * 0.8;
        bar.style.transform = `scaleY(${height})`;
      });

      requestAnimationFrame(() => {
        setTimeout(() => this.updateMiniViz(), 100);
      });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EVENTS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Emit custom event
     */
    emit(eventName, detail = {}) {
      const event = new CustomEvent(`membrane:${eventName}`, {
        detail,
        bubbles: true,
      });
      this.container.dispatchEvent(event);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get current track info
     */
    getCurrentTrack() {
      return {
        index: this.currentTrackIndex,
        ...this.tracks[this.currentTrackIndex],
      };
    }

    /**
     * Get playback state
     */
    getState() {
      return {
        isPlaying: this.isPlaying,
        currentTime: this.audio.currentTime,
        duration: this.audio.duration,
        volume: this.volume,
        track: this.getCurrentTrack(),
      };
    }

    /**
     * Destroy player
     */
    destroy() {
      this.pause();
      this.audio.src = '';

      // Remove event listeners
      window.removeEventListener('resize', this.handleResize);

      // Remove DOM
      this.elements.player.remove();
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // EXPORT
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Initialize the Membrane Player
   *
   * @param {string|Element} containerSelector - CSS selector or DOM element
   * @param {Array} tracks - Array of track objects { title, file }
   * @returns {Object} Player API
   */
  function initPlayer(containerSelector, tracks) {
    const player = new MembranePlayer(containerSelector, tracks);

    // Return public API
    return {
      play: () => player.play(),
      pause: () => player.pause(),
      togglePlay: () => player.togglePlay(),
      next: () => player.next(),
      prev: () => player.prev(),
      seek: (time) => player.seek(time),
      setVolume: (vol) => player.setVolume(vol),
      loadTrack: (index) => player.loadTrack(index),
      getCurrentTrack: () => player.getCurrentTrack(),
      getState: () => player.getState(),
      expand: () => {
        player.isExpanded = true;
        player.elements.player.classList.add('expanded');
        player.elements.player.classList.remove('collapsed');
      },
      collapse: () => {
        player.isExpanded = false;
        player.elements.player.classList.add('collapsed');
        player.elements.player.classList.remove('expanded');
      },
      destroy: () => player.destroy(),
      on: (event, callback) => {
        player.container.addEventListener(`membrane:${event}`, (e) => callback(e.detail));
      },
      getAudioElement: () => player.audio,
    };
  }

  // Export to global scope
  global.initPlayer = initPlayer;
  global.MembranePlayer = MembranePlayer;

})(typeof window !== 'undefined' ? window : this);
