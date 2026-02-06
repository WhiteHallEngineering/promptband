<?php
/**
 * Twitter/X API v2 Posting with OAuth 1.0a
 *
 * POST parameters:
 * - message: Text content of the tweet (max 280 chars)
 * - image_url: (optional) URL of image to upload and attach
 * - image_base64: (optional) Base64-encoded image data to upload
 * - reply_to: (optional) Tweet ID to reply to
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

// Load credentials
$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Social config not found']);
    exit;
}

$config = require $configFile;
$twitterConfig = $config['twitter'];

if (empty($twitterConfig['api_key']) || empty($twitterConfig['api_secret']) ||
    empty($twitterConfig['access_token']) || empty($twitterConfig['access_token_secret'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Twitter credentials not configured']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$message = $input['message'] ?? '';
$imageUrl = $input['image_url'] ?? '';
$imageBase64 = $input['image_base64'] ?? '';
$replyTo = $input['reply_to'] ?? '';

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

/**
 * Generate OAuth 1.0a signature
 */
function buildOAuthHeader($method, $url, $params, $consumerKey, $consumerSecret, $accessToken, $accessTokenSecret) {
    $oauth = [
        'oauth_consumer_key' => $consumerKey,
        'oauth_nonce' => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => time(),
        'oauth_token' => $accessToken,
        'oauth_version' => '1.0'
    ];

    // Combine OAuth params with request params for signature
    $signatureParams = array_merge($oauth, $params);
    ksort($signatureParams);

    // Build signature base string
    $paramString = http_build_query($signatureParams, '', '&', PHP_QUERY_RFC3986);
    $signatureBase = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);

    // Create signing key
    $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($accessTokenSecret);

    // Generate signature
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $signatureBase, $signingKey, true));

    // Build Authorization header
    $headerParts = [];
    foreach ($oauth as $key => $value) {
        $headerParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
    }

    return 'OAuth ' . implode(', ', $headerParts);
}

/**
 * Upload media to Twitter
 */
function uploadMedia($imageUrl, $config) {
    // Download image
    $imageData = file_get_contents($imageUrl);
    if (!$imageData) {
        throw new Exception('Failed to download image');
    }

    // Detect media type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($imageData);

    $mediaUploadUrl = 'https://upload.twitter.com/1.1/media/upload.json';

    $params = [
        'media_data' => base64_encode($imageData)
    ];

    $authHeader = buildOAuthHeader(
        'POST',
        $mediaUploadUrl,
        $params,
        $config['api_key'],
        $config['api_secret'],
        $config['access_token'],
        $config['access_token_secret']
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $mediaUploadUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($result['media_id_string'])) {
        return $result['media_id_string'];
    }

    throw new Exception('Media upload failed: ' . ($result['error'] ?? $response));
}

/**
 * Upload base64-encoded media to Twitter
 */
function uploadMediaBase64($base64Data, $config) {
    $mediaUploadUrl = 'https://upload.twitter.com/1.1/media/upload.json';

    $params = [
        'media_data' => $base64Data
    ];

    $authHeader = buildOAuthHeader(
        'POST',
        $mediaUploadUrl,
        $params,
        $config['api_key'],
        $config['api_secret'],
        $config['access_token'],
        $config['access_token_secret']
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $mediaUploadUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($result['media_id_string'])) {
        return $result['media_id_string'];
    }

    throw new Exception('Media upload failed: ' . ($result['error'] ?? $response));
}

try {
    $mediaId = null;

    // Upload media if provided (base64 takes priority)
    if (!empty($imageBase64)) {
        $mediaId = uploadMediaBase64($imageBase64, $twitterConfig);
    } elseif (!empty($imageUrl)) {
        $mediaId = uploadMedia($imageUrl, $twitterConfig);
    }

    // Post tweet using Twitter API v2
    $tweetUrl = 'https://api.twitter.com/2/tweets';

    $tweetData = ['text' => $message];

    if (!empty($replyTo)) {
        $tweetData['reply'] = ['in_reply_to_tweet_id' => $replyTo];
    }

    if ($mediaId) {
        $tweetData['media'] = ['media_ids' => [$mediaId]];
    }

    $jsonBody = json_encode($tweetData);

    // For API v2, we need OAuth 1.0a with no body params in signature
    $authHeader = buildOAuthHeader(
        'POST',
        $tweetUrl,
        [], // Empty params for JSON body requests
        $twitterConfig['api_key'],
        $twitterConfig['api_secret'],
        $twitterConfig['access_token'],
        $twitterConfig['access_token_secret']
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tweetUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader,
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
        logSocialPost('twitter', $message, $imageUrl, '', true, $result['data']['id']);

        echo json_encode([
            'success' => true,
            'platform' => 'twitter',
            'tweet_id' => $result['data']['id'],
            'message' => 'Posted successfully to Twitter/X'
        ]);
    } else {
        $errorMsg = $result['detail'] ?? $result['errors'][0]['message'] ?? 'Unknown error';
        logSocialPost('twitter', $message, $imageUrl, '', false, null, $errorMsg);

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'platform' => 'twitter',
            'error' => $errorMsg,
            'details' => $result
        ]);
    }

} catch (Exception $e) {
    logSocialPost('twitter', $message, $imageUrl, '', false, null, $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'platform' => 'twitter',
        'error' => $e->getMessage()
    ]);
}

/**
 * Log social media post to analytics
 */
function logSocialPost($platform, $message, $imageUrl, $link, $success, $postId = null, $error = null) {
    $analyticsDir = dirname(__DIR__) . '/analytics';
    $logFile = $analyticsDir . '/social-posts.json';

    if (!is_dir($analyticsDir)) {
        mkdir($analyticsDir, 0755, true);
    }

    $logs = [];
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?? [];
    }

    $logs[] = [
        'platform' => $platform,
        'timestamp' => date('c'),
        'message' => substr($message, 0, 280),
        'image_url' => $imageUrl,
        'link' => $link,
        'success' => $success,
        'post_id' => $postId,
        'error' => $error,
        'ip_hash' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown')
    ];

    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }

    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
}
