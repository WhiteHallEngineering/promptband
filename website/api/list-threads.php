<?php
/**
 * List saved thread drafts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$threadsFile = dirname(__DIR__) . '/analytics/threads.json';

$threads = [];
if (file_exists($threadsFile)) {
    $threads = json_decode(file_get_contents($threadsFile), true) ?? [];
}

// Return summary without full tweet content
$summary = array_map(function($t) {
    return [
        'id' => $t['id'],
        'title' => $t['title'],
        'tweet_count' => $t['tweet_count'],
        'created' => $t['created'],
        'posted' => $t['posted'] ?? false
    ];
}, $threads);

// Sort by created date, newest first
usort($summary, function($a, $b) {
    return strtotime($b['created']) - strtotime($a['created']);
});

echo json_encode(['success' => true, 'threads' => $summary]);
