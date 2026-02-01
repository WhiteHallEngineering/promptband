<?php
/**
 * PROMPT Band - Track Play Logger
 * Logs track plays to a JSON file for analytics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['track'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing track data']);
    exit;
}

// Log file path (outside web root for security, but we'll use a protected dir)
$logDir = __DIR__ . '/../analytics';
$logFile = $logDir . '/plays.json';

// Create directory if needed
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
    // Protect directory with .htaccess
    file_put_contents($logDir . '/.htaccess', "Deny from all\n");
}

// Build play record
$play = [
    'timestamp' => date('c'),
    'track' => $input['track'],
    'trackIndex' => $input['trackIndex'] ?? null,
    'duration' => $input['duration'] ?? null,
    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'ip' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown'), // Hashed for privacy
    'referer' => $_SERVER['HTTP_REFERER'] ?? null
];

// Load existing plays or start fresh
$plays = [];
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $plays = json_decode($content, true) ?: [];
}

// Add new play
$plays[] = $play;

// Keep only last 10000 plays to prevent file from growing too large
if (count($plays) > 10000) {
    $plays = array_slice($plays, -10000);
}

// Save
if (file_put_contents($logFile, json_encode($plays, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true, 'totalPlays' => count($plays)]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save']);
}
