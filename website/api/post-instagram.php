<?php
/**
 * Instagram Graph API Posting
 *
 * POST parameters:
 * - message: Caption for the post
 * - image_url: URL of image to post (REQUIRED - Instagram requires media)
 *
 * Note: Uses Instagram Graph API which requires:
 * - Facebook Business account
 * - Instagram Professional account connected to Facebook Page
 * - Image URL must be publicly accessible
 *
 * Requires: key=pr0mpt-m3ss4g3s-2026
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Authentication
$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing API key']);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Load credentials
$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Social config not found']);
    exit;
}

$config = require $configFile;
$igConfig = $config['instagram'];

if (empty($igConfig['account_id']) || empty($igConfig['access_token'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Instagram credentials not configured']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$message = $input['message'] ?? '';
$imageUrl = $input['image_url'] ?? '';

// Instagram requires an image
if (empty($imageUrl)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'image_url is required for Instagram posts']);
    exit;
}

// Validate URL format
if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid image_url format']);
    exit;
}

$accountId = $igConfig['account_id'];
$accessToken = $igConfig['access_token'];

try {
    // Step 1: Create media container
    $containerUrl = "https://graph.facebook.com/v18.0/{$accountId}/media";

    $containerParams = [
        'image_url' => $imageUrl,
        'caption' => $message,
        'access_token' => $accessToken
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $containerUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($containerParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("cURL error: {$curlError}");
    }

    $containerResult = json_decode($response, true);

    if (!isset($containerResult['id'])) {
        $errorMsg = $containerResult['error']['message'] ?? 'Failed to create media container';
        throw new Exception($errorMsg);
    }

    $containerId = $containerResult['id'];

    // Step 2: Wait for container to be ready (poll status)
    $maxAttempts = 30;
    $attempts = 0;
    $ready = false;

    while ($attempts < $maxAttempts && !$ready) {
        $statusUrl = "https://graph.facebook.com/v18.0/{$containerId}?fields=status_code&access_token={$accessToken}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $statusResponse = curl_exec($ch);
        curl_close($ch);

        $statusResult = json_decode($statusResponse, true);
        $status = $statusResult['status_code'] ?? '';

        if ($status === 'FINISHED') {
            $ready = true;
        } elseif ($status === 'ERROR') {
            throw new Exception('Media processing failed');
        } else {
            // Wait 2 seconds before checking again
            sleep(2);
            $attempts++;
        }
    }

    if (!$ready) {
        throw new Exception('Media processing timeout');
    }

    // Step 3: Publish the container
    $publishUrl = "https://graph.facebook.com/v18.0/{$accountId}/media_publish";

    $publishParams = [
        'creation_id' => $containerId,
        'access_token' => $accessToken
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $publishUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($publishParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $publishResult = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($publishResult['id'])) {
        logSocialPost('instagram', $message, $imageUrl, '', true, $publishResult['id']);

        echo json_encode([
            'success' => true,
            'platform' => 'instagram',
            'post_id' => $publishResult['id'],
            'message' => 'Posted successfully to Instagram'
        ]);
    } else {
        $errorMsg = $publishResult['error']['message'] ?? 'Failed to publish post';
        logSocialPost('instagram', $message, $imageUrl, '', false, null, $errorMsg);

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'platform' => 'instagram',
            'error' => $errorMsg,
            'details' => $publishResult
        ]);
    }

} catch (Exception $e) {
    logSocialPost('instagram', $message, $imageUrl, '', false, null, $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'platform' => 'instagram',
        'error' => $e->getMessage()
    ]);
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
        'ip_hash' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown')
    ];

    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }

    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
}
