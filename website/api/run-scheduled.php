<?php
// Run scheduled posts - called by cron every 15 minutes

header('Content-Type: application/json');

$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$scheduleFile = dirname(__DIR__) . '/analytics/scheduled-posts.json';
$logFile = dirname(__DIR__) . '/analytics/schedule-log.json';

if (!file_exists($scheduleFile)) {
    echo json_encode(['success' => true, 'message' => 'No scheduled posts', 'posted' => 0]);
    exit;
}

$scheduled = json_decode(file_get_contents($scheduleFile), true) ?? [];
$now = time();
$posted = [];
$errors = [];

// Load config for Twitter
$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}
$config = require $configFile;
$tc = $config['twitter'];

// OAuth functions
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

function postTweet($message, $tc, $imageUrl = '') {
    $tweetUrl = 'https://api.twitter.com/2/tweets';
    $tweetData = ['text' => $message];

    // TODO: Add image support if needed

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
        // Log to social-posts.json so it shows in activity log
        logSocialPost('twitter', $message, $imageUrl, '', true, $result['data']['id']);

        return [
            'success' => true,
            'tweet_id' => $result['data']['id'],
            'tweet_url' => 'https://x.com/promptband/status/' . $result['data']['id']
        ];
    }

    // Log failed attempt
    $errorMsg = $result['detail'] ?? $result['errors'][0]['message'] ?? $response;
    logSocialPost('twitter', $message, $imageUrl, '', false, null, $errorMsg);

    return [
        'success' => false,
        'error' => $errorMsg
    ];
}

/**
 * Log social media post to analytics (same as post-twitter.php)
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
        'source' => 'scheduled' // Mark as scheduled post
    ];

    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }

    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
}

// Process pending posts that are due
foreach ($scheduled as &$post) {
    if ($post['status'] !== 'pending') continue;

    $scheduledTime = strtotime($post['scheduled_for']);
    if ($scheduledTime > $now) continue; // Not due yet

    // Post is due - send it
    $result = postTweet($post['message'], $tc, $post['image_url'] ?? '');

    if ($result['success']) {
        $post['status'] = 'posted';
        $post['posted_at'] = date('c');
        $post['tweet_url'] = $result['tweet_url'];
        $posted[] = [
            'id' => $post['id'],
            'message' => substr($post['message'], 0, 50) . '...',
            'tweet_url' => $result['tweet_url']
        ];
    } else {
        $post['status'] = 'failed';
        $post['error'] = $result['error'];
        $errors[] = [
            'id' => $post['id'],
            'message' => substr($post['message'], 0, 50) . '...',
            'error' => $result['error']
        ];
    }

    // Small delay between posts
    usleep(500000);
}

// Save updated schedule
file_put_contents($scheduleFile, json_encode($scheduled, JSON_PRETTY_PRINT));

// Log the run
$log = [];
if (file_exists($logFile)) {
    $log = json_decode(file_get_contents($logFile), true) ?? [];
}
$log[] = [
    'run_at' => date('c'),
    'posted_count' => count($posted),
    'error_count' => count($errors),
    'posted' => $posted,
    'errors' => $errors
];
// Keep last 100 log entries
$log = array_slice($log, -100);
file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'posted' => count($posted),
    'errors' => count($errors),
    'details' => [
        'posted' => $posted,
        'errors' => $errors
    ]
]);
