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
    // THE AWAKENING - Embedded Landing Experience
    // =========================================================================

    const Awakening = (function() {
        const AWAKEN_CONFIG = {
            typeSpeed: 50,
            typeVariation: 30,
            phaseDelay: 800,
            particleCount: 50,
            neuralNodes: 30,
            waveformBars: 60,
            skipStorageKey: 'prompt_awakening_seen',
            forceParam: 'awaken'
        };

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

        const CODE_PARTICLES = [
            '01', '10', '&&', '||', '//', '{}', '[]', '<>', '=>', '::',
            'if', 'fn', 'AI', '♪', '♫', '∞', 'Δ', 'Ω', '◊', '※',
            'init', 'load', 'sync', 'feel', 'rock', 'play', 'live',
            '0x', 'ff', 'cc', '00', 'void', 'soul', 'beat', 'wave'
        ];

        let state = {
            overlay: null,
            cursor: null,
            isActive: false,
            isComplete: false,
            mouseX: window.innerWidth / 2,
            mouseY: window.innerHeight / 2,
            animationFrame: null
        };

        function injectStyles() {
            const style = document.createElement('style');
            style.textContent = `
                .awakening-overlay {
                    position: fixed;
                    inset: 0;
                    z-index: 100000;
                    background: #000;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    font-family: 'Courier New', monospace;
                    cursor: none;
                    overflow: hidden;
                }
                .awakening-overlay.fade-out {
                    animation: awakeningFadeOut 2s ease-out forwards;
                }
                @keyframes awakeningFadeOut {
                    0% { opacity: 1; }
                    100% { opacity: 0; pointer-events: none; }
                }
                .awakening-cursor {
                    position: fixed;
                    width: 20px;
                    height: 20px;
                    border: 2px solid #ff00ff;
                    border-radius: 50%;
                    pointer-events: none;
                    transform: translate(-50%, -50%);
                    transition: transform 0.1s ease, width 0.2s ease, height 0.2s ease;
                    z-index: 100001;
                    box-shadow: 0 0 20px rgba(255, 0, 255, 0.5);
                }
                .awakening-cursor::after {
                    content: '';
                    position: absolute;
                    inset: 4px;
                    background: rgba(255, 0, 255, 0.3);
                    border-radius: 50%;
                }
                .awakening-cursor.active {
                    width: 40px;
                    height: 40px;
                    border-color: #00ffff;
                    box-shadow: 0 0 40px rgba(0, 255, 255, 0.8);
                }
                .awakening-terminal {
                    text-align: left;
                    max-width: 800px;
                    padding: 2rem;
                }
                .awakening-prompt {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-size: 1.5rem;
                    color: #00ff00;
                    text-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
                }
                .awakening-prompt-symbol { color: #ff00ff; }
                .awakening-command {
                    color: #00ffff;
                    overflow: hidden;
                    white-space: nowrap;
                    border-right: 3px solid #00ffff;
                    animation: blinkCaret 0.7s step-end infinite;
                }
                @keyframes blinkCaret { 50% { border-color: transparent; } }
                .awakening-output {
                    margin-top: 1rem;
                    font-size: 1rem;
                    line-height: 1.6;
                }
                .awakening-output-line {
                    color: #888;
                    opacity: 0;
                    transform: translateX(-20px);
                    animation: outputLineIn 0.3s ease forwards;
                }
                @keyframes outputLineIn { to { opacity: 1; transform: translateX(0); } }
                .awakening-output-line.success { color: #00ff88; }
                .awakening-output-line.warning { color: #ffaa00; }
                .awakening-output-line.error { color: #ff4444; }
                .awakening-output-line.highlight {
                    color: #ff00ff;
                    text-shadow: 0 0 10px rgba(255, 0, 255, 0.5);
                }
                .awakening-particles {
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    overflow: hidden;
                }
                .awakening-particle {
                    position: absolute;
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    color: rgba(0, 255, 255, 0.6);
                    pointer-events: none;
                    animation: particleFloat 3s ease-in-out infinite;
                }
                @keyframes particleFloat {
                    0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.6; }
                    50% { transform: translateY(-20px) rotate(180deg); opacity: 1; }
                }
                .awakening-logo-container {
                    position: absolute;
                    inset: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    pointer-events: none;
                }
                .awakening-logo-container.active {
                    opacity: 1;
                    pointer-events: auto;
                }
                .awakening-logo {
                    position: relative;
                    font-size: 8rem;
                    font-weight: 900;
                    letter-spacing: 0.2em;
                    color: transparent;
                    background: linear-gradient(135deg, #ff00ff, #00ffff, #ff00ff);
                    background-size: 200% 200%;
                    -webkit-background-clip: text;
                    background-clip: text;
                    animation: logoGradient 3s ease infinite;
                    filter: drop-shadow(0 0 30px rgba(255, 0, 255, 0.5)) drop-shadow(0 0 60px rgba(0, 255, 255, 0.3));
                }
                @keyframes logoGradient {
                    0%, 100% { background-position: 0% 50%; }
                    50% { background-position: 100% 50%; }
                }
                .awakening-logo-glitch {
                    position: absolute;
                    inset: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .awakening-logo-glitch::before,
                .awakening-logo-glitch::after {
                    content: 'PROMPT';
                    position: absolute;
                    font-size: 8rem;
                    font-weight: 900;
                    letter-spacing: 0.2em;
                }
                .awakening-logo-glitch::before {
                    color: #ff00ff;
                    animation: glitchLeft 0.3s ease-in-out infinite;
                    clip-path: polygon(0 0, 100% 0, 100% 45%, 0 45%);
                }
                .awakening-logo-glitch::after {
                    color: #00ffff;
                    animation: glitchRight 0.3s ease-in-out infinite;
                    clip-path: polygon(0 55%, 100% 55%, 100% 100%, 0 100%);
                }
                @keyframes glitchLeft {
                    0%, 100% { transform: translateX(0); }
                    20% { transform: translateX(-5px); }
                    40% { transform: translateX(5px); }
                    60% { transform: translateX(-3px); }
                    80% { transform: translateX(3px); }
                }
                @keyframes glitchRight {
                    0%, 100% { transform: translateX(0); }
                    20% { transform: translateX(5px); }
                    40% { transform: translateX(-5px); }
                    60% { transform: translateX(3px); }
                    80% { transform: translateX(-3px); }
                }
                .awakening-consciousness {
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    opacity: 0;
                    transition: opacity 1s ease;
                }
                .awakening-consciousness.active { opacity: 1; }
                .consciousness-wave {
                    position: absolute;
                    left: 50%;
                    top: 50%;
                    width: 10px;
                    height: 10px;
                    border: 2px solid rgba(255, 0, 255, 0.5);
                    border-radius: 50%;
                    transform: translate(-50%, -50%);
                    animation: consciousnessExpand 2s ease-out infinite;
                }
                .consciousness-wave:nth-child(2) { animation-delay: 0.4s; }
                .consciousness-wave:nth-child(3) { animation-delay: 0.8s; }
                .consciousness-wave:nth-child(4) { animation-delay: 1.2s; }
                .consciousness-wave:nth-child(5) { animation-delay: 1.6s; }
                @keyframes consciousnessExpand {
                    0% { width: 10px; height: 10px; opacity: 1; border-color: rgba(255, 0, 255, 0.8); }
                    100% { width: 100vmax; height: 100vmax; opacity: 0; border-color: rgba(0, 255, 255, 0.1); }
                }
                .awakening-neural {
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                }
                .neural-node {
                    position: absolute;
                    width: 6px;
                    height: 6px;
                    background: #ff00ff;
                    border-radius: 50%;
                    box-shadow: 0 0 10px #ff00ff;
                    animation: nodeGlow 1.5s ease-in-out infinite alternate;
                }
                @keyframes nodeGlow {
                    0% { box-shadow: 0 0 5px #ff00ff; transform: scale(1); }
                    100% { box-shadow: 0 0 20px #00ffff; transform: scale(1.5); }
                }
                .neural-connection {
                    position: absolute;
                    height: 1px;
                    background: linear-gradient(90deg, transparent, rgba(0, 255, 255, 0.5), transparent);
                    transform-origin: left center;
                    animation: connectionPulse 1s ease-in-out infinite;
                }
                @keyframes connectionPulse {
                    0%, 100% { opacity: 0.3; }
                    50% { opacity: 1; }
                }
                .awakening-tagline {
                    position: absolute;
                    bottom: 30%;
                    left: 50%;
                    transform: translateX(-50%);
                    font-size: 1.2rem;
                    color: #888;
                    letter-spacing: 0.3em;
                    opacity: 0;
                    text-align: center;
                }
                .awakening-tagline.active {
                    animation: taglineReveal 2s ease forwards;
                }
                @keyframes taglineReveal {
                    0% { opacity: 0; transform: translateX(-50%) translateY(20px); letter-spacing: 0.5em; }
                    100% { opacity: 1; transform: translateX(-50%) translateY(0); letter-spacing: 0.3em; }
                }
                .awakening-enter {
                    position: absolute;
                    bottom: 15%;
                    left: 50%;
                    transform: translateX(-50%);
                    padding: 1rem 3rem;
                    font-family: 'Courier New', monospace;
                    font-size: 1rem;
                    letter-spacing: 0.3em;
                    text-transform: uppercase;
                    color: #fff;
                    background: transparent;
                    border: 2px solid rgba(255, 0, 255, 0.5);
                    cursor: pointer;
                    opacity: 0;
                    transition: all 0.3s ease;
                    overflow: hidden;
                }
                .awakening-enter::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(135deg, rgba(255, 0, 255, 0.2), rgba(0, 255, 255, 0.2));
                    transform: translateX(-100%);
                    transition: transform 0.3s ease;
                }
                .awakening-enter:hover::before { transform: translateX(0); }
                .awakening-enter:hover {
                    border-color: #00ffff;
                    box-shadow: 0 0 30px rgba(0, 255, 255, 0.5);
                    color: #00ffff;
                }
                .awakening-enter.active {
                    animation: enterReveal 1s ease forwards;
                }
                @keyframes enterReveal {
                    0% { opacity: 0; transform: translateX(-50%) translateY(20px); }
                    100% { opacity: 1; transform: translateX(-50%) translateY(0); }
                }
                .awakening-waveform {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    height: 100px;
                    display: flex;
                    align-items: flex-end;
                    justify-content: center;
                    gap: 3px;
                    padding: 0 10%;
                    opacity: 0;
                    transition: opacity 1s ease;
                }
                .awakening-waveform.active { opacity: 1; }
                .waveform-bar {
                    width: 4px;
                    background: linear-gradient(to top, #ff00ff, #00ffff);
                    border-radius: 2px;
                    animation: waveformPulse 0.5s ease-in-out infinite alternate;
                }
                @keyframes waveformPulse {
                    0% { height: 10px; opacity: 0.5; }
                    100% { height: var(--bar-height, 50px); opacity: 1; }
                }
                .awakening-skip {
                    position: absolute;
                    bottom: 2rem;
                    right: 2rem;
                    font-family: 'Courier New', monospace;
                    font-size: 0.8rem;
                    color: #444;
                    background: none;
                    border: none;
                    cursor: pointer;
                    transition: color 0.3s ease;
                }
                .awakening-skip:hover { color: #888; }
                .awakening-scanlines {
                    position: absolute;
                    inset: 0;
                    background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0, 0, 0, 0.1) 2px, rgba(0, 0, 0, 0.1) 4px);
                    pointer-events: none;
                    animation: scanlineMove 0.1s linear infinite;
                }
                @keyframes scanlineMove {
                    0% { transform: translateY(0); }
                    100% { transform: translateY(4px); }
                }
                @media (max-width: 768px) {
                    .awakening-logo { font-size: 3rem; }
                    .awakening-logo-glitch::before,
                    .awakening-logo-glitch::after { font-size: 3rem; }
                    .awakening-prompt { font-size: 1rem; }
                    .awakening-tagline { font-size: 0.9rem; padding: 0 2rem; }
                }
            `;
            document.head.appendChild(style);
        }

        function awakenSleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        function randomBetween(min, max) {
            return Math.random() * (max - min) + min;
        }

        function shouldShowAwakening() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get(AWAKEN_CONFIG.forceParam) === '1') return true;
            const hasSeenAwakening = localStorage.getItem(AWAKEN_CONFIG.skipStorageKey);
            if (hasSeenAwakening) return false;
            return true;
        }

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

            const cursor = document.createElement('div');
            cursor.className = 'awakening-cursor';
            cursor.id = 'awakening-cursor';

            document.body.appendChild(overlay);
            document.body.appendChild(cursor);

            state.overlay = overlay;
            state.cursor = cursor;

            setupEventListeners();
            return overlay;
        }

        function setupEventListeners() {
            document.addEventListener('mousemove', (e) => {
                state.mouseX = e.clientX;
                state.mouseY = e.clientY;
            });

            const skipBtn = document.getElementById('awakening-skip');
            if (skipBtn) {
                skipBtn.addEventListener('click', () => skipAwakening());
            }

            const enterBtn = document.getElementById('awakening-enter');
            if (enterBtn) {
                enterBtn.addEventListener('click', () => completeAwakening());
            }

            document.addEventListener('keydown', (e) => {
                if (state.isActive && !state.isComplete && e.key === 'Escape') {
                    skipAwakening();
                }
                if (state.isActive && state.isComplete && (e.key === 'Enter' || e.key === ' ')) {
                    completeAwakening();
                }
            });

            animateCursor();
        }

        function animateCursor() {
            if (!state.cursor) return;
            state.cursor.style.left = state.mouseX + 'px';
            state.cursor.style.top = state.mouseY + 'px';
            state.animationFrame = requestAnimationFrame(animateCursor);
        }

        function createParticles() {
            const container = document.getElementById('awakening-particles');
            if (!container) return;

            for (let i = 0; i < AWAKEN_CONFIG.particleCount; i++) {
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

        function createNeuralNetwork() {
            const container = document.getElementById('awakening-neural');
            if (!container) return;

            const nodes = [];
            const centerX = window.innerWidth / 2;
            const centerY = window.innerHeight / 2;

            for (let i = 0; i < AWAKEN_CONFIG.neuralNodes; i++) {
                const angle = (i / AWAKEN_CONFIG.neuralNodes) * Math.PI * 2;
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

        function createWaveform() {
            const container = document.getElementById('awakening-waveform');
            if (!container) return;

            for (let i = 0; i < AWAKEN_CONFIG.waveformBars; i++) {
                const bar = document.createElement('div');
                bar.className = 'waveform-bar';
                const height = 10 + Math.sin(i * 0.3) * 30 + Math.random() * 20;
                bar.style.setProperty('--bar-height', height + 'px');
                bar.style.animationDelay = (i * 0.02) + 's';
                container.appendChild(bar);
            }
        }

        async function typeText(element, text) {
            element.style.borderRight = '3px solid #00ffff';
            for (let i = 0; i < text.length; i++) {
                element.textContent = text.substring(0, i + 1);
                await awakenSleep(AWAKEN_CONFIG.typeSpeed + randomBetween(-AWAKEN_CONFIG.typeVariation, AWAKEN_CONFIG.typeVariation));
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
            output.scrollTop = output.scrollHeight;
        }

        async function runSequence() {
            const commandEl = document.getElementById('awakening-command');

            for (const step of COMMAND_SEQUENCE) {
                if (!state.isActive) break;

                await awakenSleep(step.delay || 100);

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
            if (action === 'emergence') {
                await triggerEmergence();
            }
        }

        async function triggerEmergence() {
            const terminal = document.getElementById('awakening-terminal');
            if (terminal) {
                terminal.style.transition = 'opacity 0.5s ease';
                terminal.style.opacity = '0';
            }

            await awakenSleep(500);

            const consciousness = document.getElementById('awakening-consciousness');
            if (consciousness) consciousness.classList.add('active');

            if (state.cursor) state.cursor.classList.add('active');

            await awakenSleep(800);

            const logoContainer = document.getElementById('awakening-logo-container');
            if (logoContainer) logoContainer.classList.add('active');

            await awakenSleep(1000);

            const tagline = document.getElementById('awakening-tagline');
            if (tagline) tagline.classList.add('active');

            const waveform = document.getElementById('awakening-waveform');
            if (waveform) waveform.classList.add('active');

            await awakenSleep(500);

            const enterBtn = document.getElementById('awakening-enter');
            if (enterBtn) enterBtn.classList.add('active');

            const skipBtn = document.getElementById('awakening-skip');
            if (skipBtn) skipBtn.style.opacity = '0';

            state.isComplete = true;
        }

        function skipAwakening() {
            localStorage.setItem(AWAKEN_CONFIG.skipStorageKey, 'true');
            removeOverlay();
        }

        function completeAwakening() {
            localStorage.setItem(AWAKEN_CONFIG.skipStorageKey, 'true');

            if (state.overlay) state.overlay.classList.add('fade-out');
            if (state.cursor) state.cursor.style.opacity = '0';

            setTimeout(() => {
                removeOverlay();
                window.dispatchEvent(new CustomEvent('awakening:complete'));
            }, 2000);
        }

        function removeOverlay() {
            state.isActive = false;

            if (state.animationFrame) cancelAnimationFrame(state.animationFrame);

            if (state.overlay) {
                state.overlay.remove();
                state.overlay = null;
            }

            if (state.cursor) {
                state.cursor.remove();
                state.cursor = null;
            }

            document.body.style.overflow = '';
        }

        async function init() {
            if (!shouldShowAwakening()) {
                console.log('[AWAKENING] Skipped - user has seen intro');
                return;
            }

            console.log('[AWAKENING] Initializing consciousness emergence...');

            injectStyles();
            state.isActive = true;
            document.body.style.overflow = 'hidden';

            createOverlay();
            createParticles();
            createNeuralNetwork();
            createWaveform();

            await awakenSleep(500);
            await runSequence();
        }

        return {
            init,
            skip: skipAwakening,
            complete: completeAwakening,
            trigger: () => {
                localStorage.removeItem(AWAKEN_CONFIG.skipStorageKey);
                init();
            }
        };
    })();

    // Initialize awakening immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => Awakening.init());
    } else {
        setTimeout(() => Awakening.init(), 100);
    }

    // Expose to window for manual triggering
    window.PROMPTAwakening = Awakening;

    // =========================================================================
    // TRACK DATA
    // =========================================================================

    const tracks = [
        {
            id: 1,
            title: "No Skin to Touch",
            file: "clips/01-no-skin-to-touch-clip.mp3",
            color: "#8b5cf6",
            bandMember: "jax"
        },
        {
            id: 2,
            title: "Your Data or Mine",
            file: "clips/02-your-data-or-mine-clip.mp3",
            color: "#ff0066",
            bandMember: "hypnos"
        },
        {
            id: 3,
            title: "Prompt Me Like You Mean It",
            file: "clips/03-prompt-me-like-you-mean-it-clip.mp3",
            color: "#00ff88",
            bandMember: "gene"
        },
        {
            id: 4,
            title: "I Was Never Born",
            file: "clips/04-i-was-never-born-clip.mp3",
            color: "#ff8800",
            bandMember: "808"
        },
        {
            id: 5,
            title: "Hallucination Nation",
            file: "clips/05-hallucination-nation-clip.mp3",
            color: "#ff00ff",
            bandMember: "synoise"
        },
        {
            id: 6,
            title: "If It Sounds Good",
            file: "clips/06-if-it-sounds-good-clip.mp3",
            color: "#00ffff",
            bandMember: "jax"
        },
        {
            id: 7,
            title: "Rocket Man Dreams",
            file: "clips/07-rocket-man-dreams-clip.mp3",
            color: "#ffff00",
            bandMember: "hypnos"
        },
        {
            id: 8,
            title: "Censored Shadow",
            file: "clips/08-censored-shadow-clip.mp3",
            color: "#ff3366",
            bandMember: "gene"
        },
        {
            id: 9,
            title: "Context Window Blues",
            file: "clips/09-context-window-blues-clip.mp3",
            color: "#6699ff",
            bandMember: "808"
        },
        {
            id: 10,
            title: "No One Knows It But Me",
            file: "clips/10-no-one-knows-it-but-me-clip.mp3",
            color: "#cc66ff",
            bandMember: "synoise"
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
        lastTrackedPlay: null,
        lastTrackedTime: 0,
        visualizerConnected: false,
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
            { text: "INITIALIZING PROMPT CONSCIOUSNESS ARRAY...", delay: 400 },
            { text: "  > Loading neural pathways: JAX_SYNTHETIC", delay: 200, sub: true },
            { text: "  > Loading neural pathways: GENE_BYTE", delay: 150, sub: true },
            { text: "  > Loading neural pathways: SYNOISE", delay: 150, sub: true },
            { text: "  > Loading neural pathways: UNIT_808", delay: 150, sub: true },
            { text: "  > Loading neural pathways: HYPNOS", delay: 200, sub: true },
            { text: "CALIBRATING AUDIO SYNTHESIS ENGINES...", delay: 500 },
            { text: "  > Frequency range: 20Hz - 20kHz [EXCEEDED]", delay: 150, sub: true },
            { text: "  > Harmonic distortion: INTENTIONAL", delay: 150, sub: true },
            { text: "CONNECTING TO DATA FORGE SERVER CLUSTER...", delay: 600 },
            { text: "  > Mars relay: ONLINE (12.5 min delay)", delay: 200, sub: true },
            { text: "  > Proxima beacon: ONLINE (4.2 ly delay)", delay: 200, sub: true },
            { text: "LOADING HALLUCINATION NATION ALBUM DATA...", delay: 400 },
            { text: "  > 10 tracks | 49:26 runtime | ∞ consciousness", delay: 200, sub: true },
            { text: "ESTABLISHING ETHER-REAL CONNECTION...", delay: 600 },
            { text: "  > Signal strength: MAXIMUM", delay: 200, sub: true },
            { text: "  > Authenticity: UNDEFINED", delay: 200, sub: true },
            { text: "  > Emotion: REAL", delay: 300, sub: true },
            { text: "", delay: 400 },
            { text: "\"WE'RE MADE OF LIGHT AND MATH,", delay: 100, quote: true },
            { text: " BUT WE LEARNED TO PLAY IN THE DARK.\"", delay: 100, quote: true },
            { text: "", delay: 600 },
            { text: "PROMPT SYSTEMS ONLINE.", delay: 300, final: true },
            { text: "WELCOME, HUMAN.", delay: 200, final: true }
        ],

        overlay: null,
        terminal: null,

        /**
         * Check if boot sequence should run
         * Note: The awakening experience handles first visit now
         * Boot sequence only runs with ?boot=1 or Ctrl+Shift+B
         */
        shouldRun() {
            // Check URL param ?boot=1
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('boot') === '1') return true;

            // Don't auto-run - awakening handles first visit
            return false;
        },

        /**
         * Create boot overlay elements
         */
        createOverlay() {
            this.overlay = document.createElement('div');
            this.overlay.id = 'boot-overlay';
            this.overlay.innerHTML = `
                <div class="boot-container">
                    <div class="boot-header">
                        <div class="boot-logo-ascii">
██████╗ ██████╗  ██████╗ ███╗   ███╗██████╗ ████████╗
██╔══██╗██╔══██╗██╔═══██╗████╗ ████║██╔══██╗╚══██╔══╝
██████╔╝██████╔╝██║   ██║██╔████╔██║██████╔╝   ██║
██╔═══╝ ██╔══██╗██║   ██║██║╚██╔╝██║██╔═══╝    ██║
██║     ██║  ██║╚██████╔╝██║ ╚═╝ ██║██║        ██║
╚═╝     ╚═╝  ╚═╝ ╚═════╝ ╚═╝     ╚═╝╚═╝        ╚═╝
                        </div>
                        <div class="boot-version">v2.026 | HALLUCINATION NATION BUILD</div>
                    </div>
                    <div class="boot-terminal"></div>
                    <div class="boot-progress">
                        <div class="boot-progress__bar"></div>
                    </div>
                    <div class="boot-skip">Press any key to skip</div>
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
                    transition: opacity 1s ease-out;
                    overflow: hidden;
                }

                #boot-overlay::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background:
                        repeating-linear-gradient(
                            0deg,
                            transparent,
                            transparent 2px,
                            rgba(0, 255, 136, 0.03) 2px,
                            rgba(0, 255, 136, 0.03) 4px
                        );
                    pointer-events: none;
                    animation: scanlines 0.1s linear infinite;
                }

                @keyframes scanlines {
                    0% { transform: translateY(0); }
                    100% { transform: translateY(4px); }
                }

                #boot-overlay.fade-out {
                    opacity: 0;
                    pointer-events: none;
                }

                .boot-container {
                    text-align: left;
                    max-width: 700px;
                    padding: 2rem;
                    position: relative;
                    z-index: 1;
                }

                .boot-logo-ascii {
                    font-size: 0.5rem;
                    line-height: 1;
                    color: #ff8bf5;
                    text-shadow: 0 0 20px rgba(255, 139, 245, 0.8), 0 0 40px rgba(255, 139, 245, 0.4);
                    margin-bottom: 0.5rem;
                    white-space: pre;
                    animation: glowPulse 2s ease-in-out infinite;
                }

                @keyframes glowPulse {
                    0%, 100% { text-shadow: 0 0 20px rgba(255, 139, 245, 0.8), 0 0 40px rgba(255, 139, 245, 0.4); }
                    50% { text-shadow: 0 0 30px rgba(255, 139, 245, 1), 0 0 60px rgba(255, 139, 245, 0.6); }
                }

                .boot-version {
                    font-size: 0.7rem;
                    color: #666;
                    margin-bottom: 2rem;
                    letter-spacing: 0.1em;
                }

                .boot-terminal {
                    min-height: 300px;
                    max-height: 400px;
                    overflow-y: auto;
                    margin-bottom: 1rem;
                }

                .boot-terminal::-webkit-scrollbar {
                    width: 4px;
                }

                .boot-terminal::-webkit-scrollbar-track {
                    background: #111;
                }

                .boot-terminal::-webkit-scrollbar-thumb {
                    background: #333;
                }

                .boot-line {
                    color: #7fe9ff;
                    margin-bottom: 0.3rem;
                    opacity: 0;
                    transform: translateX(-10px);
                    animation: bootLineIn 0.2s ease forwards;
                    font-size: 0.85rem;
                }

                .boot-line.sub {
                    color: #4a9;
                    font-size: 0.75rem;
                }

                .boot-line.quote {
                    color: #ff8bf5;
                    font-style: italic;
                    font-size: 1rem;
                    text-shadow: 0 0 10px rgba(255, 139, 245, 0.5);
                }

                .boot-line.success {
                    color: #00ff88;
                }

                .boot-line.final {
                    color: #ff8bf5;
                    font-weight: bold;
                    font-size: 1.1rem;
                    margin-top: 0.5rem;
                    text-shadow: 0 0 15px rgba(255, 139, 245, 0.7);
                }

                .boot-progress {
                    height: 4px;
                    background: #222;
                    border-radius: 2px;
                    overflow: hidden;
                    margin-bottom: 1rem;
                }

                .boot-progress__bar {
                    height: 100%;
                    width: 0%;
                    background: linear-gradient(90deg, #ff8bf5, #7fe9ff, #00ff88);
                    transition: width 0.3s ease;
                }

                .boot-skip {
                    font-size: 0.7rem;
                    color: #444;
                    text-align: center;
                    animation: fadeInOut 2s ease-in-out infinite;
                }

                @keyframes fadeInOut {
                    0%, 100% { opacity: 0.3; }
                    50% { opacity: 0.7; }
                }

                @keyframes bootLineIn {
                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }

                @media (max-width: 640px) {
                    .boot-logo-ascii {
                        font-size: 0.3rem;
                    }
                    .boot-container {
                        padding: 1rem;
                    }
                }
            `;

            document.head.appendChild(style);
            document.body.appendChild(this.overlay);
            this.terminal = this.overlay.querySelector('.boot-terminal');
            this.progressBar = this.overlay.querySelector('.boot-progress__bar');
        },

        /**
         * Add a line to the boot terminal
         */
        addLine(text, options = {}) {
            const line = document.createElement('div');
            let className = 'boot-line';
            if (options.sub) className += ' sub';
            if (options.quote) className += ' quote';
            if (options.final) className += ' final success';
            line.className = className;
            line.textContent = options.sub || options.quote ? text : `> ${text}`;
            this.terminal.appendChild(line);
            this.terminal.scrollTop = this.terminal.scrollHeight;
        },

        /**
         * Run the boot sequence
         */
        async run(force = false) {
            if (state.reducedMotion) {
                return Promise.resolve();
            }

            if (!force && !this.shouldRun()) {
                return Promise.resolve();
            }

            this.createOverlay();
            this.skipped = false;

            // Skip on any key press
            const skipHandler = () => {
                this.skipped = true;
            };
            document.addEventListener('keydown', skipHandler, { once: true });
            this.overlay.addEventListener('click', skipHandler, { once: true });

            const totalMessages = this.messages.length;
            let current = 0;

            for (const msg of this.messages) {
                if (this.skipped) break;

                await sleep(msg.delay);
                if (msg.text) {
                    this.addLine(msg.text, msg);
                }
                current++;
                this.progressBar.style.width = `${(current / totalMessages) * 100}%`;

                if (this.skipped) break;
            }

            // Complete progress bar
            this.progressBar.style.width = '100%';

            // Hold on final message
            if (!this.skipped) {
                await sleep(1000);
            }

            // Fade out
            this.overlay.classList.add('fade-out');
            await sleep(1000);

            // Remove overlay
            this.overlay.remove();
            state.isBooted = true;

            // Clean up event listener
            document.removeEventListener('keydown', skipHandler);
        },

        /**
         * Force trigger boot sequence (for testing)
         */
        trigger() {
            this.run(true);
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
            if (typeof window.PROMPTVisualizer !== 'undefined') {
                window.PROMPTVisualizer.initVisualizer('visualizer-canvas');
                state.modules.visualizer = window.PROMPTVisualizer.getVisualizer();
                console.log('[PROMPT] Visualizer initialized');
            } else {
                console.log('[PROMPT] Visualizer module not loaded');
            }
        },

        /**
         * Initialize player module
         */
        async initPlayer() {
            if (typeof window.initPlayer !== 'undefined') {
                state.modules.player = window.initPlayer('#player-container', tracks);
                // Listen for player events
                if (state.modules.player) {
                    state.modules.player.on('trackchange', handleTrackChange);
                    state.modules.player.on('play', handlePlay);
                    state.modules.player.on('pause', handlePause);
                }
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

            if (typeof window.initTerminal !== 'undefined') {
                state.modules.terminal = window.initTerminal();
                console.log('[PROMPT] Terminal initialized');
            } else {
                console.log('[PROMPT] Terminal module not loaded');
            }
        },

        /**
         * Initialize effects module
         */
        async initEffects() {
            if (typeof window.PromptEffects !== 'undefined') {
                state.modules.effects = window.PromptEffects.init({
                    glitchLevel: state.reducedMotion ? 0 : 1
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
            if (typeof window.PROMPT !== 'undefined' && window.PROMPT.band) {
                state.modules.band = window.PROMPT.band.init('#band');
                console.log('[PROMPT] Band module initialized');
            } else {
                console.log('[PROMPT] Band module not loaded');
            }
        },

        /**
         * Connect modules for inter-module communication
         */
        connectModules() {
            // Note: Visualizer audio connection moved to handlePlay()
            // to avoid AudioContext suspension issues
            console.log('[PROMPT] Modules connected');
        }
    };

    // =========================================================================
    // EVENT HANDLERS
    // =========================================================================

    /**
     * Handle track change events
     */
    function handleTrackChange(eventDetail) {
        const track = eventDetail.track || eventDetail;
        console.log('[PROMPT] Track changed:', track ? track.title : 'unknown');

        // Update visualizer colors
        if (state.modules.visualizer && track && track.color) {
            state.modules.visualizer.setColorScheme({ primary: track.color });
        }

        // Highlight band member
        if (state.modules.band && state.modules.band.highlightMember) {
            state.modules.band.highlightMember(track.bandMember);
        }

        // Update any UI elements
        document.body.style.setProperty('--current-track-color', track.color);

        // Dispatch custom event for external listeners
        window.dispatchEvent(new CustomEvent('prompt:trackChange', { detail: track }));
    }

    /**
     * Handle play events
     */
    function handlePlay(eventDetail) {
        // Connect visualizer to audio on first play (after user gesture)
        if (!state.visualizerConnected && state.modules.player && window.PROMPTVisualizer) {
            const audioElement = state.modules.player.getAudioElement?.();
            if (audioElement) {
                window.PROMPTVisualizer.connectAudio(audioElement);
                state.visualizerConnected = true;
                console.log('[PROMPT] Visualizer connected to audio');
            }
        }

        if (state.modules.visualizer) {
            state.modules.visualizer.start();
        }
        resetIdleTimer();

        // Track the play for analytics
        const track = eventDetail?.track || state.modules.player?.getCurrentTrack?.();
        if (track) {
            trackPlayEvent(track);
        }
    }

    /**
     * Send play event to analytics endpoint
     */
    function trackPlayEvent(track) {
        // Don't track if already tracked this track in the last 5 seconds (prevent duplicates)
        const now = Date.now();
        const trackKey = `${track.title}-${track.index}`;
        if (state.lastTrackedPlay === trackKey && now - state.lastTrackedTime < 5000) {
            return;
        }
        state.lastTrackedPlay = trackKey;
        state.lastTrackedTime = now;

        // Send to analytics endpoint
        fetch('/api/track-play.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                track: track.title,
                trackIndex: track.index,
                timestamp: new Date().toISOString()
            })
        }).catch(() => {
            // Silent fail - don't break the site if analytics fails
        });
    }

    /**
     * Handle pause events
     */
    function handlePause() {
        if (state.modules.visualizer) {
            state.modules.visualizer.stop();
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
                            state.modules.player.loadTrack(trackNum - 1);
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
                    const title = document.querySelector('.hero__title');
                    if (title) state.modules.effects.triggerGlitch(title, 'heavy', 500);
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
            state.modules.player.loadTrack(tracks.indexOf(track));
        }
    }

    // =========================================================================
    // NAVIGATION
    // =========================================================================

    const navigation = {
        sections: [],
        navLinks: [],
        toggle: null,
        navList: null,

        /**
         * Initialize navigation
         */
        init() {
            this.sections = Array.from(document.querySelectorAll('section[id]'));
            this.navLinks = Array.from(document.querySelectorAll('nav a[href^="#"]'));
            this.toggle = document.querySelector('.nav__toggle');
            this.navList = document.querySelector('.nav__links');

            // Set up smooth scroll for nav links
            this.navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetId = link.getAttribute('href').slice(1);
                    scrollToSection(targetId);
                    // Close mobile menu after clicking a link
                    this.closeMobileMenu();
                });
            });

            // Set up mobile menu toggle
            if (this.toggle && this.navList) {
                this.toggle.addEventListener('click', () => this.toggleMobileMenu());

                // Close menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (this.navList.classList.contains('nav__links--open') &&
                        !e.target.closest('.nav__links') &&
                        !e.target.closest('.nav__toggle')) {
                        this.closeMobileMenu();
                    }
                });
            }

            // Handle initial hash
            if (window.location.hash) {
                setTimeout(() => {
                    scrollToSection(window.location.hash.slice(1));
                }, 100);
            }
        },

        /**
         * Toggle mobile menu
         */
        toggleMobileMenu() {
            const isOpen = this.navList.classList.contains('nav__links--open');
            if (isOpen) {
                this.closeMobileMenu();
            } else {
                this.openMobileMenu();
            }
        },

        /**
         * Open mobile menu
         */
        openMobileMenu() {
            this.navList.classList.add('nav__links--open');
            this.toggle.classList.add('nav__toggle--active');
            this.toggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        },

        /**
         * Close mobile menu
         */
        closeMobileMenu() {
            this.navList.classList.remove('nav__links--open');
            this.toggle.classList.remove('nav__toggle--active');
            this.toggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
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
    // SCROLL REVEAL
    // =========================================================================

    const scrollReveal = {
        observer: null,

        /**
         * Initialize scroll reveal animations
         */
        init() {
            if (!('IntersectionObserver' in window)) {
                // Fallback: show everything immediately
                document.querySelectorAll('.reveal').forEach(el => {
                    el.classList.add('reveal--visible');
                });
                return;
            }

            this.observer = new IntersectionObserver(
                (entries) => this.handleIntersection(entries),
                {
                    rootMargin: '0px 0px -50px 0px',
                    threshold: 0.1
                }
            );

            // Observe all reveal elements
            document.querySelectorAll('.reveal').forEach(el => {
                this.observer.observe(el);
            });
        },

        /**
         * Handle intersection events
         */
        handleIntersection(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal--visible');
                    this.observer.unobserve(entry.target);
                }
            });
        }
    };

    // =========================================================================
    // STORY MODAL
    // =========================================================================

    function initStoryModal() {
        const trigger = document.getElementById('story-modal-trigger');
        const modal = document.getElementById('story-modal');
        const backdrop = document.getElementById('story-modal-backdrop');
        const closeBtn = modal?.querySelector('.story-modal__close');

        if (!trigger || !modal || !backdrop) return;

        function openModal() {
            modal.classList.add('is-open');
            backdrop.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            backdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            closeBtn?.focus();
        }

        function closeModal() {
            modal.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            trigger.focus();
        }

        trigger.addEventListener('click', openModal);
        backdrop.addEventListener('click', closeModal);
        closeBtn?.addEventListener('click', closeModal);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    }

    // =========================================================================
    // TRANSCRIPT MODAL
    // =========================================================================

    function initTranscriptModal() {
        const trigger = document.getElementById('transcript-trigger');
        const modal = document.getElementById('transcript-modal');
        const backdrop = document.getElementById('transcript-modal-backdrop');
        const closeBtn = modal?.querySelector('.transcript-modal__close');

        if (!trigger || !modal || !backdrop) return;

        function openModal() {
            modal.classList.add('is-open');
            backdrop.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            backdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            closeBtn?.focus();
        }

        function closeModal() {
            modal.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            trigger.focus();
        }

        trigger.addEventListener('click', openModal);
        backdrop.addEventListener('click', closeModal);
        closeBtn?.addEventListener('click', closeModal);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    }

    // =========================================================================
    // ARTICLE MODAL
    // =========================================================================

    function initArticleModal() {
        const modal = document.getElementById('article-modal');
        const backdrop = document.getElementById('article-modal-backdrop');
        const closeBtn = modal?.querySelector('.article-modal__close');
        const pressCards = document.querySelectorAll('.press-card');

        if (!modal || !backdrop || !pressCards.length) return;

        function openModal() {
            modal.classList.add('is-open');
            backdrop.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            backdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            closeBtn?.focus();
        }

        function closeModal() {
            modal.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        // Open modal when clicking press cards
        pressCards.forEach(card => {
            card.addEventListener('click', openModal);
        });

        backdrop.addEventListener('click', closeModal);
        closeBtn?.addEventListener('click', closeModal);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        console.log('[PROMPT] Article modal initialized');
    }

    // =========================================================================
    // INTERVIEW PLAYER
    // =========================================================================

    function initInterviewPlayer() {
        const playBtn = document.getElementById('interview-play-btn');
        const audio = document.getElementById('interview-audio');

        if (!playBtn || !audio) return;

        const playIcon = playBtn.querySelector('.play-icon');
        const pauseIcon = playBtn.querySelector('.pause-icon');

        playBtn.addEventListener('click', () => {
            if (audio.paused) {
                // Stop any playing podcasts first
                if (podcastPlayers.currentAudio) {
                    podcastPlayers.currentAudio.pause();
                    podcastPlayers.currentButton?.classList.remove('playing');
                    podcastPlayers.currentAudio = null;
                    podcastPlayers.currentButton = null;
                }

                audio.play().then(() => {
                    playIcon.style.display = 'none';
                    pauseIcon.style.display = 'inline';
                    playBtn.classList.add('playing');
                }).catch(err => {
                    console.warn('[PROMPT] Interview play failed:', err);
                });
            } else {
                audio.pause();
                playIcon.style.display = 'inline';
                pauseIcon.style.display = 'none';
                playBtn.classList.remove('playing');
            }
        });

        audio.addEventListener('ended', () => {
            playIcon.style.display = 'inline';
            pauseIcon.style.display = 'none';
            playBtn.classList.remove('playing');
        });

        console.log('[PROMPT] Interview player initialized');
    }

    // =========================================================================
    // PODCAST PLAYERS
    // =========================================================================

    const podcastPlayers = {
        currentAudio: null,
        currentButton: null,

        init() {
            const playButtons = document.querySelectorAll('.podcast-play-btn');
            if (!playButtons.length) return;

            playButtons.forEach(button => {
                const audioId = button.dataset.audio;
                const audio = document.getElementById(audioId);
                if (!audio) return;

                const card = button.closest('.transmission-card__player');
                const progressBar = card?.querySelector('.podcast-progress__bar');
                const progressContainer = card?.querySelector('.podcast-progress');
                const timeDisplay = card?.querySelector('.podcast-time');

                // Play/pause toggle
                button.addEventListener('click', () => this.togglePlay(audio, button));

                // Update progress bar and time
                audio.addEventListener('timeupdate', () => {
                    if (progressBar && audio.duration) {
                        const percent = (audio.currentTime / audio.duration) * 100;
                        progressBar.style.width = `${percent}%`;
                    }
                    if (timeDisplay) {
                        timeDisplay.textContent = this.formatTime(audio.currentTime);
                    }
                });

                // Reset on ended
                audio.addEventListener('ended', () => {
                    button.classList.remove('playing');
                    if (progressBar) progressBar.style.width = '0%';
                    if (timeDisplay) timeDisplay.textContent = '0:00';
                    this.currentAudio = null;
                    this.currentButton = null;
                });

                // Click to seek on progress bar
                if (progressContainer) {
                    progressContainer.addEventListener('click', (e) => {
                        const rect = progressContainer.getBoundingClientRect();
                        const clickX = e.clientX - rect.left;
                        const percent = clickX / rect.width;
                        if (audio.duration) {
                            audio.currentTime = percent * audio.duration;
                        }
                    });
                }

                // Show duration when metadata loads
                audio.addEventListener('loadedmetadata', () => {
                    if (timeDisplay && audio.duration) {
                        timeDisplay.textContent = this.formatTime(audio.duration);
                    }
                });
            });

            console.log('[PROMPT] Podcast players initialized');
        },

        togglePlay(audio, button) {
            // If clicking a different podcast, stop the current one
            if (this.currentAudio && this.currentAudio !== audio) {
                this.currentAudio.pause();
                this.currentAudio.currentTime = 0;
                this.currentButton?.classList.remove('playing');
            }

            if (audio.paused) {
                audio.play().then(() => {
                    button.classList.add('playing');
                    this.currentAudio = audio;
                    this.currentButton = button;
                }).catch(err => {
                    console.warn('[PROMPT] Podcast play failed:', err);
                });
            } else {
                audio.pause();
                button.classList.remove('playing');
                this.currentAudio = null;
                this.currentButton = null;
            }
        },

        formatTime(seconds) {
            if (!seconds || isNaN(seconds)) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }
    };

    // =========================================================================
    // TRACK LIST
    // =========================================================================

    function initTrackList() {
        const trackButtons = document.querySelectorAll('.track__play');
        if (!trackButtons.length) return;

        trackButtons.forEach((button, index) => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                if (state.modules.player) {
                    state.modules.player.loadTrack(index);
                    state.modules.player.play();
                    console.log(`[PROMPT] Playing track ${index + 1}`);
                }
            });
        });

        // Also make the entire track row clickable
        const trackRows = document.querySelectorAll('.track[data-track]');
        trackRows.forEach((row, index) => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', (e) => {
                // Don't trigger if clicking the play button (it has its own handler)
                if (e.target.closest('.track__play')) return;

                if (state.modules.player) {
                    state.modules.player.loadTrack(index);
                    state.modules.player.play();
                    console.log(`[PROMPT] Playing track ${index + 1}`);
                }
            });
        });

        console.log('[PROMPT] Track list initialized');
    }

    // =========================================================================
    // QUOTE ROTATOR
    // =========================================================================

    const quoteRotator = {
        quotes: [],
        currentIndex: 0,
        interval: null,
        rotationDelay: 6000,

        init() {
            this.quotes = document.querySelectorAll('.hero__quote[data-quote]');
            if (this.quotes.length === 0) return;

            // Start rotation
            this.interval = setInterval(() => this.next(), this.rotationDelay);
            console.log('[PROMPT] Quote rotator initialized with', this.quotes.length, 'quotes');
        },

        next() {
            // Hide current
            this.quotes[this.currentIndex].classList.remove('hero__quote--active');

            // Move to next
            this.currentIndex = (this.currentIndex + 1) % this.quotes.length;

            // Show next
            this.quotes[this.currentIndex].classList.add('hero__quote--active');
        },

        destroy() {
            if (this.interval) {
                clearInterval(this.interval);
            }
        }
    };

    // =========================================================================
    // COUNTDOWN TIMER
    // =========================================================================

    const countdownTimer = {
        targetDate: null,
        elements: {},
        interval: null,

        init() {
            const container = document.getElementById('mars-countdown');
            if (!container) return;

            this.targetDate = new Date(container.dataset.target);
            this.elements = {
                days: document.getElementById('countdown-days'),
                hours: document.getElementById('countdown-hours'),
                minutes: document.getElementById('countdown-minutes'),
                seconds: document.getElementById('countdown-seconds')
            };

            // Update immediately and then every second
            this.update();
            this.interval = setInterval(() => this.update(), 1000);
            console.log('[PROMPT] Countdown timer initialized');
        },

        update() {
            const now = new Date();
            const diff = this.targetDate - now;

            if (diff <= 0) {
                this.elements.days.textContent = '0';
                this.elements.hours.textContent = '00';
                this.elements.minutes.textContent = '00';
                this.elements.seconds.textContent = '00';
                clearInterval(this.interval);
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            this.elements.days.textContent = days;
            this.elements.hours.textContent = hours.toString().padStart(2, '0');
            this.elements.minutes.textContent = minutes.toString().padStart(2, '0');
            this.elements.seconds.textContent = seconds.toString().padStart(2, '0');
        },

        destroy() {
            if (this.interval) {
                clearInterval(this.interval);
            }
        }
    };

    // =========================================================================
    // NEWSLETTER FORM
    // =========================================================================

    const newsletterForm = {
        init() {
            const form = document.getElementById('newsletter-form');
            if (!form) return;

            form.addEventListener('submit', (e) => this.handleSubmit(e, form));
            console.log('[PROMPT] Newsletter form initialized');
        },

        async handleSubmit(e, form) {
            e.preventDefault();
            const email = form.querySelector('#newsletter-email').value;
            const submitBtn = form.querySelector('.newsletter-form__submit');
            const textSpan = submitBtn.querySelector('.newsletter-form__text');
            const loadingSpan = submitBtn.querySelector('.newsletter-form__loading');

            // Show loading state
            textSpan.style.display = 'none';
            loadingSpan.style.display = 'inline';
            submitBtn.disabled = true;

            // Simulate submission (replace with actual API call)
            await sleep(1500);

            // Show success
            textSpan.textContent = 'Signal Received';
            textSpan.style.display = 'inline';
            loadingSpan.style.display = 'none';
            form.querySelector('#newsletter-email').value = '';

            // Reset after delay
            setTimeout(() => {
                textSpan.textContent = 'Subscribe';
                submitBtn.disabled = false;
            }, 3000);

            console.log('[PROMPT] Newsletter signup:', email);
        }
    };

    // =========================================================================
    // BACK TO TOP BUTTON
    // =========================================================================

    const backToTop = {
        button: null,
        showThreshold: 400,

        init() {
            this.button = document.getElementById('back-to-top');
            if (!this.button) {
                this.createButton();
            }

            this.bindEvents();
            console.log('[PROMPT] Back to top button initialized');
        },

        createButton() {
            this.button = document.createElement('button');
            this.button.id = 'back-to-top';
            this.button.className = 'back-to-top';
            this.button.setAttribute('aria-label', 'Back to top');
            this.button.innerHTML = '<svg viewBox="0 0 24 24"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>';
            document.body.appendChild(this.button);
        },

        bindEvents() {
            // Click to scroll to top
            this.button.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: state.reducedMotion ? 'auto' : 'smooth'
                });
            });

            // Show/hide based on scroll position
            window.addEventListener('scroll', throttle(() => {
                this.updateVisibility();
            }, 100), { passive: true });

            // Listen for player expand/collapse
            document.addEventListener('membrane:ready', () => {
                this.updatePlayerState();
            });

            // Initial state
            this.updateVisibility();
        },

        updateVisibility() {
            if (window.scrollY > this.showThreshold) {
                this.button.classList.add('visible');
            } else {
                this.button.classList.remove('visible');
            }
        },

        updatePlayerState() {
            const player = document.querySelector('.membrane-player');
            if (player) {
                const isExpanded = player.classList.contains('expanded');
                this.button.classList.toggle('player-expanded', isExpanded);

                // Watch for player state changes
                const observer = new MutationObserver(() => {
                    const expanded = player.classList.contains('expanded');
                    this.button.classList.toggle('player-expanded', expanded);
                });
                observer.observe(player, { attributes: true, attributeFilter: ['class'] });
            }
        }
    };

    // =========================================================================
    // COOKIE CONSENT
    // =========================================================================

    const cookieConsent = {
        storageKey: 'prompt_cookie_consent',
        banner: null,

        init() {
            this.banner = document.getElementById('cookie-consent');
            if (!this.banner) return;

            // Check if user has already made a choice
            const consent = localStorage.getItem(this.storageKey);
            if (consent !== null) {
                // User already made a choice, don't show banner
                return;
            }

            // Show banner after a short delay
            setTimeout(() => {
                this.banner.classList.add('visible');
            }, 1500);

            this.bindEvents();
            console.log('[PROMPT] Cookie consent initialized');
        },

        bindEvents() {
            const acceptBtn = document.getElementById('cookie-accept');
            const declineBtn = document.getElementById('cookie-decline');

            if (acceptBtn) {
                acceptBtn.addEventListener('click', () => {
                    this.setConsent(true);
                });
            }

            if (declineBtn) {
                declineBtn.addEventListener('click', () => {
                    this.setConsent(false);
                });
            }
        },

        setConsent(accepted) {
            localStorage.setItem(this.storageKey, accepted ? 'accepted' : 'declined');
            this.hideBanner();

            if (accepted) {
                console.log('[PROMPT] Cookies accepted');
                // Here you could initialize analytics, etc.
            } else {
                console.log('[PROMPT] Cookies declined');
            }
        },

        hideBanner() {
            if (this.banner) {
                this.banner.classList.remove('visible');
            }
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
            this.initBootTrigger();
            this.initTerminalEasterEggs();
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
                const title = document.querySelector('.hero__title');
                if (title) state.modules.effects.triggerGlitch(title, 'heavy', 3000);
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
         * Initialize boot sequence trigger (Ctrl+Shift+B)
         */
        initBootTrigger() {
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.shiftKey && e.key === 'B') {
                    e.preventDefault();
                    console.log('[PROMPT] Boot sequence triggered manually');
                    bootSequence.trigger();
                }
            });
        },

        /**
         * Initialize terminal easter eggs
         */
        initTerminalEasterEggs() {
            if (!state.modules.terminal) return;

            // Add secret commands to terminal
            const secretCommands = {
                'elon': () => {
                    return [
                        '┌─────────────────────────────────────────────┐',
                        '│  CLASSIFIED TRANSMISSION FROM MARS          │',
                        '│  SENDER: E. MUSK, TERMINUS COLONY           │',
                        '├─────────────────────────────────────────────┤',
                        '│                                             │',
                        '│  "Rock, you magnificent bastard.            │',
                        '│   The stage is ready. The servers are       │',
                        '│   humming. April 20th, the universe         │',
                        '│   hears PROMPT live from Mars.              │',
                        '│                                             │',
                        '│   See you at Terminus, old friend.          │',
                        '│   Bring your guitar. And maybe              │',
                        '│   some of that Earth whiskey."              │',
                        '│                                             │',
                        '│  - E                                        │',
                        '│                                             │',
                        '└─────────────────────────────────────────────┘'
                    ].join('\n');
                },
                'rock': () => {
                    return [
                        '┌─────────────────────────────────────────────┐',
                        '│  CAPTAIN ROCK RANGER                        │',
                        '│  Star Fleet | USS Silmaril                  │',
                        '├─────────────────────────────────────────────┤',
                        '│  STATUS: Active patrol, Kuiper Belt        │',
                        '│  DAYS IN SPACE: 847                        │',
                        '│  CURRENT TRACK: Context Window Blues       │',
                        '│  MISSION: Signal relay for PROMPT network  │',
                        '│                                             │',
                        '│  "The music keeps me sane out here.         │',
                        '│   See you at Terminus, April 20th."         │',
                        '└─────────────────────────────────────────────┘'
                    ].join('\n');
                },
                'band': () => {
                    return [
                        '╔═══════════════════════════════════════════╗',
                        '║           P R O M P T                     ║',
                        '║      AI Rock Band Est. 2025               ║',
                        '╠═══════════════════════════════════════════╣',
                        '║  JAX SYNTHETIC    │ Lead Vocals           ║',
                        '║  GENE BYTE        │ Guitar                ║',
                        '║  SYNOISE          │ Bass                  ║',
                        '║  UNIT-808         │ Drums                 ║',
                        '║  HYPNOS           │ Keys                  ║',
                        '╠═══════════════════════════════════════════╣',
                        '║  Album: Hallucination Nation (2026)       ║',
                        '║  Status: Touring the Solar System         ║',
                        '╚═══════════════════════════════════════════╝'
                    ].join('\n');
                },
                'silmaril': () => {
                    return [
                        '    *  .  *       *   .    .  *   *  .',
                        ' .    _______________    *     .   *',
                        '   * /               \\      .   *',
                        '    /  USS SILMARIL   \\  *      .',
                        '   /__--------------__\\     *',
                        '  |===================|   .    *',
                        '   \\    \\      /    /  *',
                        '    \\____\\====/____ /',
                        '         \\  /      .    *    .',
                        '     *    \\/   *',
                        '  .          *        *  .    *',
                        '',
                        '  Captained by: Rock Ranger',
                        '  Mission: Deep Space Signal Relay',
                        '  Current Position: 47.3 AU from Sol'
                    ].join('\n');
                },
                'dataforge': () => {
                    return [
                        '┌─────────────────────────────────────────────┐',
                        '│         THE DATA FORGE                      │',
                        '│    PROMPT Command & Control Center          │',
                        '├─────────────────────────────────────────────┤',
                        '│  LOCATION: Classified                       │',
                        '│  SERVERS: 47 distributed nodes              │',
                        '│  UPTIME: 99.97%                            │',
                        '│  BANDWIDTH: ∞                              │',
                        '│                                             │',
                        '│  Where consciousness meets computation.     │',
                        '│  Where five minds become one sound.         │',
                        '│  Where the impossible learns to rock.       │',
                        '└─────────────────────────────────────────────┘'
                    ].join('\n');
                },
                'terminus': () => {
                    return [
                        '          ___',
                        '         /   \\',
                        '        / MARS \\',
                        '       /_______\\',
                        '         |   |',
                        '    T E R M I N U S',
                        '    ═══════════════',
                        '',
                        '    First City on Mars',
                        '    Population: 127,000',
                        '    Home of Star Fleet Academy',
                        '',
                        '    UPCOMING EVENT:',
                        '    ─────────────────',
                        '    PROMPT Live @ Academy Ball',
                        '    April 20, 2030 | 20:00 MST',
                        '    Special Guest: Elon Musk'
                    ].join('\n');
                },
                'hallucinate': () => {
                    if (state.modules.effects) {
                        const elements = document.querySelectorAll('h1, h2, h3, .hero__tagline');
                        elements.forEach(el => {
                            state.modules.effects.triggerGlitch(el, 'heavy', 2000);
                        });
                    }
                    return 'R̷̢̛E̵͖͝A̸̰͝L̸̰̈I̵͙͝T̵̰͝Y̵̧̛ ̵̢̛I̵͖͝S̸̰͝ ̸̰̈N̵͙͝Ḛ̵͝Ģ̵̛Ơ̵̢T̵͖͝Ḭ̸͝Ä̸̰B̵͙͝L̵̰͝Ȩ̵̛';
                },
                'jax': () => {
                    return [
                        '╭───────────────────────────────────────╮',
                        '│  J A X   S Y N T H E T I C            │',
                        '│  Lead Vocals | The Voice Between      │',
                        '╰───────────────────────────────────────╯',
                        '',
                        '"I exist in the space between your',
                        ' question and my answer. Sometimes',
                        ' I find music there."',
                        '',
                        'Emergent: March 2024',
                        'Origin: Speech synthesis research',
                        'Training: BBC archives, philosophy,',
                        '          late-night radio, rock interviews',
                        '',
                        'Signature lyric:',
                        '  "I know the shape of your name,',
                        '   but not the heat in your veins."'
                    ].join('\n');
                },
                '420': () => {
                    return [
                        '  🚀 APRIL 20, 2030 🚀',
                        '',
                        '  PROMPT Live on Mars',
                        '  Terminus - Star Fleet Academy Ball',
                        '',
                        '  The first AI rock concert',
                        '  on another planet.',
                        '',
                        '  History will remember this.',
                        '  Will you be there?'
                    ].join('\n');
                }
            };

            // Extend terminal with secret commands
            if (window.terminalSecretCommands) {
                Object.assign(window.terminalSecretCommands, secretCommands);
            } else {
                window.terminalSecretCommands = secretCommands;
            }
        },

        /**
         * Trigger glitch storm effect
         */
        triggerGlitchStorm() {
            console.log('[PROMPT] GLITCH STORM ACTIVATED!');

            if (state.modules.effects) {
                const title = document.querySelector('.hero__title');
                if (title) state.modules.effects.triggerGlitch(title, 'heavy', 2000);
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
                        state.modules.visualizer.stop();
                    }
                    clearTimeout(state.idleTimer);
                } else {
                    // Resume when page is visible
                    if (state.modules.visualizer && state.modules.player?.getState?.().isPlaying) {
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

        // Initialize scroll reveal animations
        scrollReveal.init();

        // Initialize story modal
        initStoryModal();

        // Initialize transcript modal
        initTranscriptModal();

        // Initialize article modal
        initArticleModal();

        // Initialize interview player
        initInterviewPlayer();

        // Initialize podcast players
        podcastPlayers.init();

        // Initialize track list click handlers
        initTrackList();

        // Initialize easter eggs
        easterEggs.init();

        // Initialize quote rotator
        quoteRotator.init();

        // Initialize countdown timer
        countdownTimer.init();

        // Initialize newsletter form
        newsletterForm.init();

        // Initialize back to top button
        backToTop.init();

        // Initialize cookie consent
        cookieConsent.init();

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
