<?php
/**
 * Detailed OAuth 1.0a debug - compare with known working implementations
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

$config = require __DIR__ . '/social-config.php';
$tc = $config['twitter'];

echo "=== OAuth 1.0a Detailed Debug ===\n\n";

// Credentials
$consumerKey = $tc['api_key'];
$consumerSecret = $tc['api_secret'];
$accessToken = $tc['access_token'];
$accessTokenSecret = $tc['access_token_secret'];

echo "Consumer Key: $consumerKey\n";
echo "Consumer Secret: $consumerSecret\n";
echo "Access Token: $accessToken\n";
echo "Access Token Secret: $accessTokenSecret\n\n";

echo "Lengths:\n";
echo "- Consumer Key: " . strlen($consumerKey) . " chars\n";
echo "- Consumer Secret: " . strlen($consumerSecret) . " chars\n";
echo "- Access Token: " . strlen($accessToken) . " chars\n";
echo "- Access Token Secret: " . strlen($accessTokenSecret) . " chars\n\n";

// Fixed timestamp and nonce for reproducible testing
$timestamp = time();
$nonce = md5(uniqid(mt_rand(), true));

echo "Timestamp: $timestamp\n";
echo "Nonce: $nonce\n\n";

$url = 'https://api.x.com/2/tweets';
$method = 'POST';

// OAuth parameters
$oauthParams = [
    'oauth_consumer_key' => $consumerKey,
    'oauth_nonce' => $nonce,
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp' => $timestamp,
    'oauth_token' => $accessToken,
    'oauth_version' => '1.0',
];

echo "=== OAuth Parameters ===\n";
foreach ($oauthParams as $k => $v) {
    echo "$k = $v\n";
}

// Sort parameters
ksort($oauthParams);

echo "\n=== Sorted Parameters ===\n";
foreach ($oauthParams as $k => $v) {
    echo "$k = $v\n";
}

// Build parameter string (percent-encode each key and value)
$paramPairs = [];
foreach ($oauthParams as $key => $value) {
    $paramPairs[] = rawurlencode($key) . '=' . rawurlencode($value);
}
$paramString = implode('&', $paramPairs);

echo "\n=== Parameter String ===\n";
echo $paramString . "\n";

// Build signature base string
$signatureBaseString = $method . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);

echo "\n=== Signature Base String ===\n";
echo $signatureBaseString . "\n";

// Build signing key
$signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($accessTokenSecret);

echo "\n=== Signing Key ===\n";
echo $signingKey . "\n";

// Generate signature
$signature = base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));

echo "\n=== Signature ===\n";
echo $signature . "\n";

// Add signature to oauth params
$oauthParams['oauth_signature'] = $signature;

// Build Authorization header
$authParts = [];
foreach ($oauthParams as $key => $value) {
    $authParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
}
$authHeader = 'OAuth ' . implode(', ', $authParts);

echo "\n=== Authorization Header ===\n";
echo $authHeader . "\n";

// Make request
echo "\n=== Making Request ===\n";

$body = '{"text":"Test signal from PROMPT"}';
echo "Body: $body\n\n";

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
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}
echo "Response: $response\n";
