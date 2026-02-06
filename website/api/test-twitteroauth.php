<?php
/**
 * Test Twitter posting using abraham/twitteroauth library
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(30);

require_once __DIR__ . '/twitteroauth-main/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

header('Content-Type: text/plain');

// Load credentials
$configFile = __DIR__ . '/social-config.php';
$config = require $configFile;
$tc = $config['twitter'];

echo "Testing TwitterOAuth library...\n\n";

try {
    // Create connection
    $connection = new TwitterOAuth(
        $tc['api_key'],
        $tc['api_secret'],
        $tc['access_token'],
        $tc['access_token_secret']
    );

    // Set timeout
    $connection->setTimeouts(10, 30);

    // Set API version to 2
    $connection->setApiVersion('2');

    echo "Connection created.\n";
    echo "API Key: " . substr($tc['api_key'], 0, 10) . "...\n";
    echo "Access Token: " . substr($tc['access_token'], 0, 25) . "...\n\n";

    echo "Attempting to post tweet...\n";
    flush();

    // Try to post a tweet (API v2 requires json: true in parameters)
    $result = $connection->post('tweets', ['text' => 'First signal from PROMPT... the machines are making music. 🤖🎸']);

    echo "HTTP Code: " . $connection->getLastHttpCode() . "\n";
    echo "Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

    if ($connection->getLastHttpCode() == 201 || $connection->getLastHttpCode() == 200) {
        echo "\n\n✓ SUCCESS! Tweet posted.\n";
    } else {
        echo "\n\n✗ Failed to post tweet.\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\nDone.\n";
