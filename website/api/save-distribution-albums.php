<?php
/**
 * Save Distribution Albums API
 * Saves album data for music distribution management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['albums'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No albums data provided']);
    exit;
}

$albumsFile = __DIR__ . '/distribution-albums.json';

// Validate basic structure
$albums = $input['albums'];
foreach ($albums as $albumId => $album) {
    if (!isset($album['id']) || !isset($album['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Invalid album structure for: $albumId"]);
        exit;
    }
}

// Save
$data = [
    'albums' => $albums,
    'lastModified' => date('c')
];

$result = file_put_contents($albumsFile, json_encode($data, JSON_PRETTY_PRINT));

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save albums']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Albums saved successfully',
    'albumCount' => count($albums)
]);
