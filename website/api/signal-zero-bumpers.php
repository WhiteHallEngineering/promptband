<?php
/**
 * Signal 0 Radio — List & Delete Bumpers
 * Manages the DJ bumper library
 *
 * GET: List all bumpers (optional filters: ?dj=slug, ?type=station-id)
 * POST action=delete: Remove a bumper (metadata + MP3 file)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$password = $_GET['key'] ?? '';
$validKey = 'pr0mpt-m3ss4g3s-2026';

if ($password !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$bumpersFile = __DIR__ . '/signal-zero-bumpers.json';

// ─── GET: List bumpers with optional filters ─────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $bumpers = [];
    if (file_exists($bumpersFile)) {
        $bumpers = json_decode(file_get_contents($bumpersFile), true) ?: [];
    }

    // Optional filters
    $djFilter = $_GET['dj'] ?? '';
    $typeFilter = $_GET['type'] ?? '';

    if ($djFilter) {
        $bumpers = array_filter($bumpers, function($b) use ($djFilter) {
            return $b['djSlug'] === $djFilter;
        });
    }

    if ($typeFilter) {
        $bumpers = array_filter($bumpers, function($b) use ($typeFilter) {
            return $b['type'] === $typeFilter;
        });
    }

    // Re-index array after filtering
    $bumpers = array_values($bumpers);

    // Build summary stats
    $stats = [
        'total' => count($bumpers),
        'byDj' => [],
        'byType' => []
    ];
    foreach ($bumpers as $bumper) {
        $dj = $bumper['djSlug'] ?? 'unknown';
        $type = $bumper['type'] ?? 'unknown';
        if (!isset($stats['byDj'][$dj])) $stats['byDj'][$dj] = 0;
        if (!isset($stats['byType'][$type])) $stats['byType'][$type] = 0;
        $stats['byDj'][$dj]++;
        $stats['byType'][$type]++;
    }

    echo json_encode([
        'stats' => $stats,
        'bumpers' => $bumpers
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── POST: Delete a bumper ───────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'GET or POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action !== 'delete') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action. Use: delete']);
    exit;
}

$id = $input['id'] ?? '';
if (empty($id)) {
    http_response_code(400);
    echo json_encode(['error' => 'id is required']);
    exit;
}

if (!file_exists($bumpersFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'No bumpers data found']);
    exit;
}

$bumpers = json_decode(file_get_contents($bumpersFile), true) ?: [];

// Find and remove the bumper
$found = false;
$deletedBumper = null;
$updatedBumpers = [];

foreach ($bumpers as $bumper) {
    if ($bumper['id'] === $id) {
        $found = true;
        $deletedBumper = $bumper;

        // Delete the MP3 file from disk
        $audioPath = $bumper['audioUrl'] ?? '';
        if ($audioPath) {
            $fullPath = __DIR__ . '/..' . $audioPath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    } else {
        $updatedBumpers[] = $bumper;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['error' => 'Bumper not found: ' . $id]);
    exit;
}

// Save updated list
file_put_contents($bumpersFile, json_encode($updatedBumpers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Return updated stats
$stats = [
    'total' => count($updatedBumpers),
    'byDj' => [],
    'byType' => []
];
foreach ($updatedBumpers as $bumper) {
    $dj = $bumper['djSlug'] ?? 'unknown';
    $type = $bumper['type'] ?? 'unknown';
    if (!isset($stats['byDj'][$dj])) $stats['byDj'][$dj] = 0;
    if (!isset($stats['byType'][$type])) $stats['byType'][$type] = 0;
    $stats['byDj'][$dj]++;
    $stats['byType'][$type]++;
}

echo json_encode([
    'success' => true,
    'action' => 'delete',
    'deleted' => $deletedBumper,
    'stats' => $stats,
    'bumpers' => $updatedBumpers
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
