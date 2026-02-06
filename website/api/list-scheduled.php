<?php
/**
 * List scheduled posts - returns only pending by default
 * Use ?include_archived=1 to also get archived posts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$scheduleFile = dirname(__DIR__) . '/analytics/scheduled-posts.json';
$archiveFile = dirname(__DIR__) . '/analytics/posted-archive.json';
$activityFile = dirname(__DIR__) . '/analytics/activity-log.json';

$scheduled = [];
if (file_exists($scheduleFile)) {
    $scheduled = json_decode(file_get_contents($scheduleFile), true) ?? [];
}

// Auto-archive: move posted/failed items to archive
$toArchive = array_filter($scheduled, fn($p) => in_array($p['status'], ['posted', 'failed']));
if (!empty($toArchive)) {
    // Load existing archive
    $archive = [];
    if (file_exists($archiveFile)) {
        $archive = json_decode(file_get_contents($archiveFile), true) ?? [];
    }

    // Add to archive
    foreach ($toArchive as $post) {
        $post['archived_at'] = date('c');
        $archive[] = $post;
    }

    // Keep only last 200 archived items
    $archive = array_slice($archive, -200);
    file_put_contents($archiveFile, json_encode($archive, JSON_PRETTY_PRINT));

    // Remove from scheduled
    $scheduled = array_filter($scheduled, fn($p) => $p['status'] === 'pending');
    file_put_contents($scheduleFile, json_encode(array_values($scheduled), JSON_PRETTY_PRINT));
}

// Optional filters
$category = $_GET['category'] ?? '';
if ($category) {
    $scheduled = array_filter($scheduled, fn($p) => $p['category'] === $category);
}

// Load archive if requested
$archivedPosts = [];
if ($_GET['include_archived'] ?? false) {
    if (file_exists($archiveFile)) {
        $archivedPosts = json_decode(file_get_contents($archiveFile), true) ?? [];
    }
}

// Load recent activity
$recentActivity = [];
if (file_exists($activityFile)) {
    $activity = json_decode(file_get_contents($activityFile), true) ?? [];
    $recentActivity = array_slice($activity, -20);
}

// Stats
$allArchived = file_exists($archiveFile) ? json_decode(file_get_contents($archiveFile), true) ?? [] : [];
$stats = [
    'pending' => count($scheduled),
    'posted' => count(array_filter($allArchived, fn($p) => $p['status'] === 'posted')),
    'failed' => count(array_filter($allArchived, fn($p) => $p['status'] === 'failed'))
];

echo json_encode([
    'success' => true,
    'stats' => $stats,
    'posts' => array_values($scheduled),
    'archived' => array_values($archivedPosts),
    'recent_activity' => array_reverse($recentActivity)
]);
