<?php
// Animate image via Replicate Minimax video-01 model

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

$config = require $configFile;
$replicateToken = $config['replicate']['api_token'] ?? '';

if (empty($replicateToken)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Replicate API token not configured']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$imageUrl = $input['image_url'] ?? '';
$prompt = $input['prompt'] ?? '';
$predictionId = $input['prediction_id'] ?? ''; // For status checks

// If prediction_id provided, check status
if (!empty($predictionId)) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.replicate.com/v1/predictions/' . $predictionId);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $replicateToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    echo json_encode([
        'success' => true,
        'status' => $result['status'] ?? 'unknown',
        'prediction_id' => $predictionId,
        'output' => $result['output'] ?? null,
        'error' => $result['error'] ?? null,
        'logs' => $result['logs'] ?? null
    ]);
    exit;
}

// Create new animation
if (empty($imageUrl)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'image_url is required']);
    exit;
}

if (empty($prompt)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'prompt is required']);
    exit;
}

// Enhance prompt with PROMPT band aesthetic
$enhancedPrompt = $prompt . " Cinematic motion, neon magenta and cyan lighting, cyberpunk atmosphere.";

// Convert local/self-hosted images to data URI (Bluehost ModSecurity blocks external fetches)
$finalImageUrl = $imageUrl;
if (strpos($imageUrl, 'promptband.ai') !== false || strpos($imageUrl, $_SERVER['HTTP_HOST'] ?? '') !== false) {
    // Extract the path from the URL
    $parsedUrl = parse_url($imageUrl);
    $localPath = $_SERVER['DOCUMENT_ROOT'] . ($parsedUrl['path'] ?? '');

    if (file_exists($localPath)) {
        $imageData = file_get_contents($localPath);
        $mimeType = mime_content_type($localPath) ?: 'image/png';
        $finalImageUrl = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    }
}

// Call Replicate API to start prediction
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.replicate.com/v1/predictions');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Token ' . $replicateToken,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'version' => '974c9c5bc69f8f9c178ddea80d8936ba46c48081ad6b6ccca8843d44010c0642', // kling-v1.6-pro
    'input' => [
        'prompt' => $enhancedPrompt,
        'start_image' => $finalImageUrl,
        'duration' => 5,
        'cfg_scale' => 0.5,
        'negative_prompt' => 'blurry, low quality, distorted faces, text, watermark'
    ]
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API request failed: ' . $curlError]);
    exit;
}

$result = json_decode($response, true);

if ($httpCode !== 201 && $httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => $result['detail'] ?? 'Replicate API error',
        'details' => $result
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'prediction_id' => $result['id'],
    'status' => $result['status'],
    'message' => 'Animation started. Poll with prediction_id to check status.',
    'estimated_time' => '60-120 seconds'
]);
