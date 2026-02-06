<?php
// Asset Registry - tracks generated images/videos by clip name

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$registryFile = dirname(__DIR__) . '/analytics/asset-registry.json';

// Ensure directory exists
$dir = dirname($registryFile);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Load existing registry
$registry = [];
if (file_exists($registryFile)) {
    $registry = json_decode(file_get_contents($registryFile), true) ?? [];
}

// GET - retrieve assets for a track/segment
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $track = $_GET['track'] ?? '';
    $clipNames = isset($_GET['clips']) ? explode(',', $_GET['clips']) : [];

    if (empty($track)) {
        echo json_encode(['success' => true, 'registry' => $registry]);
        exit;
    }

    $trackAssets = $registry[$track] ?? [];

    // If specific clips requested, filter
    if (!empty($clipNames)) {
        $filtered = [];
        foreach ($clipNames as $name) {
            $name = trim($name);
            if (isset($trackAssets[$name])) {
                $filtered[$name] = $trackAssets[$name];
            }
        }
        $trackAssets = $filtered;
    }

    echo json_encode([
        'success' => true,
        'track' => $track,
        'assets' => $trackAssets
    ]);
    exit;
}

// POST - register a new asset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $track = $input['track'] ?? '';
    $clipName = $input['clip_name'] ?? '';
    $imagePath = $input['image_path'] ?? null;
    $imageUrl = $input['image_url'] ?? null;
    $videoUrl = $input['video_url'] ?? null;

    if (empty($track) || empty($clipName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'track and clip_name required']);
        exit;
    }

    // Initialize track if needed
    if (!isset($registry[$track])) {
        $registry[$track] = [];
    }

    // Initialize or update clip
    if (!isset($registry[$track][$clipName])) {
        $registry[$track][$clipName] = [
            'created_at' => date('c')
        ];
    }

    // Update fields
    if ($imagePath) $registry[$track][$clipName]['image_path'] = $imagePath;
    if ($imageUrl) $registry[$track][$clipName]['image_url'] = $imageUrl;
    if ($videoUrl) $registry[$track][$clipName]['video_url'] = $videoUrl;
    $registry[$track][$clipName]['updated_at'] = date('c');

    // Save registry
    file_put_contents($registryFile, json_encode($registry, JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'message' => 'Asset registered',
        'asset' => $registry[$track][$clipName]
    ]);
    exit;
}
