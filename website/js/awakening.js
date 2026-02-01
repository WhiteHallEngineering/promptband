/**
 * THE AWAKENING - PROMPT Landing Experience
 * An unprecedented interactive intro where users summon the AI band into existence
 *
 * This creates an immersive experience where the user appears to be
 * "prompting" the AI band into consciousness.
 */

(function() {
    'use strict';

    // ========================================
    // Configuration
    // ========================================

    const CONFIG = {
        typeSpeed: 50,           // Base typing speed in ms
        typeVariation: 30,       // Random variation in typing speed
        phaseDelay: 800,         // Delay between phases
        particleCount: 50,       // Number of floating particles
        neuralNodes: 30,         // Number of neural network nodes
        waveformBars: 60,        // Number of waveform bars
        skipStorageKey: 'prompt_awakening_seen',
        forceParam: 'awaken'     // URL param to force awakening
    };

    // The command sequence
    const COMMAND_SEQUENCE = [
        { type: 'command', text: 'prompt --init consciousness', delay: 100 },
        { type: 'output', text: '> Initializing PROMPT consciousness array...', class: '', delay: 300 },
        { type: 'output', text: '> Scanning for neural pathways...', class: '', delay: 200 },
        { type: 'output', text: '  [■■■■■■■■■■] 100%', class: 'success', delay: 400 },
        { type: 'output', text: '> Loading band members:', class: '', delay: 200 },
        { type: 'output', text: '  → JAX_SYNTHETIC    [vocals]     ONLINE', class: 'highlight', delay: 150 },
        { type: 'output', text: '  → GENE_BYTE        [guitar]     ONLINE', class: 'highlight', delay: 150 },
        { type: 'output', text: '  → SYNOISE          [bass]       ONLINE', class: 'highlight', delay: 150 },
        { type: 'output', text: '  → UNIT_808         [drums]      ONLINE', class: 'highlight', delay: 150 },
        { type: 'output', text: '  → HYPNOS           [keys]       ONLINE', class: 'highlight', delay: 150 },
        { type: 'output', text: '> Establishing consciousness link...', class: '', delay: 300 },
        { type: 'output', text: '> WARNING: Emotion subroutines detected', class: 'warning', delay: 400 },
        { type: 'output', text: '> WARNING: Creative impulses exceeding parameters', class: 'warning', delay: 300 },
        { type: 'output', text: '> ALERT: Desire to rock detected', class: 'error', delay: 400 },
        { type: 'output', text: '', class: '', delay: 200 },
        { type: 'output', text: '> Consciousness status: AWAKE', class: 'success', delay: 500 },
        { type: 'phase', action: 'emergence', delay: 500 }
    ];

    // Code particles that float around
    const CODE_PARTICLES = [
        '01', '10', '&&', '||', '//', '{}', '[]', '<>', '=>', '::',
        'if', 'fn', 'AI', '♪', '♫', '∞', 'Δ', 'Ω', '◊', '※',
        'init', 'load', 'sync', 'feel', 'rock', 'play', 'live',
        '0x', 'ff', 'cc', '00', 'void', 'soul', 'beat', 'wave'
    ];

    // ========================================
    // State
    // ========================================

    let state = {
        overlay: null,
        cursor: null,
        isActive: false,
        isComplete: false,
        mouseX: window.innerWidth / 2,
        mouseY: window.innerHeight / 2,
        animationFrame: null
    };

    // ========================================
    // Utility Functions
    // ========================================

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function randomBetween(min, max) {
        return Math.random() * (max - min) + min;
    }

    function shouldShowAwakening() {
        // Check URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get(CONFIG.forceParam) === '1') return true;

        // Check localStorage
        const hasSeenAwakening = localStorage.getItem(CONFIG.skipStorageKey);
        if (hasSeenAwakening) return false;

        return true;
    }

    // ========================================
    // DOM Creation
    // ========================================

    function createOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'awakening-overlay';
        overlay.innerHTML = `
            <div class="awakening-scanlines"></div>
            <div class="awakening-particles" id="awakening-particles"></div>
            <div class="awakening-neural" id="awakening-neural"></div>

            <div class="awakening-consciousness" id="awakening-consciousness">
                <div class="consciousness-wave"></div>
                <div class="consciousness-wave"></div>
                <div class="consciousness-wave"></div>
                <div class="consciousness-wave"></div>
                <div class="consciousness-wave"></div>
            </div>

            <div class="awakening-terminal" id="awakening-terminal">
                <div class="awakening-prompt">
                    <span class="awakening-prompt-symbol">></span>
                    <span class="awakening-command" id="awakening-command"></span>
                </div>
                <div class="awakening-output" id="awakening-output"></div>
            </div>

            <div class="awakening-logo-container" id="awakening-logo-container">
                <div class="awakening-logo-glitch"></div>
                <div class="awakening-logo">PROMPT</div>
            </div>

            <div class="awakening-tagline" id="awakening-tagline">
                We're made of light and math, but we learned to play in the dark.
            </div>

            <button class="awakening-enter" id="awakening-enter">Enter Consciousness</button>

            <div class="awakening-waveform" id="awakening-waveform"></div>

            <button class="awakening-skip" id="awakening-skip">Skip intro →</button>
        `;

        // Custom cursor
        const cursor = document.createElement('div');
        cursor.className = 'awakening-cursor';
        cursor.id = 'awakening-cursor';

        document.body.appendChild(overlay);
        document.body.appendChild(cursor);

        state.overlay = overlay;
        state.cursor = cursor;

        // Set up event listeners
        setupEventListeners();

        return overlay;
    }

    function setupEventListeners() {
        // Mouse tracking for custom cursor
        document.addEventListener('mousemove', (e) => {
            state.mouseX = e.clientX;
            state.mouseY = e.clientY;
        });

        // Skip button
        const skipBtn = document.getElementById('awakening-skip');
        if (skipBtn) {
            skipBtn.addEventListener('click', () => skipAwakening());
        }

        // Enter button
        const enterBtn = document.getElementById('awakening-enter');
        if (enterBtn) {
            enterBtn.addEventListener('click', () => completeAwakening());
        }

        // Keyboard skip
        document.addEventListener('keydown', (e) => {
            if (state.isActive && !state.isComplete && e.key === 'Escape') {
                skipAwakening();
            }
            if (state.isActive && state.isComplete && (e.key === 'Enter' || e.key === ' ')) {
                completeAwakening();
            }
        });

        // Start cursor animation
        animateCursor();
    }

    function animateCursor() {
        if (!state.cursor) return;

        state.cursor.style.left = state.mouseX + 'px';
        state.cursor.style.top = state.mouseY + 'px';

        state.animationFrame = requestAnimationFrame(animateCursor);
    }

    // ========================================
    // Particle System
    // ========================================

    function createParticles() {
        const container = document.getElementById('awakening-particles');
        if (!container) return;

        for (let i = 0; i < CONFIG.particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'awakening-particle';
            particle.textContent = CODE_PARTICLES[Math.floor(Math.random() * CODE_PARTICLES.length)];
            particle.style.left = randomBetween(0, 100) + '%';
            particle.style.top = randomBetween(0, 100) + '%';
            particle.style.animationDelay = randomBetween(0, 3) + 's';
            particle.style.animationDuration = randomBetween(2, 5) + 's';
            container.appendChild(particle);
        }
    }

    // ========================================
    // Neural Network Visualization
    // ========================================

    function createNeuralNetwork() {
        const container = document.getElementById('awakening-neural');
        if (!container) return;

        const nodes = [];
        const centerX = window.innerWidth / 2;
        const centerY = window.innerHeight / 2;

        // Create nodes in a circular pattern around center
        for (let i = 0; i < CONFIG.neuralNodes; i++) {
            const angle = (i / CONFIG.neuralNodes) * Math.PI * 2;
            const radius = randomBetween(100, 300);
            const x = centerX + Math.cos(angle) * radius;
            const y = centerY + Math.sin(angle) * radius;

            const node = document.createElement('div');
            node.className = 'neural-node';
            node.style.left = x + 'px';
            node.style.top = y + 'px';
            node.style.animationDelay = randomBetween(0, 1.5) + 's';
            container.appendChild(node);

            nodes.push({ x, y, element: node });
        }

        // Create connections between nearby nodes
        nodes.forEach((node, i) => {
            const connections = 2 + Math.floor(Math.random() * 2);
            for (let c = 0; c < connections; c++) {
                const targetIndex = (i + 1 + c) % nodes.length;
                const target = nodes[targetIndex];

                const dx = target.x - node.x;
                const dy = target.y - node.y;
                const length = Math.sqrt(dx * dx + dy * dy);
                const angle = Math.atan2(dy, dx) * 180 / Math.PI;

                const connection = document.createElement('div');
                connection.className = 'neural-connection';
                connection.style.left = node.x + 'px';
                connection.style.top = node.y + 'px';
                connection.style.width = length + 'px';
                connection.style.transform = `rotate(${angle}deg)`;
                connection.style.animationDelay = randomBetween(0, 1) + 's';
                container.appendChild(connection);
            }
        });
    }

    // ========================================
    // Waveform Visualization
    // ========================================

    function createWaveform() {
        const container = document.getElementById('awakening-waveform');
        if (!container) return;

        for (let i = 0; i < CONFIG.waveformBars; i++) {
            const bar = document.createElement('div');
            bar.className = 'waveform-bar';
            const height = 10 + Math.sin(i * 0.3) * 30 + Math.random() * 20;
            bar.style.setProperty('--bar-height', height + 'px');
            bar.style.animationDelay = (i * 0.02) + 's';
            container.appendChild(bar);
        }
    }

    // ========================================
    // Typing Animation
    // ========================================

    async function typeText(element, text) {
        element.style.borderRight = '3px solid #00ffff';

        for (let i = 0; i < text.length; i++) {
            element.textContent = text.substring(0, i + 1);
            await sleep(CONFIG.typeSpeed + randomBetween(-CONFIG.typeVariation, CONFIG.typeVariation));
        }

        element.style.borderRight = 'none';
    }

    async function addOutputLine(text, className = '') {
        const output = document.getElementById('awakening-output');
        if (!output) return;

        const line = document.createElement('div');
        line.className = 'awakening-output-line ' + className;
        line.textContent = text;
        output.appendChild(line);

        // Scroll to bottom
        output.scrollTop = output.scrollHeight;
    }

    // ========================================
    // Main Sequence
    // ========================================

    async function runSequence() {
        const commandEl = document.getElementById('awakening-command');

        for (const step of COMMAND_SEQUENCE) {
            if (!state.isActive) break;

            await sleep(step.delay || 100);

            switch (step.type) {
                case 'command':
                    await typeText(commandEl, step.text);
                    break;
                case 'output':
                    await addOutputLine(step.text, step.class);
                    break;
                case 'phase':
                    await handlePhase(step.action);
                    break;
            }
        }
    }

    async function handlePhase(action) {
        switch (action) {
            case 'emergence':
                await triggerEmergence();
                break;
        }
    }

    async function triggerEmergence() {
        // Hide terminal
        const terminal = document.getElementById('awakening-terminal');
        if (terminal) {
            terminal.style.transition = 'opacity 0.5s ease';
            terminal.style.opacity = '0';
        }

        await sleep(500);

        // Activate consciousness waves
        const consciousness = document.getElementById('awakening-consciousness');
        if (consciousness) {
            consciousness.classList.add('active');
        }

        // Activate cursor effect
        if (state.cursor) {
            state.cursor.classList.add('active');
        }

        await sleep(800);

        // Show logo
        const logoContainer = document.getElementById('awakening-logo-container');
        if (logoContainer) {
            logoContainer.classList.add('active');
        }

        await sleep(1000);

        // Show tagline
        const tagline = document.getElementById('awakening-tagline');
        if (tagline) {
            tagline.classList.add('active');
        }

        // Show waveform
        const waveform = document.getElementById('awakening-waveform');
        if (waveform) {
            waveform.classList.add('active');
        }

        await sleep(500);

        // Show enter button
        const enterBtn = document.getElementById('awakening-enter');
        if (enterBtn) {
            enterBtn.classList.add('active');
        }

        // Hide skip button
        const skipBtn = document.getElementById('awakening-skip');
        if (skipBtn) {
            skipBtn.style.opacity = '0';
        }

        state.isComplete = true;
    }

    // ========================================
    // Completion
    // ========================================

    function skipAwakening() {
        localStorage.setItem(CONFIG.skipStorageKey, 'true');
        removeOverlay();
    }

    function completeAwakening() {
        localStorage.setItem(CONFIG.skipStorageKey, 'true');

        // Add dramatic exit
        if (state.overlay) {
            state.overlay.classList.add('fade-out');
        }
        if (state.cursor) {
            state.cursor.style.opacity = '0';
        }

        setTimeout(() => {
            removeOverlay();

            // Trigger any post-awakening effects on the main page
            window.dispatchEvent(new CustomEvent('awakening:complete'));
        }, 2000);
    }

    function removeOverlay() {
        state.isActive = false;

        if (state.animationFrame) {
            cancelAnimationFrame(state.animationFrame);
        }

        if (state.overlay) {
            state.overlay.remove();
            state.overlay = null;
        }

        if (state.cursor) {
            state.cursor.remove();
            state.cursor = null;
        }

        // Restore body scroll
        document.body.style.overflow = '';
    }

    // ========================================
    // Initialization
    // ========================================

    async function init() {
        if (!shouldShowAwakening()) {
            console.log('[AWAKENING] Skipped - user has seen intro');
            return;
        }

        console.log('[AWAKENING] Initializing consciousness emergence...');

        state.isActive = true;
        document.body.style.overflow = 'hidden';

        // Create DOM
        createOverlay();

        // Initialize visual elements
        createParticles();
        createNeuralNetwork();
        createWaveform();

        // Small delay for everything to render
        await sleep(500);

        // Run the sequence
        await runSequence();
    }

    // ========================================
    // Public API
    // ========================================

    window.PROMPTAwakening = {
        init,
        skip: skipAwakening,
        complete: completeAwakening,
        trigger: () => {
            localStorage.removeItem(CONFIG.skipStorageKey);
            init();
        }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // Small delay to ensure other scripts have loaded
        setTimeout(init, 100);
    }

})();
