<?php
/**
 * Signal 0 Radio — Get Bands
 * Returns bands with optional filtering by region, status, search
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

$bandsFile = __DIR__ . '/signal-zero-bands.json';
$songsFile = __DIR__ . '/signal-zero-songs.json';

if (!file_exists($bandsFile)) {
    echo json_encode([
        'bands' => [],
        'meta' => ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'priority' => 0],
        'regions' => []
    ]);
    exit;
}

$bands = json_decode(file_get_contents($bandsFile), true) ?: [];
$songs = json_decode(file_get_contents($songsFile), true) ?: [];

// Build song stats per band
$songStats = [];
foreach ($songs as $song) {
    $bs = $song['bandSlug'];
    if (!isset($songStats[$bs])) {
        $songStats[$bs] = ['total' => 0, 'withLyrics' => 0, 'withAudio' => 0, 'inRotation' => 0];
    }
    $songStats[$bs]['total']++;
    if ($song['lyricsStatus'] === 'approved' || $song['lyricsStatus'] === 'pending') $songStats[$bs]['withLyrics']++;
    if ($song['audioStatus'] === 'complete') $songStats[$bs]['withAudio']++;
    if ($song['rotation']) $songStats[$bs]['inRotation']++;
}

// Add song stats to bands
foreach ($bands as &$band) {
    $band['songStats'] = $songStats[$band['slug']] ?? ['total' => 0, 'withLyrics' => 0, 'withAudio' => 0, 'inRotation' => 0];
}
unset($band);

// Filter parameters
$regionFilter = $_GET['region'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = strtolower($_GET['search'] ?? '');

$filtered = $bands;

if ($regionFilter) {
    $filtered = array_filter($filtered, function($b) use ($regionFilter) {
        return $b['region'] === $regionFilter;
    });
}

if ($statusFilter) {
    if ($statusFilter === 'priority') {
        $filtered = array_filter($filtered, function($b) { return $b['priority']; });
    } else {
        $filtered = array_filter($filtered, function($b) use ($statusFilter) {
            return $b['status'] === $statusFilter;
        });
    }
}

if ($search) {
    $filtered = array_filter($filtered, function($b) use ($search) {
        return strpos(strtolower($b['name']), $search) !== false ||
               strpos(strtolower($b['origin']), $search) !== false ||
               strpos(strtolower($b['style']), $search) !== false ||
               strpos(strtolower($b['description']), $search) !== false;
    });
}

// Meta counts (from all bands, not filtered)
$meta = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'priority' => 0];
$regionCounts = [];
$totalSongsGenerated = 0;
$totalSongsInRotation = 0;

foreach ($bands as $band) {
    $meta['total']++;
    $meta[$band['status']]++;
    if ($band['priority']) $meta['priority']++;

    $r = $band['region'];
    if (!isset($regionCounts[$r])) $regionCounts[$r] = 0;
    $regionCounts[$r]++;

    $stats = $songStats[$band['slug']] ?? ['withAudio' => 0, 'inRotation' => 0];
    $totalSongsGenerated += $stats['withAudio'];
    $totalSongsInRotation += $stats['inRotation'];
}

$meta['songsGenerated'] = $totalSongsGenerated;
$meta['songsInRotation'] = $totalSongsInRotation;
$meta['totalSongs'] = count($songs);

// Lyrics-specific counts
$lyricsNone = 0; $lyricsPending = 0; $lyricsApproved = 0; $lyricsRejected = 0;
foreach ($songs as $song) {
    $ls = $song['lyricsStatus'] ?? 'none';
    if ($ls === 'none') $lyricsNone++;
    elseif ($ls === 'pending') $lyricsPending++;
    elseif ($ls === 'approved') $lyricsApproved++;
    elseif ($ls === 'rejected') $lyricsRejected++;
}
$meta['lyricsNone'] = $lyricsNone;
$meta['lyricsPending'] = $lyricsPending;
$meta['lyricsApproved'] = $lyricsApproved;
$meta['lyricsRejected'] = $lyricsRejected;

echo json_encode([
    'bands' => array_values($filtered),
    'meta' => $meta,
    'regions' => $regionCounts
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
