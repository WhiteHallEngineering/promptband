<?php
/**
 * Twitter OAuth 2.0 PKCE Authorization - Step 1
 * Redirects user to Twitter to authorize the app
 */

session_start();

$configFile = __DIR__ . '/social-config.php';
$config = require $configFile;

// OAuth 2.0 Client ID from config
$clientId = $config['twitter']['oauth2_client_id'];

// Generate PKCE code verifier and challenge
$codeVerifier = bin2hex(random_bytes(32));
$codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

// Store verifier in session for callback
$_SESSION['twitter_code_verifier'] = $codeVerifier;

// State for CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['twitter_oauth_state'] = $state;

// Scopes needed for posting
$scopes = 'tweet.read tweet.write users.read offline.access';

// Build authorization URL (must use x.com, not twitter.com)
$authUrl = 'https://x.com/i/oauth2/authorize?' . http_build_query([
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => 'https://promptband.ai/api/twitter-oauth2-callback.php',
    'scope' => $scopes,
    'state' => $state,
    'code_challenge' => $codeChallenge,
    'code_challenge_method' => 'S256'
]);

// Redirect to Twitter
header('Location: ' . $authUrl);
exit;
