<?php
/**
 * Get video concepts for PROMPT tracks
 * Returns story, logline, acts, visual signature, color palette, motifs
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$conceptsFile = __DIR__ . '/video-concepts.json';
if (!file_exists($conceptsFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Video concepts file not found']);
    exit;
}

$concepts = json_decode(file_get_contents($conceptsFile), true);

// If specific track requested
$trackId = $_GET['track'] ?? null;
if ($trackId) {
    if (isset($concepts[$trackId])) {
        echo json_encode(['success' => true, 'concept' => $concepts[$trackId]]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Track $trackId not found"]);
    }
    exit;
}

// Return all concepts
echo json_encode(['success' => true, 'concepts' => $concepts]);
