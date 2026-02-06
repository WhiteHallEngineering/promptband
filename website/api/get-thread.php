<?php
/**
 * Get a specific saved thread
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id is required']);
    exit;
}

$threadsFile = dirname(__DIR__) . '/analytics/threads.json';

$threads = [];
if (file_exists($threadsFile)) {
    $threads = json_decode(file_get_contents($threadsFile), true) ?? [];
}

$found = null;
foreach ($threads as $thread) {
    if ($thread['id'] === $id) {
        $found = $thread;
        break;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Thread not found']);
    exit;
}

echo json_encode(['success' => true, 'thread' => $found]);
