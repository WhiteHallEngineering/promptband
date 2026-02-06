<?php
/**
 * Twitter OAuth 2.0 PKCE Callback - Step 2
 * Exchanges code for access token
 */

session_start();
header('Content-Type: text/html');

$configFile = __DIR__ . '/social-config.php';
$config = require $configFile;

// OAuth 2.0 credentials
$clientId = $config['twitter']['oauth2_client_id'];
$clientSecret = $config['twitter']['oauth2_client_secret'];

// Verify state
$state = $_GET['state'] ?? '';
if ($state !== ($_SESSION['twitter_oauth_state'] ?? '')) {
    die('Error: Invalid state parameter. CSRF protection triggered.');
}

// Get authorization code
$code = $_GET['code'] ?? '';
if (empty($code)) {
    $error = $_GET['error'] ?? 'Unknown error';
    $errorDesc = $_GET['error_description'] ?? '';
    die("Error: $error - $errorDesc");
}

// Get code verifier from session
$codeVerifier = $_SESSION['twitter_code_verifier'] ?? '';
if (empty($codeVerifier)) {
    die('Error: Code verifier not found in session.');
}

// Exchange code for token
$tokenUrl = 'https://api.twitter.com/2/oauth2/token';

$postData = [
    'code' => $code,
    'grant_type' => 'authorization_code',
    'client_id' => $clientId,
    'redirect_uri' => 'https://promptband.ai/api/twitter-oauth2-callback.php',
    'code_verifier' => $codeVerifier
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
    // Save token to file
    $tokenFile = __DIR__ . '/../analytics/twitter-oauth2-token.json';
    $tokenData = [
        'access_token' => $result['access_token'],
        'refresh_token' => $result['refresh_token'] ?? null,
        'expires_in' => $result['expires_in'] ?? null,
        'created_at' => time(),
        'scope' => $result['scope'] ?? ''
    ];

    if (!is_dir(dirname($tokenFile))) {
        mkdir(dirname($tokenFile), 0755, true);
    }
    file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));

    // Clear session
    unset($_SESSION['twitter_code_verifier']);
    unset($_SESSION['twitter_oauth_state']);

    echo "<h1>Twitter OAuth 2.0 Success!</h1>";
    echo "<p>Access token saved. You can now post to Twitter.</p>";
    echo "<p>Token expires in: " . ($result['expires_in'] ?? 'unknown') . " seconds</p>";
    echo "<p>Scopes: " . ($result['scope'] ?? 'unknown') . "</p>";
    echo "<p><a href='/admin/'>Return to Admin Dashboard</a></p>";

} else {
    echo "<h1>Error</h1>";
    echo "<p>HTTP Code: $httpCode</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}
