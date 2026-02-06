<?php
/**
 * Standalone Twitter API v2 test with OAuth 1.0a
 * No external dependencies
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

// Load credentials
$config = require __DIR__ . '/social-config.php';
$tc = $config['twitter'];

$consumerKey = $tc['api_key'];
$consumerSecret = $tc['api_secret'];
$accessToken = $tc['access_token'];
$accessTokenSecret = $tc['access_token_secret'];

echo "=== Twitter API v2 Standalone Test ===\n\n";
echo "Consumer Key: " . substr($consumerKey, 0, 10) . "...\n";
echo "Access Token: " . substr($accessToken, 0, 25) . "...\n\n";

/**
 * Build OAuth 1.0a signature
 */
function buildAuthorizationHeader($method, $url, $consumerKey, $consumerSecret, $accessToken, $accessTokenSecret, $postParams = []) {
    $oauth = [
        'oauth_consumer_key' => $consumerKey,
        'oauth_nonce' => bin2hex(random_bytes(16)), // Use hex instead of base64
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => (string)time(),
        'oauth_token' => $accessToken,
        'oauth_version' => '1.0',
    ];

    // For signing, combine oauth params (not body params for JSON requests)
    $sigParams = $oauth;
    ksort($sigParams);

    // Build parameter string
    $paramPairs = [];
    foreach ($sigParams as $key => $value) {
        $paramPairs[] = rawurlencode($key) . '=' . rawurlencode($value);
    }
    $paramString = implode('&', $paramPairs);

    // Build signature base string
    $sigBase = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);

    // Build signing key
    $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($accessTokenSecret);

    // Generate signature
    $signature = base64_encode(hash_hmac('sha1', $sigBase, $signingKey, true));
    $oauth['oauth_signature'] = $signature;

    // Build Authorization header
    $authParts = [];
    foreach ($oauth as $key => $value) {
        $authParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
    }

    return 'OAuth ' . implode(', ', $authParts);
}

// Test message
$message = 'First signal from PROMPT HQ... the machines are making music. 🤖🎸 #AIMusic';

echo "Attempting to post: \"$message\"\n\n";

// Build request
$url = 'https://api.twitter.com/2/tweets';
$body = json_encode(['text' => $message]);

$authHeader = buildAuthorizationHeader(
    'POST',
    $url,
    $consumerKey,
    $consumerSecret,
    $accessToken,
    $accessTokenSecret
);

echo "Auth Header (truncated):\n" . substr($authHeader, 0, 100) . "...\n\n";

// Make request
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $authHeader,
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADER => true,
]);

$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);

echo "=== Response ===\n";
echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}
echo "\nResponse Body:\n";
echo $responseBody . "\n";

$result = json_decode($responseBody, true);
if ($httpCode == 201 && isset($result['data']['id'])) {
    echo "\n✓ SUCCESS! Tweet ID: " . $result['data']['id'] . "\n";
} else {
    echo "\n✗ Failed to post tweet.\n";
}
