<?php
/**
 * PROMPT Sound Profile API - GET
 * Retrieve PROMPT's sound/style settings for song generation
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

$profileFile = __DIR__ . '/prompt-profile.json';

if (!file_exists($profileFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Profile not found']);
    exit;
}

$data = json_decode(file_get_contents($profileFile), true);

if ($data === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to parse profile data']);
    exit;
}

echo json_encode($data, JSON_PRETTY_PRINT);
