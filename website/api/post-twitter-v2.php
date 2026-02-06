<?php
/**
 * Twitter/X API v2 Posting with OAuth 2.0
 * Uses token from OAuth 2.0 PKCE flow
 *
 * POST parameters:
 * - message: Text content of the tweet (max 280 chars)
 *
 * Requires: key=pr0mpt-m3ss4g3s-2026
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Authentication
$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing API key']);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Load OAuth 2.0 token
$tokenFile = __DIR__ . '/../analytics/twitter-oauth2-token.json';
if (!file_exists($tokenFile)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Twitter not authorized. Visit /api/twitter-oauth2-start.php to authorize.'
    ]);
    exit;
}

$tokenData = json_decode(file_get_contents($tokenFile), true);
$accessToken = $tokenData['access_token'] ?? '';

if (empty($accessToken)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Invalid token data']);
    exit;
}

// Check if token is expired (tokens last 2 hours)
$createdAt = $tokenData['created_at'] ?? 0;
$expiresIn = $tokenData['expires_in'] ?? 7200;
if (time() > $createdAt + $expiresIn - 60) {
    // Token expired or about to expire - try to refresh
    $configFile = __DIR__ . '/social-config.php';
    $config = require $configFile;

    $refreshToken = $tokenData['refresh_token'] ?? '';
    if (!empty($refreshToken)) {
        $newToken = refreshToken($refreshToken, $config['twitter']);
        if ($newToken) {
            $accessToken = $newToken['access_token'];
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Token expired. Re-authorize at /api/twitter-oauth2-start.php'
            ]);
            exit;
        }
    }
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$message = $input['message'] ?? '';

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

if (strlen($message) > 280) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message exceeds 280 characters']);
    exit;
}

try {
    // Post tweet using Twitter API v2 with OAuth 2.0
    $tweetUrl = 'https://api.twitter.com/2/tweets';
    $tweetData = ['text' => $message];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tweetUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tweetData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("cURL error: {$curlError}");
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($result['data']['id'])) {
        echo json_encode([
            'success' => true,
            'platform' => 'twitter',
            'tweet_id' => $result['data']['id'],
            'message' => 'Posted successfully to Twitter/X'
        ]);
    } else {
        $errorMsg = $result['detail'] ?? $result['errors'][0]['message'] ?? $result['title'] ?? 'Unknown error';
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'platform' => 'twitter',
            'error' => $errorMsg,
            'details' => $result
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'platform' => 'twitter',
        'error' => $e->getMessage()
    ]);
}

/**
 * Refresh OAuth 2.0 token
 */
function refreshToken($refreshToken, $twitterConfig) {
    $tokenUrl = 'https://api.twitter.com/2/oauth2/token';

    $clientId = $twitterConfig['oauth2_client_id'];
    $clientSecret = $twitterConfig['oauth2_client_secret'];

    $postData = [
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
        'client_id' => $clientId
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode === 200 && isset($result['access_token'])) {
        // Save new token
        $tokenFile = __DIR__ . '/../analytics/twitter-oauth2-token.json';
        $tokenData = [
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'] ?? $refreshToken,
            'expires_in' => $result['expires_in'] ?? 7200,
            'created_at' => time(),
            'scope' => $result['scope'] ?? ''
        ];
        file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));

        return $tokenData;
    }

    return null;
}
