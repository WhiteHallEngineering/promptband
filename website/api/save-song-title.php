<?php
/**
 * PROMPT Song Titles Save API
 * Add, update, move, and delete song titles + album management (protected)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Simple key-based authentication
$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$action = $input['action'] ?? '';

$validActions = [
    'add', 'move', 'delete', 'update', 'save_generation',
    'create_album', 'update_album', 'delete_album',
    'add_to_album', 'remove_from_album', 'reorder_tracks'
];

if (!in_array($action, $validActions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action. Valid: ' . implode(', ', $validActions)]);
    exit;
}

$titlesFile = __DIR__ . '/song-titles.json';

// Load existing data
if (file_exists($titlesFile)) {
    $data = json_decode(file_get_contents($titlesFile), true);
} else {
    $data = [
        'albums' => [],
        'used' => [],
        'suggested' => [],
        'ideas' => []
    ];
}

// Ensure all categories exist
$data['albums'] = $data['albums'] ?? [];
$data['used'] = $data['used'] ?? [];
$data['suggested'] = $data['suggested'] ?? [];
$data['ideas'] = $data['ideas'] ?? [];

$result = ['success' => false, 'message' => ''];

switch ($action) {
    // ========== ALBUM MANAGEMENT ==========

    case 'create_album':
        $albumName = trim($input['name'] ?? '');
        $releaseDate = $input['releaseDate'] ?? null;
        $status = $input['status'] ?? 'planning';

        if (empty($albumName)) {
            http_response_code(400);
            echo json_encode(['error' => 'Album name is required']);
            exit;
        }

        // Generate album ID from name
        $albumId = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $albumName));
        $albumId = trim($albumId, '-');

        // Check if album already exists
        if (isset($data['albums'][$albumId])) {
            http_response_code(400);
            echo json_encode(['error' => "Album \"$albumName\" already exists"]);
            exit;
        }

        $data['albums'][$albumId] = [
            'name' => $albumName,
            'releaseDate' => $releaseDate,
            'status' => $status,
            'createdAt' => date('Y-m-d H:i:s')
        ];

        $result = ['success' => true, 'message' => "Created album \"$albumName\"", 'albumId' => $albumId];
        break;

    case 'update_album':
        $albumId = $input['albumId'] ?? '';
        $updates = $input['updates'] ?? [];

        if (empty($albumId)) {
            http_response_code(400);
            echo json_encode(['error' => 'albumId is required']);
            exit;
        }

        if (!isset($data['albums'][$albumId])) {
            http_response_code(404);
            echo json_encode(['error' => "Album not found: $albumId"]);
            exit;
        }

        $allowedFields = ['name', 'releaseDate', 'status'];
        foreach ($updates as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $data['albums'][$albumId][$key] = $value;
            }
        }

        $result = ['success' => true, 'message' => "Updated album \"$albumId\""];
        break;

    case 'delete_album':
        $albumId = $input['albumId'] ?? '';
        $moveTo = $input['moveTo'] ?? 'suggested'; // Where to move orphaned tracks

        if (empty($albumId)) {
            http_response_code(400);
            echo json_encode(['error' => 'albumId is required']);
            exit;
        }

        if (!isset($data['albums'][$albumId])) {
            http_response_code(404);
            echo json_encode(['error' => "Album not found: $albumId"]);
            exit;
        }

        // Move tracks from this album to suggested/ideas
        $movedCount = 0;
        foreach ($data['used'] as $idx => $track) {
            if (($track['albumId'] ?? '') === $albumId) {
                $trackObj = [
                    'title' => $track['title'],
                    'theme' => $track['theme'] ?? null,
                    'source' => 'Album: ' . $data['albums'][$albumId]['name']
                ];
                $data[$moveTo][] = $trackObj;
                unset($data['used'][$idx]);
                $movedCount++;
            }
        }
        $data['used'] = array_values($data['used']); // Re-index

        unset($data['albums'][$albumId]);

        $result = ['success' => true, 'message' => "Deleted album, moved $movedCount tracks to $moveTo"];
        break;

    case 'add_to_album':
        $albumId = $input['albumId'] ?? '';
        $titleStr = $input['title'] ?? '';
        $trackNum = $input['track'] ?? null;
        $fromCategory = $input['from'] ?? null; // 'suggested' or 'ideas'

        if (empty($albumId) || empty($titleStr)) {
            http_response_code(400);
            echo json_encode(['error' => 'albumId and title are required']);
            exit;
        }

        if (!isset($data['albums'][$albumId])) {
            http_response_code(404);
            echo json_encode(['error' => "Album not found: $albumId"]);
            exit;
        }

        // If no track number, auto-assign next
        if ($trackNum === null) {
            $maxTrack = 0;
            foreach ($data['used'] as $track) {
                if (($track['albumId'] ?? '') === $albumId && ($track['track'] ?? 0) > $maxTrack) {
                    $maxTrack = $track['track'];
                }
            }
            $trackNum = $maxTrack + 1;
        }

        // Remove from source category if specified
        $titleObj = ['title' => $titleStr];
        if ($fromCategory && in_array($fromCategory, ['suggested', 'ideas'])) {
            foreach ($data[$fromCategory] as $idx => $item) {
                if ($item['title'] === $titleStr) {
                    $titleObj = $item;
                    array_splice($data[$fromCategory], $idx, 1);
                    break;
                }
            }
        }

        // Add to used with album info
        $data['used'][] = [
            'title' => $titleStr,
            'track' => $trackNum,
            'albumId' => $albumId,
            'lyrics' => $titleObj['lyrics'] ?? null,
            'audioUrl' => $titleObj['audioUrl'] ?? null
        ];

        $result = ['success' => true, 'message' => "Added \"$titleStr\" to album as track $trackNum"];
        break;

    case 'remove_from_album':
        $titleStr = $input['title'] ?? '';
        $moveTo = $input['moveTo'] ?? 'suggested';

        if (empty($titleStr)) {
            http_response_code(400);
            echo json_encode(['error' => 'title is required']);
            exit;
        }

        $found = false;
        foreach ($data['used'] as $idx => $track) {
            if ($track['title'] === $titleStr) {
                $trackObj = [
                    'title' => $track['title'],
                    'theme' => $track['theme'] ?? null,
                    'lyrics' => $track['lyrics'] ?? null,
                    'audioUrl' => $track['audioUrl'] ?? null,
                    'source' => 'Removed from album'
                ];
                $data[$moveTo][] = $trackObj;
                array_splice($data['used'], $idx, 1);
                $found = true;
                break;
            }
        }

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => "Track \"$titleStr\" not found in any album"]);
            exit;
        }

        $result = ['success' => true, 'message' => "Removed \"$titleStr\" from album, moved to $moveTo"];
        break;

    case 'reorder_tracks':
        $albumId = $input['albumId'] ?? '';
        $trackOrder = $input['trackOrder'] ?? []; // Array of {title, track}

        if (empty($albumId)) {
            http_response_code(400);
            echo json_encode(['error' => 'albumId is required']);
            exit;
        }

        foreach ($trackOrder as $orderItem) {
            $title = $orderItem['title'] ?? '';
            $newTrackNum = $orderItem['track'] ?? null;

            if ($title && $newTrackNum !== null) {
                foreach ($data['used'] as &$track) {
                    if ($track['title'] === $title && ($track['albumId'] ?? '') === $albumId) {
                        $track['track'] = $newTrackNum;
                        break;
                    }
                }
            }
        }
        unset($track);

        $result = ['success' => true, 'message' => "Reordered tracks in album"];
        break;

    // ========== TITLE MANAGEMENT ==========

    case 'add':
        $category = $input['category'] ?? '';
        $title = $input['title'] ?? null;

        if (!in_array($category, ['suggested', 'ideas'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Can only add to "suggested" or "ideas" categories']);
            exit;
        }

        if (!$title || !isset($title['title']) || empty(trim($title['title']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Title object with "title" field is required']);
            exit;
        }

        $titleStr = trim($title['title']);
        foreach (['used', 'suggested', 'ideas'] as $cat) {
            foreach ($data[$cat] as $existing) {
                if (strcasecmp($existing['title'], $titleStr) === 0) {
                    http_response_code(400);
                    echo json_encode(['error' => "Title \"$titleStr\" already exists in $cat"]);
                    exit;
                }
            }
        }

        $title['added'] = date('Y-m-d H:i:s');
        $data[$category][] = $title;
        $result = ['success' => true, 'message' => "Added \"$titleStr\" to $category"];
        break;

    case 'move':
        $from = $input['from'] ?? '';
        $to = $input['to'] ?? '';
        $titleStr = $input['title'] ?? '';

        if (!in_array($from, ['suggested', 'ideas'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Can only move from "suggested" or "ideas"']);
            exit;
        }

        if (!in_array($to, ['used', 'suggested', 'ideas'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid destination category']);
            exit;
        }

        if (empty($titleStr)) {
            http_response_code(400);
            echo json_encode(['error' => 'Title string is required']);
            exit;
        }

        $found = false;
        $titleObj = null;
        foreach ($data[$from] as $idx => $item) {
            if ($item['title'] === $titleStr) {
                $titleObj = $item;
                array_splice($data[$from], $idx, 1);
                $found = true;
                break;
            }
        }

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => "Title \"$titleStr\" not found in $from"]);
            exit;
        }

        if ($to === 'used') {
            $trackNum = $input['track'] ?? null;
            $albumId = $input['albumId'] ?? null;
            $titleObj = [
                'title' => $titleStr,
                'track' => $trackNum,
                'albumId' => $albumId
            ];
        }

        $data[$to][] = $titleObj;
        $result = ['success' => true, 'message' => "Moved \"$titleStr\" from $from to $to"];
        break;

    case 'delete':
        $category = $input['category'] ?? '';
        $titleStr = $input['title'] ?? '';

        if (!in_array($category, ['suggested', 'ideas', 'used'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid category']);
            exit;
        }

        if (empty($titleStr)) {
            http_response_code(400);
            echo json_encode(['error' => 'Title string is required']);
            exit;
        }

        $found = false;
        foreach ($data[$category] as $idx => $item) {
            if ($item['title'] === $titleStr) {
                array_splice($data[$category], $idx, 1);
                $found = true;
                break;
            }
        }

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => "Title \"$titleStr\" not found in $category"]);
            exit;
        }

        $result = ['success' => true, 'message' => "Deleted \"$titleStr\" from $category"];
        break;

    case 'update':
        $category = $input['category'] ?? '';
        $titleStr = $input['title'] ?? '';
        $updates = $input['updates'] ?? [];

        if (!in_array($category, ['suggested', 'ideas', 'used'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid category']);
            exit;
        }

        if (empty($titleStr)) {
            http_response_code(400);
            echo json_encode(['error' => 'Title string is required']);
            exit;
        }

        $found = false;
        foreach ($data[$category] as &$item) {
            if ($item['title'] === $titleStr) {
                foreach ($updates as $key => $value) {
                    if ($key !== 'title') {
                        $item[$key] = $value;
                    }
                }
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => "Title \"$titleStr\" not found in $category"]);
            exit;
        }

        $result = ['success' => true, 'message' => "Updated \"$titleStr\" in $category"];
        break;

    case 'save_generation':
        $category = $input['category'] ?? '';
        $titleStr = $input['title'] ?? '';
        $generation = $input['generation'] ?? [];

        if (!in_array($category, ['suggested', 'ideas'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Can only save generation data for "suggested" or "ideas"']);
            exit;
        }

        if (empty($titleStr)) {
            http_response_code(400);
            echo json_encode(['error' => 'Title string is required']);
            exit;
        }

        $allowedFields = ['lyrics', 'sunoJobId', 'sunoStatus', 'audioUrl', 'videoUrl', 'imageUrl', 'generatedAt', 'sunoPrompt', 'duration'];

        $found = false;
        foreach ($data[$category] as &$item) {
            if ($item['title'] === $titleStr) {
                foreach ($generation as $key => $value) {
                    if (in_array($key, $allowedFields)) {
                        $item[$key] = $value;
                    }
                }
                if (isset($generation['audioUrl']) && !isset($generation['generatedAt'])) {
                    $item['generatedAt'] = date('Y-m-d H:i:s');
                }
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            http_response_code(404);
            echo json_encode(['error' => "Title \"$titleStr\" not found in $category"]);
            exit;
        }

        $result = ['success' => true, 'message' => "Saved generation data for \"$titleStr\""];
        break;
}

// Save the data
$saved = file_put_contents($titlesFile, json_encode($data, JSON_PRETTY_PRINT));

if ($saved === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save data']);
    exit;
}

// Return success with updated counts
$result['counts'] = [
    'albums' => count($data['albums']),
    'used' => count($data['used']),
    'suggested' => count($data['suggested']),
    'ideas' => count($data['ideas'])
];

echo json_encode($result, JSON_PRETTY_PRINT);
