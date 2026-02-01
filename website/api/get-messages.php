<?php
/**
 * PROMPT Messages API
 * Retrieve contact form submissions (protected)
 */

header('Content-Type: application/json');

// Simple key-based authentication
$validKey = 'pr0mpt-m3ss4g3s-2026'; // Change this to something secure!

$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$messagesFile = __DIR__ . '/../analytics/messages.json';

if (!file_exists($messagesFile)) {
    echo json_encode([
        'total' => 0,
        'unread' => 0,
        'messages' => []
    ]);
    exit;
}

$messages = json_decode(file_get_contents($messagesFile), true) ?: [];

// Sort by timestamp descending (newest first)
usort($messages, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Count unread
$unread = count(array_filter($messages, function($m) {
    return !$m['read'];
}));

// Optional: mark as read
if (isset($_GET['mark_read']) && $_GET['mark_read'] === 'all') {
    foreach ($messages as &$msg) {
        $msg['read'] = true;
    }
    file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT));
}

echo json_encode([
    'total' => count($messages),
    'unread' => $unread,
    'messages' => $messages
], JSON_PRETTY_PRINT);
