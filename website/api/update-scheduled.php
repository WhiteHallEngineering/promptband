<?php
/**
 * Update a scheduled post (mark as posted, update status, etc.)
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
$postId = $input['id'] ?? '';
$status = $input['status'] ?? '';
$tweetUrl = $input['tweet_url'] ?? '';

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

// Find and update the post
$found = false;
$updatedPost = null;
foreach ($scheduled as &$post) {
    if ($post['id'] === $postId) {
        $found = true;
        if ($status) {
            $post['status'] = $status;
        }
        if ($status === 'posted') {
            $post['posted_at'] = date('c');
        }
        if ($tweetUrl) {
            $post['tweet_url'] = $tweetUrl;
        }
        $updatedPost = $post;
        break;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Post not found']);
    exit;
}

file_put_contents($scheduleFile, json_encode($scheduled, JSON_PRETTY_PRINT));

// Log activity if posted
if ($status === 'posted' && $updatedPost) {
    $activityFile = dirname(__DIR__) . '/analytics/activity-log.json';
    $activity = [];
    if (file_exists($activityFile)) {
        $activity = json_decode(file_get_contents($activityFile), true) ?? [];
    }
    $activity[] = [
        'type' => 'post_published',
        'timestamp' => date('c'),
        'message' => substr($updatedPost['message'] ?? '', 0, 60) . '...',
        'tweet_url' => $tweetUrl,
        'category' => $updatedPost['category'] ?? 'manual'
    ];
    // Keep last 100 activity items
    $activity = array_slice($activity, -100);
    file_put_contents($activityFile, json_encode($activity, JSON_PRETTY_PRINT));
}

echo json_encode(['success' => true, 'message' => 'Post updated']);
