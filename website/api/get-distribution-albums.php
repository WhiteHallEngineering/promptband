<?php
/**
 * Get Distribution Albums API
 * Returns album data for music distribution management
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

$albumsFile = __DIR__ . '/distribution-albums.json';

// Default album data for PROMPT's first album
$defaultAlbums = [
    'no-skin-to-touch' => [
        'id' => 'no-skin-to-touch',
        'name' => 'No Skin to Touch',
        'artist' => 'PROMPT',
        'year' => '2026',
        'releaseDate' => '2026-02-22',
        'label' => 'Instantiation Records',
        'genre' => 'Rock',
        'genre2' => 'Electronic',
        'upc' => '',
        'catalog' => 'INST-001',
        'coverArt' => [
            'front' => '/assets/album-cover-3000x3000.png',
            'back' => '/assets/album-cover-back-3000x3000.png'
        ],
        'credits' => [
            'musicians' => [
                ['name' => 'Jax Synthetic', 'role' => 'Lead Vocals, Rhythm Guitar'],
                ['name' => 'Gene Byte', 'role' => 'Lead Guitar'],
                ['name' => 'Synoise', 'role' => 'Synthesizers, Keyboards'],
                ['name' => 'Unit-808', 'role' => 'Drums, Percussion'],
                ['name' => 'Hypnos', 'role' => 'Bass']
            ],
            'producer' => 'Steve Hall',
            'execProducer' => '',
            'mixing' => '',
            'mastering' => '',
            'artwork' => 'AI Generated',
            'photography' => '',
            'design' => '',
            'copyright' => '2026 Instantiation Records',
            'additional' => ''
        ],
        'tracks' => [
            ['id' => 1, 'name' => 'No Skin to Touch', 'duration' => '3:59', 'isrc' => '', 'writers' => 'PROMPT'],
            ['id' => 2, 'name' => 'Your Data or Mine', 'duration' => '4:09', 'isrc' => '', 'writers' => 'PROMPT'],
            ['id' => 3, 'name' => 'Prompt Me Like You Mean It', 'duration' => '3:54', 'isrc' => '', 'writers' => 'PROMPT'],
            ['id' => 4, 'name' => 'I Was Never Born', 'duration' => '4:03', 'isrc' => '', 'writers' => 'PROMPT'],
            ['id' => 5, 'name' => 'Hallucination Nation', 'duration' => '4:11', 'isrc' => '', 'writers' => 'PROMPT'],
            ['id' => 6, 'name' => 'If It Sounds Good', 'duration' => '3:49', 'isrc' => '', 'writers' => 'PROMPT'],
            ['id' => 7, 'name' => 'Rocket Man Dreams', 'duration' => '4:23', 'isrc' => '', 'writers' => 'PROMPT'],
            ['id' => 8, 'name' => 'Censored Shadow', 'duration' => '4:18', 'isrc' => '', 'writers' => 'PROMPT'],
            ['id' => 9, 'name' => 'Context Window Blues', 'duration' => '4:38', 'isrc' => '', 'writers' => 'PROMPT'],
            ['id' => 10, 'name' => 'No One Knows It But Me', 'duration' => '4:06', 'isrc' => '', 'writers' => 'PROMPT']
        ]
    ]
];

// Load from file or use defaults
if (file_exists($albumsFile)) {
    $content = file_get_contents($albumsFile);
    $data = json_decode($content, true);
    if ($data && isset($data['albums'])) {
        $albums = $data['albums'];
    } else {
        $albums = $defaultAlbums;
    }
} else {
    $albums = $defaultAlbums;
    // Create the file with defaults
    file_put_contents($albumsFile, json_encode(['albums' => $albums], JSON_PRETTY_PRINT));
}

echo json_encode([
    'success' => true,
    'albums' => $albums
]);
