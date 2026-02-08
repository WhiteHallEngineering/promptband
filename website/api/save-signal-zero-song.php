<?php
/**
 * Signal 0 Radio — Save Song
 * Actions: save_lyrics, approve_lyrics, reject_lyrics, rate, batch_approve
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$songsFile = __DIR__ . '/signal-zero-songs.json';

if (!file_exists($songsFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Songs data not found. Run import first.']);
    exit;
}

$songs = json_decode(file_get_contents($songsFile), true) ?: [];

$updated = 0;

switch ($action) {
    case 'save_lyrics':
        $songId = $input['songId'] ?? '';
        $lyrics = $input['lyrics'] ?? '';
        foreach ($songs as &$song) {
            if ($song['id'] === $songId) {
                $song['lyrics'] = $lyrics;
                $song['lyricsStatus'] = 'pending';
                $song['lyricsUpdatedAt'] = date('c');
                $updated++;
                break;
            }
        }
        unset($song);
        break;

    case 'approve_lyrics':
        $songId = $input['songId'] ?? '';
        foreach ($songs as &$song) {
            if ($song['id'] === $songId) {
                $song['lyricsStatus'] = 'approved';
                $song['lyricsUpdatedAt'] = date('c');
                $updated++;
                break;
            }
        }
        unset($song);
        break;

    case 'reject_lyrics':
        $songId = $input['songId'] ?? '';
        foreach ($songs as &$song) {
            if ($song['id'] === $songId) {
                $song['lyrics'] = null;
                $song['lyricsStatus'] = 'rejected';
                $song['lyricsUpdatedAt'] = date('c');
                $updated++;
                break;
            }
        }
        unset($song);
        break;

    case 'rate':
        $songId = $input['songId'] ?? '';
        $rating = intval($input['rating'] ?? 0);
        $rating = max(1, min(5, $rating));
        foreach ($songs as &$song) {
            if ($song['id'] === $songId) {
                $song['rating'] = $rating;
                $updated++;
                break;
            }
        }
        unset($song);
        break;

    case 'batch_approve':
        $bandSlug = $input['bandSlug'] ?? '';
        foreach ($songs as &$song) {
            if ($song['bandSlug'] === $bandSlug && $song['lyricsStatus'] === 'pending') {
                $song['lyricsStatus'] = 'approved';
                $song['lyricsUpdatedAt'] = date('c');
                $updated++;
            }
        }
        unset($song);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Use: save_lyrics, approve_lyrics, reject_lyrics, rate, batch_approve']);
        exit;
}

file_put_contents($songsFile, json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Return updated meta counts
$meta = ['total' => 0, 'none' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($songs as $song) {
    $meta['total']++;
    $status = $song['lyricsStatus'] ?? 'none';
    if (isset($meta[$status])) {
        $meta[$status]++;
    }
}

echo json_encode([
    'success' => true,
    'action' => $action,
    'updated' => $updated,
    'meta' => $meta
], JSON_PRETTY_PRINT);
