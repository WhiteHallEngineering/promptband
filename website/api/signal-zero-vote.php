<?php
/**
 * Signal 0 Radio — Thumbs Up/Down Rotation Vote
 * Accepts song title + band name from ESP32 display, adjusts rotation tier.
 *
 * POST JSON: { "title": "Tidebreak", "band": "Cascade Effect", "direction": "up" }
 * Returns:   { "success": true, "songId": "...", "rotation": "heavy", "previous": "medium" }
 *
 * Rotation ladder:
 *   up:   null → light → medium → heavy
 *   down: heavy → medium → light → null
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
$title = trim($input['title'] ?? '');
$band = trim($input['band'] ?? '');
$direction = strtolower(trim($input['direction'] ?? ''));

if (empty($title) || empty($band)) {
    http_response_code(400);
    echo json_encode(['error' => 'title and band are required']);
    exit;
}

if (!in_array($direction, ['up', 'down'])) {
    http_response_code(400);
    echo json_encode(['error' => 'direction must be "up" or "down"']);
    exit;
}

// Slugify: lowercase, replace non-alphanumeric with dash, collapse multiples, trim edges
function slugify($str) {
    $str = strtolower($str);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

$bandSlug = slugify($band);
$titleSlug = slugify($title);
$songId = $bandSlug . '--' . $titleSlug;

$songsFile = __DIR__ . '/signal-zero-songs.json';

if (!file_exists($songsFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Songs data not found']);
    exit;
}

$songs = json_decode(file_get_contents($songsFile), true) ?: [];

// Rotation ladder
$upLadder = [null => 'light', 'light' => 'medium', 'medium' => 'heavy', 'heavy' => 'heavy'];
$downLadder = ['heavy' => 'medium', 'medium' => 'light', 'light' => null, null => null];

$found = false;
$previous = null;
$newRotation = null;

foreach ($songs as &$song) {
    if ($song['id'] === $songId) {
        $previous = $song['rotation'] ?? null;

        if ($direction === 'up') {
            $newRotation = $upLadder[$previous] ?? 'light';
        } else {
            $key = $previous ?? null;
            $newRotation = array_key_exists($key, $downLadder) ? $downLadder[$key] : null;
        }

        $song['rotation'] = $newRotation;
        $found = true;
        break;
    }
}
unset($song);

if (!$found) {
    http_response_code(404);
    echo json_encode([
        'error' => 'Song not found',
        'songId' => $songId,
        'searchedTitle' => $title,
        'searchedBand' => $band
    ]);
    exit;
}

file_put_contents($songsFile, json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'success' => true,
    'songId' => $songId,
    'rotation' => $newRotation,
    'previous' => $previous
], JSON_PRETTY_PRINT);
