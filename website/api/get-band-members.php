<?php
/**
 * Get Band Members API
 * Returns band member data from band-members.json
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Load band member data
$jsonFile = __DIR__ . '/band-members.json';

if (!file_exists($jsonFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Band members data file not found']);
    exit;
}

$data = file_get_contents($jsonFile);
$bandMembers = json_decode($data, true);

if ($bandMembers === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to parse band members data']);
    exit;
}

echo json_encode($bandMembers, JSON_PRETTY_PRINT);
