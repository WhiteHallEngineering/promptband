<?php
/**
 * Signal 0 Radio — Get Songs
 * Returns songs with optional filtering by band, lyricsStatus, region, search
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$password = $_GET['key'] ?? '';
$validKey = 'pr0mpt-m3ss4g3s-2026';

if ($password !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$songsFile = __DIR__ . '/signal-zero-songs.json';

if (!file_exists($songsFile)) {
    echo json_encode([
        'songs' => [],
        'meta' => ['total' => 0, 'none' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0]
    ]);
    exit;
}

$songs = json_decode(file_get_contents($songsFile), true) ?: [];

// Filter parameters
$bandFilter = $_GET['band'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$regionFilter = $_GET['region'] ?? '';
$search = strtolower($_GET['search'] ?? '');

$filtered = $songs;

if ($bandFilter) {
    $filtered = array_filter($filtered, function($s) use ($bandFilter) {
        return $s['bandSlug'] === $bandFilter;
    });
}

if ($statusFilter) {
    $filtered = array_filter($filtered, function($s) use ($statusFilter) {
        return $s['lyricsStatus'] === $statusFilter;
    });
}

if ($regionFilter) {
    $filtered = array_filter($filtered, function($s) use ($regionFilter) {
        return $s['region'] === $regionFilter;
    });
}

if ($search) {
    $filtered = array_filter($filtered, function($s) use ($search) {
        return strpos(strtolower($s['title']), $search) !== false ||
               strpos(strtolower($s['bandName']), $search) !== false;
    });
}

// Meta counts (from all songs, not filtered)
$meta = ['total' => 0, 'none' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($songs as $song) {
    $meta['total']++;
    $status = $song['lyricsStatus'] ?? 'none';
    if (isset($meta[$status])) {
        $meta[$status]++;
    }
}

echo json_encode([
    'songs' => array_values($filtered),
    'meta' => $meta
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
