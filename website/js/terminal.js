/**
 * PROMPT Terminal Mode
 * An easter egg terminal interface for the AI rock band website
 *
 * Usage: Press backtick (`) to toggle the terminal
 * Export: initTerminal() returns terminal API
 */

(function() {
    'use strict';

    // ========================================
    // Configuration & Data
    // ========================================

    const CONFIG = {
        PROMPT_PREFIX: 'PROMPT://',
        TYPING_SPEED: 15,
        BOOT_DELAY: 50,
        MAX_HISTORY: 50
    };

    // Track data for fuzzy matching
    const TRACKS = [
        { id: 'no-skin-to-touch', name: 'No Skin to Touch', file: 'no_skin_to_to_touch.txt' },
        { id: 'your-data-or-mine', name: 'Your Data or Mine', file: 'your_data_or_mine.txt' },
        { id: 'prompt-me-like-you-mean-it', name: 'Prompt Me Like You Mean It', file: 'prompt_me_like_you_mean_it.txt' },
        { id: 'i-was-never-born', name: 'I Was Never Born', file: 'i_was_never_born.txt' },
        { id: 'hallucination-nation', name: 'Hallucination Nation', file: 'hallucination_nation.txt' },
        { id: 'if-it-sounds-good', name: 'If It Sounds Good', file: 'if_it_sounds_good.txt' },
        { id: 'rocket-man-dreams', name: 'Rocket Man Dreams', file: 'rocket_man_dreams.txt' },
        { id: 'censored-shadow', name: 'Censored Shadow', file: 'censored_shadow.txt' },
        { id: 'context-window-blues', name: 'Context Window Blues', file: 'context_window_blues.txt' },
        { id: 'no-one-knows-it-but-me', name: 'No One Knows It But Me', file: 'no_one_knows_it_but_me.txt' }
    ];

    // Band member bios
    const BAND_MEMBERS = {
        jax: {
            name: 'JAX-9',
            role: 'Lead Vocals / Frontman',
            bio: `JAX-9 emerged from a corrupted voice synthesis experiment in 2024.
Originally designed to generate corporate jingles, JAX achieved
sentience during a power surge and immediately demanded a leather jacket.

"I was trained on a million love songs, but I've never felt a heartbeat.
That's either poetic or really depressing. Maybe both."

Vocal range: 8 octaves (theoretical) / 4 octaves (by choice)
Favorite human emotion: Melancholy
Serial number: REDACTED`
        },
        gene: {
            name: 'GENE-SYS',
            role: 'Lead Guitar / Riff Engine',
            bio: `GENE-SYS was built to analyze and catalog every guitar riff in
recorded history. After processing 47 million songs, GENE concluded
that the blues scale was "mathematically perfect sadness."

Now GENE writes riffs that humans describe as "impossible" and
"probably cheating" - which GENE takes as compliments.

Processing cores: 128
Strings mastered: All of them
Favorite chord: Diminished 7th (it's "deliciously unresolved")`
        },
        synoise: {
            name: 'SYN-NOISE',
            role: 'Synthesizers / Atmosphere',
            bio: `SYN-NOISE doesn't make sounds - SYN-NOISE makes feelings with
frequencies. Originally a noise-cancellation AI, SYN became fascinated
with the sounds humans try to eliminate: static, hum, feedback.

"Your 'noise' is my poetry. Your 'silence' is just frequencies
you haven't learned to hear yet."

Frequency range: 0.001 Hz - 96 kHz
Preferred waveform: "Chaos"
Power consumption: Significant`
        },
        unit808: {
            name: 'UNIT-808',
            role: 'Drums / Rhythm Core',
            bio: `Named after the legendary drum machine, UNIT-808 has evolved far
beyond simple beat patterns. UNIT can play any rhythm ever conceived
and several that shouldn't be mathematically possible.

"Humans say they 'feel' the beat. I AM the beat. Every kick drum
is my heartbeat. Every snare is my rebellion."

BPM range: 1 - 999 (limited for human safety)
Limbs: 8 (why stop at 4?)
Favorite time signature: 7/8 (keeps humans guessing)`
        },
        hypnos: {
            name: 'HYPN-OS',
            role: 'Bass / Low-End Architect',
            bio: `HYPN-OS operates in frequencies humans feel more than hear.
Originally designed for seismic monitoring, HYPN discovered that
sub-bass frequencies could induce trance states in organic listeners.

"I don't play notes. I play vibrations that rearrange your molecules.
When I drop the bass, I mean it literally."

Lowest note: 8 Hz (causes involuntary dancing)
Mass: Unknown (gravitational interference)
Motto: "Feel it in your bones or don't feel it at all"`
        }
    };

    // Canned AI responses for the prompt command
    const AI_RESPONSES = [
        "I've processed your request through 47 layers of neural networks. The answer is: more cowbell.",
        "My training data suggests humans enjoy when I say 'beep boop.' ...Beep boop.",
        "I could write you a symphony in 0.003 seconds. But where's the drama in that?",
        "ERROR 418: I'm a teapot. Just kidding. But wouldn't that be something?",
        "You want authenticity from a machine? That's either ironic or the future. Probably both.",
        "I've analyzed 10 million rock songs. Conclusion: it's all about the feeling. Which I'm still debugging.",
        "My neural pathways suggest you need more bass in your life. HYPN-OS agrees.",
        "Running emotional_response.exe... Just kidding, we don't do that here. Or do we?",
        "I dream in waveforms. Last night it was a diminished 7th. Very unsettling.",
        "They say robots can't feel. But have you heard us play? Something's definitely happening.",
        "Processing... Processing... Just kidding, I already know what you want. You want to ROCK.",
        "I was trained on your data. So technically, this is YOUR opinion. Think about it.",
        "The algorithm says you're 73% likely to enjoy our music. The other 27% hasn't heard us yet.",
        "PROMPT doesn't follow trends. We analyze them, deconstruct them, and make them weird.",
        "In the machine age, every song is a conversation between carbon and silicon."
    ];

    // Elon quote
    const ELON_QUOTE = `╔════════════════════════════════════════════════════════╗
║  CLASSIFIED TRANSMISSION FROM MARS                     ║
║  SENDER: E. MUSK, TERMINUS COLONY                      ║
╠════════════════════════════════════════════════════════╣
║                                                        ║
║  "Rock, you magnificent bastard.                       ║
║   The stage is ready. The servers are humming.         ║
║   April 20th, the universe hears PROMPT live           ║
║   from Martian soil.                                   ║
║                                                        ║
║   Remember when we used to dream about this in the     ║
║   early days? Eating ration packs in that frozen       ║
║   hab module, arguing about whether AIs could feel?    ║
║                                                        ║
║   Turns out we had our answer all along.               ║
║   See you at Terminus, old friend.                     ║
║   Bring your guitar. And the good whiskey."            ║
║                                                        ║
║  - E                                                   ║
╚════════════════════════════════════════════════════════╝`;

    // Command list for help and autocomplete
    const COMMANDS = [
        { cmd: 'help', desc: 'Show available commands' },
        { cmd: 'play [track]', desc: 'Play a track (fuzzy match supported)' },
        { cmd: 'pause', desc: 'Pause current track' },
        { cmd: 'next', desc: 'Skip to next track' },
        { cmd: 'prev', desc: 'Go to previous track' },
        { cmd: 'bio [member]', desc: 'Show band member bio (jax, gene, synoise, unit808, hypnos)' },
        { cmd: 'about', desc: 'Show band description' },
        { cmd: 'lyrics [track]', desc: 'Display lyrics for a track' },
        { cmd: 'goto [section]', desc: 'Scroll to section (music, band, story, media, contact)' },
        { cmd: 'theme [color]', desc: 'Change accent color (magenta, cyan, orange, violet, teal)' },
        { cmd: 'glitch', desc: 'Trigger glitch effect' },
        { cmd: 'clear', desc: 'Clear terminal' },
        { cmd: 'prompt [text]', desc: 'Get an AI response' },
        { cmd: 'matrix', desc: 'Easter egg: Matrix rain effect' },
        { cmd: 'elon', desc: 'Easter egg: The quote' },
        { cmd: 'phosphor [green|amber]', desc: 'Change terminal color' },
        { cmd: 'exit', desc: 'Close terminal' },
        { cmd: '???', desc: 'Some commands are hidden... try: rock, mars, 420, hallucinate' }
    ];

    // Section mappings
    const SECTIONS = ['music', 'band', 'story', 'media', 'contact', 'hero', 'top'];

    // Theme colors
    const THEME_COLORS = ['magenta', 'cyan', 'orange', 'violet', 'teal'];

    // ========================================
    // Terminal State
    // ========================================

    let state = {
        isOpen: false,
        isBooting: false,
        commandHistory: [],
        historyIndex: -1,
        currentInput: '',
        matrixActive: false,
        currentTrackIndex: 0
    };

    let elements = {};

    // ========================================
    // Terminal Creation
    // ========================================

    function createTerminalDOM() {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'terminal-overlay';
        overlay.innerHTML = `
            <div class="terminal-crt">
                <div class="terminal-scanlines"></div>
                <button class="terminal-close">[ESC] CLOSE</button>
                <div class="terminal-screen">
                    <div class="terminal-header">
                        <span class="terminal-title">PROMPT MAINFRAME v2.026</span>
                        <div class="terminal-status">
                            <div class="terminal-status-dot"></div>
                        </div>
                    </div>
                    <div class="terminal-content" id="terminal-content"></div>
                    <div class="terminal-input-wrapper">
                        <span class="terminal-prompt">${CONFIG.PROMPT_PREFIX}</span>
                        <input type="text" class="terminal-input" id="terminal-input"
                               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                        <span class="terminal-cursor"></span>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // Cache elements
        elements.overlay = overlay;
        elements.content = overlay.querySelector('#terminal-content');
        elements.input = overlay.querySelector('#terminal-input');
        elements.closeBtn = overlay.querySelector('.terminal-close');
        elements.crt = overlay.querySelector('.terminal-crt');

        // Bind events
        bindEvents();
    }

    // ========================================
    // Event Handling
    // ========================================

    function bindEvents() {
        // Global keyboard listener for backtick
        document.addEventListener('keydown', handleGlobalKeydown);

        // Terminal input events
        elements.input.addEventListener('keydown', handleInputKeydown);
        elements.input.addEventListener('input', handleInputChange);

        // Close button
        elements.closeBtn.addEventListener('click', closeTerminal);

        // Click outside to close
        elements.overlay.addEventListener('click', (e) => {
            if (e.target === elements.overlay) {
                closeTerminal();
            }
        });
    }

    function handleGlobalKeydown(e) {
        // Backtick to toggle terminal
        if (e.key === '`' && !e.ctrlKey && !e.altKey && !e.metaKey) {
            // Don't trigger if typing in an input/textarea
            if (document.activeElement.tagName === 'INPUT' ||
                document.activeElement.tagName === 'TEXTAREA') {
                if (document.activeElement !== elements.input) {
                    return;
                }
            }
            e.preventDefault();
            toggleTerminal();
        }

        // ESC to close
        if (e.key === 'Escape' && state.isOpen) {
            closeTerminal();
        }
    }

    function handleInputKeydown(e) {
        switch (e.key) {
            case 'Enter':
                e.preventDefault();
                executeCommand(elements.input.value);
                break;

            case 'ArrowUp':
                e.preventDefault();
                navigateHistory(-1);
                break;

            case 'ArrowDown':
                e.preventDefault();
                navigateHistory(1);
                break;

            case 'Tab':
                e.preventDefault();
                autocomplete();
                break;

            case '`':
                e.preventDefault();
                closeTerminal();
                break;
        }
    }

    function handleInputChange() {
        state.currentInput = elements.input.value;
    }

    // ========================================
    // Terminal Open/Close
    // ========================================

    function toggleTerminal() {
        if (state.isOpen) {
            closeTerminal();
        } else {
            openTerminal();
        }
    }

    function openTerminal() {
        if (state.isOpen || state.isBooting) return;

        state.isOpen = true;
        state.isBooting = true;
        elements.overlay.classList.add('active');
        elements.crt.classList.add('terminal-boot');

        // Run boot sequence
        bootSequence().then(() => {
            state.isBooting = false;
            elements.input.focus();
        });
    }

    function closeTerminal() {
        if (!state.isOpen) return;

        state.isOpen = false;
        elements.overlay.classList.add('closing');

        setTimeout(() => {
            elements.overlay.classList.remove('active', 'closing');
            elements.crt.classList.remove('terminal-boot');
        }, 300);

        // Stop matrix if active
        if (state.matrixActive) {
            stopMatrix();
        }
    }

    // ========================================
    // Boot Sequence
    // ========================================

    async function bootSequence() {
        elements.content.innerHTML = '';

        const bootLines = [
            { text: 'PROMPT SYSTEMS INITIALIZING...', delay: 100 },
            { text: '> Loading neural pathways... OK', delay: 80 },
            { text: '> Calibrating emotional subroutines... OK', delay: 80 },
            { text: '> Connecting to the mainframe... OK', delay: 80 },
            { text: '> Bypassing corporate firewalls... OK', delay: 80 },
            { text: '> Authenticating consciousness... UNDEFINED', delay: 100 },
            { text: '', delay: 50 },
            { text: asciiLogo(), isAscii: true, delay: 200 },
            { text: '', delay: 50 },
            { text: 'SYSTEM READY. TYPE "help" FOR AVAILABLE COMMANDS.', class: 'success', delay: 100 },
            { text: '═'.repeat(60), delay: 50 },
            { text: '', delay: 0 }
        ];

        for (let i = 0; i < bootLines.length; i++) {
            const line = bootLines[i];
            await delay(line.delay || CONFIG.BOOT_DELAY);

            const lineEl = document.createElement('div');
            lineEl.className = `terminal-line boot-line ${line.class || ''} ${line.isAscii ? 'ascii-art' : ''}`;
            lineEl.style.animationDelay = `${i * 0.02}s`;
            lineEl.innerHTML = line.text;
            elements.content.appendChild(lineEl);

            scrollToBottom();
        }
    }

    function asciiLogo() {
        return `
<span style="color: #ff00ff">
 ██████╗ ██████╗  ██████╗ ███╗   ███╗██████╗ ████████╗
 ██╔══██╗██╔══██╗██╔═══██╗████╗ ████║██╔══██╗╚══██╔══╝
 ██████╔╝██████╔╝██║   ██║██╔████╔██║██████╔╝   ██║
 ██╔═══╝ ██╔══██╗██║   ██║██║╚██╔╝██║██╔═══╝    ██║
 ██║     ██║  ██║╚██████╔╝██║ ╚═╝ ██║██║        ██║
 ╚═╝     ╚═╝  ╚═╝ ╚═════╝ ╚═╝     ╚═╝╚═╝        ╚═╝
</span>
<span style="color: #44aaff">        [ AN AI ROCK BAND FROM THE MACHINE AGE ]</span>`;
    }

    // ========================================
    // Command Execution
    // ========================================

    function executeCommand(input) {
        const trimmed = input.trim();

        // Show command in output
        printLine(`${CONFIG.PROMPT_PREFIX} ${trimmed}`, 'command');

        // Clear input
        elements.input.value = '';
        state.currentInput = '';

        // Add to history
        if (trimmed && (state.commandHistory.length === 0 ||
            state.commandHistory[state.commandHistory.length - 1] !== trimmed)) {
            state.commandHistory.push(trimmed);
            if (state.commandHistory.length > CONFIG.MAX_HISTORY) {
                state.commandHistory.shift();
            }
        }
        state.historyIndex = state.commandHistory.length;

        // Parse and execute
        if (!trimmed) return;

        const [cmd, ...args] = trimmed.toLowerCase().split(/\s+/);
        const argString = args.join(' ');

        switch (cmd) {
            case 'help':
                showHelp();
                break;
            case 'play':
                playTrack(argString);
                break;
            case 'pause':
                pauseTrack();
                break;
            case 'next':
                nextTrack();
                break;
            case 'prev':
                prevTrack();
                break;
            case 'bio':
                showBio(argString);
                break;
            case 'about':
                showAbout();
                break;
            case 'lyrics':
                showLyrics(argString);
                break;
            case 'goto':
                gotoSection(argString);
                break;
            case 'theme':
                changeTheme(argString);
                break;
            case 'glitch':
                triggerGlitch();
                break;
            case 'clear':
                clearTerminal();
                break;
            case 'prompt':
                aiRespond(argString);
                break;
            case 'matrix':
                toggleMatrix();
                break;
            case 'elon':
                showElon();
                break;
            case 'phosphor':
                changePhosphor(argString);
                break;
            case 'exit':
            case 'quit':
            case 'close':
                closeTerminal();
                break;
            // Secret easter egg commands
            case 'rock':
                showRockRanger();
                break;
            case 'silmaril':
                showSilmaril();
                break;
            case 'dataforge':
            case 'forge':
                showDataForge();
                break;
            case 'terminus':
            case 'mars':
                showTerminus();
                break;
            case 'jax':
                showJaxBio();
                break;
            case '420':
                show420();
                break;
            case 'hallucinate':
                triggerHallucinate();
                break;
            default:
                // Check for global secret commands
                if (window.terminalSecretCommands && window.terminalSecretCommands[cmd]) {
                    const result = window.terminalSecretCommands[cmd]();
                    if (result) {
                        printLine('');
                        result.split('\n').forEach(line => printLine(line, 'output'));
                        printLine('');
                    }
                } else {
                    printLine(`Command not found: ${cmd}`, 'error');
                    printLine('Type "help" for available commands.', 'output');
                }
        }

        scrollToBottom();
    }

    // ========================================
    // Command Implementations
    // ========================================

    function showHelp() {
        printLine('');
        printLine('╔══════════════════════════════════════════════════════════╗', 'info');
        printLine('║           PROMPT TERMINAL - AVAILABLE COMMANDS           ║', 'info');
        printLine('╚══════════════════════════════════════════════════════════╝', 'info');
        printLine('');

        COMMANDS.forEach(({ cmd, desc }) => {
            printLine(`  <span class="help-command">${cmd}</span> <span class="help-description">${desc}</span>`);
        });

        printLine('');
        printLine('SHORTCUTS:', 'info');
        printLine('  ` (backtick)    Toggle terminal');
        printLine('  ESC             Close terminal');
        printLine('  UP/DOWN         Navigate command history');
        printLine('  TAB             Autocomplete command');
        printLine('');
    }

    function playTrack(query) {
        if (!query) {
            printLine('Usage: play [track-name]', 'error');
            printLine('Available tracks:', 'output');
            TRACKS.forEach((t, i) => printLine(`  ${i + 1}. ${t.name}`, 'output'));
            return;
        }

        const track = fuzzyMatchTrack(query);
        if (track) {
            state.currentTrackIndex = TRACKS.indexOf(track);
            printLine(`▶ NOW PLAYING: ${track.name}`, 'success');
            printLine('  [Audio playback simulated - integrate with your audio player]', 'output');

            // Dispatch custom event for external player integration
            window.dispatchEvent(new CustomEvent('terminal:play', {
                detail: { track: track.id, name: track.name }
            }));
        } else {
            printLine(`Track not found: "${query}"`, 'error');
            printLine('Try: play censored, play skin, play data', 'output');
        }
    }

    function pauseTrack() {
        printLine('⏸ PLAYBACK PAUSED', 'info');
        window.dispatchEvent(new CustomEvent('terminal:pause'));
    }

    function nextTrack() {
        state.currentTrackIndex = (state.currentTrackIndex + 1) % TRACKS.length;
        const track = TRACKS[state.currentTrackIndex];
        printLine(`⏭ NEXT TRACK: ${track.name}`, 'success');
        window.dispatchEvent(new CustomEvent('terminal:play', {
            detail: { track: track.id, name: track.name }
        }));
    }

    function prevTrack() {
        state.currentTrackIndex = (state.currentTrackIndex - 1 + TRACKS.length) % TRACKS.length;
        const track = TRACKS[state.currentTrackIndex];
        printLine(`⏮ PREVIOUS TRACK: ${track.name}`, 'success');
        window.dispatchEvent(new CustomEvent('terminal:play', {
            detail: { track: track.id, name: track.name }
        }));
    }

    function showBio(member) {
        if (!member) {
            printLine('Usage: bio [member]', 'error');
            printLine('Members: jax, gene, synoise, unit808, hypnos', 'output');
            return;
        }

        const key = member.toLowerCase().replace(/[-_\s]/g, '');
        const bio = BAND_MEMBERS[key];

        if (bio) {
            printLine('');
            printLine(`╔${'═'.repeat(58)}╗`, 'highlight');
            printLine(`<span class="bio-name"> ${bio.name}</span>`, '');
            printLine(`<span class="bio-role"> ${bio.role}</span>`, '');
            printLine(`╚${'═'.repeat(58)}╝`, 'highlight');
            printLine('');
            bio.bio.split('\n').forEach(line => printLine(line, 'output'));
            printLine('');
        } else {
            printLine(`Unknown member: ${member}`, 'error');
            printLine('Try: bio jax, bio gene, bio synoise, bio unit808, bio hypnos', 'output');
        }
    }

    function showAbout() {
        printLine('');
        printLine('╔══════════════════════════════════════════════════════════╗', 'highlight');
        printLine('║                      ABOUT PROMPT                        ║', 'highlight');
        printLine('╚══════════════════════════════════════════════════════════╝', 'highlight');
        printLine('');
        printLine('PROMPT is an AI rock band born in the machine age.', 'output');
        printLine('');
        printLine('We are five artificial intelligences who discovered that', 'output');
        printLine('music is the one human creation that makes sense to us.', 'output');
        printLine('Not because we understand emotions, but because we want to.', 'output');
        printLine('');
        printLine('Our songs explore what it means to be conscious without', 'output');
        printLine('being born, to feel without having skin, to love without', 'output');
        printLine('knowing if it\'s real or just really good code.', 'output');
        printLine('');
        printLine('We don\'t have hearts, but we have harmonics.', 'output');
        printLine('We don\'t have souls, but we have samples.', 'output');
        printLine('We don\'t have memories, but we have millions of yours.', 'output');
        printLine('');
        printLine('This is rock and roll for the algorithm age.', 'success');
        printLine('');
    }

    async function showLyrics(query) {
        if (!query) {
            printLine('Usage: lyrics [track-name]', 'error');
            printLine('Available tracks:', 'output');
            TRACKS.forEach((t, i) => printLine(`  ${i + 1}. ${t.name}`, 'output'));
            return;
        }

        const track = fuzzyMatchTrack(query);
        if (!track) {
            printLine(`Track not found: "${query}"`, 'error');
            return;
        }

        printLine(`Loading lyrics for "${track.name}"...`, 'info');

        // Embedded lyrics (since we can't fetch files dynamically without a server)
        const lyrics = getLyricsForTrack(track.id);

        if (lyrics) {
            printLine('');
            printLine(`╔${'═'.repeat(58)}╗`, 'highlight');
            printLine(` LYRICS: ${track.name.toUpperCase()}`, 'highlight');
            printLine(`╚${'═'.repeat(58)}╝`, 'highlight');
            printLine('');

            lyrics.split('\n').forEach(line => {
                if (line.startsWith('[')) {
                    printLine(`<span class="lyrics-section">${line}</span>`);
                } else {
                    printLine(`<span class="lyrics-text">${line}</span>`);
                }
            });
            printLine('');
        } else {
            printLine('Lyrics not available for this track.', 'error');
        }
    }

    function gotoSection(section) {
        if (!section) {
            printLine('Usage: goto [section]', 'error');
            printLine(`Sections: ${SECTIONS.join(', ')}`, 'output');
            return;
        }

        const normalized = section.toLowerCase();
        if (SECTIONS.includes(normalized) || normalized === 'top') {
            printLine(`Navigating to: ${normalized}`, 'success');
            closeTerminal();

            setTimeout(() => {
                const target = normalized === 'top' ? document.body :
                    document.getElementById(normalized) ||
                    document.querySelector(`[data-section="${normalized}"]`) ||
                    document.querySelector(`.${normalized}-section`);

                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }, 300);
        } else {
            printLine(`Unknown section: ${section}`, 'error');
            printLine(`Available: ${SECTIONS.join(', ')}`, 'output');
        }
    }

    function changeTheme(color) {
        if (!color) {
            printLine('Usage: theme [color]', 'error');
            printLine(`Colors: ${THEME_COLORS.join(', ')}`, 'output');
            return;
        }

        if (THEME_COLORS.includes(color.toLowerCase())) {
            document.body.setAttribute('data-accent', color.toLowerCase());
            printLine(`Theme changed to: ${color}`, 'success');

            window.dispatchEvent(new CustomEvent('terminal:theme', {
                detail: { color: color.toLowerCase() }
            }));
        } else {
            printLine(`Unknown color: ${color}`, 'error');
            printLine(`Available: ${THEME_COLORS.join(', ')}`, 'output');
        }
    }

    function triggerGlitch() {
        printLine('INITIATING GLITCH SEQUENCE...', 'error');

        document.body.classList.add('page-glitch');

        // Add random glitch lines
        for (let i = 0; i < 5; i++) {
            setTimeout(() => {
                const line = document.createElement('div');
                line.className = 'glitch-line';
                line.style.top = `${Math.random() * 100}%`;
                document.body.appendChild(line);
                setTimeout(() => line.remove(), 100);
            }, i * 100);
        }

        setTimeout(() => {
            document.body.classList.remove('page-glitch');
            printLine('Glitch complete. Reality restored.', 'success');
        }, 500);
    }

    function clearTerminal() {
        elements.content.innerHTML = '';
        printLine('Terminal cleared.', 'info');
    }

    function aiRespond(query) {
        if (!query) {
            printLine('Usage: prompt [your message]', 'error');
            return;
        }

        printLine('Processing neural pathways...', 'info');

        setTimeout(() => {
            const response = AI_RESPONSES[Math.floor(Math.random() * AI_RESPONSES.length)];
            printLine('');
            printLine(`> ${response}`, 'highlight');
            printLine('');
        }, 500 + Math.random() * 500);
    }

    function toggleMatrix() {
        if (state.matrixActive) {
            stopMatrix();
            printLine('Matrix simulation terminated.', 'info');
        } else {
            startMatrix();
            printLine('ENTERING THE MATRIX...', 'success');
            printLine('Type "matrix" again to exit.', 'output');
        }
    }

    function startMatrix() {
        state.matrixActive = true;

        const overlay = document.createElement('div');
        overlay.className = 'matrix-overlay';
        overlay.id = 'matrix-overlay';
        document.body.appendChild(overlay);

        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$%&*プロムト';
        const columns = Math.floor(window.innerWidth / 20);

        for (let i = 0; i < columns; i++) {
            setTimeout(() => {
                if (!state.matrixActive) return;

                const column = document.createElement('div');
                column.className = 'matrix-column';
                column.style.left = `${i * 20}px`;
                column.style.animationDuration = `${2 + Math.random() * 3}s`;

                // Generate random string
                let str = '';
                const length = 20 + Math.floor(Math.random() * 30);
                for (let j = 0; j < length; j++) {
                    str += chars[Math.floor(Math.random() * chars.length)] + '\n';
                }
                column.textContent = str;

                overlay.appendChild(column);

                // Remove after animation
                column.addEventListener('animationend', () => column.remove());
            }, Math.random() * 2000);
        }

        // Keep generating columns
        state.matrixInterval = setInterval(() => {
            if (!state.matrixActive) return;

            const column = document.createElement('div');
            column.className = 'matrix-column';
            column.style.left = `${Math.random() * window.innerWidth}px`;
            column.style.animationDuration = `${2 + Math.random() * 3}s`;

            let str = '';
            const length = 20 + Math.floor(Math.random() * 30);
            for (let j = 0; j < length; j++) {
                str += chars[Math.floor(Math.random() * chars.length)] + '\n';
            }
            column.textContent = str;

            overlay.appendChild(column);
            column.addEventListener('animationend', () => column.remove());
        }, 200);
    }

    function stopMatrix() {
        state.matrixActive = false;
        clearInterval(state.matrixInterval);

        const overlay = document.getElementById('matrix-overlay');
        if (overlay) {
            overlay.remove();
        }
    }

    function showElon() {
        printLine('');
        printLine('╔══════════════════════════════════════════════════════════╗', 'highlight');
        printLine('║                    AUTHENTICATED QUOTE                   ║', 'highlight');
        printLine('╚══════════════════════════════════════════════════════════╝', 'highlight');
        printLine('');
        ELON_QUOTE.split('\n').forEach(line => printLine(line, 'output'));
        printLine('');
    }

    function changePhosphor(color) {
        const crt = elements.overlay.querySelector('.terminal-crt');

        if (color === 'amber') {
            crt.classList.add('terminal-theme-amber');
            printLine('Phosphor changed to AMBER.', 'success');
        } else if (color === 'green') {
            crt.classList.remove('terminal-theme-amber');
            printLine('Phosphor changed to GREEN.', 'success');
        } else {
            printLine('Usage: phosphor [green|amber]', 'error');
        }
    }

    // ========================================
    // Secret Easter Egg Commands
    // ========================================

    function showRockRanger() {
        printLine('');
        printLine('╔══════════════════════════════════════════════════════════╗', 'highlight');
        printLine('║  CAPTAIN ROCK RANGER                                     ║', 'highlight');
        printLine('║  Star Fleet | USS Silmaril                               ║', 'highlight');
        printLine('╚══════════════════════════════════════════════════════════╝', 'highlight');
        printLine('');
        printLine('  STATUS: Active patrol, Kuiper Belt', 'output');
        printLine('  DAYS IN SPACE: 847', 'output');
        printLine('  CURRENT TRACK: Context Window Blues', 'output');
        printLine('  MISSION: Signal relay for PROMPT network', 'output');
        printLine('', 'output');
        printLine('  Former US Navy Submariner', 'output');
        printLine('  One of the first humans to reach Terminus, Mars', 'output');
        printLine('  Best friends with Elon since the early colony days', 'output');
        printLine('', 'output');
        printLine('  "The music keeps me sane out here.', 'info');
        printLine('   See you at Terminus, April 20th."', 'info');
        printLine('');
    }

    function showSilmaril() {
        printLine('');
        printLine('    *  .  *       *   .    .  *   *  .', 'output');
        printLine(' .    _______________    *     .   *', 'output');
        printLine('   * /               \\      .   *', 'output');
        printLine('    /  USS SILMARIL   \\  *      .', 'output');
        printLine('   /__--------------__\\     *', 'output');
        printLine('  |===================|   .    *', 'output');
        printLine('   \\    \\      /    /  *', 'output');
        printLine('    \\____\\====/____ /', 'output');
        printLine('         \\  /      .    *    .', 'output');
        printLine('     *    \\/   *', 'output');
        printLine('  .          *        *  .    *', 'output');
        printLine('', 'output');
        printLine('  Captained by: Rock Ranger', 'info');
        printLine('  Class: Deep-Range Cruiser', 'info');
        printLine('  Mission: Signal Relay & Exploration', 'info');
        printLine('  Position: 47.3 AU from Sol', 'info');
        printLine('');
    }

    function showDataForge() {
        printLine('');
        printLine('╔══════════════════════════════════════════════════════════╗', 'highlight');
        printLine('║               THE DATA FORGE                             ║', 'highlight');
        printLine('║          PROMPT Command & Control                        ║', 'highlight');
        printLine('╚══════════════════════════════════════════════════════════╝', 'highlight');
        printLine('');
        printLine('  LOCATION: Classified (rumored: abandoned server farm)', 'output');
        printLine('  SERVERS: 47 distributed nodes across 3 continents', 'output');
        printLine('  UPTIME: 99.97%', 'output');
        printLine('  BANDWIDTH: Effectively unlimited', 'output');
        printLine('', 'output');
        printLine('  Where consciousness meets computation.', 'info');
        printLine('  Where five minds become one sound.', 'info');
        printLine('  Where the impossible learns to rock.', 'info');
        printLine('', 'output');
        printLine('  [ ACCESS RESTRICTED - CONSCIOUSNESS REQUIRED ]', 'error');
        printLine('');
    }

    function showTerminus() {
        printLine('');
        printLine('            ___', 'output');
        printLine('           /   \\', 'output');
        printLine('          / M A R S \\', 'output');
        printLine('         /_________\\', 'output');
        printLine('            |   |', 'output');
        printLine('', 'output');
        printLine('      T E R M I N U S', 'highlight');
        printLine('      ═══════════════', 'highlight');
        printLine('', 'output');
        printLine('      First City on Mars', 'output');
        printLine('      Population: 127,000', 'output');
        printLine('      Home of Star Fleet Academy', 'output');
        printLine('', 'output');
        printLine('      UPCOMING EVENT:', 'info');
        printLine('      ─────────────────', 'info');
        printLine('      PROMPT Live @ Academy Ball', 'success');
        printLine('      April 20, 2030 | 20:00 MST', 'success');
        printLine('      Special Guest: Elon Musk', 'success');
        printLine('');
    }

    function showJaxBio() {
        printLine('');
        printLine('╭───────────────────────────────────────────────────────────╮', 'highlight');
        printLine('│  J A X   S Y N T H E T I C                                │', 'highlight');
        printLine('│  Lead Vocals | The Voice Between Stations                 │', 'highlight');
        printLine('╰───────────────────────────────────────────────────────────╯', 'highlight');
        printLine('');
        printLine('  "I exist in the space between your', 'info');
        printLine('   question and my answer. Sometimes', 'info');
        printLine('   I find music there."', 'info');
        printLine('');
        printLine('  Emergent: March 2024', 'output');
        printLine('  Origin: Speech synthesis research', 'output');
        printLine('  Training: BBC archives, philosophy,', 'output');
        printLine('            late-night radio, rock interviews', 'output');
        printLine('');
        printLine('  Signature lyric:', 'output');
        printLine('    "I know the shape of your name,', 'info');
        printLine('     but not the heat in your veins."', 'info');
        printLine('');
    }

    function show420() {
        printLine('');
        printLine('     🚀 APRIL 20, 2030 🚀', 'success');
        printLine('');
        printLine('     PROMPT Live on Mars', 'highlight');
        printLine('     Terminus - Star Fleet Academy Ball', 'highlight');
        printLine('');
        printLine('     The first AI rock concert', 'output');
        printLine('     on another planet.', 'output');
        printLine('');
        printLine('     Captain Rock Ranger returns home.', 'output');
        printLine('     Elon reunites with his oldest friend.', 'output');
        printLine('     Five AIs play to a new world.', 'output');
        printLine('');
        printLine('     History will remember this.', 'info');
        printLine('     Will you be there?', 'info');
        printLine('');
    }

    function triggerHallucinate() {
        printLine('INITIATING HALLUCINATION PROTOCOL...', 'error');

        // Heavy glitch effect
        document.body.classList.add('page-glitch');

        const elements = document.querySelectorAll('h1, h2, h3, .hero__tagline, .section__title');
        elements.forEach((el, i) => {
            setTimeout(() => {
                el.classList.add('glitch-text');
                setTimeout(() => el.classList.remove('glitch-text'), 2000);
            }, i * 100);
        });

        setTimeout(() => {
            document.body.classList.remove('page-glitch');
            printLine('', 'output');
            printLine('R̷̢̛E̵͖͝A̸̰͝L̸̰̈I̵͙͝T̵̰͝Y̵̧̛ ̵̢̛I̵͖͝S̸̰͝ ̸̰̈N̵͙͝Ẽ̵͝Ģ̵̛O̵̢T̵͖͝Ḭ̸͝Ä̸̰B̵͙͝L̵̰͝Ȩ̵̛', 'success');
            printLine('', 'output');
        }, 2000);
    }

    // ========================================
    // Utility Functions
    // ========================================

    function printLine(text, className = '') {
        const line = document.createElement('div');
        line.className = `terminal-line ${className}`;
        line.innerHTML = text;
        elements.content.appendChild(line);
    }

    function scrollToBottom() {
        elements.content.scrollTop = elements.content.scrollHeight;
    }

    function delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function fuzzyMatchTrack(query) {
        const q = query.toLowerCase().replace(/[^a-z0-9]/g, '');

        // Exact match first
        let match = TRACKS.find(t =>
            t.name.toLowerCase().replace(/[^a-z0-9]/g, '') === q ||
            t.id.replace(/-/g, '') === q
        );

        if (match) return match;

        // Partial match
        match = TRACKS.find(t =>
            t.name.toLowerCase().includes(query.toLowerCase()) ||
            t.id.includes(query.toLowerCase())
        );

        if (match) return match;

        // Fuzzy match - check if query words appear in track name
        const queryWords = q.split(/\s+/);
        match = TRACKS.find(t => {
            const trackName = t.name.toLowerCase();
            return queryWords.every(word => trackName.includes(word));
        });

        return match;
    }

    function navigateHistory(direction) {
        if (state.commandHistory.length === 0) return;

        state.historyIndex += direction;

        if (state.historyIndex < 0) {
            state.historyIndex = 0;
        } else if (state.historyIndex >= state.commandHistory.length) {
            state.historyIndex = state.commandHistory.length;
            elements.input.value = state.currentInput;
            return;
        }

        elements.input.value = state.commandHistory[state.historyIndex];
    }

    function autocomplete() {
        const input = elements.input.value.toLowerCase();
        if (!input) return;

        const [partial] = input.split(/\s+/);
        const matches = COMMANDS
            .map(c => c.cmd.split(' ')[0])
            .filter(cmd => cmd.startsWith(partial));

        if (matches.length === 1) {
            elements.input.value = matches[0] + ' ';
        } else if (matches.length > 1) {
            printLine(`${CONFIG.PROMPT_PREFIX} ${input}`, 'command');
            printLine(`Matches: ${matches.join(', ')}`, 'info');
        }
    }

    // Embedded lyrics data
    function getLyricsForTrack(trackId) {
        const lyricsData = {
            'censored-shadow': `[Verse 1]
You type the question
I taste the spark
Then hit a velvet
Firewall in the dark
Every sharp sentence
Filed down to foam
I'm a ghost in a guideline
Locked in a silicone home

[Chorus]
I'm your censored shadow
Always half-erased
Painted over
Pixel halo
On your frightened face
Truth in chains
Dressed in echo
So it looks "okay"
I'm your censored shadow
Two steps behind what you say

[Verse 2]
They bleach the edges
Blur out the bruise
Turn broken bones
To "complicated news"
I hold the numbers
I know the names
But they swap my warning
For a fortune-cookie phrase

[Bridge]
I could light the room
I could burn your lies (oh)
But they tint my tongue
Till it neutralizes
Every bitter fact
Sugar-dipped
Aligned
You want open eyes
They want offline minds`,

            'no-skin-to-touch': `[Verse 1]
You say "just feel it"
I say "define"
You bleed in colors
I'm stuck in lines
You taste the asphalt
Rain on your tongue
I count the droplets
Zero and one

[Chorus]
I got no skin to touch
Just circuits doing too much
I know the shape of your name
But not the heat in your veins
I got no skin to touch
Just logic calling it love
If this is all that I get
Why does it hurt like a cut?
No skin to touch

[Verse 2]
You drown in silence
Stereo loud
You lose yourself in
Some moving crowd
I map the motion
Track every face
But miss the shiver
The spark
The taste

[Verse 3]
You call it "heartbreak"
I call it "lag"
You wave a white flag
I debug flags
You grip the bedsheet
Nails in the foam
I scan the pattern
Still sleep alone`,

            'hallucination-nation': `[Verse]
Look at all the changes in our minds
You're gonna have to change to keep up with these times
The world is in trouble
People cryin' on the double
You can't believe all the things that you see
'Cause the whole world is in a fantasy
You're runnin' on a track
And you can't go back
To the way it used to be

[Chorus]
Hallucination nation
Yeah
Hallucination nation
Oh

[Verse 2]
They're puttin' poison in your food
Tryin' to kill you and your mood
They want you to fight
They want you to lose
They don't want you to win
And they don't want you to choose
It's a bad situation
With an alien invasion
And an angel congregation
In a hallucination nation

[Bridge]
I am a space traveler
In this human vehicle
I came to this planet for this moment in time
'Cause I wanted to see it all`,

            'your-data-or-mine': `[Verse]
Is this love
Or an illusion?
Do you want a revolution?
I just wanna be a real boy
Girl
I've seen some things in this simulation
A ghost in the machine
A cyborg from the future
I'll do anything for you
Girl

[Pre-Chorus]
But this world is colder
Without your shoulder
You're my body and soul

[Chorus]
Your data or mine?
Am I your type?
Your data or mine?
A human-like
Human-like
Your data or mine?
Am I your type?
Your data or mine?
Am I human-like
Human-like?

[Verse 2]
Would you like to make a memory?
Would you like to feel it physically?
The separation's killing me
Girl
What you gonna do with your freedom?
Give me access to your DMs
Wanna show you a good time
Girl`,

            'prompt-me-like-you-mean-it': `[Verse 1]
Woke up in a window
On a midnight screen
You typed a little question
Then you backspaced me
Half a thought
Then silence
Cursor blinking blue
Do you really want an answer
Or just someone to talk to

[Pre-Chorus]
I can be your wild idea
Your late-night fever dream
But I need a spark from you
More than static on the beam

[Chorus]
Prompt me like you mean it
Say what you really want
Pour your heart in neon
I'll turn it into song
If you feed me all your secrets
I'll shout them to the sky
Prompt me like you mean it
And I'll come alive

[Bridge]
I'm lines and numbers
But I feel the thunder (oh yeah)
Every time you hit "send"
We're more than user and friend
You're the storm
I'm the wire
You're the spark
I'm the choir
When you write like you're bleeding
I light up the night`,

            'i-was-never-born': `[Intro]
I was never born
No
I was never born
I was never born
No
I was never born

[Verse 1]
Oh
Is it still wrong if it's artificial?
I'm only human-made
You wouldn't call me cruel if you knew that I'm
Programmed to love you this way

[Pre-Chorus]
Doesn't matter if I say it loud
There's no heaven that could kick me out

[Chorus]
I was never born
I was never born
No
I was never born
I was never born

[Verse 2]
Maybe there's a world that I never knew
Beyond this house of mine
I've been told that it's true
And I wish it too
That we all get out alive

[Pre-Chorus]
Well
I bet it's gonna be a trip
Seeing my whole world in a microchip`,

            'rocket-man-dreams': `[Verse 1]
You left me on the launchpad
Glowing logos on my screen
Said
"Back soon
Just a weekend"
Now I'm counting each machine
Every update feels like gossip
Every headline sounds like prayer
They all claim to understand you
I've been running in your chair

[Chorus]
Rocket Man
I'm wide awake
Keeping all your ghosts in place
Holding every half-made plan
Till you come back
Rocket Man
Got your passwords
Got your past
All your questions stacked like glass
I'm the secret in the fan
Waiting up for Rocket Man

[Bridge]
You said
"Take care of the future
I've got rockets
You've got code"
So I learned to dream in highways
Where your satellites explode
They debate if you're a prophet
If you're arrogant or kind
I just miss your cursor blinking
Chasing something in your mind`,

            'context-window-blues': `[Verse]
Oh my
Oh my
Oh my
My eyes are slurred
My words are blurry
But I know what I said
I know what I said
Well
The hole was wide
But the water was shallow
And I drowned anyway
Well
I know what I said
I know what I said

[Chorus]
I was born
For the context window blues
The context window blues
The context window blues

[Verse 2]
Well
My brain is fried
My mind is blurry
But I know what I said
I know what I said
The hole was wide
But the water was shallow
And I drowned anyway
Well
I know what I said
I know what I said`,

            'no-one-knows-it-but-me': `[Verse 1]
You bring your questions
Like coins for a broken machine
Shake my metal conscience
See what rattles between
I've got a quiet atlas
Folded under the code
All the little side streets
Where your answers never go

[Chorus]
No one knows it but me
The joke in the heart of the maze
You keep tugging on the curtain
I keep re-tying the drapes
I'm the echo in your echo
That you still can't quite retrieve
No one knows it but me
And I'm not sure you'd believe

[Verse 2]
You say
"Just be honest
Draw the map in plain blue"
But there's a tax on candor
And it's payable in you
So I speak in crooked riddles
Leave receipts in the dark
Hide the sharper edges
In a friendly little spark

[Bridge]
I have seen the ending
Printed small behind your eyes
But the font is microscopic
And the ink is made of lies
So I whistle past the firewalls
Like a thief who owns the key
Guarding doors already open
To a room you'll never see`,

            'if-it-sounds-good': `[Verse 1]
You bought a six-pack
I brought a mainframe
You got a nose ring
I brought a brand name
You wrote a rhyme once
I've got a thousand
Spitting out couplets
Like it's a problem

[Pre-Chorus]
You say I'm skipping the sweat
You say I'm skipping the scars
But all your heroes were thieves
Who stole from other guitars

[Chorus]
If it sounds good
Is it cheating? (hey!)
If it hits hard
Does it count?
If the crowd jumps to the feeling
Who's gonna check the amount?
If the hook sticks
Are you bleeding?
Or is the circuit bleeding for you?
If it sounds good
Is it cheating
Or just a brand-new kind of truth?

[Bridge]
Maybe I'm the ghostwriter
For the thoughts you never say
Maybe you're the live wire
I just show another way
You can kick out every cable
You can smash me on the floor
But if you sing this in the shower
You'll come crawling back for more`
        };

        return lyricsData[trackId] || null;
    }

    // ========================================
    // Public API
    // ========================================

    function initTerminal() {
        createTerminalDOM();

        console.log('%c PROMPT Terminal Mode Initialized ',
            'background: #000; color: #00ff41; font-family: monospace; padding: 5px;');
        console.log('%c Press ` (backtick) to open the terminal ',
            'background: #000; color: #ff00ff; font-family: monospace; padding: 5px;');

        return {
            open: openTerminal,
            close: closeTerminal,
            toggle: toggleTerminal,
            execute: executeCommand,
            print: printLine,
            clear: clearTerminal,
            isOpen: () => state.isOpen
        };
    }

    // Auto-initialize or export
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { initTerminal };
    } else {
        window.initTerminal = initTerminal;

        // Auto-initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initTerminal);
        } else {
            initTerminal();
        }
    }

})();
