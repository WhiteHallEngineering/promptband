<?php
/**
 * Delete a saved thread
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$threadId = $input['id'] ?? $_GET['id'] ?? '';

if (empty($threadId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Thread id is required']);
    exit;
}

$threadsFile = dirname(__DIR__) . '/analytics/threads.json';

$threads = [];
if (file_exists($threadsFile)) {
    $threads = json_decode(file_get_contents($threadsFile), true) ?? [];
}

// Find and remove the thread
$found = false;
$threads = array_filter($threads, function($t) use ($threadId, &$found) {
    if ($t['id'] === $threadId) {
        $found = true;
        return false;
    }
    return true;
});

if (!$found) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Thread not found']);
    exit;
}

// Re-index array and save
$threads = array_values($threads);
file_put_contents($threadsFile, json_encode($threads, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'message' => 'Thread deleted']);
