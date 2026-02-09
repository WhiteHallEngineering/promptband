<?php
/**
 * Signal 0 Radio — Rotation Management
 * Manages rotation tier assignments (heavy/medium/light) for songs
 *
 * GET: Returns rotation overview with stats, region breakdown, and eligible songs
 * POST action=assign: Set rotation tier for a single song
 * POST action=auto_assign: Auto-assign tiers based on audioRating
 * POST action=clear_all: Reset all rotation assignments to null
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$password = $_GET['key'] ?? '';
$validKey = 'pr0mpt-m3ss4g3s-2026';

if ($password !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$songsFile = __DIR__ . '/signal-zero-songs.json';
$stationFile = __DIR__ . '/signal-zero-station.json';

if (!file_exists($songsFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Songs data not found. Run import first.']);
    exit;
}

$songs = json_decode(file_get_contents($songsFile), true) ?: [];
$station = file_exists($stationFile) ? (json_decode(file_get_contents($stationFile), true) ?: []) : [];
$rotationTarget = $station['rotation']['target'] ?? 600;

// ─── GET: Return rotation overview ───────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stats = ['heavy' => 0, 'medium' => 0, 'light' => 0, 'unassigned' => 0, 'target' => $rotationTarget];
    $byRegion = [];
    $eligibleSongs = [];

    foreach ($songs as $song) {
        // Only include songs with completed audio in the overview
        if (($song['audioStatus'] ?? 'none') !== 'complete') {
            continue;
        }

        $region = $song['region'] ?? 'Unknown';
        $rotation = $song['rotation'] ?? null;

        // Initialize region bucket
        if (!isset($byRegion[$region])) {
            $byRegion[$region] = ['heavy' => 0, 'medium' => 0, 'light' => 0];
        }

        if ($rotation && in_array($rotation, ['heavy', 'medium', 'light'])) {
            $stats[$rotation]++;
            $byRegion[$region][$rotation]++;
        } else {
            $stats['unassigned']++;
        }

        $eligibleSongs[] = $song;
    }

    // Sort regions alphabetically
    ksort($byRegion);

    echo json_encode([
        'stats' => $stats,
        'byRegion' => $byRegion,
        'songs' => $eligibleSongs
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── POST: Rotation actions ──────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'GET or POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {

    // ── Assign a single song to a rotation tier ──────────────────────────────
    case 'assign':
        $songId = $input['songId'] ?? '';
        $rotation = $input['rotation'] ?? null;

        if (empty($songId)) {
            http_response_code(400);
            echo json_encode(['error' => 'songId is required']);
            exit;
        }

        // Validate rotation value
        $validTiers = ['heavy', 'medium', 'light', null];
        if ($rotation !== null && !in_array($rotation, ['heavy', 'medium', 'light'])) {
            http_response_code(400);
            echo json_encode(['error' => 'rotation must be heavy, medium, light, or null']);
            exit;
        }

        $found = false;
        foreach ($songs as &$song) {
            if ($song['id'] === $songId) {
                $song['rotation'] = $rotation;
                $found = true;
                break;
            }
        }
        unset($song);

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => 'Song not found: ' . $songId]);
            exit;
        }

        file_put_contents($songsFile, json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'success' => true,
            'action' => 'assign',
            'songId' => $songId,
            'rotation' => $rotation
        ], JSON_PRETTY_PRINT);
        break;

    // ── Auto-assign rotation tiers based on audioRating ──────────────────────
    case 'auto_assign':
        $assigned = 0;
        $skipped = 0;

        // First pass: count eligible songs per region for balance checking
        $regionCounts = [];
        $totalEligible = 0;

        foreach ($songs as $song) {
            if (($song['audioStatus'] ?? 'none') !== 'complete') continue;
            $rating = $song['audioRating'] ?? $song['rating'] ?? null;
            if ($rating === null || $rating < 3) continue;
            $totalEligible++;
            $region = $song['region'] ?? 'Unknown';
            if (!isset($regionCounts[$region])) $regionCounts[$region] = 0;
            $regionCounts[$region]++;
        }

        // Region balance limit: no single region should exceed 15% of total rotation
        $maxPerRegion = max(1, floor($rotationTarget * 0.15));

        // Track how many songs per region are assigned in this pass
        $regionAssigned = [];

        // Sort songs by rating descending so highest-rated get assigned first
        $indexedSongs = [];
        foreach ($songs as $idx => $song) {
            $indexedSongs[] = ['idx' => $idx, 'song' => $song];
        }
        usort($indexedSongs, function($a, $b) {
            $ratingA = $a['song']['audioRating'] ?? $a['song']['rating'] ?? 0;
            $ratingB = $b['song']['audioRating'] ?? $b['song']['rating'] ?? 0;
            return $ratingB - $ratingA;
        });

        foreach ($indexedSongs as $entry) {
            $idx = $entry['idx'];
            $song = $entry['song'];

            // Only songs with completed audio
            if (($song['audioStatus'] ?? 'none') !== 'complete') continue;

            // Only songs with rating >= 3
            $rating = $song['audioRating'] ?? $song['rating'] ?? null;
            if ($rating === null || $rating < 3) continue;

            $region = $song['region'] ?? 'Unknown';
            if (!isset($regionAssigned[$region])) $regionAssigned[$region] = 0;

            // Check region balance cap
            if ($regionAssigned[$region] >= $maxPerRegion) {
                $skipped++;
                continue;
            }

            // Assign tier based on rating
            $tier = null;
            if ($rating >= 5) {
                $tier = 'heavy';
            } elseif ($rating >= 4) {
                $tier = 'medium';
            } elseif ($rating >= 3) {
                $tier = 'light';
            }

            if ($tier) {
                $songs[$idx]['rotation'] = $tier;
                $regionAssigned[$region]++;
                $assigned++;
            }
        }

        file_put_contents($songsFile, json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Build summary counts
        $tierCounts = ['heavy' => 0, 'medium' => 0, 'light' => 0];
        foreach ($songs as $song) {
            $r = $song['rotation'] ?? null;
            if ($r && isset($tierCounts[$r])) {
                $tierCounts[$r]++;
            }
        }

        echo json_encode([
            'success' => true,
            'action' => 'auto_assign',
            'assigned' => $assigned,
            'skipped' => $skipped,
            'totalEligible' => $totalEligible,
            'maxPerRegion' => $maxPerRegion,
            'tierCounts' => $tierCounts,
            'regionAssigned' => $regionAssigned
        ], JSON_PRETTY_PRINT);
        break;

    // ── Clear all rotation assignments ───────────────────────────────────────
    case 'clear_all':
        $cleared = 0;
        foreach ($songs as &$song) {
            if ($song['rotation'] !== null) {
                $song['rotation'] = null;
                $cleared++;
            }
        }
        unset($song);

        file_put_contents($songsFile, json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'success' => true,
            'action' => 'clear_all',
            'cleared' => $cleared
        ], JSON_PRETTY_PRINT);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Use: assign, auto_assign, clear_all']);
        exit;
}
