<?php
/**
 * PROMPT Band - Play Statistics
 * Returns aggregated play statistics (password protected)
 */

header('Content-Type: application/json');

// Simple password protection - change this!
$password = $_GET['key'] ?? '';
$validKey = 'pr0mpt-st4ts-2024'; // Change this to something secure

if ($password !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$logFile = __DIR__ . '/../analytics/plays.json';

if (!file_exists($logFile)) {
    echo json_encode([
        'totalPlays' => 0,
        'tracks' => [],
        'recentPlays' => [],
        'playsByDay' => []
    ]);
    exit;
}

$plays = json_decode(file_get_contents($logFile), true) ?: [];

// Aggregate stats
$trackCounts = [];
$playsByDay = [];
$uniqueListeners = [];

foreach ($plays as $play) {
    // Count by track
    $track = $play['track'] ?? 'unknown';
    if (!isset($trackCounts[$track])) {
        $trackCounts[$track] = 0;
    }
    $trackCounts[$track]++;

    // Count by day
    $day = substr($play['timestamp'] ?? '', 0, 10);
    if ($day) {
        if (!isset($playsByDay[$day])) {
            $playsByDay[$day] = 0;
        }
        $playsByDay[$day]++;
    }

    // Unique listeners (by hashed IP)
    $ip = $play['ip'] ?? '';
    if ($ip) {
        $uniqueListeners[$ip] = true;
    }
}

// Sort tracks by play count
arsort($trackCounts);

// Get last 7 days
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $last7Days[$day] = $playsByDay[$day] ?? 0;
}

// Recent plays (last 20)
$recentPlays = array_slice(array_reverse($plays), 0, 20);

echo json_encode([
    'totalPlays' => count($plays),
    'uniqueListeners' => count($uniqueListeners),
    'tracks' => $trackCounts,
    'playsByDay' => $last7Days,
    'recentPlays' => $recentPlays
], JSON_PRETTY_PRINT);
