<?php
/**
 * PROMPT Song Titles API
 * Retrieve song titles data (protected)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simple key-based authentication
$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$titlesFile = __DIR__ . '/song-titles.json';

if (!file_exists($titlesFile)) {
    echo json_encode([
        'used' => [],
        'suggested' => [],
        'ideas' => []
    ]);
    exit;
}

$data = json_decode(file_get_contents($titlesFile), true);

if ($data === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to parse song titles data']);
    exit;
}

// Return the data with counts
echo json_encode([
    'albums' => $data['albums'] ?? [],
    'used' => $data['used'] ?? [],
    'suggested' => $data['suggested'] ?? [],
    'ideas' => $data['ideas'] ?? [],
    'counts' => [
        'albums' => count($data['albums'] ?? []),
        'used' => count($data['used'] ?? []),
        'suggested' => count($data['suggested'] ?? []),
        'ideas' => count($data['ideas'] ?? [])
    ]
], JSON_PRETTY_PRINT);
