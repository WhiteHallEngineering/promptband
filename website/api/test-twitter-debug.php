<?php
/**
 * Twitter API Debug Test
 */

header('Content-Type: application/json');

$configFile = __DIR__ . '/social-config.php';
$config = require $configFile;
$tc = $config['twitter'];

// Verify credentials are loaded
echo "Credentials check:\n";
echo "API Key length: " . strlen($tc['api_key']) . "\n";
echo "API Secret length: " . strlen($tc['api_secret']) . "\n";
echo "Access Token length: " . strlen($tc['access_token']) . "\n";
echo "Access Token Secret length: " . strlen($tc['access_token_secret']) . "\n";
echo "API Key starts with: " . substr($tc['api_key'], 0, 5) . "...\n";
echo "Access Token starts with: " . substr($tc['access_token'], 0, 20) . "...\n\n";

// Try to verify credentials with Twitter
$verifyUrl = 'https://api.twitter.com/1.1/account/verify_credentials.json';

function buildOAuth($method, $url, $params, $ck, $cs, $at, $ats) {
    $oauth = [
        'oauth_consumer_key' => $ck,
        'oauth_nonce' => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => time(),
        'oauth_token' => $at,
        'oauth_version' => '1.0'
    ];

    $signatureParams = array_merge($oauth, $params);
    ksort($signatureParams);

    $paramString = http_build_query($signatureParams, '', '&', PHP_QUERY_RFC3986);
    $signatureBase = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);

    $signingKey = rawurlencode($cs) . '&' . rawurlencode($ats);
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $signatureBase, $signingKey, true));

    $headerParts = [];
    foreach ($oauth as $key => $value) {
        $headerParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
    }

    return 'OAuth ' . implode(', ', $headerParts);
}

$authHeader = buildOAuth('GET', $verifyUrl, [], $tc['api_key'], $tc['api_secret'], $tc['access_token'], $tc['access_token_secret']);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $verifyUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $authHeader]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Verify Credentials Test (v1.1):\n";
echo "HTTP Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n\n";

// Try v2 endpoint
$meUrl = 'https://api.twitter.com/2/users/me';
$authHeader2 = buildOAuth('GET', $meUrl, [], $tc['api_key'], $tc['api_secret'], $tc['access_token'], $tc['access_token_secret']);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $meUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $authHeader2]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Users/Me Test (v2):\n";
echo "HTTP Code: " . $httpCode2 . "\n";
echo "Response: " . $response2 . "\n";
