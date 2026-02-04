<?php
/**
 * Fetch Tweet Content API
 * Retrieves tweet text from a URL or ID for reply generation
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);
$tweetUrl = $input['url'] ?? '';

if (empty($tweetUrl)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tweet URL is required']);
    exit;
}

// Extract tweet ID from URL
$tweetId = null;
if (preg_match('/status\/(\d+)/', $tweetUrl, $matches)) {
    $tweetId = $matches[1];
} elseif (preg_match('/^\d+$/', $tweetUrl)) {
    $tweetId = $tweetUrl;
}

if (!$tweetId) {
    http_response_code(400);
    echo json_encode(['error' => 'Could not extract tweet ID from URL']);
    exit;
}

// Load Twitter credentials
$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Twitter config not found']);
    exit;
}

$config = require $configFile;
$twitter = $config['twitter'] ?? null;

if (!$twitter || empty($twitter['bearer_token'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Twitter bearer token not configured']);
    exit;
}

// Fetch tweet from Twitter API v2
$apiUrl = "https://api.twitter.com/2/tweets/{$tweetId}?tweet.fields=text,author_id,created_at&expansions=author_id&user.fields=username,name";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $twitter['bearer_token']
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $error = json_decode($response, true);
    http_response_code($httpCode);
    echo json_encode([
        'error' => 'Twitter API error: ' . ($error['detail'] ?? $error['title'] ?? 'Unknown error'),
        'status' => $httpCode
    ]);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['data'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Tweet not found']);
    exit;
}

$tweet = $data['data'];
$author = null;

// Get author info from includes
if (isset($data['includes']['users'][0])) {
    $author = $data['includes']['users'][0];
}

echo json_encode([
    'success' => true,
    'tweet' => [
        'id' => $tweet['id'],
        'text' => $tweet['text'],
        'created_at' => $tweet['created_at'] ?? null,
        'author' => $author ? [
            'id' => $author['id'],
            'username' => $author['username'],
            'name' => $author['name']
        ] : null,
        'url' => "https://x.com/i/status/{$tweet['id']}"
    ]
]);
