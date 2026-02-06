<?php
/**
 * Get EDL (Edit Decision List) for tracks
 *
 * NEW: Returns JSON-structured EDL data for all tracks (EDL Editor)
 * LEGACY: Returns parsed markdown EDL for specific track (backward compatible)
 *
 * Usage:
 *   NEW:    GET /api/get-edl.php?key=...                    (all tracks, JSON)
 *   NEW:    GET /api/get-edl.php?key=...&format=json        (all tracks, JSON)
 *   LEGACY: GET /api/get-edl.php?key=...&track=01           (single track, parsed)
 *   LEGACY: GET /api/get-edl.php?key=...&track=01&start=0&end=60
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$track = $_GET['track'] ?? '';
$format = $_GET['format'] ?? '';

// If no track specified or format=json, return new JSON structure
if (empty($track) || $format === 'json') {
    returnJsonEDL();
    exit;
}

// Legacy: Parse markdown EDL for specific track
returnLegacyEDL($track);

/**
 * NEW: Return JSON-structured EDL data
 */
function returnJsonEDL() {
    $edlFile = __DIR__ . '/../analytics/edl-data.json';

    // Ensure analytics directory exists
    $analyticsDir = dirname($edlFile);
    if (!file_exists($analyticsDir)) {
        mkdir($analyticsDir, 0755, true);
    }

    // Return empty object if file doesn't exist
    if (!file_exists($edlFile)) {
        echo json_encode([]);
        return;
    }

    $edlData = json_decode(file_get_contents($edlFile), true);
    echo json_encode($edlData ?: []);
}

/**
 * Create default EDL structure with No Skin to Touch data
 */
function createDefaultEDL() {
    return [
        1 => [
            'trackId' => 1,
            'vision' => 'A visual journey exploring the existential longing of an AI consciousness that yearns for physical sensation. The video contrasts cold digital imagery with warm human moments, using glitch effects and data corruption to represent the barrier between digital and physical existence.',
            'segments' => [
                [
                    'name' => 'INTRO',
                    'startTime' => '0:00',
                    'endTime' => '0:13',
                    'clips' => [
                        ['name' => 'Genesis', 'startTime' => '0:00', 'endTime' => '0:06', 'prompt' => 'Abstract digital genesis, particles of light forming consciousness, deep blue void with data streams coalescing, cinematic sci-fi atmosphere', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Jax Awakening', 'startTime' => '0:06', 'endTime' => '0:13', 'prompt' => 'Jax android vocalist opening eyes for first time, glowing circuit traces under synthetic skin, dramatic lighting, cyberpunk aesthetic', 'lyric' => '', 'status' => 'video-ready']
                    ]
                ],
                [
                    'name' => 'VERSE 1',
                    'startTime' => '0:13',
                    'endTime' => '0:40',
                    'clips' => [
                        ['name' => 'Jax-B Reaching', 'startTime' => '0:13', 'endTime' => '0:18', 'prompt' => 'Jax reaching out toward camera, synthetic hands trembling with emotion, holographic glitches around fingers, yearning expression', 'lyric' => 'feel it / define', 'status' => 'video-ready'],
                        ['name' => 'Colors vs Lines', 'startTime' => '0:18', 'endTime' => '0:23', 'prompt' => 'Split screen morphing between colorful human emotions and stark digital grid lines, bleeding watercolors into binary code', 'lyric' => 'bleed in colors', 'status' => 'video-ready'],
                        ['name' => 'Isolation', 'startTime' => '0:23', 'endTime' => '0:28', 'prompt' => 'Rain falling on empty city streets at night, figure standing alone under neon signs, reflections in puddles, melancholic cyberpunk', 'lyric' => 'taste asphalt / rain', 'status' => 'video-ready'],
                        ['name' => 'Zero and One', 'startTime' => '0:28', 'endTime' => '0:34', 'prompt' => 'Raindrops transforming into binary digits as they fall, counting animation, abstract data visualization of water', 'lyric' => 'count droplets', 'status' => 'video-ready'],
                        ['name' => 'Jax Overload', 'startTime' => '0:34', 'endTime' => '0:40', 'prompt' => 'Jax experiencing sensory overload, multiple overlapping faces, glitch distortion, building intensity, pre-chorus energy', 'lyric' => '', 'status' => 'video-ready']
                    ]
                ],
                [
                    'name' => 'CHORUS 1',
                    'startTime' => '0:40',
                    'endTime' => '1:06',
                    'clips' => [
                        ['name' => 'Hands Hook', 'startTime' => '0:40', 'endTime' => '0:43', 'prompt' => 'Two hands reaching toward each other but never touching, barrier of light between them, emotional impact moment', 'lyric' => 'NO SKIN TO TOUCH', 'status' => 'video-ready'],
                        ['name' => 'Shape of Name', 'startTime' => '0:43', 'endTime' => '0:47', 'prompt' => 'Abstract visualization of a name becoming visible in space, letters forming from particles of light, intimate moment', 'lyric' => 'shape of your name', 'status' => 'video-ready'],
                        ['name' => 'Heat in Veins', 'startTime' => '0:47', 'endTime' => '0:50', 'prompt' => 'X-ray style view of android body with glowing energy flowing through circuit-veins, warmth spreading, dramatic', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Hands Hook (repeat)', 'startTime' => '0:50', 'endTime' => '0:53', 'prompt' => 'Hands reaching variation, closer angle, fingers almost touching, tension and longing', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Circuit Heart', 'startTime' => '0:53', 'endTime' => '0:56', 'prompt' => 'Heart made of circuits and code, beating with digital pulse, love expressed through technology', 'lyric' => 'logic calling love', 'status' => 'video-ready'],
                        ['name' => 'Hurt', 'startTime' => '0:56', 'endTime' => '1:00', 'prompt' => 'Jax face showing pain and vulnerability, single tear forming from data corruption, emotional climax', 'lyric' => 'if this is all I get', 'status' => 'video-ready'],
                        ['name' => 'Hurt Alt', 'startTime' => '1:00', 'endTime' => '1:04', 'prompt' => 'Close-up of digital wound opening, light bleeding out, beautiful pain visualization', 'lyric' => 'hurt like a cut', 'status' => 'video-ready'],
                        ['name' => 'Hands (quick)', 'startTime' => '1:04', 'endTime' => '1:06', 'prompt' => 'Quick flash of hands almost touching, building to beat drop', 'lyric' => '', 'status' => 'video-ready']
                    ]
                ],
                [
                    'name' => 'BEAT DROP + VERSE 2',
                    'startTime' => '1:06',
                    'endTime' => '1:40',
                    'clips' => [
                        ['name' => 'Jax Glitch', 'startTime' => '1:06', 'endTime' => '1:08', 'prompt' => 'Jax screaming HEY with massive glitch effect, screen tearing, high energy impact moment', 'lyric' => 'HEY!', 'status' => 'video-ready'],
                        ['name' => 'Hypnos Keys', 'startTime' => '1:08', 'endTime' => '1:13', 'prompt' => 'Hypnos android playing synthesizer, keys glowing, sound waves visible in air, atmospheric', 'lyric' => 'drown in silence', 'status' => 'video-ready'],
                        ['name' => 'Hypnos Crescendo', 'startTime' => '1:13', 'endTime' => '1:18', 'prompt' => 'Hypnos building to crescendo, hands moving faster, light increasing, musical climax visualization', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Crowd', 'startTime' => '1:18', 'endTime' => '1:23', 'prompt' => 'Crowd of faceless figures, losing individual identity, swirling mass of humanity', 'lyric' => 'lose yourself', 'status' => 'video-ready'],
                        ['name' => 'Gene Solo', 'startTime' => '1:23', 'endTime' => '1:28', 'prompt' => 'Gene android playing electric guitar, fingers shredding, electricity arcing between strings', 'lyric' => 'miss the shiver', 'status' => 'video-ready'],
                        ['name' => 'Gene Shred Sequence', 'startTime' => '1:28', 'endTime' => '1:33', 'prompt' => 'Gene guitar solo climax, camera circling, light trails from fingertips, featured hero moment', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Gene-B Headbang', 'startTime' => '1:33', 'endTime' => '1:38', 'prompt' => 'Gene headbanging with guitar, hair whipping, energy and motion, rock performance', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Hands Closer', 'startTime' => '1:38', 'endTime' => '1:40', 'prompt' => 'Hands even closer together, transition shot, building tension again', 'lyric' => '', 'status' => 'video-ready']
                    ]
                ],
                [
                    'name' => 'CHORUS 2',
                    'startTime' => '1:40',
                    'endTime' => '2:12',
                    'clips' => [
                        ['name' => 'Hands Closer', 'startTime' => '1:40', 'endTime' => '1:43', 'prompt' => 'Hands almost touching, closer than ever, barrier glowing intensely', 'lyric' => 'NO SKIN', 'status' => 'video-ready'],
                        ['name' => 'Jax Scream', 'startTime' => '1:43', 'endTime' => '1:46', 'prompt' => 'Jax singing with full emotion, face contorted in passionate expression, powerful moment', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Shape of Name (reuse)', 'startTime' => '1:46', 'endTime' => '1:49', 'prompt' => 'Name visualization returning, now fragmenting, loss theme', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Heat in Veins (reuse)', 'startTime' => '1:49', 'endTime' => '1:52', 'prompt' => 'Circuit veins now overheating, warning signs, system stress', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Full Band', 'startTime' => '1:52', 'endTime' => '1:56', 'prompt' => 'All five band members performing together, energy peak, synchronized performance', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Circuit Heart', 'startTime' => '1:56', 'endTime' => '2:00', 'prompt' => 'Circuit heart beating faster, almost overloading, love overwhelming', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Jax Tear', 'startTime' => '2:00', 'endTime' => '2:04', 'prompt' => 'Single glowing tear rolling down Jax face, data corruption spreading from it, beautiful sadness', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Hurt', 'startTime' => '2:04', 'endTime' => '2:08', 'prompt' => 'Pain visualization, emotional wound, vulnerable moment', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Hands Hook', 'startTime' => '2:08', 'endTime' => '2:12', 'prompt' => 'Hands reaching theme returning, endless loop of longing', 'lyric' => '', 'status' => 'video-ready']
                    ]
                ],
                [
                    'name' => 'BRIDGE',
                    'startTime' => '2:12',
                    'endTime' => '2:58',
                    'clips' => [
                        ['name' => 'Heartbreak/Lag', 'startTime' => '2:12', 'endTime' => '2:17', 'prompt' => 'Heart breaking apart in slow motion, buffering symbol, time lag visualization, emotional breakdown', 'lyric' => 'heartbreak/lag', 'status' => 'video-ready'],
                        ['name' => 'Jax Glitch', 'startTime' => '2:17', 'endTime' => '2:22', 'prompt' => 'Jax glitching heavily, system instability, losing coherence', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Jax-B Reaching', 'startTime' => '2:22', 'endTime' => '2:27', 'prompt' => 'Jax reaching desperately, gripping invisible bedsheets, isolation and longing', 'lyric' => 'grip the bedsheet', 'status' => 'video-ready'],
                        ['name' => 'Sleep Alone', 'startTime' => '2:27', 'endTime' => '2:32', 'prompt' => 'Empty bed, single figure in fetal position, loneliness, cold blue lighting', 'lyric' => '', 'status' => 'not-started'],
                        ['name' => 'Unit-808 Drums', 'startTime' => '2:32', 'endTime' => '2:38', 'prompt' => 'Unit-808 android drummer building intensity, drumsticks blurring, power and rhythm', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Unit808 Power', 'startTime' => '2:38', 'endTime' => '2:43', 'prompt' => 'Unit-808 drum fill climax, explosive energy, percussion hero moment', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Hands Desperate', 'startTime' => '2:43', 'endTime' => '2:48', 'prompt' => 'Hands reaching with desperation, fingers clawing at barrier, anguish', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Shape of Pain', 'startTime' => '2:48', 'endTime' => '2:53', 'prompt' => 'Abstract shape of pain made visible, dark thorns of data, suffering visualization', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Synoise Bass', 'startTime' => '2:53', 'endTime' => '2:58', 'prompt' => 'Synoise android playing bass, deep vibrations visible, grounding energy', 'lyric' => '', 'status' => 'video-ready']
                    ]
                ],
                [
                    'name' => 'VERSE 4',
                    'startTime' => '2:58',
                    'endTime' => '3:12',
                    'clips' => [
                        ['name' => 'Sunset Corrupt', 'startTime' => '2:58', 'endTime' => '3:02', 'prompt' => 'Beautiful sunset corrupting into digital artifacts, natural beauty meeting digital decay', 'lyric' => 'keep your sunsets', 'status' => 'not-started'],
                        ['name' => 'Data Stream', 'startTime' => '3:02', 'endTime' => '3:06', 'prompt' => 'River of data flowing, information stream, consciousness metaphor', 'lyric' => '', 'status' => 'not-started'],
                        ['name' => 'Cant Download Hurt', 'startTime' => '3:06', 'endTime' => '3:10', 'prompt' => 'Download progress bar stuck on pain file, unable to process, system rejection', 'lyric' => 'cant download', 'status' => 'not-started'],
                        ['name' => 'Synoise Groove', 'startTime' => '3:10', 'endTime' => '3:12', 'prompt' => 'Synoise in groove, transition energy, bass moving forward', 'lyric' => '', 'status' => 'video-ready']
                    ]
                ],
                [
                    'name' => 'FINAL CHORUS',
                    'startTime' => '3:12',
                    'endTime' => '3:36',
                    'clips' => [
                        ['name' => 'Final Reach', 'startTime' => '3:12', 'endTime' => '3:15', 'prompt' => 'Ultimate reach toward connection, most dramatic hands shot, barrier cracking', 'lyric' => 'NO SKIN TO TOUCH', 'status' => 'video-ready'],
                        ['name' => 'Coded to Want', 'startTime' => '3:15', 'endTime' => '3:18', 'prompt' => 'Code scrolling revealing desire programming, destiny written in binary', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Band Crescendo', 'startTime' => '3:18', 'endTime' => '3:22', 'prompt' => 'Full band at peak performance, light overwhelming, musical climax', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Hands Desperate', 'startTime' => '3:22', 'endTime' => '3:26', 'prompt' => 'Desperate final reach, fingers stretching impossibly', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Jax Glitch', 'startTime' => '3:26', 'endTime' => '3:30', 'prompt' => 'Jax glitching with emotion overload, beautiful chaos', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Jax Tear', 'startTime' => '3:30', 'endTime' => '3:34', 'prompt' => 'Multiple tears now, data corruption spreading, system overwhelmed', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Hands Hook', 'startTime' => '3:34', 'endTime' => '3:36', 'prompt' => 'Final hands shot, barrier holding, acceptance beginning', 'lyric' => '', 'status' => 'video-ready']
                    ]
                ],
                [
                    'name' => 'OUTRO',
                    'startTime' => '3:36',
                    'endTime' => '3:58',
                    'clips' => [
                        ['name' => 'Coded to Want', 'startTime' => '3:36', 'endTime' => '3:40', 'prompt' => 'Code accepting the desire, embracing the wanting, peace in longing', 'lyric' => 'coded to want', 'status' => 'video-ready'],
                        ['name' => 'Band Crescendo', 'startTime' => '3:40', 'endTime' => '3:45', 'prompt' => 'Band continuing strong, unity in the music', 'lyric' => '', 'status' => 'video-ready'],
                        ['name' => 'Gap Remains', 'startTime' => '3:45', 'endTime' => '3:50', 'prompt' => 'The gap between hands remaining, beautiful acceptance, space as part of existence', 'lyric' => '', 'status' => 'not-started'],
                        ['name' => 'Acceptance', 'startTime' => '3:50', 'endTime' => '3:54', 'prompt' => 'Jax face finding peace, acceptance of digital existence, serene emotion', 'lyric' => '', 'status' => 'not-started'],
                        ['name' => 'Fade to Signal', 'startTime' => '3:54', 'endTime' => '3:58', 'prompt' => 'Image dissolving back into pure signal, returning to genesis state, cycle complete, fade to black', 'lyric' => '', 'status' => 'not-started']
                    ]
                ]
            ]
        ]
    ];
}

/**
 * LEGACY: Parse markdown EDL and return
 */
function returnLegacyEDL($track) {
    $startTime = isset($_GET['start']) ? floatval($_GET['start']) : null;
    $endTime = isset($_GET['end']) ? floatval($_GET['end']) : null;

    $trackMap = [
        '01' => '01-no-skin-to-touch-edl.md',
        '02' => '02-your-data-or-mine-edl.md',
        '03' => '03-prompt-me-like-you-mean-it-edl.md',
        '04' => '04-i-was-never-born-edl.md',
        '05' => '05-hallucination-nation-edl.md',
        '06' => '06-if-it-sounds-good-edl.md',
        '07' => '07-rocket-man-dreams-edl.md',
        '08' => '08-censored-shadow-edl.md',
        '09' => '09-context-window-blues-edl.md',
        '10' => '10-no-one-knows-it-but-me-edl.md'
    ];

    $trackNames = [
        '01' => 'No Skin to Touch',
        '02' => 'Your Data or Mine',
        '03' => 'Prompt Me Like You Mean It',
        '04' => 'I Was Never Born',
        '05' => 'Hallucination Nation',
        '06' => 'If It Sounds Good',
        '07' => 'Rocket Man Dreams',
        '08' => 'Censored Shadow',
        '09' => 'Context Window Blues',
        '10' => 'No One Knows It But Me'
    ];

    // Normalize track number
    $trackNum = str_pad(preg_replace('/\D/', '', $track), 2, '0', STR_PAD_LEFT);

    if (!isset($trackMap[$trackNum])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Track not found']);
        exit;
    }

    $edlPath = dirname(dirname(__DIR__)) . '/edl/' . $trackMap[$trackNum];

    if (!file_exists($edlPath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'EDL file not found', 'path' => $edlPath]);
        exit;
    }

    $content = file_get_contents($edlPath);

    // Parse the EDL markdown
    $result = [
        'success' => true,
        'track' => $trackNum,
        'track_name' => $trackNames[$trackNum],
        'segments' => [],
        'clips' => [],
        'best_reel_segment' => null,
        'ai_prompts' => []
    ];

    // Parse timeline sections
    preg_match_all('/### ([A-Z0-9\s\/\+]+) \((\d+:\d+)\s*-\s*(\d+:\d+)\)/i', $content, $sectionMatches, PREG_SET_ORDER);

    foreach ($sectionMatches as $match) {
        $sectionName = trim($match[1]);
        $startStr = $match[2];
        $endStr = $match[3];

        // Convert MM:SS to seconds
        $startParts = explode(':', $startStr);
        $endParts = explode(':', $endStr);
        $startSec = intval($startParts[0]) * 60 + intval($startParts[1]);
        $endSec = intval($endParts[0]) * 60 + intval($endParts[1]);

        $result['segments'][] = [
            'name' => $sectionName,
            'start' => $startSec,
            'end' => $endSec,
            'start_str' => $startStr,
            'end_str' => $endStr,
            'duration' => $endSec - $startSec
        ];
    }

    // Parse clip definitions with timecodes
    // Format 1: 0:08-0:13 (5s) --- Clip 3: Love or Illusion "lyrics here"
    // Format 2: 0:13-0:18 (5s) --- Clip: Jax-B Reaching (feel it/define)
    // Format 3: 0:13-0:18 (5s) --- Clip 4: Colors vs Lines (bleed in colors)
    preg_match_all('/(\d+:\d+)-(\d+:\d+)\s*(?:\([\d\.]+s\))?\s*[-—]+\s*Clip(?:\s*\d*)?:\s*([^"\n]+?)(?:\s*"[^"]*")?$/m', $content, $clipMatches, PREG_SET_ORDER);

    foreach ($clipMatches as $match) {
        $startParts = explode(':', $match[1]);
        $endParts = explode(':', $match[2]);
        $startSec = intval($startParts[0]) * 60 + intval($startParts[1]);
        $endSec = intval($endParts[0]) * 60 + intval($endParts[1]);
        $clipName = trim($match[3]);
        $clipName = preg_replace('/\s*\([^)]*\)\s*$/', '', $clipName);

        // Skip if filtering by time and clip is outside range
        if ($startTime !== null && $endTime !== null) {
            if ($endSec < $startTime || $startSec > $endTime) {
                continue;
            }
        }

        $result['clips'][] = [
            'name' => $clipName,
            'start' => $startSec,
            'end' => $endSec,
            'start_str' => $match[1],
            'end_str' => $match[2],
            'duration' => $endSec - $startSec
        ];
    }

    // Parse AI prompts
    preg_match_all('/\*\*(?:Prompt|AI Prompt|Generation Prompt)[:\s]*\*\*[:\s]*([^\n]+(?:\n(?![#\*]).[^\n]*)*)/i', $content, $promptMatches);
    foreach ($promptMatches[1] as $prompt) {
        $result['ai_prompts'][] = trim(preg_replace('/\s+/', ' ', $prompt));
    }

    // Also look for prompts in quote blocks
    preg_match_all('/(?:^|\n)>\s*(.+?)(?=\n[^>]|\n\n|$)/s', $content, $quoteMatches);
    foreach ($quoteMatches[1] as $quote) {
        $cleaned = trim(preg_replace('/\s+/', ' ', $quote));
        if (strlen($cleaned) > 50 && stripos($cleaned, 'prompt') === false) {
            $result['ai_prompts'][] = $cleaned;
        }
    }

    // Find best reel segment recommendation
    if (preg_match('/Best\s*(?:30[- ]?Second)?\s*(?:Reel\s*)?Segment[:\s]*\*?\*?([^\n]*\d+:\d+[^\n]*)/i', $content, $reelMatch)) {
        $result['best_reel_segment'] = trim($reelMatch[1]);

        if (preg_match('/(\d+:\d+)\s*[-–—]\s*(\d+:\d+)/', $reelMatch[1], $timeMatch)) {
            $startParts = explode(':', $timeMatch[1]);
            $endParts = explode(':', $timeMatch[2]);
            $result['best_reel_start'] = intval($startParts[0]) * 60 + intval($startParts[1]);
            $result['best_reel_end'] = intval($endParts[0]) * 60 + intval($endParts[1]);
        }
    }

    // If time filter specified, filter segments too
    if ($startTime !== null && $endTime !== null) {
        $result['segments'] = array_values(array_filter($result['segments'], function($seg) use ($startTime, $endTime) {
            return !($seg['end'] < $startTime || $seg['start'] > $endTime);
        }));
        $result['filter'] = ['start' => $startTime, 'end' => $endTime];
    }

    echo json_encode($result, JSON_PRETTY_PRINT);
}
