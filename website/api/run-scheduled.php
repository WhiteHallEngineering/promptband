<?php
// Run scheduled posts - called by cron every 15 minutes
// Posts to Twitter, Facebook, and Instagram based on post platform setting

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

// Load config
$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}
$config = require $configFile;
$tc = $config['twitter'];
$fbConfig = $config['facebook'];
$igConfig = $config['instagram'];

// OAuth functions for Twitter
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
        logSocialPost('twitter', $message, $imageUrl, '', true, $result['data']['id']);
        return [
            'success' => true,
            'post_id' => $result['data']['id'],
            'post_url' => 'https://x.com/promptband/status/' . $result['data']['id']
        ];
    }

    $errorMsg = $result['detail'] ?? $result['errors'][0]['message'] ?? $response;
    logSocialPost('twitter', $message, $imageUrl, '', false, null, $errorMsg);
    return ['success' => false, 'error' => $errorMsg];
}

function postFacebook($message, $fbConfig, $imageUrl = '') {
    if (empty($fbConfig['page_access_token']) || empty($fbConfig['page_id'])) {
        return ['success' => false, 'error' => 'Facebook not configured'];
    }

    $pageId = $fbConfig['page_id'];
    $accessToken = $fbConfig['page_access_token'];
    $graphUrl = "https://graph.facebook.com/v18.0/{$pageId}";

    if (!empty($imageUrl)) {
        $endpoint = "{$graphUrl}/photos";
        $postData = [
            'url' => $imageUrl,
            'caption' => $message,
            'access_token' => $accessToken
        ];
    } else {
        $endpoint = "{$graphUrl}/feed";
        $postData = [
            'message' => $message,
            'access_token' => $accessToken
        ];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($result['id'])) {
        logSocialPost('facebook', $message, $imageUrl, '', true, $result['id']);
        return [
            'success' => true,
            'post_id' => $result['id'],
            'post_url' => 'https://facebook.com/' . $result['id']
        ];
    }

    $errorMsg = $result['error']['message'] ?? 'Unknown Facebook error';
    logSocialPost('facebook', $message, $imageUrl, '', false, null, $errorMsg);
    return ['success' => false, 'error' => $errorMsg];
}

function postInstagram($message, $igConfig, $imageUrl = '') {
    if (empty($igConfig['account_id']) || empty($igConfig['access_token'])) {
        return ['success' => false, 'error' => 'Instagram not configured'];
    }

    if (empty($imageUrl)) {
        return ['success' => false, 'error' => 'Instagram requires an image'];
    }

    $accountId = $igConfig['account_id'];
    $accessToken = $igConfig['access_token'];

    // Step 1: Create media container
    $containerUrl = "https://graph.facebook.com/v18.0/{$accountId}/media";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $containerUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'image_url' => $imageUrl,
        'caption' => $message,
        'access_token' => $accessToken
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    curl_close($ch);

    $containerResult = json_decode($response, true);
    if (!isset($containerResult['id'])) {
        $errorMsg = $containerResult['error']['message'] ?? 'Failed to create media container';
        logSocialPost('instagram', $message, $imageUrl, '', false, null, $errorMsg);
        return ['success' => false, 'error' => $errorMsg];
    }

    $containerId = $containerResult['id'];

    // Step 2: Wait for container to be ready
    $maxAttempts = 30;
    $attempts = 0;
    $ready = false;

    while ($attempts < $maxAttempts && !$ready) {
        $statusUrl = "https://graph.facebook.com/v18.0/{$containerId}?fields=status_code&access_token={$accessToken}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $statusResponse = curl_exec($ch);
        curl_close($ch);

        $statusResult = json_decode($statusResponse, true);
        $status = $statusResult['status_code'] ?? '';

        if ($status === 'FINISHED') {
            $ready = true;
        } elseif ($status === 'ERROR') {
            logSocialPost('instagram', $message, $imageUrl, '', false, null, 'Media processing failed');
            return ['success' => false, 'error' => 'Media processing failed'];
        } else {
            sleep(2);
            $attempts++;
        }
    }

    if (!$ready) {
        logSocialPost('instagram', $message, $imageUrl, '', false, null, 'Media processing timeout');
        return ['success' => false, 'error' => 'Media processing timeout'];
    }

    // Step 3: Publish
    $publishUrl = "https://graph.facebook.com/v18.0/{$accountId}/media_publish";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $publishUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'creation_id' => $containerId,
        'access_token' => $accessToken
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $publishResult = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($publishResult['id'])) {
        logSocialPost('instagram', $message, $imageUrl, '', true, $publishResult['id']);
        return [
            'success' => true,
            'post_id' => $publishResult['id'],
            'post_url' => 'https://instagram.com/promptband.ai'
        ];
    }

    $errorMsg = $publishResult['error']['message'] ?? 'Failed to publish';
    logSocialPost('instagram', $message, $imageUrl, '', false, null, $errorMsg);
    return ['success' => false, 'error' => $errorMsg];
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
        'source' => 'scheduled'
    ];

    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }

    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
}

/**
 * Post to all platforms based on post's platform setting
 * Returns array of results per platform
 */
function postToAllPlatforms($post, $tc, $fbConfig, $igConfig) {
    $platform = $post['platform'] ?? 'twitter';
    $message = $post['message'];
    $imageUrl = $post['image_url'] ?? '';
    $results = [];

    // Determine which platforms to post to
    if ($platform === 'all') {
        $platforms = ['twitter', 'facebook'];
        // Only include Instagram if there's an image
        if (!empty($imageUrl)) {
            $platforms[] = 'instagram';
        }
    } elseif (strpos($platform, ',') !== false) {
        // Support comma-separated platforms like 'facebook,instagram'
        $platforms = array_map('trim', explode(',', $platform));
    } else {
        $platforms = [$platform];
    }

    foreach ($platforms as $p) {
        switch ($p) {
            case 'twitter':
                $results['twitter'] = postTweet($message, $tc, $imageUrl);
                break;
            case 'facebook':
                $results['facebook'] = postFacebook($message, $fbConfig, $imageUrl);
                break;
            case 'instagram':
                $results['instagram'] = postInstagram($message, $igConfig, $imageUrl);
                break;
        }
        usleep(500000); // Small delay between platforms
    }

    return $results;
}

// Process pending posts that are due
foreach ($scheduled as &$post) {
    if ($post['status'] !== 'pending') continue;

    $scheduledTime = strtotime($post['scheduled_for']);
    if ($scheduledTime > $now) continue; // Not due yet

    // Post to all configured platforms
    $results = postToAllPlatforms($post, $tc, $fbConfig, $igConfig);

    // Check if at least one platform succeeded
    $anySuccess = false;
    $allErrors = [];
    $postUrls = [];

    foreach ($results as $platform => $result) {
        if ($result['success']) {
            $anySuccess = true;
            $postUrls[$platform] = $result['post_url'] ?? $result['post_id'];
        } else {
            $allErrors[$platform] = $result['error'];
        }
    }

    if ($anySuccess) {
        $post['status'] = 'posted';
        $post['posted_at'] = date('c');
        $post['post_urls'] = $postUrls;
        // Keep tweet_url for backwards compat
        $post['tweet_url'] = $postUrls['twitter'] ?? ($postUrls['facebook'] ?? null);
        $post['platform_results'] = $results;

        $posted[] = [
            'id' => $post['id'],
            'message' => substr($post['message'], 0, 50) . '...',
            'platforms' => array_keys(array_filter($results, function($r) { return $r['success']; })),
            'post_urls' => $postUrls
        ];

        // Log any partial failures
        if (!empty($allErrors)) {
            $post['partial_errors'] = $allErrors;
        }
    } else {
        $post['status'] = 'failed';
        $post['error'] = implode('; ', array_map(function($p, $e) { return "$p: $e"; }, array_keys($allErrors), $allErrors));
        $errors[] = [
            'id' => $post['id'],
            'message' => substr($post['message'], 0, 50) . '...',
            'error' => $post['error']
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
