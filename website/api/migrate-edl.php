<?php
/**
 * Migrate markdown EDL files to JSON format for EDL Editor
 *
 * Handles two formats:
 * FORMAT 1 (tracks 01-05, 07-10): Code blocks with inline clip definitions
 *   ### INTRO (0:00 - 0:08) = 8 sec
 *   ```
 *   0:00-0:04 (4s) --- Clip 1: Genesis
 *   ```
 *
 * FORMAT 2 (track 06): Structured clips with separate Time: lines
 *   ### INTRO (0:00 - 0:08) | 2 clips
 *   **Clip 1 - "Name"**
 *   - Time: 0:00-0:04
 *   - Prompt: "..."
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$trackMap = [
    '01' => ['file' => '01-no-skin-to-touch-edl.md', 'name' => 'No Skin to Touch'],
    '02' => ['file' => '02-your-data-or-mine-edl.md', 'name' => 'Your Data or Mine'],
    '03' => ['file' => '03-prompt-me-like-you-mean-it-edl.md', 'name' => 'Prompt Me Like You Mean It'],
    '04' => ['file' => '04-i-was-never-born-edl.md', 'name' => 'I Was Never Born'],
    '05' => ['file' => '05-hallucination-nation-edl.md', 'name' => 'Hallucination Nation'],
    '06' => ['file' => '06-if-it-sounds-good-edl.md', 'name' => 'If It Sounds Good'],
    '07' => ['file' => '07-rocket-man-dreams-edl.md', 'name' => 'Rocket Man Dreams'],
    '08' => ['file' => '08-censored-shadow-edl.md', 'name' => 'Censored Shadow'],
    '09' => ['file' => '09-context-window-blues-edl.md', 'name' => 'Context Window Blues'],
    '10' => ['file' => '10-no-one-knows-it-but-me-edl.md', 'name' => 'No One Knows It But Me']
];

$edlDir = dirname(dirname(__DIR__)) . '/edl/';
$outputFile = __DIR__ . '/../analytics/edl-data.json';

$analyticsDir = dirname($outputFile);
if (!file_exists($analyticsDir)) {
    mkdir($analyticsDir, 0755, true);
}

$allEDLs = [];
$migrationLog = [];

foreach ($trackMap as $trackNum => $trackInfo) {
    $filePath = $edlDir . $trackInfo['file'];

    if (!file_exists($filePath)) {
        $migrationLog[] = "Track $trackNum: File not found";
        continue;
    }

    $content = file_get_contents($filePath);
    $trackId = intval($trackNum);

    // Parse creative vision
    $vision = '';
    if (preg_match('/## Creative Vision\s*\n\n(.*?)(?=\n---|\n## )/s', $content, $match)) {
        $vision = trim($match[1]);
        $vision = preg_replace('/\*\*([^*]+)\*\*/', '$1', $vision);
    }

    // Try FORMAT 1 first (code block style), then FORMAT 2 if no segments found
    $segments = parseFormat1($content);
    $clipPrompts = parseClipInventory($content);

    // If FORMAT 1 found nothing, try FORMAT 2 (structured style like track 06)
    if (empty($segments)) {
        $segments = parseFormat2($content);
    }

    // Apply prompts from inventory to clips
    $promptsApplied = 0;
    if (!empty($clipPrompts)) {
        foreach ($segments as &$segment) {
            foreach ($segment['clips'] as &$clip) {
                $lookupName = strtolower(trim($clip['name']));
                foreach ($clipPrompts as $pName => $pPrompt) {
                    if ($lookupName === $pName ||
                        strpos($lookupName, $pName) === 0 ||
                        strpos($pName, $lookupName) === 0) {
                        if (empty($clip['prompt'])) {
                            $clip['prompt'] = $pPrompt;
                            $clip['status'] = 'has-prompt';
                            $promptsApplied++;
                        }
                        break;
                    }
                }
            }
        }
        unset($segment, $clip);
    }

    // Count totals
    $totalClips = 0;
    foreach ($segments as $seg) {
        $totalClips += count($seg['clips']);
    }

    $allEDLs[$trackId] = [
        'trackId' => $trackId,
        'trackName' => $trackInfo['name'],
        'vision' => $vision,
        'segments' => $segments,
        'updatedAt' => date('c'),
        'migratedFrom' => $trackInfo['file']
    ];

    $migrationLog[] = "Track $trackNum ({$trackInfo['name']}): {$totalClips} clips, {$promptsApplied} prompts applied, " . count($segments) . " segments";
}

// Save
$result = file_put_contents($outputFile, json_encode($allEDLs, JSON_PRETTY_PRINT));

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to write EDL data file']);
    exit;
}

// Backup
$backupDir = $analyticsDir . '/edl-backups';
if (!file_exists($backupDir)) mkdir($backupDir, 0755, true);
copy($outputFile, $backupDir . '/edl-migration-' . date('Y-m-d-His') . '.json');

echo json_encode([
    'success' => true,
    'message' => 'Migration completed',
    'tracks_migrated' => count($allEDLs),
    'log' => $migrationLog
], JSON_PRETTY_PRINT);

/**
 * Parse FORMAT 1: Code block style
 * ### SEGMENT (0:00 - 0:08) = 8 sec
 * ```
 * 0:00-0:04 (4s) --- Clip 1: Name "lyrics"
 * ```
 */
function parseFormat1($content) {
    $segments = [];

    // Find all segment blocks with their code blocks
    // Allow up to 3 lines between header and code block (blank line, lyrics, etc)
    preg_match_all('/### ([A-Z][^\n(]*?) \((\d+:\d+)\s*-\s*(\d+:\d+)\)[^\n]*\n(?:[^\n`]*\n){0,3}```\n(.*?)\n```/s',
        $content, $segMatches, PREG_SET_ORDER);

    foreach ($segMatches as $segMatch) {
        $segmentName = trim($segMatch[1]);
        $segStartStr = $segMatch[2];
        $segEndStr = $segMatch[3];
        $clipsBlock = $segMatch[4];

        $clips = [];

        // Parse clip lines: 0:08-0:13 (5s) --- Clip 3: Love or Illusion "lyrics"
        // Also handle: 0:13-0:18 (5s) --- Clip 01: Name or Clip: Name
        preg_match_all('/(\d+:\d+)-(\d+:\d+)\s*\([^)]+\)\s*[-—]+\s*Clip\s*(?:\d+)?\s*:?\s*([^"\n]+?)(?:\s*"([^"]*)")?$/m',
            $clipsBlock, $clipMatches, PREG_SET_ORDER);

        foreach ($clipMatches as $cm) {
            $clipName = trim($cm[3]);
            $clipName = preg_replace('/\s*\([^)]*\)\s*$/', '', $clipName); // Remove (repeat) etc
            $clipName = preg_replace('/\s*⭐+/', '', $clipName); // Remove stars
            $clipName = trim($clipName);

            $clips[] = [
                'name' => $clipName,
                'startTime' => $cm[1],
                'endTime' => $cm[2],
                'prompt' => '',
                'lyric' => isset($cm[4]) ? trim($cm[4]) : '',
                'status' => 'not-started'
            ];
        }

        if (!empty($clips)) {
            $segments[] = [
                'name' => $segmentName,
                'startTime' => $segStartStr,
                'endTime' => $segEndStr,
                'clips' => $clips
            ];
        }
    }

    return $segments;
}

/**
 * Parse FORMAT 2: Structured style (track 06)
 * ### INTRO (0:00 - 0:08) | 2 clips
 * **Clip 1 - "Name"**
 * - Time: 0:00-0:04
 * - Prompt: "..."
 */
function parseFormat2($content) {
    $segments = [];

    // Find segment headers with | N clips pattern
    preg_match_all('/### ([A-Z][A-Z0-9\s\/\+\-]*) \((\d+:\d+)\s*-\s*(\d+:\d+)\)\s*\|\s*(\d+)\s*clips?/i',
        $content, $segHeaders, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    if (empty($segHeaders)) {
        return $segments;
    }

    for ($i = 0; $i < count($segHeaders); $i++) {
        $segmentName = trim($segHeaders[$i][1][0]);
        $segStartStr = $segHeaders[$i][2][0];
        $segEndStr = $segHeaders[$i][3][0];
        $expectedClips = intval($segHeaders[$i][4][0]);
        $startOffset = $segHeaders[$i][0][1];

        // Find end of this segment's content
        $endOffset = isset($segHeaders[$i + 1]) ? $segHeaders[$i + 1][0][1] : strlen($content);
        $segmentContent = substr($content, $startOffset, $endOffset - $startOffset);

        $clips = [];

        // Parse clips: **Clip N - "Name"** followed by - Time: and - Prompt:
        preg_match_all('/\*\*Clip\s*(\d+)\s*[-–—]\s*"([^"]+)"\*\*.*?- Time:\s*(\d+:\d+)-(\d+:\d+).*?(?:- (?:Lyric|Description):\s*([^\n]+))?.*?(?:- Prompt:\s*"([^"]+)")?/s',
            $segmentContent, $clipMatches, PREG_SET_ORDER);

        foreach ($clipMatches as $cm) {
            $clips[] = [
                'name' => trim($cm[2]),
                'startTime' => $cm[3],
                'endTime' => $cm[4],
                'prompt' => isset($cm[6]) ? trim($cm[6]) : '',
                'lyric' => isset($cm[5]) ? trim($cm[5]) : '',
                'status' => !empty($cm[6]) ? 'has-prompt' : 'not-started'
            ];
        }

        if (!empty($clips)) {
            $segments[] = [
                'name' => $segmentName,
                'startTime' => $segStartStr,
                'endTime' => $segEndStr,
                'clips' => $clips
            ];
        }
    }

    return $segments;
}

/**
 * Parse clip inventory section for prompts
 * **Clip 1 - "Genesis"**
 * - Prompt: "..."
 */
function parseClipInventory($content) {
    $prompts = [];

    // Look for clip definitions with prompts
    preg_match_all('/\*\*Clip\s*\d*\s*[-–—]\s*"?([^"*\n]+)"?\*\*.*?- Prompt:\s*"([^"]+)"/s',
        $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $name = strtolower(trim($m[1]));
        $name = preg_replace('/^"?(.*?)"?$/', '$1', $name);
        $prompts[$name] = trim($m[2]);
    }

    return $prompts;
}
