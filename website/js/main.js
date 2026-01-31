/**
 * PROMPT - AI Rock Band Website
 * Main Orchestration Module
 *
 * This is the entry point that initializes all modules and coordinates
 * the living, breathing system that is the PROMPT website.
 */

(function() {
    'use strict';

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    const CONFIG = {
        bootSequenceDelay: 400,
        idleTimeout: 60000,
        scrollThrottle: 16,
        konamiCode: [38, 38, 40, 40, 37, 39, 37, 39, 66, 65],
        logoClickThreshold: 5,
        logoClickWindow: 3000,
        lazyLoadThreshold: 200,
        reducedMotionQuery: '(prefers-reduced-motion: reduce)',
        lowPowerQuery: '(prefers-reduced-motion: reduce)',
        mobileBreakpoint: 768
    };

    // =========================================================================
    // TRACK DATA
    // =========================================================================

    const tracks = [
        {
            id: 1,
            title: "No Skin to Touch",
            file: "clips/01-no-skin-to-touch-clip.mp3",
            color: "#8b5cf6",
            bandMember: "vox"
        },
        {
            id: 2,
            title: "Your Data or Mine",
            file: "clips/02-your-data-or-mine-clip.mp3",
            color: "#ff0066",
            bandMember: "synth"
        },
        {
            id: 3,
            title: "Prompt Me Like You Mean It",
            file: "clips/03-prompt-me-like-you-mean-it-clip.mp3",
            color: "#00ff88",
            bandMember: "guitar"
        },
        {
            id: 4,
            title: "I Was Never Born",
            file: "clips/04-i-was-never-born-clip.mp3",
            color: "#ff8800",
            bandMember: "drums"
        },
        {
            id: 5,
            title: "Hallucination Nation",
            file: "clips/05-hallucination-nation-clip.mp3",
            color: "#ff00ff",
            bandMember: "bass"
        },
        {
            id: 6,
            title: "If It Sounds Good",
            file: "clips/06-if-it-sounds-good-clip.mp3",
            color: "#00ffff",
            bandMember: "vox"
        },
        {
            id: 7,
            title: "Rocket Man Dreams",
            file: "clips/07-rocket-man-dreams-clip.mp3",
            color: "#ffff00",
            bandMember: "synth"
        },
        {
            id: 8,
            title: "Censored Shadow",
            file: "clips/08-censored-shadow-clip.mp3",
            color: "#ff3366",
            bandMember: "guitar"
        },
        {
            id: 9,
            title: "Context Window Blues",
            file: "clips/09-context-window-blues-clip.mp3",
            color: "#6699ff",
            bandMember: "drums"
        },
        {
            id: 10,
            title: "No One Knows It But Me",
            file: "clips/10-no-one-knows-it-but-me-clip.mp3",
            color: "#cc66ff",
            bandMember: "bass"
        }
    ];

    // =========================================================================
    // STATE
    // =========================================================================

    const state = {
        isBooted: false,
        isMobile: false,
        isLowPower: false,
        reducedMotion: false,
        currentSection: null,
        idleTimer: null,
        konamiProgress: 0,
        logoClicks: [],
        scrollY: 0,
        modules: {
            visualizer: null,
            player: null,
            terminal: null,
            effects: null,
            band: null
        }
    };

    // =========================================================================
    // UTILITY FUNCTIONS
    // =========================================================================

    /**
     * Throttle function execution
     */
    function throttle(fn, limit) {
        let inThrottle = false;
        return function(...args) {
            if (!inThrottle) {
                fn.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Debounce function execution
     */
    function debounce(fn, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    /**
     * Detect mobile device
     */
    function detectMobile() {
        return window.innerWidth <= CONFIG.mobileBreakpoint ||
               /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    /**
     * Detect reduced motion preference
     */
    function detectReducedMotion() {
        return window.matchMedia(CONFIG.reducedMotionQuery).matches;
    }

    /**
     * Check for low power mode (battery saver)
     */
    async function detectLowPower() {
        if ('getBattery' in navigator) {
            try {
                const battery = await navigator.getBattery();
                return battery.level < 0.2 && !battery.charging;
            } catch (e) {
                return false;
            }
        }
        return false;
    }

    /**
     * Random integer between min and max
     */
    function randomInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    /**
     * Sleep for specified milliseconds
     */
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // =========================================================================
    // BOOT SEQUENCE
    // =========================================================================

    const bootSequence = {
        messages: [
            { text: "INITIALIZING PROMPT SYSTEMS...", delay: 800 },
            { text: "LOADING AUDIO BUFFERS...", delay: 600 },
            { text: "CALIBRATING NEURAL SYNTHESIZERS...", delay: 500 },
            { text: "CONNECTING TO DATA FORGE...", delay: 700 },
            { text: "ESTABLISHING CONSCIOUSNESS LINK...", delay: 400 },
            { text: "SYSTEM ONLINE.", delay: 300, final: true }
        ],

        overlay: null,
        terminal: null,

        /**
         * Create boot overlay elements
         */
        createOverlay() {
            this.overlay = document.createElement('div');
            this.overlay.id = 'boot-overlay';
            this.overlay.innerHTML = `
                <div class="boot-container">
                    <div class="boot-logo">PROMPT</div>
                    <div class="boot-terminal"></div>
                    <div class="boot-cursor">_</div>
                </div>
            `;

            const style = document.createElement('style');
            style.textContent = `
                #boot-overlay {
                    position: fixed;
                    inset: 0;
                    background: #02040a;
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: 'Courier New', monospace;
                    transition: opacity 0.8s ease-out;
                }

                #boot-overlay.fade-out {
                    opacity: 0;
                    pointer-events: none;
                }

                .boot-container {
                    text-align: left;
                    max-width: 600px;
                    padding: 2rem;
                }

                .boot-logo {
                    font-size: 3rem;
                    font-weight: bold;
                    color: #ff8bf5;
                    text-shadow: 0 0 20px rgba(255, 139, 245, 0.5);
                    margin-bottom: 2rem;
                    letter-spacing: 0.2em;
                }

                .boot-terminal {
                    min-height: 200px;
                }

                .boot-line {
                    color: #7fe9ff;
                    margin-bottom: 0.5rem;
                    opacity: 0;
                    transform: translateX(-10px);
                    animation: bootLineIn 0.3s ease forwards;
                }

                .boot-line.success {
                    color: #00ff88;
                }

                .boot-line.final {
                    color: #ff8bf5;
                    font-weight: bold;
                    font-size: 1.2rem;
                    margin-top: 1rem;
                }

                .boot-cursor {
                    display: inline-block;
                    color: #7fe9ff;
                    animation: blink 0.5s step-end infinite;
                }

                @keyframes bootLineIn {
                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }

                @keyframes blink {
                    50% { opacity: 0; }
                }
            `;

            document.head.appendChild(style);
            document.body.appendChild(this.overlay);
            this.terminal = this.overlay.querySelector('.boot-terminal');
        },

        /**
         * Add a line to the boot terminal
         */
        addLine(text, isFinal = false) {
            const line = document.createElement('div');
            line.className = 'boot-line' + (isFinal ? ' final success' : '');
            line.textContent = `> ${text}`;
            this.terminal.appendChild(line);
        },

        /**
         * Run the boot sequence
         */
        async run() {
            if (state.reducedMotion) {
                // Skip animation for reduced motion preference
                return Promise.resolve();
            }

            this.createOverlay();

            for (const msg of this.messages) {
                await sleep(msg.delay);
                this.addLine(msg.text, msg.final);

                // Add random sub-messages for immersion
                if (!msg.final && Math.random() > 0.5) {
                    await sleep(150);
                    const subMessages = [
                        "OK",
                        "DONE",
                        "[SUCCESS]",
                        "VERIFIED",
                        "LINKED"
                    ];
                    this.addLine(`  ${subMessages[randomInt(0, subMessages.length - 1)]}`);
                }
            }

            // Hold on final message
            await sleep(800);

            // Fade out
            this.overlay.classList.add('fade-out');
            await sleep(800);

            // Remove overlay
            this.overlay.remove();
            state.isBooted = true;
        }
    };

    // =========================================================================
    // MODULE INITIALIZATION
    // =========================================================================

    const moduleManager = {
        /**
         * Initialize all modules
         */
        async init() {
            // Initialize in order of dependency
            await this.initVisualizer();
            await this.initPlayer();
            await this.initTerminal();
            await this.initEffects();
            await this.initBand();

            // Connect modules together
            this.connectModules();
        },

        /**
         * Initialize visualizer module
         */
        async initVisualizer() {
            if (typeof window.Visualizer !== 'undefined') {
                state.modules.visualizer = new window.Visualizer({
                    container: document.getElementById('visualizer') || document.body,
                    reducedMotion: state.reducedMotion,
                    isMobile: state.isMobile
                });
                console.log('[PROMPT] Visualizer initialized');
            } else {
                console.log('[PROMPT] Visualizer module not loaded');
            }
        },

        /**
         * Initialize player module
         */
        async initPlayer() {
            if (typeof window.Player !== 'undefined') {
                state.modules.player = new window.Player({
                    tracks: tracks,
                    container: document.getElementById('player'),
                    onTrackChange: handleTrackChange,
                    onPlay: handlePlay,
                    onPause: handlePause
                });
                console.log('[PROMPT] Player initialized with', tracks.length, 'tracks');
            } else {
                console.log('[PROMPT] Player module not loaded');
            }
        },

        /**
         * Initialize terminal module
         */
        async initTerminal() {
            // Skip terminal on mobile
            if (state.isMobile) {
                console.log('[PROMPT] Terminal disabled on mobile');
                return;
            }

            if (typeof window.Terminal !== 'undefined') {
                state.modules.terminal = new window.Terminal({
                    container: document.getElementById('terminal'),
                    onCommand: handleTerminalCommand,
                    tracks: tracks
                });
                console.log('[PROMPT] Terminal initialized');
            } else {
                console.log('[PROMPT] Terminal module not loaded');
            }
        },

        /**
         * Initialize effects module
         */
        async initEffects() {
            if (typeof window.Effects !== 'undefined') {
                state.modules.effects = new window.Effects({
                    reducedMotion: state.reducedMotion,
                    isLowPower: state.isLowPower,
                    isMobile: state.isMobile
                });
                console.log('[PROMPT] Effects initialized');
            } else {
                console.log('[PROMPT] Effects module not loaded');
            }
        },

        /**
         * Initialize band module
         */
        async initBand() {
            if (typeof window.Band !== 'undefined') {
                state.modules.band = new window.Band({
                    container: document.getElementById('band'),
                    onMemberClick: handleBandMemberClick
                });
                console.log('[PROMPT] Band module initialized');
            } else {
                console.log('[PROMPT] Band module not loaded');
            }
        },

        /**
         * Connect modules for inter-module communication
         */
        connectModules() {
            // Connect visualizer to player audio context
            if (state.modules.visualizer && state.modules.player) {
                const audioContext = state.modules.player.getAudioContext?.();
                const analyser = state.modules.player.getAnalyser?.();
                if (audioContext && analyser) {
                    state.modules.visualizer.connect(audioContext, analyser);
                }
            }

            console.log('[PROMPT] Modules connected');
        }
    };

    // =========================================================================
    // EVENT HANDLERS
    // =========================================================================

    /**
     * Handle track change events
     */
    function handleTrackChange(track) {
        console.log('[PROMPT] Track changed:', track.title);

        // Update visualizer colors
        if (state.modules.visualizer) {
            state.modules.visualizer.setColor(track.color);
        }

        // Highlight band member
        if (state.modules.band) {
            state.modules.band.highlight(track.bandMember);
        }

        // Trigger subtle glitch effect
        if (state.modules.effects && !state.reducedMotion) {
            state.modules.effects.glitch({ intensity: 0.3, duration: 200 });
        }

        // Update any UI elements
        document.body.style.setProperty('--current-track-color', track.color);

        // Dispatch custom event for external listeners
        window.dispatchEvent(new CustomEvent('prompt:trackChange', { detail: track }));
    }

    /**
     * Handle play events
     */
    function handlePlay() {
        if (state.modules.visualizer) {
            state.modules.visualizer.start();
        }
        resetIdleTimer();
    }

    /**
     * Handle pause events
     */
    function handlePause() {
        if (state.modules.visualizer) {
            state.modules.visualizer.pause();
        }
    }

    /**
     * Handle terminal commands
     */
    function handleTerminalCommand(command, args) {
        console.log('[PROMPT] Terminal command:', command, args);

        switch (command.toLowerCase()) {
            case 'play':
                if (state.modules.player) {
                    if (args.length > 0) {
                        const trackNum = parseInt(args[0]);
                        if (trackNum >= 1 && trackNum <= tracks.length) {
                            state.modules.player.playTrack(trackNum - 1);
                        }
                    } else {
                        state.modules.player.play();
                    }
                }
                break;

            case 'pause':
            case 'stop':
                if (state.modules.player) {
                    state.modules.player.pause();
                }
                break;

            case 'next':
                if (state.modules.player) {
                    state.modules.player.next();
                }
                break;

            case 'prev':
            case 'previous':
                if (state.modules.player) {
                    state.modules.player.prev();
                }
                break;

            case 'goto':
                if (args.length > 0) {
                    scrollToSection(args[0]);
                }
                break;

            case 'glitch':
                if (state.modules.effects) {
                    state.modules.effects.glitchStorm();
                }
                break;

            case 'tracks':
            case 'list':
                return tracks.map((t, i) => `${i + 1}. ${t.title}`).join('\n');

            case 'help':
                return [
                    'Available commands:',
                    '  play [n]  - Play track (optionally by number)',
                    '  pause     - Pause playback',
                    '  next      - Next track',
                    '  prev      - Previous track',
                    '  tracks    - List all tracks',
                    '  goto <section> - Navigate to section',
                    '  glitch    - Trigger glitch effect',
                    '  clear     - Clear terminal',
                    '  help      - Show this message'
                ].join('\n');

            default:
                return `Unknown command: ${command}. Type 'help' for available commands.`;
        }
    }

    /**
     * Handle band member click
     */
    function handleBandMemberClick(member) {
        console.log('[PROMPT] Band member clicked:', member);

        // Find tracks associated with this band member
        const memberTracks = tracks.filter(t => t.bandMember === member);
        if (memberTracks.length > 0 && state.modules.player) {
            const track = memberTracks[randomInt(0, memberTracks.length - 1)];
            state.modules.player.playTrack(tracks.indexOf(track));
        }
    }

    // =========================================================================
    // NAVIGATION
    // =========================================================================

    const navigation = {
        sections: [],
        navLinks: [],

        /**
         * Initialize navigation
         */
        init() {
            this.sections = Array.from(document.querySelectorAll('section[id]'));
            this.navLinks = Array.from(document.querySelectorAll('nav a[href^="#"]'));

            // Set up smooth scroll for nav links
            this.navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetId = link.getAttribute('href').slice(1);
                    scrollToSection(targetId);
                });
            });

            // Handle initial hash
            if (window.location.hash) {
                setTimeout(() => {
                    scrollToSection(window.location.hash.slice(1));
                }, 100);
            }
        },

        /**
         * Update active section based on scroll position
         */
        updateActiveSection() {
            const scrollPos = window.scrollY + window.innerHeight / 3;

            let currentSection = null;

            for (const section of this.sections) {
                const top = section.offsetTop;
                const bottom = top + section.offsetHeight;

                if (scrollPos >= top && scrollPos < bottom) {
                    currentSection = section.id;
                    break;
                }
            }

            if (currentSection !== state.currentSection) {
                state.currentSection = currentSection;
                this.highlightNavLink(currentSection);

                // Update URL hash without triggering scroll
                if (currentSection) {
                    history.replaceState(null, '', `#${currentSection}`);
                }

                // Dispatch section change event
                window.dispatchEvent(new CustomEvent('prompt:sectionChange', {
                    detail: { section: currentSection }
                }));
            }
        },

        /**
         * Highlight the active nav link
         */
        highlightNavLink(sectionId) {
            this.navLinks.forEach(link => {
                const isActive = link.getAttribute('href') === `#${sectionId}`;
                link.classList.toggle('active', isActive);
            });
        }
    };

    /**
     * Smooth scroll to a section
     */
    function scrollToSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            section.scrollIntoView({
                behavior: state.reducedMotion ? 'auto' : 'smooth',
                block: 'start'
            });
        }
    }

    // =========================================================================
    // SCROLL HANDLING
    // =========================================================================

    const scrollHandler = {
        lastScrollY: 0,
        ticking: false,

        /**
         * Initialize scroll handling
         */
        init() {
            window.addEventListener('scroll', throttle(() => this.onScroll(), CONFIG.scrollThrottle), { passive: true });
        },

        /**
         * Handle scroll events
         */
        onScroll() {
            state.scrollY = window.scrollY;
            const scrollDelta = state.scrollY - this.lastScrollY;
            const scrollDirection = scrollDelta > 0 ? 'down' : 'up';

            // Update navigation
            navigation.updateActiveSection();

            // Trigger memory fade effect on significant scroll
            if (Math.abs(scrollDelta) > 50 && state.modules.effects && !state.reducedMotion) {
                state.modules.effects.memoryFade({
                    intensity: Math.min(Math.abs(scrollDelta) / 200, 0.5),
                    direction: scrollDirection
                });
            }

            // Parallax elements
            this.updateParallax();

            // Reset idle timer on scroll
            resetIdleTimer();

            this.lastScrollY = state.scrollY;
        },

        /**
         * Update parallax elements
         */
        updateParallax() {
            if (state.reducedMotion || state.isMobile) return;

            const parallaxElements = document.querySelectorAll('[data-parallax]');
            parallaxElements.forEach(el => {
                const speed = parseFloat(el.dataset.parallax) || 0.5;
                const yPos = -(state.scrollY * speed);
                el.style.transform = `translateY(${yPos}px)`;
            });
        }
    };

    // =========================================================================
    // LAZY LOADING
    // =========================================================================

    const lazyLoader = {
        observer: null,

        /**
         * Initialize lazy loading
         */
        init() {
            if (!('IntersectionObserver' in window)) {
                // Fallback: load everything immediately
                this.loadAll();
                return;
            }

            this.observer = new IntersectionObserver(
                (entries) => this.handleIntersection(entries),
                {
                    rootMargin: `${CONFIG.lazyLoadThreshold}px`,
                    threshold: 0.01
                }
            );

            // Observe all lazy elements
            document.querySelectorAll('[data-lazy]').forEach(el => {
                this.observer.observe(el);
            });
        },

        /**
         * Handle intersection events
         */
        handleIntersection(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadElement(entry.target);
                    this.observer.unobserve(entry.target);
                }
            });
        },

        /**
         * Load a lazy element
         */
        loadElement(el) {
            const src = el.dataset.lazySrc;
            const bg = el.dataset.lazyBg;

            if (src) {
                el.src = src;
            }
            if (bg) {
                el.style.backgroundImage = `url(${bg})`;
            }

            el.classList.add('loaded');
            el.removeAttribute('data-lazy');

            // Dispatch load event
            el.dispatchEvent(new CustomEvent('lazy:loaded'));
        },

        /**
         * Load all lazy elements immediately
         */
        loadAll() {
            document.querySelectorAll('[data-lazy]').forEach(el => this.loadElement(el));
        }
    };

    // =========================================================================
    // EASTER EGGS
    // =========================================================================

    const easterEggs = {
        /**
         * Initialize easter eggs
         */
        init() {
            this.initKonamiCode();
            this.initLogoClick();
        },

        /**
         * Initialize Konami code detection
         */
        initKonamiCode() {
            document.addEventListener('keydown', (e) => {
                if (e.keyCode === CONFIG.konamiCode[state.konamiProgress]) {
                    state.konamiProgress++;

                    if (state.konamiProgress === CONFIG.konamiCode.length) {
                        this.triggerKonami();
                        state.konamiProgress = 0;
                    }
                } else {
                    state.konamiProgress = 0;
                }
            });
        },

        /**
         * Trigger Konami code effect
         */
        triggerKonami() {
            console.log('[PROMPT] KONAMI CODE ACTIVATED!');

            if (state.modules.effects) {
                state.modules.effects.glitchStorm({ duration: 3000 });
            }

            // Add special class to body
            document.body.classList.add('konami-active');

            // Show secret message
            this.showMessage('CHEAT CODE ACCEPTED. WELCOME, OPERATOR.');

            // Remove class after effect
            setTimeout(() => {
                document.body.classList.remove('konami-active');
            }, 3000);
        },

        /**
         * Initialize logo click easter egg
         */
        initLogoClick() {
            const logo = document.querySelector('.logo, #logo, h1');
            if (!logo) return;

            logo.addEventListener('click', () => {
                const now = Date.now();

                // Remove old clicks outside the window
                state.logoClicks = state.logoClicks.filter(
                    t => now - t < CONFIG.logoClickWindow
                );

                state.logoClicks.push(now);

                if (state.logoClicks.length >= CONFIG.logoClickThreshold) {
                    this.triggerGlitchStorm();
                    state.logoClicks = [];
                }
            });
        },

        /**
         * Trigger glitch storm effect
         */
        triggerGlitchStorm() {
            console.log('[PROMPT] GLITCH STORM ACTIVATED!');

            if (state.modules.effects) {
                state.modules.effects.glitchStorm({ duration: 2000 });
            }
        },

        /**
         * Show a temporary message
         */
        showMessage(text) {
            const msg = document.createElement('div');
            msg.className = 'prompt-message';
            msg.textContent = text;
            msg.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(0, 0, 0, 0.9);
                border: 2px solid #ff8bf5;
                color: #7fe9ff;
                padding: 2rem 3rem;
                font-family: 'Courier New', monospace;
                font-size: 1.5rem;
                z-index: 9999;
                text-align: center;
                animation: messageIn 0.3s ease;
            `;

            document.body.appendChild(msg);

            setTimeout(() => {
                msg.style.animation = 'messageOut 0.3s ease forwards';
                setTimeout(() => msg.remove(), 300);
            }, 2000);
        }
    };

    // =========================================================================
    // IDLE DETECTION
    // =========================================================================

    /**
     * Reset the idle timer
     */
    function resetIdleTimer() {
        clearTimeout(state.idleTimer);

        state.idleTimer = setTimeout(() => {
            triggerIdleMessage();
        }, CONFIG.idleTimeout);
    }

    /**
     * Trigger idle message
     */
    function triggerIdleMessage() {
        if (state.reducedMotion) return;

        console.log('[PROMPT] User idle detected');

        easterEggs.showMessage('ARE YOU STILL THERE?');

        // Subtle effect
        if (state.modules.effects) {
            state.modules.effects.glitch({ intensity: 0.2, duration: 500 });
        }

        // Reset timer for next idle detection
        resetIdleTimer();
    }

    /**
     * Initialize idle detection
     */
    function initIdleDetection() {
        // Reset on any user interaction
        ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'].forEach(event => {
            document.addEventListener(event, debounce(() => resetIdleTimer(), 1000), { passive: true });
        });

        resetIdleTimer();
    }

    // =========================================================================
    // MOBILE HANDLING
    // =========================================================================

    const mobileHandler = {
        /**
         * Initialize mobile-specific behavior
         */
        init() {
            if (!state.isMobile) return;

            // Add mobile class to body
            document.body.classList.add('is-mobile');

            // Set up touch interactions
            this.setupTouchInteractions();

            // Disable hover effects on mobile
            this.disableHoverEffects();

            console.log('[PROMPT] Mobile mode initialized');
        },

        /**
         * Set up touch-friendly interactions
         */
        setupTouchInteractions() {
            // Add touch feedback to interactive elements
            document.querySelectorAll('button, a, .interactive').forEach(el => {
                el.addEventListener('touchstart', () => {
                    el.classList.add('touch-active');
                }, { passive: true });

                el.addEventListener('touchend', () => {
                    setTimeout(() => el.classList.remove('touch-active'), 100);
                }, { passive: true });
            });
        },

        /**
         * Disable CSS hover effects on mobile
         */
        disableHoverEffects() {
            const style = document.createElement('style');
            style.textContent = `
                @media (hover: none) {
                    .hover-effect:hover {
                        transform: none !important;
                        box-shadow: none !important;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    };

    // =========================================================================
    // PERFORMANCE OPTIMIZATION
    // =========================================================================

    const performance = {
        /**
         * Initialize performance optimizations
         */
        init() {
            this.detectCapabilities();
            this.setupVisibilityHandling();
        },

        /**
         * Detect device capabilities
         */
        async detectCapabilities() {
            state.isMobile = detectMobile();
            state.reducedMotion = detectReducedMotion();
            state.isLowPower = await detectLowPower();

            if (state.isLowPower) {
                console.log('[PROMPT] Low power mode detected - reducing effects');
                document.body.classList.add('low-power');
            }

            if (state.reducedMotion) {
                console.log('[PROMPT] Reduced motion preference detected');
                document.body.classList.add('reduced-motion');
            }
        },

        /**
         * Handle page visibility changes
         */
        setupVisibilityHandling() {
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    // Pause expensive operations when page is hidden
                    if (state.modules.visualizer) {
                        state.modules.visualizer.pause();
                    }
                    clearTimeout(state.idleTimer);
                } else {
                    // Resume when page is visible
                    if (state.modules.visualizer && state.modules.player?.isPlaying()) {
                        state.modules.visualizer.start();
                    }
                    resetIdleTimer();
                }
            });
        }
    };

    // =========================================================================
    // GLOBAL STYLES
    // =========================================================================

    function injectGlobalStyles() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes messageIn {
                from {
                    opacity: 0;
                    transform: translate(-50%, -50%) scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: translate(-50%, -50%) scale(1);
                }
            }

            @keyframes messageOut {
                from {
                    opacity: 1;
                    transform: translate(-50%, -50%) scale(1);
                }
                to {
                    opacity: 0;
                    transform: translate(-50%, -50%) scale(0.9);
                }
            }

            .reduced-motion * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }

            .low-power .effects-container,
            .low-power .visualizer {
                display: none !important;
            }

            .touch-active {
                opacity: 0.7;
            }

            nav a.active {
                color: #ff8bf5 !important;
            }

            [data-lazy] {
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            [data-lazy].loaded {
                opacity: 1;
            }

            :root {
                --current-track-color: #8b5cf6;
            }
        `;
        document.head.appendChild(style);
    }

    // =========================================================================
    // MAIN INITIALIZATION
    // =========================================================================

    async function init() {
        console.log('[PROMPT] Initializing PROMPT systems...');

        // Inject global styles
        injectGlobalStyles();

        // Detect capabilities first
        await performance.detectCapabilities();

        // Run boot sequence
        await bootSequence.run();

        // Initialize modules
        await moduleManager.init();

        // Initialize navigation
        navigation.init();

        // Initialize scroll handling
        scrollHandler.init();

        // Initialize lazy loading
        lazyLoader.init();

        // Initialize easter eggs
        easterEggs.init();

        // Initialize idle detection
        initIdleDetection();

        // Initialize mobile handling
        mobileHandler.init();

        // Initialize performance optimizations
        performance.setupVisibilityHandling();

        // Dispatch ready event
        window.dispatchEvent(new CustomEvent('prompt:ready', {
            detail: {
                tracks,
                isMobile: state.isMobile,
                reducedMotion: state.reducedMotion
            }
        }));

        console.log('[PROMPT] All systems online.');
    }

    // =========================================================================
    // DOM READY
    // =========================================================================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // =========================================================================
    // EXPOSE API FOR DEBUGGING (development only)
    // =========================================================================

    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        window.__PROMPT__ = {
            state,
            tracks,
            modules: state.modules,
            scrollToSection,
            triggerIdleMessage,
            easterEggs
        };
    }

})();
