<?php
/**
 * PROMPT Sound Profile API - SAVE
 * Update PROMPT's sound/style settings for song generation
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
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

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

// Load existing data for merging
$existingData = [];
if (file_exists($profileFile)) {
    $existingData = json_decode(file_get_contents($profileFile), true) ?? [];
}

// Deep merge the input with existing data
$newData = array_replace_recursive($existingData, $input);

// Validate required structure
$requiredSections = ['persona', 'style', 'lyricGuidelines'];
foreach ($requiredSections as $section) {
    if (!isset($newData[$section])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required section: $section"]);
        exit;
    }
}

// Save the data
$saved = file_put_contents($profileFile, json_encode($newData, JSON_PRETTY_PRINT));

if ($saved === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save profile data']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Profile saved successfully',
    'profile' => $newData
], JSON_PRETTY_PRINT);
