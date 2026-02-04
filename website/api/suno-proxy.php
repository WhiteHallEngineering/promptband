<?php
/**
 * PROMPT Suno API Proxy
 * Proxies requests to self-hosted Suno API (gcui-art/suno-api)
 *
 * Supported actions:
 * - generate: Start a song generation
 * - status: Check generation status
 * - list: List recent generations
 * - download: Download a generated song
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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

// Load config for Suno API URL
$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Config not found']);
    exit;
}

$config = require $configFile;
$sunoApiUrl = $config['suno']['api_url'] ?? 'http://localhost:3000';

// Remove trailing slash
$sunoApiUrl = rtrim($sunoApiUrl, '/');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'generate':
        handleGenerate($sunoApiUrl);
        break;
    case 'status':
        handleStatus($sunoApiUrl);
        break;
    case 'list':
        handleList($sunoApiUrl);
        break;
    case 'download':
        handleDownload($sunoApiUrl);
        break;
    case 'quota':
        handleQuota($sunoApiUrl);
        break;
    default:
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid action',
            'validActions' => ['generate', 'status', 'list', 'download', 'quota']
        ]);
        exit;
}

/**
 * Start a song generation
 */
function handleGenerate($sunoApiUrl) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    // Required fields
    $prompt = trim($input['prompt'] ?? '');
    $lyrics = trim($input['lyrics'] ?? '');
    $title = trim($input['title'] ?? '');

    // Optional fields
    $makeInstrumental = $input['instrumental'] ?? false;
    $customMode = isset($input['lyrics']) && !empty($input['lyrics']);

    if (empty($prompt) && empty($lyrics)) {
        http_response_code(400);
        echo json_encode(['error' => 'Either prompt or lyrics is required']);
        exit;
    }

    // Build request body based on whether we have custom lyrics
    if ($customMode && !empty($lyrics)) {
        // Custom mode with lyrics
        $requestBody = [
            'prompt' => $lyrics,
            'tags' => $prompt, // Style/genre tags go here in custom mode
            'title' => $title,
            'make_instrumental' => $makeInstrumental,
            'wait_audio' => false // Don't block, we'll poll for status
        ];
        $endpoint = '/api/custom_generate';
    } else {
        // Description mode - AI generates lyrics from prompt
        $requestBody = [
            'prompt' => $prompt,
            'make_instrumental' => $makeInstrumental,
            'wait_audio' => false
        ];
        $endpoint = '/api/generate';
    }

    $response = makeRequest($sunoApiUrl . $endpoint, 'POST', $requestBody);

    if ($response['error']) {
        http_response_code(500);
        echo json_encode(['error' => $response['error']]);
        exit;
    }

    // Parse response to extract job IDs
    $data = $response['data'];

    // The API returns an array of generation objects
    $jobs = [];
    if (is_array($data)) {
        foreach ($data as $item) {
            $jobs[] = [
                'id' => $item['id'] ?? null,
                'title' => $item['title'] ?? $title,
                'status' => $item['status'] ?? 'submitted',
                'audioUrl' => $item['audio_url'] ?? null
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Generation started',
        'jobs' => $jobs,
        'raw' => $data // Include raw response for debugging
    ], JSON_PRETTY_PRINT);
}

/**
 * Check status of generation(s)
 */
function handleStatus($sunoApiUrl) {
    $ids = $_GET['ids'] ?? '';

    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'ids parameter is required']);
        exit;
    }

    $response = makeRequest($sunoApiUrl . '/api/get?ids=' . urlencode($ids), 'GET');

    if ($response['error']) {
        http_response_code(500);
        echo json_encode(['error' => $response['error']]);
        exit;
    }

    $data = $response['data'];

    // Parse status for each job
    $statuses = [];
    if (is_array($data)) {
        foreach ($data as $item) {
            $statuses[] = [
                'id' => $item['id'] ?? null,
                'title' => $item['title'] ?? '',
                'status' => $item['status'] ?? 'unknown',
                'audioUrl' => $item['audio_url'] ?? null,
                'videoUrl' => $item['video_url'] ?? null,
                'imageUrl' => $item['image_url'] ?? null,
                'duration' => $item['duration'] ?? null,
                'lyrics' => $item['lyric'] ?? null,
                'tags' => $item['tags'] ?? null,
                'createdAt' => $item['created_at'] ?? null
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'statuses' => $statuses
    ], JSON_PRETTY_PRINT);
}

/**
 * List recent generations
 */
function handleList($sunoApiUrl) {
    $response = makeRequest($sunoApiUrl . '/api/feed', 'GET');

    if ($response['error']) {
        http_response_code(500);
        echo json_encode(['error' => $response['error']]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'generations' => $response['data']
    ], JSON_PRETTY_PRINT);
}

/**
 * Check remaining quota/credits
 */
function handleQuota($sunoApiUrl) {
    $response = makeRequest($sunoApiUrl . '/api/get_limit', 'GET');

    if ($response['error']) {
        http_response_code(500);
        echo json_encode(['error' => $response['error']]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'quota' => $response['data']
    ], JSON_PRETTY_PRINT);
}

/**
 * Download a generated song and save locally
 */
function handleDownload($sunoApiUrl) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $audioUrl = $input['audioUrl'] ?? '';
    $filename = $input['filename'] ?? '';

    if (empty($audioUrl)) {
        http_response_code(400);
        echo json_encode(['error' => 'audioUrl is required']);
        exit;
    }

    // Sanitize filename
    if (empty($filename)) {
        $filename = 'generated-' . time();
    }
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '-', $filename);
    $filename = strtolower($filename);

    // Determine file extension from URL or default to mp3
    $extension = 'mp3';
    if (preg_match('/\.(mp3|wav|m4a)(\?|$)/i', $audioUrl, $matches)) {
        $extension = strtolower($matches[1]);
    }

    $fullFilename = $filename . '.' . $extension;
    $savePath = __DIR__ . '/../audio/generated/' . $fullFilename;

    // Ensure directory exists
    $dir = dirname($savePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Download the file
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $audioUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $audioData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to download audio',
            'details' => $curlError ?: "HTTP $httpCode"
        ]);
        exit;
    }

    // Save the file
    $saved = file_put_contents($savePath, $audioData);

    if ($saved === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save audio file']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Audio downloaded successfully',
        'filename' => $fullFilename,
        'localPath' => '/audio/generated/' . $fullFilename,
        'fileSize' => strlen($audioData)
    ], JSON_PRETTY_PRINT);
}

/**
 * Make HTTP request to Suno API
 */
function makeRequest($url, $method = 'GET', $body = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => 'Request failed: ' . $curlError, 'data' => null];
    }

    if ($httpCode >= 400) {
        $errorData = json_decode($response, true);
        return [
            'error' => $errorData['detail'] ?? $errorData['error'] ?? "HTTP error $httpCode",
            'data' => null
        ];
    }

    return ['error' => null, 'data' => json_decode($response, true)];
}
