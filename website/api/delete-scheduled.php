<?php
/**
 * Delete a scheduled post
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
$postId = $input['id'] ?? $_GET['id'] ?? '';

if (empty($postId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Post id is required']);
    exit;
}

$scheduleFile = dirname(__DIR__) . '/analytics/scheduled-posts.json';

$scheduled = [];
if (file_exists($scheduleFile)) {
    $scheduled = json_decode(file_get_contents($scheduleFile), true) ?? [];
}

$found = false;
$scheduled = array_filter($scheduled, function($p) use ($postId, &$found) {
    if ($p['id'] === $postId) {
        $found = true;
        return false;
    }
    return true;
});

if (!$found) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Post not found']);
    exit;
}

$scheduled = array_values($scheduled);
file_put_contents($scheduleFile, json_encode($scheduled, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'message' => 'Post deleted']);
