<?php
/**
 * Save video concept for a PROMPT track
 * Saves AI-generated creative vision to video-concepts.json
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$trackId = $input['trackId'] ?? null;
$concept = $input['concept'] ?? null;

if (!$trackId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'trackId required']);
    exit;
}

$conceptsFile = __DIR__ . '/video-concepts.json';
$concepts = [];

if (file_exists($conceptsFile)) {
    $concepts = json_decode(file_get_contents($conceptsFile), true) ?? [];
}

// Update or delete the concept for this track
if ($concept === null) {
    // Delete the concept
    unset($concepts[$trackId]);
} else {
    // Save the concept
    $concepts[$trackId] = $concept;
}

// Update meta
$concepts['meta'] = [
    'version' => '1.0',
    'createdAt' => '2026-02-04',
    'lastUpdated' => date('c'),
    'description' => 'Video concept stories for all 10 PROMPT tracks. Each concept includes a logline, 4-act story structure, visual signature, color palette, key motifs, and director references.',
    'usage' => 'These concepts drive the visual storytelling. Shot lists and prompts should serve these stories, not the other way around.'
];

// Save
if (file_put_contents($conceptsFile, json_encode($concepts, JSON_PRETTY_PRINT))) {
    echo json_encode([
        'success' => true,
        'message' => "Video concept saved for track $trackId",
        'trackId' => $trackId
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save concept']);
}
