<?php
/**
 * Signal 0 Radio — Now Playing Tracker
 *
 * GET: Returns current now-playing data + last 5 recently played (public, no auth)
 * POST: Updates now-playing and appends to playlog (requires API key)
 *
 * JSON files:
 *   signal-zero-nowplaying.json  — current track
 *   signal-zero-playlog.json     — last 200 entries for history
 */

// CORS headers for public GET access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$nowPlayingFile = __DIR__ . '/../analytics/signal-zero-nowplaying.json';
$playlogFile = __DIR__ . '/../analytics/signal-zero-playlog.json';
$validKey = 'pr0mpt-m3ss4g3s-2026';

// ─── GET: Return current now-playing + recently played ───
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $nowPlaying = null;
    if (file_exists($nowPlayingFile)) {
        $nowPlaying = json_decode(file_get_contents($nowPlayingFile), true);
    }

    $recentlyPlayed = [];
    if (file_exists($playlogFile)) {
        $playlog = json_decode(file_get_contents($playlogFile), true);
        if (is_array($playlog) && count($playlog) > 0) {
            // Playlog is newest-first; skip the first entry (current) and take next 5
            if ($nowPlaying && count($playlog) > 1) {
                $recentlyPlayed = array_slice($playlog, 1, 5);
            } else if (!$nowPlaying) {
                $recentlyPlayed = array_slice($playlog, 0, 5);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'nowPlaying' => $nowPlaying,
        'recentlyPlayed' => $recentlyPlayed,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─── POST: Update now-playing (requires auth) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Auth check
    $key = $_GET['key'] ?? '';
    if ($key !== $validKey) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid API key']);
        exit;
    }

    // Parse input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
        exit;
    }

    // Validate required fields
    $title = trim($input['title'] ?? '');
    $band = trim($input['band'] ?? '');
    if (empty($title) || empty($band)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'title and band are required']);
        exit;
    }

    // Build now-playing record
    $nowPlaying = [
        'songId' => $input['songId'] ?? null,
        'title' => $title,
        'band' => $band,
        'region' => $input['region'] ?? null,
        'style' => $input['style'] ?? null,
        'audioUrl' => $input['audioUrl'] ?? null,
        'startedAt' => $input['startedAt'] ?? gmdate('Y-m-d\TH:i:s\Z'),
        'updatedAt' => gmdate('Y-m-d\TH:i:s\Z')
    ];

    // Remove null optional fields
    $nowPlaying = array_filter($nowPlaying, function($v) { return $v !== null; });

    // Ensure analytics directory exists
    $analyticsDir = __DIR__ . '/../analytics';
    if (!is_dir($analyticsDir)) {
        mkdir($analyticsDir, 0755, true);
    }

    // Save current now-playing
    file_put_contents($nowPlayingFile, json_encode($nowPlaying, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Append to playlog (newest first, max 200 entries)
    $playlog = [];
    if (file_exists($playlogFile)) {
        $playlog = json_decode(file_get_contents($playlogFile), true);
        if (!is_array($playlog)) {
            $playlog = [];
        }
    }

    // Prepend new entry
    array_unshift($playlog, $nowPlaying);

    // Trim to 200 entries
    if (count($playlog) > 200) {
        $playlog = array_slice($playlog, 0, 200);
    }

    file_put_contents($playlogFile, json_encode($playlog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    echo json_encode([
        'success' => true,
        'nowPlaying' => $nowPlaying,
        'playlogSize' => count($playlog)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Unsupported method
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
