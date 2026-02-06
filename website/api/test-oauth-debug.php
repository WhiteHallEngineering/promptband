<?php
/**
 * Detailed OAuth 1.0a Debug Test
 */

header('Content-Type: text/plain');

$configFile = __DIR__ . '/social-config.php';
$config = require $configFile;
$tc = $config['twitter'];

echo "=== CREDENTIALS ===\n";
echo "Consumer Key: " . $tc['api_key'] . "\n";
echo "Consumer Secret: " . substr($tc['api_secret'], 0, 10) . "...(hidden)\n";
echo "Access Token: " . $tc['access_token'] . "\n";
echo "Access Secret: " . substr($tc['access_token_secret'], 0, 10) . "...(hidden)\n\n";

// Test POST to /2/tweets
$url = 'https://api.twitter.com/2/tweets';
$method = 'POST';

$oauth = [
    'oauth_consumer_key' => $tc['api_key'],
    'oauth_nonce' => bin2hex(random_bytes(16)),
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp' => (string)time(),
    'oauth_token' => $tc['access_token'],
    'oauth_version' => '1.0'
];

echo "=== OAUTH PARAMS ===\n";
print_r($oauth);

// For API v2 JSON body, params should be empty for signature
$signatureParams = $oauth;
ksort($signatureParams);

echo "\n=== SORTED PARAMS ===\n";
print_r($signatureParams);

// Build param string
$paramString = http_build_query($signatureParams, '', '&', PHP_QUERY_RFC3986);
echo "\n=== PARAM STRING ===\n";
echo $paramString . "\n";

// Build signature base string
$signatureBase = $method . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
echo "\n=== SIGNATURE BASE STRING ===\n";
echo $signatureBase . "\n";

// Signing key
$signingKey = rawurlencode($tc['api_secret']) . '&' . rawurlencode($tc['access_token_secret']);
echo "\n=== SIGNING KEY (partial) ===\n";
echo substr($signingKey, 0, 20) . "...\n";

// Generate signature
$signature = base64_encode(hash_hmac('sha1', $signatureBase, $signingKey, true));
echo "\n=== SIGNATURE ===\n";
echo $signature . "\n";

$oauth['oauth_signature'] = $signature;

// Build header
$headerParts = [];
foreach ($oauth as $key => $value) {
    $headerParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
}
$authHeader = 'OAuth ' . implode(', ', $headerParts);

echo "\n=== AUTH HEADER ===\n";
echo $authHeader . "\n";

// Now make the request
$body = json_encode(['text' => 'Test from PROMPT - delete me']);

echo "\n=== REQUEST BODY ===\n";
echo $body . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: ' . $authHeader,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);
curl_close($ch);

echo "\n=== RESPONSE ===\n";
echo "HTTP Code: " . $httpCode . "\n";
echo "Headers:\n" . $responseHeaders . "\n";
echo "Body:\n" . $responseBody . "\n";
