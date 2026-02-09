<?php
/**
 * Signal 0 Radio — Audio Stats & Management
 * Returns audio generation stats and manages audio ratings
 *
 * GET (no action): Audio stats overview with optional Suno quota
 * POST action=rate: Rate a single song's audio (1-5)
 * POST action=batch_rate: Rate all complete audio for a band
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

if (!file_exists($songsFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Songs data not found. Run import first.']);
    exit;
}

// Determine action from POST body or default to stats (GET)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    $action = $input['action'] ?? '';

    switch ($action) {
        case 'rate':
            handleRate($songsFile, $input);
            break;
        case 'batch_rate':
            handleBatchRate($songsFile, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid action',
                'validActions' => ['rate', 'batch_rate']
            ]);
            exit;
    }
} else {
    // GET — return stats
    handleStats($songsFile);
}

/**
 * Return audio stats overview
 */
function handleStats($songsFile) {
    $songs = json_decode(file_get_contents($songsFile), true) ?: [];

    // Count audio statuses
    $stats = [
        'total' => count($songs),
        'none' => 0,
        'generating' => 0,
        'complete' => 0,
        'rejected' => 0
    ];

    $bandAudioCounts = [];
    $ratingSum = 0;
    $ratingCount = 0;
    $generating = [];

    foreach ($songs as $song) {
        $audioStatus = $song['audioStatus'] ?? 'none';

        // Count by status
        if (isset($stats[$audioStatus])) {
            $stats[$audioStatus]++;
        } else {
            // Unknown status, count as 'none'
            $stats['none']++;
        }

        // Track per-band completion
        $bs = $song['bandSlug'];
        if (!isset($bandAudioCounts[$bs])) {
            $bandAudioCounts[$bs] = ['total' => 0, 'complete' => 0, 'generating' => 0];
        }
        $bandAudioCounts[$bs]['total']++;
        if ($audioStatus === 'complete') $bandAudioCounts[$bs]['complete']++;
        if ($audioStatus === 'generating') $bandAudioCounts[$bs]['generating']++;

        // Track ratings
        if (isset($song['audioRating']) && $song['audioRating'] !== null) {
            $ratingSum += $song['audioRating'];
            $ratingCount++;
        }

        // Track currently generating songs
        if ($audioStatus === 'generating') {
            $generating[] = [
                'songId' => $song['id'],
                'title' => $song['title'],
                'band' => $song['bandName'],
                'sunoJobId' => $song['sunoJobId'] ?? null,
                'startedAt' => $song['audioGenerateStartedAt'] ?? null
            ];
        }
    }

    // Find bands with most complete audio
    $topBands = [];
    foreach ($bandAudioCounts as $slug => $counts) {
        if ($counts['complete'] > 0) {
            $topBands[] = [
                'bandSlug' => $slug,
                'complete' => $counts['complete'],
                'total' => $counts['total']
            ];
        }
    }
    usort($topBands, function($a, $b) { return $b['complete'] - $a['complete']; });
    $topBands = array_slice($topBands, 0, 10);

    // Try to get Suno quota (non-blocking, fail silently)
    $quota = null;
    $sunoProxyUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/api/suno-proxy.php?key=pr0mpt-m3ss4g3s-2026&action=quota';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sunoProxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Short timeout — don't block if Suno is down
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $quotaResult = json_decode($response, true);
        if (isset($quotaResult['success']) && $quotaResult['success']) {
            $quota = $quotaResult['quota'] ?? null;
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'averageRating' => $ratingCount > 0 ? round($ratingSum / $ratingCount, 2) : null,
        'ratedCount' => $ratingCount,
        'generating' => $generating,
        'topBands' => $topBands,
        'quota' => $quota
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Rate a single song's audio (1-5)
 */
function handleRate($songsFile, $input) {
    $songId = $input['songId'] ?? '';
    $rating = $input['rating'] ?? null;

    if (empty($songId)) {
        http_response_code(400);
        echo json_encode(['error' => 'songId is required']);
        exit;
    }

    if ($rating === null || !is_numeric($rating) || $rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'rating must be a number between 1 and 5']);
        exit;
    }

    $rating = (int)$rating;

    $songs = json_decode(file_get_contents($songsFile), true) ?: [];

    // Find and update the song
    $found = false;
    foreach ($songs as &$song) {
        if ($song['id'] === $songId) {
            if (($song['audioStatus'] ?? 'none') !== 'complete') {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Can only rate songs with complete audio',
                    'currentStatus' => $song['audioStatus'] ?? 'none'
                ]);
                exit;
            }
            $song['audioRating'] = $rating;
            $song['audioRatedAt'] = date('c');
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
        'message' => 'Audio rated',
        'songId' => $songId,
        'rating' => $rating
    ], JSON_PRETTY_PRINT);
}

/**
 * Batch rate all complete audio for a band
 */
function handleBatchRate($songsFile, $input) {
    $bandSlug = $input['bandSlug'] ?? '';
    $rating = $input['rating'] ?? null;

    if (empty($bandSlug)) {
        http_response_code(400);
        echo json_encode(['error' => 'bandSlug is required']);
        exit;
    }

    if ($rating === null || !is_numeric($rating) || $rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'rating must be a number between 1 and 5']);
        exit;
    }

    $rating = (int)$rating;

    $songs = json_decode(file_get_contents($songsFile), true) ?: [];

    $updated = 0;
    $songIds = [];

    foreach ($songs as &$song) {
        if ($song['bandSlug'] === $bandSlug && ($song['audioStatus'] ?? 'none') === 'complete') {
            $song['audioRating'] = $rating;
            $song['audioRatedAt'] = date('c');
            $songIds[] = $song['id'];
            $updated++;
        }
    }
    unset($song);

    if ($updated === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'No complete audio found for band',
            'bandSlug' => $bandSlug,
            'updated' => 0
        ], JSON_PRETTY_PRINT);
        exit;
    }

    file_put_contents($songsFile, json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => "Rated $updated songs for $bandSlug",
        'bandSlug' => $bandSlug,
        'rating' => $rating,
        'updated' => $updated,
        'songIds' => $songIds
    ], JSON_PRETTY_PRINT);
}
