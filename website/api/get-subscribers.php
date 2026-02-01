<?php
/**
 * PROMPT Newsletter Subscribers API
 * Retrieve newsletter subscribers (protected)
 */

header('Content-Type: application/json');

// Simple key-based authentication
$validKey = 'pr0mpt-m3ss4g3s-2026';

$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$signupsFile = __DIR__ . '/../analytics/newsletter.json';

if (!file_exists($signupsFile)) {
    echo json_encode([
        'total' => 0,
        'subscribers' => []
    ]);
    exit;
}

$subscribers = json_decode(file_get_contents($signupsFile), true) ?: [];

// Sort by timestamp descending (newest first)
usort($subscribers, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Option to export as CSV
if (isset($_GET['format']) && $_GET['format'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="prompt-subscribers-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Email', 'Subscribed Date']);

    foreach ($subscribers as $sub) {
        fputcsv($output, [
            $sub['email'],
            date('Y-m-d H:i:s', strtotime($sub['timestamp']))
        ]);
    }

    fclose($output);
    exit;
}

echo json_encode([
    'total' => count($subscribers),
    'subscribers' => $subscribers
], JSON_PRETTY_PRINT);
