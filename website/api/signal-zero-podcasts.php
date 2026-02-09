<?php
/**
 * Signal 0 Radio — Podcast Archive API
 *
 * GET:  List all episodes (optional ?show= filter)
 * POST: action=register — add episode to archive
 * POST: action=delete   — remove episode by ID
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$validKey = 'pr0mpt-m3ss4g3s-2026';
$key = $_GET['key'] ?? '';

if ($key !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$podcastFile = __DIR__ . '/../analytics/signal-zero-podcasts.json';

// Ensure file exists
if (!file_exists($podcastFile)) {
    $dir = dirname($podcastFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($podcastFile, json_encode([], JSON_PRETTY_PRINT));
}

$episodes = json_decode(file_get_contents($podcastFile), true) ?: [];

// ─── GET: List episodes ───
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $showFilter = $_GET['show'] ?? '';

    $filtered = $episodes;
    if ($showFilter) {
        $filtered = array_values(array_filter($episodes, function($ep) use ($showFilter) {
            return ($ep['show'] ?? '') === $showFilter;
        }));
    }

    // Sort by airDate descending
    usort($filtered, function($a, $b) {
        return strcmp($b['airDate'] ?? '', $a['airDate'] ?? '');
    });

    echo json_encode([
        'success' => true,
        'count' => count($filtered),
        'episodes' => $filtered
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─── POST: Register or Delete ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'register') {
        $required = ['id', 'show', 'xj', 'title', 'audioUrl'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                exit;
            }
        }

        // Check for duplicate ID
        foreach ($episodes as $ep) {
            if ($ep['id'] === $input['id']) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Episode ID already exists']);
                exit;
            }
        }

        $episode = [
            'id' => $input['id'],
            'show' => $input['show'],
            'xj' => $input['xj'],
            'title' => $input['title'],
            'description' => $input['description'] ?? '',
            'audioUrl' => $input['audioUrl'],
            'duration' => $input['duration'] ?? '',
            'airDate' => $input['airDate'] ?? date('Y-m-d'),
            'archived' => true,
            'registeredAt' => date('c')
        ];

        $episodes[] = $episode;
        file_put_contents($podcastFile, json_encode($episodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        echo json_encode([
            'success' => true,
            'episode' => $episode,
            'totalEpisodes' => count($episodes)
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'delete') {
        $id = $input['id'] ?? '';
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'id is required']);
            exit;
        }

        $found = false;
        $episodes = array_values(array_filter($episodes, function($ep) use ($id, &$found) {
            if ($ep['id'] === $id) {
                $found = true;
                return false;
            }
            return true;
        }));

        if (!$found) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Episode not found']);
            exit;
        }

        file_put_contents($podcastFile, json_encode($episodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        echo json_encode([
            'success' => true,
            'deleted' => $id,
            'totalEpisodes' => count($episodes)
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action. Use: register, delete']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
