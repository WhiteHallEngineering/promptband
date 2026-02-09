<?php
/**
 * Signal 0 Radio — Listener Analytics Proxy
 *
 * Proxies analytics data from Lightsail (signal0radio.com) to the admin dashboard.
 * Requires API key authentication.
 *
 * Actions:
 *   ?action=realtime   — Current listeners, peak, title, server status
 *   ?action=today      — Today's snapshots (downsampled) + sessions + running totals
 *   ?action=daily&date=YYYY-MM-DD — Full daily summary for a specific date
 *   ?action=history    — Array of daily summaries for trend table
 *   ?action=sessions&date=YYYY-MM-DD — Last 100 sessions for a date
 */

header('Access-Control-Allow-Origin: https://promptband.ai');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$validKey = 'pr0mpt-m3ss4g3s-2026';
$key = $_GET['key'] ?? '';
if ($key !== $validKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$action = $_GET['action'] ?? 'realtime';
$analyticsBase = 'https://signal0radio.com/analytics';

/**
 * Fetch a URL from Lightsail with timeout
 */
function fetchFromLightsail($url) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        return null;
    }
    return $result;
}

/**
 * Parse JSONL content into array of objects
 */
function parseJsonl($content) {
    if (empty($content)) return [];
    $lines = explode("\n", trim($content));
    $items = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        $item = json_decode($line, true);
        if ($item) $items[] = $item;
    }
    return $items;
}

/**
 * Downsample array to ~N points by taking every Nth item
 */
function downsample($items, $targetPoints = 288) {
    $count = count($items);
    if ($count <= $targetPoints) return $items;
    $step = max(1, (int)floor($count / $targetPoints));
    $result = [];
    for ($i = 0; $i < $count; $i += $step) {
        $result[] = $items[$i];
    }
    return $result;
}

// ─── Handle actions ───

switch ($action) {

case 'realtime':
    $current = fetchFromLightsail("$analyticsBase/current.json");
    if ($current === null) {
        echo json_encode([
            'success' => true,
            'server' => 'offline',
            'listeners' => 0,
            'peak' => 0,
            'title' => '',
            'updated' => gmdate('Y-m-d\TH:i:s\Z')
        ]);
        exit;
    }
    $data = json_decode($current, true);
    if (!$data) {
        echo json_encode([
            'success' => true,
            'server' => 'offline',
            'listeners' => 0,
            'peak' => 0,
            'title' => '',
            'updated' => gmdate('Y-m-d\TH:i:s\Z')
        ]);
        exit;
    }
    $data['success'] = true;
    echo json_encode($data);
    break;

case 'today':
    $date = gmdate('Y-m-d');

    // Fetch today's snapshots (downsampled)
    $snapshotsRaw = fetchFromLightsail("$analyticsBase/snapshots-{$date}.jsonl");
    $snapshots = parseJsonl($snapshotsRaw);
    $downsampled = downsample($snapshots);

    // Fetch today's sessions
    $sessionsRaw = fetchFromLightsail("$analyticsBase/sessions-{$date}.jsonl");
    $sessions = parseJsonl($sessionsRaw);

    // Calculate running totals from sessions
    $uniqueIps = [];
    $totalSeconds = 0;
    foreach ($sessions as $s) {
        $uniqueIps[$s['ip'] ?? ''] = true;
        $totalSeconds += ($s['dur'] ?? 0);
    }

    // Peak from snapshots
    $peak = 0;
    foreach ($snapshots as $s) {
        $peak = max($peak, $s['p'] ?? 0);
    }

    // Recent 50 sessions (newest first)
    $recentSessions = array_slice(array_reverse($sessions), 0, 50);

    echo json_encode([
        'success' => true,
        'date' => $date,
        'snapshots' => $downsampled,
        'stats' => [
            'unique_listeners' => count($uniqueIps),
            'total_sessions' => count($sessions),
            'total_listen_seconds' => $totalSeconds,
            'peak_concurrent' => $peak,
            'listen_hours' => round($totalSeconds / 3600, 1)
        ],
        'recent_sessions' => $recentSessions
    ]);
    break;

case 'daily':
    $date = $_GET['date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'error' => 'Invalid date format (YYYY-MM-DD)']);
        exit;
    }
    $daily = fetchFromLightsail("$analyticsBase/daily-{$date}.json");
    if ($daily === null) {
        echo json_encode(['success' => false, 'error' => 'No data for this date']);
        exit;
    }
    $data = json_decode($daily, true);
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }
    $data['success'] = true;
    echo json_encode($data);
    break;

case 'history':
    $index = fetchFromLightsail("$analyticsBase/daily-index.json");
    if ($index === null) {
        echo json_encode(['success' => true, 'days' => []]);
        exit;
    }
    $days = json_decode($index, true);
    if (!is_array($days)) $days = [];
    // Return newest first, last 30 entries
    $days = array_reverse($days);
    $days = array_slice($days, 0, 30);
    echo json_encode(['success' => true, 'days' => $days]);
    break;

case 'sessions':
    $date = $_GET['date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'error' => 'Invalid date format (YYYY-MM-DD)']);
        exit;
    }
    $sessionsRaw = fetchFromLightsail("$analyticsBase/sessions-{$date}.jsonl");
    $sessions = parseJsonl($sessionsRaw);
    // Last 100 sessions, newest first
    $sessions = array_slice(array_reverse($sessions), 0, 100);
    echo json_encode(['success' => true, 'sessions' => $sessions, 'date' => $date]);
    break;

default:
    echo json_encode(['success' => false, 'error' => 'Unknown action. Use: realtime, today, daily, history, sessions']);
    break;
}
