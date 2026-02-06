<?php
/**
 * Post a Twitter thread
 *
 * POST parameters:
 * - tweets: Array of tweet texts
 * - image_url: (optional) URL of image for first tweet
 */

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

$config = require $configFile;
$tc = $config['twitter'];

$input = json_decode(file_get_contents('php://input'), true);
$tweets = $input['tweets'] ?? [];
$imageUrl = $input['image_url'] ?? '';

if (empty($tweets) || !is_array($tweets)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'tweets array is required']);
    exit;
}

// Validate all tweets are under limit
foreach ($tweets as $i => $tweet) {
    if (strlen($tweet) > 280) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Tweet " . ($i + 1) . " exceeds 280 characters"]);
        exit;
    }
}

function buildOAuthHeader($method, $url, $params, $tc) {
    $oauth = [
        'oauth_consumer_key' => $tc['api_key'],
        'oauth_nonce' => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => time(),
        'oauth_token' => $tc['access_token'],
        'oauth_version' => '1.0'
    ];

    $signatureParams = array_merge($oauth, $params);
    ksort($signatureParams);
    $paramString = http_build_query($signatureParams, '', '&', PHP_QUERY_RFC3986);
    $signatureBase = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
    $signingKey = rawurlencode($tc['api_secret']) . '&' . rawurlencode($tc['access_token_secret']);
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $signatureBase, $signingKey, true));

    $headerParts = [];
    foreach ($oauth as $key => $value) {
        $headerParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
    }
    return 'OAuth ' . implode(', ', $headerParts);
}

function uploadMedia($imageUrl, $tc) {
    $imageData = @file_get_contents($imageUrl);
    if (!$imageData) {
        throw new Exception('Failed to download image');
    }

    $mediaUploadUrl = 'https://upload.twitter.com/1.1/media/upload.json';
    $params = ['media_data' => base64_encode($imageData)];

    $authHeader = buildOAuthHeader('POST', $mediaUploadUrl, $params, $tc);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $mediaUploadUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $authHeader]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['media_id_string'])) {
        return $result['media_id_string'];
    }
    throw new Exception('Media upload failed: ' . $response);
}

function postTweet($text, $tc, $replyTo = null, $mediaId = null) {
    $tweetUrl = 'https://api.twitter.com/2/tweets';
    $tweetData = ['text' => $text];

    if ($replyTo) {
        $tweetData['reply'] = ['in_reply_to_tweet_id' => $replyTo];
    }
    if ($mediaId) {
        $tweetData['media'] = ['media_ids' => [$mediaId]];
    }

    $authHeader = buildOAuthHeader('POST', $tweetUrl, [], $tc);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tweetUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tweetData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($result['data']['id'])) {
        return $result['data']['id'];
    }

    throw new Exception('Tweet failed: ' . ($result['detail'] ?? $result['errors'][0]['message'] ?? $response));
}

try {
    $mediaId = null;
    if (!empty($imageUrl)) {
        $mediaId = uploadMedia($imageUrl, $tc);
    }

    $lastTweetId = null;
    $firstTweetId = null;

    foreach ($tweets as $i => $tweetText) {
        // First tweet gets the image
        $currentMediaId = ($i === 0) ? $mediaId : null;

        $tweetId = postTweet($tweetText, $tc, $lastTweetId, $currentMediaId);

        if ($i === 0) {
            $firstTweetId = $tweetId;
        }

        $lastTweetId = $tweetId;

        // Small delay between tweets
        if ($i < count($tweets) - 1) {
            usleep(500000); // 0.5 seconds
        }
    }

    echo json_encode([
        'success' => true,
        'thread_url' => "https://x.com/promptband/status/{$firstTweetId}",
        'tweet_count' => count($tweets),
        'first_tweet_id' => $firstTweetId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
