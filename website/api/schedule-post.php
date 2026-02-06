<?php
/**
 * Schedule a post for future publishing
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
$message = $input['message'] ?? '';
$scheduledFor = $input['scheduled_for'] ?? ''; // ISO 8601 datetime
$category = $input['category'] ?? 'general';
$imageUrl = $input['image_url'] ?? '';
$platform = $input['platform'] ?? 'twitter'; // twitter, all

if (empty($message) || empty($scheduledFor)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'message and scheduled_for are required']);
    exit;
}

// Validate date
$scheduledTime = strtotime($scheduledFor);
if (!$scheduledTime) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid scheduled_for date format']);
    exit;
}

$storageDir = dirname(__DIR__) . '/analytics';
$scheduleFile = $storageDir . '/scheduled-posts.json';

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$scheduled = [];
if (file_exists($scheduleFile)) {
    $scheduled = json_decode(file_get_contents($scheduleFile), true) ?? [];
}

$post = [
    'id' => uniqid('post_'),
    'message' => $message,
    'image_url' => $imageUrl,
    'platform' => $platform,
    'category' => $category,
    'scheduled_for' => date('c', $scheduledTime),
    'created' => date('c'),
    'status' => 'pending', // pending, posted, failed
    'posted_at' => null,
    'tweet_url' => null,
    'error' => null
];

$scheduled[] = $post;

// Sort by scheduled time
usort($scheduled, function($a, $b) {
    return strtotime($a['scheduled_for']) - strtotime($b['scheduled_for']);
});

file_put_contents($scheduleFile, json_encode($scheduled, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'post' => $post]);
