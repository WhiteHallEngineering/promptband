<?php
/**
 * Get Albums API
 * Returns all configured albums for the EDL Editor
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$albumsFile = __DIR__ . '/albums.json';

// Default albums data - simple structure for video EDL organization
$defaultAlbums = [
    'no-skin-to-touch' => [
        'id' => 'no-skin-to-touch',
        'name' => 'No Skin to Touch',
        'year' => '2026',
        'audioPath' => '/audio/full/',
        'tracks' => [
            ['id' => 1, 'name' => 'No Skin to Touch', 'duration' => '3:59', 'file' => '01-no-skin-to-touch.mp3'],
            ['id' => 2, 'name' => 'Your Data or Mine', 'duration' => '4:09', 'file' => '02-your-data-or-mine.mp3'],
            ['id' => 3, 'name' => 'Prompt Me Like You Mean It', 'duration' => '3:54', 'file' => '03-prompt-me-like-you-mean-it.mp3'],
            ['id' => 4, 'name' => 'I Was Never Born', 'duration' => '4:03', 'file' => '04-i-was-never-born.mp3'],
            ['id' => 5, 'name' => 'Hallucination Nation', 'duration' => '4:11', 'file' => '05-hallucination-nation.mp3'],
            ['id' => 6, 'name' => 'If It Sounds Good', 'duration' => '3:49', 'file' => '06-if-it-sounds-good.mp3'],
            ['id' => 7, 'name' => 'Rocket Man Dreams', 'duration' => '4:23', 'file' => '07-rocket-man-dreams.mp3'],
            ['id' => 8, 'name' => 'Censored Shadow', 'duration' => '4:18', 'file' => '08-censored-shadow.mp3'],
            ['id' => 9, 'name' => 'Context Window Blues', 'duration' => '4:38', 'file' => '09-context-window-blues.mp3'],
            ['id' => 10, 'name' => 'No One Knows It But Me', 'duration' => '4:06', 'file' => '10-no-one-knows-it-but-me.mp3']
        ]
    ]
];

// Load albums from file or use defaults
if (file_exists($albumsFile)) {
    $content = file_get_contents($albumsFile);
    $albums = json_decode($content, true);
    if (!$albums || !isset($albums['albums'])) {
        $albums = ['albums' => $defaultAlbums];
    }
} else {
    $albums = ['albums' => $defaultAlbums];
    // Create the file with defaults
    file_put_contents($albumsFile, json_encode($albums, JSON_PRETTY_PRINT));
}

echo json_encode([
    'success' => true,
    'albums' => $albums['albums']
]);
