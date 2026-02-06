/**
 * PROMPT Band - Audio-Reactive WebGL Visualizer
 * A server consciousness visualization - data flowing, pulsing, alive.
 *
 * @author PROMPT AI Rock Band
 * @version 1.0.0
 */

(function(global) {
    'use strict';

    // =====================================================================
    // Configuration
    // =====================================================================

    const CONFIG = {
        // Default color palette - magenta, cyan, violet
        colors: {
            primary: [1.0, 0.0, 0.4],      // #ff0066 magenta
            secondary: [0.0, 0.7, 0.7],    // #00b3b3 cyan
            tertiary: [0.545, 0.361, 0.965] // #8b5cf6 violet
        },
        // Particle system
        particles: {
            count: 50,
            minSize: 2.0,
            maxSize: 6.0,
            speed: 0.3,
            spread: 2.0
        },
        // Waveform
        waveform: {
            segments: 128,
            amplitude: 0.3,
            frequency: 2.0
        },
        // Grid
        grid: {
            lines: 20,
            opacity: 0.15,
            perspective: 0.7
        },
        // Audio analysis
        audio: {
            fftSize: 256,
            smoothing: 0.8,
            minDecibels: -90,
            maxDecibels: -10
        },
        // Performance
        performance: {
            lowPowerThreshold: 30, // FPS threshold for low-power mode
            targetFPS: 60
        }
    };

    // =====================================================================
    // Shader Sources
    // =====================================================================

    const SHADERS = {
        // Vertex shader for particles
        particleVertex: `
            attribute vec2 a_position;
            attribute float a_size;
            attribute float a_alpha;
            attribute vec3 a_color;

            uniform vec2 u_resolution;
            uniform float u_time;
            uniform float u_intensity;

            varying float v_alpha;
            varying vec3 v_color;

            void main() {
                vec2 pos = a_position;

                // Add subtle wave motion
                pos.x += sin(u_time * 0.5 + pos.y * 3.0) * 0.02 * u_intensity;

                // Convert to clip space
                vec2 clipSpace = (pos / u_resolution) * 2.0 - 1.0;
                clipSpace.y *= -1.0;

                gl_Position = vec4(clipSpace, 0.0, 1.0);
                gl_PointSize = a_size * (0.5 + u_intensity * 0.5);

                v_alpha = a_alpha * (0.3 + u_intensity * 0.7);
                v_color = a_color;
            }
        `,

        // Fragment shader for particles
        particleFragment: `
            precision mediump float;

            varying float v_alpha;
            varying vec3 v_color;

            void main() {
                // Create circular particle with soft edges
                vec2 center = gl_PointCoord - vec2(0.5);
                float dist = length(center);

                if (dist > 0.5) {
                    discard;
                }

                float alpha = v_alpha * (1.0 - dist * 2.0);
                alpha = smoothstep(0.0, 0.3, alpha);

                // Add glow effect
                vec3 color = v_color + vec3(0.2) * (1.0 - dist * 2.0);

                gl_FragColor = vec4(color, alpha);
            }
        `,

        // Vertex shader for waveform/lines
        lineVertex: `
            attribute vec2 a_position;
            attribute vec3 a_color;
            attribute float a_alpha;

            uniform vec2 u_resolution;

            varying vec3 v_color;
            varying float v_alpha;

            void main() {
                vec2 clipSpace = (a_position / u_resolution) * 2.0 - 1.0;
                clipSpace.y *= -1.0;

                gl_Position = vec4(clipSpace, 0.0, 1.0);
                v_color = a_color;
                v_alpha = a_alpha;
            }
        `,

        // Fragment shader for waveform/lines
        lineFragment: `
            precision mediump float;

            varying vec3 v_color;
            varying float v_alpha;

            void main() {
                gl_FragColor = vec4(v_color, v_alpha);
            }
        `,

        // Vertex shader for background gradient
        backgroundVertex: `
            attribute vec2 a_position;

            varying vec2 v_uv;

            void main() {
                gl_Position = vec4(a_position, 0.0, 1.0);
                v_uv = a_position * 0.5 + 0.5;
            }
        `,

        // Fragment shader for background gradient
        backgroundFragment: `
            precision mediump float;

            uniform float u_time;
            uniform float u_intensity;
            uniform vec3 u_color1;
            uniform vec3 u_color2;
            uniform vec3 u_color3;

            varying vec2 v_uv;

            void main() {
                // Create flowing gradient
                float t = u_time * 0.1;

                // Base dark color
                vec3 baseColor = vec3(0.02, 0.02, 0.05);

                // Add subtle color waves
                float wave1 = sin(v_uv.y * 3.0 + t) * 0.5 + 0.5;
                float wave2 = sin(v_uv.x * 2.0 - t * 0.7) * 0.5 + 0.5;
                float wave3 = sin((v_uv.x + v_uv.y) * 2.5 + t * 0.5) * 0.5 + 0.5;

                vec3 color = baseColor;
                color += u_color1 * wave1 * 0.05 * u_intensity;
                color += u_color2 * wave2 * 0.04 * u_intensity;
                color += u_color3 * wave3 * 0.03 * u_intensity;

                // Add vignette
                float vignette = 1.0 - length(v_uv - 0.5) * 0.8;
                color *= vignette;

                gl_FragColor = vec4(color, 1.0);
            }
        `
    };

    // =====================================================================
    // Visualizer Class
    // =====================================================================

    class Visualizer {
        constructor() {
            this.canvas = null;
            this.gl = null;
            this.isInitialized = false;
            this.isRunning = false;
            this.useFallback = false;

            // Timing
            this.startTime = 0;
            this.lastFrameTime = 0;
            this.frameCount = 0;
            this.fps = 60;
            this.isLowPower = false;

            // Audio
            this.audioContext = null;
            this.analyser = null;
            this.audioSource = null;
            this.frequencyData = null;
            this.isAudioConnected = false;

            // State
            this.intensity = 0.5;
            this.targetIntensity = 0.5;
            this.colors = { ...CONFIG.colors };

            // WebGL resources
            this.programs = {};
            this.buffers = {};
            this.particles = [];
            this.waveformData = new Float32Array(CONFIG.waveform.segments);

            // Bind methods
            this.render = this.render.bind(this);
            this.handleResize = this.handleResize.bind(this);
            this.handleVisibilityChange = this.handleVisibilityChange.bind(this);
        }

        // =================================================================
        // Initialization
        // =================================================================

        init(canvasId) {
            try {
                this.canvas = document.getElementById(canvasId);

                if (!this.canvas) {
                    throw new Error(`Canvas element with id "${canvasId}" not found`);
                }

                // Try to get WebGL context
                this.gl = this.canvas.getContext('webgl', {
                    alpha: true,
                    antialias: true,
                    premultipliedAlpha: false,
                    preserveDrawingBuffer: false
                }) || this.canvas.getContext('experimental-webgl');

                if (!this.gl) {
                    console.warn('WebGL not available, using CSS fallback');
                    this.setupFallback();
                    return true;
                }

                // Setup WebGL
                this.setupWebGL();
                this.setupEventListeners();
                this.initParticles();
                this.handleResize();

                this.isInitialized = true;
                this.startTime = performance.now();

                // Start with minimal animation, will be controlled by main.js based on play state
                this.start();

                console.log('PROMPT Visualizer initialized');
                return true;

            } catch (error) {
                console.error('Visualizer initialization failed:', error);
                this.setupFallback();
                return false;
            }
        }

        setupWebGL() {
            const gl = this.gl;

            // Enable blending for transparency
            gl.enable(gl.BLEND);
            gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

            // Create shader programs
            this.programs.particles = this.createProgram(
                SHADERS.particleVertex,
                SHADERS.particleFragment
            );

            this.programs.lines = this.createProgram(
                SHADERS.lineVertex,
                SHADERS.lineFragment
            );

            this.programs.background = this.createProgram(
                SHADERS.backgroundVertex,
                SHADERS.backgroundFragment
            );

            // Create buffers
            this.buffers.particles = {
                position: gl.createBuffer(),
                size: gl.createBuffer(),
                alpha: gl.createBuffer(),
                color: gl.createBuffer()
            };

            this.buffers.lines = {
                position: gl.createBuffer(),
                color: gl.createBuffer(),
                alpha: gl.createBuffer()
            };

            this.buffers.background = {
                position: gl.createBuffer()
            };

            // Setup background quad
            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.background.position);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([
                -1, -1,
                 1, -1,
                -1,  1,
                -1,  1,
                 1, -1,
                 1,  1
            ]), gl.STATIC_DRAW);
        }

        createShader(type, source) {
            const gl = this.gl;
            const shader = gl.createShader(type);

            gl.shaderSource(shader, source);
            gl.compileShader(shader);

            if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
                const error = gl.getShaderInfoLog(shader);
                gl.deleteShader(shader);
                throw new Error(`Shader compile error: ${error}`);
            }

            return shader;
        }

        createProgram(vertexSource, fragmentSource) {
            const gl = this.gl;

            const vertexShader = this.createShader(gl.VERTEX_SHADER, vertexSource);
            const fragmentShader = this.createShader(gl.FRAGMENT_SHADER, fragmentSource);

            const program = gl.createProgram();
            gl.attachShader(program, vertexShader);
            gl.attachShader(program, fragmentShader);
            gl.linkProgram(program);

            if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
                const error = gl.getProgramInfoLog(program);
                throw new Error(`Program link error: ${error}`);
            }

            // Clean up shaders
            gl.deleteShader(vertexShader);
            gl.deleteShader(fragmentShader);

            return program;
        }

        setupEventListeners() {
            window.addEventListener('resize', this.handleResize);
            document.addEventListener('visibilitychange', this.handleVisibilityChange);
        }

        setupFallback() {
            this.useFallback = true;

            if (this.canvas) {
                this.canvas.style.background = `
                    linear-gradient(
                        135deg,
                        rgba(255, 0, 102, 0.1) 0%,
                        rgba(0, 179, 179, 0.1) 50%,
                        rgba(139, 92, 246, 0.1) 100%
                    ),
                    linear-gradient(
                        to bottom,
                        #0a0a0f 0%,
                        #0f0f1a 100%
                    )
                `;
                this.canvas.style.backgroundSize = '400% 400%, 100% 100%';
                this.canvas.style.animation = 'visualizerFallback 15s ease infinite';

                // Add fallback animation keyframes
                if (!document.getElementById('visualizer-fallback-styles')) {
                    const style = document.createElement('style');
                    style.id = 'visualizer-fallback-styles';
                    style.textContent = `
                        @keyframes visualizerFallback {
                            0% { background-position: 0% 50%, 0% 0%; }
                            50% { background-position: 100% 50%, 0% 0%; }
                            100% { background-position: 0% 50%, 0% 0%; }
                        }
                    `;
                    document.head.appendChild(style);
                }
            }

            this.isInitialized = true;
        }

        // =================================================================
        // Particle System
        // =================================================================

        initParticles() {
            const count = CONFIG.particles.count;
            this.particles = [];

            for (let i = 0; i < count; i++) {
                this.particles.push(this.createParticle());
            }
        }

        createParticle(fromBottom = false) {
            const width = this.canvas.width;
            const height = this.canvas.height;

            // Choose a color from the palette
            const colorIndex = Math.floor(Math.random() * 3);
            const colorKeys = ['primary', 'secondary', 'tertiary'];
            const color = this.colors[colorKeys[colorIndex]];

            return {
                x: Math.random() * width,
                y: fromBottom ? height + 50 : Math.random() * height,
                vx: (Math.random() - 0.5) * CONFIG.particles.spread,
                vy: -CONFIG.particles.speed * (0.5 + Math.random() * 0.5),
                size: CONFIG.particles.minSize +
                      Math.random() * (CONFIG.particles.maxSize - CONFIG.particles.minSize),
                alpha: 0.3 + Math.random() * 0.7,
                color: [...color],
                life: 1.0,
                decay: 0.001 + Math.random() * 0.002
            };
        }

        updateParticles(deltaTime, audioLevel) {
            const width = this.canvas.width;
            const height = this.canvas.height;
            const intensity = this.intensity;

            // Speed multiplier based on audio
            const speedMult = 1 + audioLevel * 2;

            for (let i = 0; i < this.particles.length; i++) {
                const p = this.particles[i];

                // Update position
                p.x += p.vx * deltaTime * 60 * intensity;
                p.y += p.vy * deltaTime * 60 * speedMult;

                // Add some wave motion
                p.x += Math.sin(p.y * 0.01 + this.getTime() * 0.001) * 0.5 * intensity;

                // Fade out
                p.life -= p.decay * deltaTime * 60;

                // Reset particle if dead or off screen
                if (p.life <= 0 || p.y < -50 || p.x < -50 || p.x > width + 50) {
                    const newP = this.createParticle(true);
                    Object.assign(p, newP);
                }
            }
        }

        // =================================================================
        // Audio Connection
        // =================================================================

        connectAudio(audioElement) {
            try {
                // If already connected to this element, just resume context if needed
                if (this.isAudioConnected && this.connectedElement === audioElement) {
                    if (this.audioContext && this.audioContext.state === 'suspended') {
                        this.audioContext.resume();
                    }
                    return true;
                }

                // Create audio context if needed
                if (!this.audioContext) {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    this.audioContext = new AudioContext();
                }

                // Resume audio context if suspended (autoplay policy)
                if (this.audioContext.state === 'suspended') {
                    this.audioContext.resume();
                }

                // Create analyser only once
                if (!this.analyser) {
                    this.analyser = this.audioContext.createAnalyser();
                    this.analyser.fftSize = CONFIG.audio.fftSize;
                    this.analyser.smoothingTimeConstant = CONFIG.audio.smoothing;
                    this.analyser.minDecibels = CONFIG.audio.minDecibels;
                    this.analyser.maxDecibels = CONFIG.audio.maxDecibels;
                }

                // Connect audio source
                if (audioElement instanceof HTMLMediaElement) {
                    // Check if already connected - MediaElementSource can only be created once per element
                    if (!audioElement._visualizerSource) {
                        audioElement._visualizerSource = this.audioContext.createMediaElementSource(audioElement);
                        // Connect once: source -> analyser -> destination
                        audioElement._visualizerSource.connect(this.analyser);
                        this.analyser.connect(this.audioContext.destination);
                    }
                    this.audioSource = audioElement._visualizerSource;
                } else if (audioElement instanceof MediaStream) {
                    this.audioSource = this.audioContext.createMediaStreamSource(audioElement);
                    this.audioSource.connect(this.analyser);
                    this.analyser.connect(this.audioContext.destination);
                } else {
                    throw new Error('Invalid audio source. Expected HTMLMediaElement or MediaStream');
                }

                // Create frequency data array
                if (!this.frequencyData) {
                    this.frequencyData = new Uint8Array(this.analyser.frequencyBinCount);
                }

                this.isAudioConnected = true;
                this.connectedElement = audioElement;
                console.log('Audio connected to visualizer');

                return true;

            } catch (error) {
                console.error('Failed to connect audio:', error);
                this.isAudioConnected = false;
                return false;
            }
        }

        disconnectAudio() {
            if (this.audioSource) {
                try {
                    this.audioSource.disconnect();
                } catch (e) {
                    // Already disconnected
                }
            }
            this.isAudioConnected = false;
            this.audioSource = null;
        }

        getAudioLevel() {
            if (!this.isAudioConnected || !this.analyser || !this.frequencyData) {
                return 0;
            }

            this.analyser.getByteFrequencyData(this.frequencyData);

            // Calculate average level
            let sum = 0;
            for (let i = 0; i < this.frequencyData.length; i++) {
                sum += this.frequencyData[i];
            }

            return (sum / this.frequencyData.length) / 255;
        }

        getFrequencyBands() {
            if (!this.isAudioConnected || !this.analyser || !this.frequencyData) {
                return { bass: 0, mid: 0, high: 0 };
            }

            this.analyser.getByteFrequencyData(this.frequencyData);

            const length = this.frequencyData.length;
            const bassEnd = Math.floor(length * 0.1);
            const midEnd = Math.floor(length * 0.5);

            let bass = 0, mid = 0, high = 0;

            for (let i = 0; i < length; i++) {
                if (i < bassEnd) {
                    bass += this.frequencyData[i];
                } else if (i < midEnd) {
                    mid += this.frequencyData[i];
                } else {
                    high += this.frequencyData[i];
                }
            }

            return {
                bass: (bass / bassEnd) / 255,
                mid: (mid / (midEnd - bassEnd)) / 255,
                high: (high / (length - midEnd)) / 255
            };
        }

        // =================================================================
        // State Control
        // =================================================================

        setIntensity(level) {
            this.targetIntensity = Math.max(0, Math.min(1, level));
        }

        setColorScheme(colors) {
            if (colors.primary) {
                this.colors.primary = this.hexToRgb(colors.primary);
            }
            if (colors.secondary) {
                this.colors.secondary = this.hexToRgb(colors.secondary);
            }
            if (colors.tertiary) {
                this.colors.tertiary = this.hexToRgb(colors.tertiary);
            }

            // Update existing particles with new colors
            this.particles.forEach(p => {
                const colorIndex = Math.floor(Math.random() * 3);
                const colorKeys = ['primary', 'secondary', 'tertiary'];
                p.color = [...this.colors[colorKeys[colorIndex]]];
            });
        }

        hexToRgb(hex) {
            // Remove # if present
            hex = hex.replace('#', '');

            const r = parseInt(hex.substring(0, 2), 16) / 255;
            const g = parseInt(hex.substring(2, 4), 16) / 255;
            const b = parseInt(hex.substring(4, 6), 16) / 255;

            return [r, g, b];
        }

        // =================================================================
        // Rendering
        // =================================================================

        start() {
            if (!this.isRunning) {
                this.isRunning = true;
                this.lastFrameTime = performance.now();
                requestAnimationFrame(this.render);
            }
        }

        stop() {
            this.isRunning = false;
        }

        getTime() {
            return performance.now() - this.startTime;
        }

        render(timestamp) {
            if (!this.isRunning) return;

            // Calculate delta time
            const deltaTime = (timestamp - this.lastFrameTime) / 1000;
            this.lastFrameTime = timestamp;

            // FPS tracking for performance throttling
            this.frameCount++;
            if (this.frameCount % 60 === 0) {
                this.fps = 1 / deltaTime;
                this.isLowPower = this.fps < CONFIG.performance.lowPowerThreshold;
            }

            // Skip frames on low power devices
            if (this.isLowPower && this.frameCount % 2 !== 0) {
                requestAnimationFrame(this.render);
                return;
            }

            // Handle fallback mode
            if (this.useFallback) {
                requestAnimationFrame(this.render);
                return;
            }

            const gl = this.gl;
            const time = this.getTime();

            // Smooth intensity transition
            this.intensity += (this.targetIntensity - this.intensity) * 0.05;

            // Get audio data
            const audioLevel = this.getAudioLevel();
            const bands = this.getFrequencyBands();

            // Boost intensity based on audio
            const effectiveIntensity = Math.min(1, this.intensity + audioLevel * 0.5);

            // Update simulation
            this.updateParticles(deltaTime, audioLevel);
            this.updateWaveform(audioLevel, bands);

            // Clear canvas
            gl.viewport(0, 0, this.canvas.width, this.canvas.height);
            gl.clearColor(0.02, 0.02, 0.05, 1.0);
            gl.clear(gl.COLOR_BUFFER_BIT);

            // Render layers
            this.renderBackground(time, effectiveIntensity);
            this.renderGrid(time, effectiveIntensity, bands.bass);
            this.renderWaveform(time, effectiveIntensity, bands);
            this.renderParticles(time, effectiveIntensity);

            requestAnimationFrame(this.render);
        }

        renderBackground(time, intensity) {
            const gl = this.gl;
            const program = this.programs.background;

            gl.useProgram(program);

            // Set uniforms
            const timeLocation = gl.getUniformLocation(program, 'u_time');
            const intensityLocation = gl.getUniformLocation(program, 'u_intensity');
            const color1Location = gl.getUniformLocation(program, 'u_color1');
            const color2Location = gl.getUniformLocation(program, 'u_color2');
            const color3Location = gl.getUniformLocation(program, 'u_color3');

            gl.uniform1f(timeLocation, time * 0.001);
            gl.uniform1f(intensityLocation, intensity);
            gl.uniform3fv(color1Location, this.colors.primary);
            gl.uniform3fv(color2Location, this.colors.secondary);
            gl.uniform3fv(color3Location, this.colors.tertiary);

            // Set up position attribute
            const positionLocation = gl.getAttribLocation(program, 'a_position');
            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.background.position);
            gl.enableVertexAttribArray(positionLocation);
            gl.vertexAttribPointer(positionLocation, 2, gl.FLOAT, false, 0, 0);

            // Draw
            gl.drawArrays(gl.TRIANGLES, 0, 6);
        }

        renderGrid(time, intensity, bassLevel) {
            const gl = this.gl;
            const program = this.programs.lines;
            const width = this.canvas.width;
            const height = this.canvas.height;

            gl.useProgram(program);

            // Generate grid lines with perspective
            const positions = [];
            const colors = [];
            const alphas = [];

            const gridLines = CONFIG.grid.lines;
            const perspective = CONFIG.grid.perspective;
            const baseAlpha = CONFIG.grid.opacity * intensity;

            // Horizontal lines (floor grid effect)
            for (let i = 0; i <= gridLines; i++) {
                const t = i / gridLines;
                // Apply perspective - lines get closer together near the top
                const y = height * (1 - Math.pow(1 - t, perspective));

                // Pulsing offset based on bass
                const offset = Math.sin(time * 0.002 + i * 0.5) * 10 * bassLevel;

                positions.push(0, y + offset, width, y + offset);

                // Fade out towards bottom
                const lineAlpha = baseAlpha * (1 - t * 0.7);
                alphas.push(lineAlpha, lineAlpha);

                // Alternate colors
                const color = i % 2 === 0 ? this.colors.secondary : this.colors.tertiary;
                colors.push(...color, ...color);
            }

            // Vertical lines
            const verticalLines = Math.floor(gridLines * 1.5);
            for (let i = 0; i <= verticalLines; i++) {
                const x = (width / verticalLines) * i;

                // Converge towards center at top
                const topX = width / 2 + (x - width / 2) * 0.3;

                positions.push(x, height, topX, height * 0.3);

                const lineAlpha = baseAlpha * 0.5;
                alphas.push(lineAlpha, lineAlpha * 0.1);

                colors.push(...this.colors.primary, ...this.colors.primary);
            }

            // Upload data
            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.position);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(positions), gl.DYNAMIC_DRAW);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.color);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(colors), gl.DYNAMIC_DRAW);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.alpha);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(alphas), gl.DYNAMIC_DRAW);

            // Set uniforms
            const resolutionLocation = gl.getUniformLocation(program, 'u_resolution');
            gl.uniform2f(resolutionLocation, width, height);

            // Set attributes
            const posLocation = gl.getAttribLocation(program, 'a_position');
            const colorLocation = gl.getAttribLocation(program, 'a_color');
            const alphaLocation = gl.getAttribLocation(program, 'a_alpha');

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.position);
            gl.enableVertexAttribArray(posLocation);
            gl.vertexAttribPointer(posLocation, 2, gl.FLOAT, false, 0, 0);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.color);
            gl.enableVertexAttribArray(colorLocation);
            gl.vertexAttribPointer(colorLocation, 3, gl.FLOAT, false, 0, 0);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.alpha);
            gl.enableVertexAttribArray(alphaLocation);
            gl.vertexAttribPointer(alphaLocation, 1, gl.FLOAT, false, 0, 0);

            // Draw lines
            const lineCount = (gridLines + 1 + verticalLines + 1);
            gl.drawArrays(gl.LINES, 0, lineCount * 2);
        }

        updateWaveform(audioLevel, bands) {
            const segments = CONFIG.waveform.segments;

            for (let i = 0; i < segments; i++) {
                const t = i / segments;

                // Base wave
                let value = Math.sin(t * Math.PI * 4 + this.getTime() * 0.002) * 0.3;

                // Add audio reactivity
                if (this.isAudioConnected && this.frequencyData) {
                    const freqIndex = Math.floor(t * this.frequencyData.length);
                    value += (this.frequencyData[freqIndex] / 255) * 0.7;
                } else {
                    // Idle animation
                    value += Math.sin(t * Math.PI * 2 + this.getTime() * 0.001) * 0.2;
                    value += Math.sin(t * Math.PI * 6 + this.getTime() * 0.003) * 0.1;
                }

                // Smooth transition
                this.waveformData[i] += (value - this.waveformData[i]) * 0.3;
            }
        }

        renderWaveform(time, intensity, bands) {
            const gl = this.gl;
            const program = this.programs.lines;
            const width = this.canvas.width;
            const height = this.canvas.height;
            const segments = CONFIG.waveform.segments;

            gl.useProgram(program);

            const positions = [];
            const colors = [];
            const alphas = [];

            const baseY = height * 0.5;
            const amplitude = height * CONFIG.waveform.amplitude * intensity;

            // Draw multiple waveform layers
            const layers = [
                { color: this.colors.primary, offset: 0, alpha: 0.8 },
                { color: this.colors.secondary, offset: Math.PI * 0.5, alpha: 0.5 },
                { color: this.colors.tertiary, offset: Math.PI, alpha: 0.3 }
            ];

            layers.forEach(layer => {
                for (let i = 0; i < segments - 1; i++) {
                    const x1 = (width / segments) * i;
                    const x2 = (width / segments) * (i + 1);

                    const wave1 = this.waveformData[i] +
                                  Math.sin(layer.offset + time * 0.001) * 0.1;
                    const wave2 = this.waveformData[i + 1] +
                                  Math.sin(layer.offset + time * 0.001) * 0.1;

                    const y1 = baseY + wave1 * amplitude;
                    const y2 = baseY + wave2 * amplitude;

                    positions.push(x1, y1, x2, y2);
                    colors.push(...layer.color, ...layer.color);
                    alphas.push(layer.alpha * intensity, layer.alpha * intensity);
                }
            });

            // Upload and draw
            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.position);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(positions), gl.DYNAMIC_DRAW);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.color);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(colors), gl.DYNAMIC_DRAW);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.alpha);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(alphas), gl.DYNAMIC_DRAW);

            const resolutionLocation = gl.getUniformLocation(program, 'u_resolution');
            gl.uniform2f(resolutionLocation, width, height);

            const posLocation = gl.getAttribLocation(program, 'a_position');
            const colorLocation = gl.getAttribLocation(program, 'a_color');
            const alphaLocation = gl.getAttribLocation(program, 'a_alpha');

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.position);
            gl.enableVertexAttribArray(posLocation);
            gl.vertexAttribPointer(posLocation, 2, gl.FLOAT, false, 0, 0);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.color);
            gl.enableVertexAttribArray(colorLocation);
            gl.vertexAttribPointer(colorLocation, 3, gl.FLOAT, false, 0, 0);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.lines.alpha);
            gl.enableVertexAttribArray(alphaLocation);
            gl.vertexAttribPointer(alphaLocation, 1, gl.FLOAT, false, 0, 0);

            gl.drawArrays(gl.LINES, 0, positions.length / 2);
        }

        renderParticles(time, intensity) {
            const gl = this.gl;
            const program = this.programs.particles;
            const width = this.canvas.width;
            const height = this.canvas.height;

            gl.useProgram(program);

            // Prepare particle data
            const positions = [];
            const sizes = [];
            const alphasArr = [];
            const colors = [];

            this.particles.forEach(p => {
                positions.push(p.x, p.y);
                sizes.push(p.size * (0.5 + intensity * 0.5));
                alphasArr.push(p.alpha * p.life);
                colors.push(...p.color);
            });

            // Upload data
            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.particles.position);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(positions), gl.DYNAMIC_DRAW);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.particles.size);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(sizes), gl.DYNAMIC_DRAW);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.particles.alpha);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(alphasArr), gl.DYNAMIC_DRAW);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.particles.color);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(colors), gl.DYNAMIC_DRAW);

            // Set uniforms
            const resolutionLocation = gl.getUniformLocation(program, 'u_resolution');
            const timeLocation = gl.getUniformLocation(program, 'u_time');
            const intensityLocation = gl.getUniformLocation(program, 'u_intensity');

            gl.uniform2f(resolutionLocation, width, height);
            gl.uniform1f(timeLocation, time * 0.001);
            gl.uniform1f(intensityLocation, intensity);

            // Set attributes
            const posLocation = gl.getAttribLocation(program, 'a_position');
            const sizeLocation = gl.getAttribLocation(program, 'a_size');
            const alphaLocation = gl.getAttribLocation(program, 'a_alpha');
            const colorLocation = gl.getAttribLocation(program, 'a_color');

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.particles.position);
            gl.enableVertexAttribArray(posLocation);
            gl.vertexAttribPointer(posLocation, 2, gl.FLOAT, false, 0, 0);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.particles.size);
            gl.enableVertexAttribArray(sizeLocation);
            gl.vertexAttribPointer(sizeLocation, 1, gl.FLOAT, false, 0, 0);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.particles.alpha);
            gl.enableVertexAttribArray(alphaLocation);
            gl.vertexAttribPointer(alphaLocation, 1, gl.FLOAT, false, 0, 0);

            gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.particles.color);
            gl.enableVertexAttribArray(colorLocation);
            gl.vertexAttribPointer(colorLocation, 3, gl.FLOAT, false, 0, 0);

            // Draw particles
            gl.drawArrays(gl.POINTS, 0, this.particles.length);
        }

        // =================================================================
        // Event Handlers
        // =================================================================

        handleResize() {
            if (!this.canvas) return;

            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const width = window.innerWidth;
            const height = window.innerHeight;

            this.canvas.width = width * dpr;
            this.canvas.height = height * dpr;
            this.canvas.style.width = width + 'px';
            this.canvas.style.height = height + 'px';

            // Reinitialize particles for new dimensions
            if (this.particles.length > 0) {
                this.initParticles();
            }
        }

        handleVisibilityChange() {
            if (document.hidden) {
                this.stop();
            } else {
                this.lastFrameTime = performance.now();
                this.start();
            }
        }

        // =================================================================
        // Cleanup
        // =================================================================

        destroy() {
            this.stop();
            this.disconnectAudio();

            window.removeEventListener('resize', this.handleResize);
            document.removeEventListener('visibilitychange', this.handleVisibilityChange);

            if (this.gl) {
                // Clean up WebGL resources
                Object.values(this.programs).forEach(program => {
                    this.gl.deleteProgram(program);
                });

                Object.values(this.buffers).forEach(bufferGroup => {
                    Object.values(bufferGroup).forEach(buffer => {
                        this.gl.deleteBuffer(buffer);
                    });
                });
            }

            if (this.audioContext) {
                this.audioContext.close();
            }

            this.isInitialized = false;
            console.log('PROMPT Visualizer destroyed');
        }
    }

    // =====================================================================
    // Public API
    // =====================================================================

    // Create singleton instance
    const visualizerInstance = new Visualizer();

    /**
     * Initialize the visualizer with a canvas element
     * @param {string} canvasId - The ID of the canvas element
     * @returns {boolean} True if initialization successful
     */
    function initVisualizer(canvasId) {
        return visualizerInstance.init(canvasId);
    }

    /**
     * Connect an audio source for reactive visualization
     * @param {HTMLMediaElement|MediaStream} audioElement - Audio source to analyze
     * @returns {boolean} True if connection successful
     */
    function connectAudio(audioElement) {
        return visualizerInstance.connectAudio(audioElement);
    }

    /**
     * Set the visualization intensity
     * @param {number} level - Intensity level from 0 to 1
     */
    function setIntensity(level) {
        visualizerInstance.setIntensity(level);
    }

    /**
     * Set custom color scheme
     * @param {Object} colors - Object with primary, secondary, tertiary hex colors
     * @example setColorScheme({ primary: '#ff0066', secondary: '#00b3b3', tertiary: '#8b5cf6' })
     */
    function setColorScheme(colors) {
        visualizerInstance.setColorScheme(colors);
    }

    /**
     * Get the visualizer instance for advanced control
     * @returns {Visualizer} The visualizer instance
     */
    function getVisualizer() {
        return visualizerInstance;
    }

    // Export to global scope and as module
    const exports = {
        initVisualizer,
        connectAudio,
        setIntensity,
        setColorScheme,
        getVisualizer,
        Visualizer
    };

    // UMD export
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = exports;
    } else if (typeof define === 'function' && define.amd) {
        define([], function() { return exports; });
    } else {
        global.PROMPTVisualizer = exports;
    }

})(typeof window !== 'undefined' ? window : this);
