<?php
/**
 * Facebook/Meta Graph API Posting
 *
 * POST parameters:
 * - message: Text content of the post
 * - image_url: (optional) URL of image to post
 * - link: (optional) URL to share
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
$fbConfig = $config['facebook'];

if (empty($fbConfig['page_access_token']) || empty($fbConfig['page_id'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Facebook credentials not configured']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$message = $input['message'] ?? '';
$imageUrl = $input['image_url'] ?? '';
$link = $input['link'] ?? '';

if (empty($message) && empty($imageUrl)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message or image_url required']);
    exit;
}

// Build Graph API request
$pageId = $fbConfig['page_id'];
$accessToken = $fbConfig['page_access_token'];
$graphUrl = "https://graph.facebook.com/v18.0/{$pageId}";

try {
    if (!empty($imageUrl)) {
        // Photo post
        $endpoint = "{$graphUrl}/photos";
        $postData = [
            'url' => $imageUrl,
            'caption' => $message,
            'access_token' => $accessToken
        ];
    } elseif (!empty($link)) {
        // Link post
        $endpoint = "{$graphUrl}/feed";
        $postData = [
            'message' => $message,
            'link' => $link,
            'access_token' => $accessToken
        ];
    } else {
        // Text post
        $endpoint = "{$graphUrl}/feed";
        $postData = [
            'message' => $message,
            'access_token' => $accessToken
        ];
    }

    // Make API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("cURL error: {$curlError}");
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($result['id'])) {
        // Log successful post
        logSocialPost('facebook', $message, $imageUrl, $link, true, $result['id']);

        echo json_encode([
            'success' => true,
            'platform' => 'facebook',
            'post_id' => $result['id'],
            'message' => 'Posted successfully to Facebook'
        ]);
    } else {
        $errorMsg = $result['error']['message'] ?? 'Unknown error';
        logSocialPost('facebook', $message, $imageUrl, $link, false, null, $errorMsg);

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'platform' => 'facebook',
            'error' => $errorMsg,
            'details' => $result
        ]);
    }

} catch (Exception $e) {
    logSocialPost('facebook', $message, $imageUrl, $link, false, null, $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'platform' => 'facebook',
        'error' => $e->getMessage()
    ]);
}

/**
 * Log social media post to analytics
 */
function logSocialPost($platform, $message, $imageUrl, $link, $success, $postId = null, $error = null) {
    $analyticsDir = dirname(__DIR__) . '/analytics';
    $logFile = $analyticsDir . '/social-posts.json';

    // Create directory if needed
    if (!is_dir($analyticsDir)) {
        mkdir($analyticsDir, 0755, true);
    }

    // Load existing logs
    $logs = [];
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?? [];
    }

    // Add new entry
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

    // Keep last 1000 entries
    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }

    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
}
