<?php
/**
 * PROMPT Band - Terminal Stats
 * Returns terminal usage statistics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Simple key protection
$validKey = 'pr0mpt-m3ss4g3s-2026';

if (!isset($_GET['key']) || $_GET['key'] !== $validKey) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid key']);
    exit;
}

$logFile = __DIR__ . '/../analytics/terminal.json';

if (!file_exists($logFile)) {
    echo json_encode([
        'totalEvents' => 0,
        'uniqueUsers' => 0,
        'opens' => 0,
        'commands' => [],
        'recentActivity' => []
    ]);
    exit;
}

$logs = json_decode(file_get_contents($logFile), true) ?: [];

// Calculate stats
$uniqueIPs = [];
$opens = 0;
$commandCounts = [];
$commandsByUser = [];

foreach ($logs as $log) {
    $ip = $log['ip'] ?? 'unknown';
    $uniqueIPs[$ip] = true;

    if ($log['event'] === 'open') {
        $opens++;
    }

    if ($log['event'] === 'command' && !empty($log['command'])) {
        $cmd = strtolower(trim($log['command']));
        // Just track the base command, not full input
        $baseCmd = explode(' ', $cmd)[0];
        $commandCounts[$baseCmd] = ($commandCounts[$baseCmd] ?? 0) + 1;

        // Track full commands per user
        if (!isset($commandsByUser[$ip])) {
            $commandsByUser[$ip] = [];
        }
        $commandsByUser[$ip][] = [
            'command' => $log['command'],
            'timestamp' => $log['timestamp']
        ];
    }
}

// Sort commands by popularity
arsort($commandCounts);

// Get recent activity (last 50)
$recentActivity = array_slice(array_reverse($logs), 0, 50);

// Sessions: group by user with their commands
$sessions = [];
foreach ($commandsByUser as $ip => $cmds) {
    $sessions[] = [
        'user' => substr($ip, 0, 8) . '...',
        'commandCount' => count($cmds),
        'commands' => array_slice($cmds, -10) // Last 10 commands per user
    ];
}

// Sort sessions by command count
usort($sessions, function($a, $b) {
    return $b['commandCount'] - $a['commandCount'];
});

echo json_encode([
    'totalEvents' => count($logs),
    'uniqueUsers' => count($uniqueIPs),
    'terminalOpens' => $opens,
    'commandBreakdown' => $commandCounts,
    'sessions' => array_slice($sessions, 0, 20),
    'recentActivity' => $recentActivity
], JSON_PRETTY_PRINT);
