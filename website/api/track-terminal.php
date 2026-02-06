<?php
/**
 * PROMPT Band - Terminal Command Logger
 * Logs terminal usage to a JSON file for analytics
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

if (!$input || !isset($input['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event data']);
    exit;
}

// Log file path
$logDir = __DIR__ . '/../analytics';
$logFile = $logDir . '/terminal.json';

// Create directory if needed
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
    file_put_contents($logDir . '/.htaccess', "Deny from all\n");
}

// Build log record
$record = [
    'timestamp' => date('c'),
    'event' => $input['event'], // 'open', 'close', 'command'
    'command' => $input['command'] ?? null,
    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'ip' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    'referer' => $_SERVER['HTTP_REFERER'] ?? null
];

// Load existing logs or start fresh
$logs = [];
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $logs = json_decode($content, true) ?: [];
}

// Add new record
$logs[] = $record;

// Keep only last 5000 entries
if (count($logs) > 5000) {
    $logs = array_slice($logs, -5000);
}

// Save
if (file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save']);
}
