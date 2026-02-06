<?php
/**
 * Save/Update EDL (Edit Decision List) for a track
 *
 * NEW FORMAT (JSON-based, for EDL Editor):
 * POST /api/save-edl.php?key=...
 * Body: { "trackId": 1, "edl": { "vision": "...", "segments": [...] } }
 *
 * LEGACY FORMAT (Markdown-based):
 * POST /api/save-edl.php?key=...
 * Body: { "track": "01", "creative_vision": "...", "segments": [...], "clips": [...], "prompts": {...} }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Validate API key
$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

// Determine which format to use based on input
if (isset($input['trackId']) && isset($input['edl'])) {
    // NEW: JSON-based save for EDL Editor
    saveJsonEDL($input);
} elseif (isset($input['track'])) {
    // LEGACY: Markdown-based save
    saveLegacyEDL($input);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input. Required: trackId+edl (new format) or track (legacy format)']);
    exit;
}

/**
 * NEW: Save EDL data to JSON file
 */
function saveJsonEDL($input) {
    $trackId = $input['trackId']; // Can be numeric (1-10) or album-based key (album-id-tracknum)
    $albumId = $input['albumId'] ?? null;
    $edl = $input['edl'];

    // Validate track ID - allow numeric 1-10 or album-based strings
    if (is_numeric($trackId)) {
        $trackId = (int)$trackId;
        if ($trackId < 1 || $trackId > 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid track ID. Must be 1-10']);
            exit;
        }
    } else {
        // Album-based key like "no-skin-to-touch-2"
        if (!preg_match('/^[a-z0-9\-]+-\d+$/i', $trackId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid track key format']);
            exit;
        }
    }

    // EDL storage file
    $edlFile = __DIR__ . '/../analytics/edl-data.json';

    // Ensure analytics directory exists
    $analyticsDir = dirname($edlFile);
    if (!file_exists($analyticsDir)) {
        mkdir($analyticsDir, 0755, true);
    }

    // Load existing data
    $edlData = [];
    if (file_exists($edlFile)) {
        $edlData = json_decode(file_get_contents($edlFile), true) ?? [];
    }

    // Update the specific track
    $edl['trackId'] = $trackId;
    $edl['updatedAt'] = date('c');
    $edlData[$trackId] = $edl;

    // Save back to file
    $result = file_put_contents($edlFile, json_encode($edlData, JSON_PRETTY_PRINT));

    if ($result === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save EDL data']);
        exit;
    }

    // Also create a backup
    $backupDir = $analyticsDir . '/edl-backups';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    $backupFile = $backupDir . '/edl-backup-' . date('Y-m-d-His') . '.json';
    file_put_contents($backupFile, json_encode($edlData, JSON_PRETTY_PRINT));

    // Clean up old backups (keep last 50)
    $backups = glob($backupDir . '/edl-backup-*.json');
    if ($backups && count($backups) > 50) {
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        $toDelete = array_slice($backups, 0, count($backups) - 50);
        foreach ($toDelete as $file) {
            unlink($file);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'EDL saved successfully',
        'trackId' => $trackId,
        'updatedAt' => $edl['updatedAt']
    ]);
}

/**
 * LEGACY: Save EDL updates to markdown file
 */
function saveLegacyEDL($input) {
    $track = $input['track'] ?? '';

    if (empty($track)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'track parameter required (e.g., 01, 02, etc.)']);
        exit;
    }

    // Map track numbers to filenames
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

    // Normalize track number
    $trackNum = str_pad(preg_replace('/\D/', '', $track), 2, '0', STR_PAD_LEFT);

    if (!isset($trackMap[$trackNum])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Track not found. Valid tracks: 01-10']);
        exit;
    }

    $edlPath = dirname(dirname(__DIR__)) . '/edl/' . $trackMap[$trackNum];

    if (!file_exists($edlPath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'EDL file not found at: ' . $edlPath]);
        exit;
    }

    // Read existing content
    $content = file_get_contents($edlPath);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to read EDL file']);
        exit;
    }

    $changes = [];

    // Update Creative Vision if provided
    if (isset($input['creative_vision']) && !empty($input['creative_vision'])) {
        $newVision = trim($input['creative_vision']);

        // Find and replace the Creative Vision section
        $pattern = '/(## Creative Vision\s*\n)(.+?)(\n---|\n## )/s';
        if (preg_match($pattern, $content, $match)) {
            $replacement = $match[1] . "\n" . $newVision . "\n\n" . ltrim($match[3]);
            $content = preg_replace($pattern, $replacement, $content, 1);
            $changes[] = 'creative_vision';
        } else {
            // If no existing Creative Vision section, add one after Track Info
            $insertPattern = '/(## Track Info.+?\n---)/s';
            if (preg_match($insertPattern, $content, $match)) {
                $insertion = $match[1] . "\n\n## Creative Vision\n\n" . $newVision . "\n";
                $content = preg_replace($insertPattern, $insertion, $content, 1);
                $changes[] = 'creative_vision (added)';
            }
        }
    }

    // Update segment timings if provided
    if (isset($input['segments']) && is_array($input['segments'])) {
        foreach ($input['segments'] as $segment) {
            $name = $segment['name'] ?? '';
            $newStart = $segment['start_str'] ?? '';
            $newEnd = $segment['end_str'] ?? '';

            if (empty($name) || empty($newStart) || empty($newEnd)) {
                continue;
            }

            // Pattern: ### SEGMENT NAME (0:00 - 0:08) = Xs
            $escapedName = preg_quote($name, '/');
            $pattern = '/(### ' . $escapedName . '\s*)\((\d+:\d+)\s*-\s*(\d+:\d+)\)(\s*=\s*\d+\s*sec)?/i';

            // Calculate new duration
            $startParts = explode(':', $newStart);
            $endParts = explode(':', $newEnd);
            $startSec = intval($startParts[0]) * 60 + intval($startParts[1]);
            $endSec = intval($endParts[0]) * 60 + intval($endParts[1]);
            $duration = $endSec - $startSec;

            $replacement = '${1}(' . $newStart . ' - ' . $newEnd . ') = ' . $duration . ' sec';
            $newContent = preg_replace($pattern, $replacement, $content, 1, $count);
            if ($count > 0) {
                $content = $newContent;
                $changes[] = 'segment:' . $name;
            }
        }
    }

    // Update clip timings if provided
    if (isset($input['clips']) && is_array($input['clips'])) {
        foreach ($input['clips'] as $clip) {
            $name = $clip['name'] ?? '';
            $newStart = $clip['start_str'] ?? '';
            $newEnd = $clip['end_str'] ?? '';

            if (empty($name) || empty($newStart) || empty($newEnd)) {
                continue;
            }

            // Calculate new duration
            $startParts = explode(':', $newStart);
            $endParts = explode(':', $newEnd);
            $startSec = intval($startParts[0]) * 60 + intval($startParts[1]);
            $endSec = intval($endParts[0]) * 60 + intval($endParts[1]);
            $duration = $endSec - $startSec;

            // Pattern: 0:08-0:13 (5s) --- Clip X: Name "lyrics"
            $escapedName = preg_quote($name, '/');
            $pattern = '/(\d+:\d+)-(\d+:\d+)\s*\([\d\.]+s\)\s*([-—]+\s*Clip\s*\d+:\s*' . $escapedName . ')/i';
            $replacement = $newStart . '-' . $newEnd . ' (' . $duration . 's) ${3}';

            $newContent = preg_replace($pattern, $replacement, $content, -1, $count);
            if ($count > 0) {
                $content = $newContent;
                $changes[] = 'clip:' . $name . ' (x' . $count . ')';
            }
        }
    }

    // Update AI prompts if provided
    if (isset($input['prompts']) && is_array($input['prompts'])) {
        foreach ($input['prompts'] as $clipName => $newPrompt) {
            if (empty($clipName) || empty($newPrompt)) {
                continue;
            }

            $newPrompt = trim($newPrompt);

            // Pattern: **Clip X - "Name"** ... - Prompt: "..."
            $escapedName = preg_quote($clipName, '/');

            // Match the full clip block and replace the Prompt line
            $pattern = '/(\*\*Clip\s*\d+\s*[-–—]\s*"' . $escapedName . '"\*\*[^\n]*\n(?:- [^\n]+\n)*)- Prompt:\s*"[^"]*"/is';
            $replacement = '${1}- Prompt: "' . str_replace('"', '\\"', $newPrompt) . '"';

            $newContent = preg_replace($pattern, $replacement, $content, 1, $count);
            if ($count > 0) {
                $content = $newContent;
                $changes[] = 'prompt:' . $clipName;
            } else {
                // Try alternative format without quotes around clip name
                $pattern = '/(\*\*Clip\s*\d+\s*[-–—]\s*' . $escapedName . '\*\*[^\n]*\n(?:- [^\n]+\n)*)- Prompt:\s*"[^"]*"/is';
                $replacement = '${1}- Prompt: "' . str_replace('"', '\\"', $newPrompt) . '"';

                $newContent = preg_replace($pattern, $replacement, $content, 1, $count);
                if ($count > 0) {
                    $content = $newContent;
                    $changes[] = 'prompt:' . $clipName;
                }
            }
        }
    }

    // If no changes were made, return early
    if (empty($changes)) {
        echo json_encode([
            'success' => true,
            'message' => 'No updates applied',
            'track' => $trackNum,
            'changes' => []
        ]);
        exit;
    }

    // Create backup before saving
    $backupDir = dirname(dirname(__DIR__)) . '/edl/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    $backupPath = $backupDir . '/' . $trackMap[$trackNum] . '.' . date('Y-m-d-His') . '.bak';
    copy($edlPath, $backupPath);

    // Save the updated content
    $result = file_put_contents($edlPath, $content);
    if ($result === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to save EDL file',
            'backup' => $backupPath
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'EDL updated successfully',
        'track' => $trackNum,
        'file' => $trackMap[$trackNum],
        'changes' => $changes,
        'backup' => basename($backupPath),
        'bytes_written' => $result
    ]);
}
