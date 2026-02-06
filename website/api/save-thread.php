<?php
/**
 * Save a thread draft
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$title = $input['title'] ?? '';
$tweets = $input['tweets'] ?? [];
$imageUrl = $input['image_url'] ?? '';

if (empty($title) || empty($tweets)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'title and tweets are required']);
    exit;
}

$storageDir = dirname(__DIR__) . '/analytics';
$threadsFile = $storageDir . '/threads.json';

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$threads = [];
if (file_exists($threadsFile)) {
    $threads = json_decode(file_get_contents($threadsFile), true) ?? [];
}

$thread = [
    'id' => uniqid('thread_'),
    'title' => $title,
    'tweets' => $tweets,
    'tweet_count' => count($tweets),
    'image_url' => $imageUrl,
    'created' => date('c'),
    'posted' => false
];

$threads[] = $thread;

file_put_contents($threadsFile, json_encode($threads, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'id' => $thread['id']]);
