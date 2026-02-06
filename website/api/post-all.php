<?php
/**
 * Post to Multiple Social Media Platforms
 *
 * POST parameters:
 * - message: Text content of the post
 * - image_url: (optional, required for Instagram) URL of image
 * - link: (optional, Facebook only) URL to share
 * - platforms: Array of platforms to post to ['facebook', 'twitter', 'instagram']
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

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$message = $input['message'] ?? '';
$imageUrl = $input['image_url'] ?? '';
$link = $input['link'] ?? '';
$platforms = $input['platforms'] ?? [];

if (empty($message) && empty($imageUrl)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message or image_url required']);
    exit;
}

if (empty($platforms) || !is_array($platforms)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'platforms array is required']);
    exit;
}

$validPlatforms = ['facebook', 'twitter', 'instagram'];
$platforms = array_intersect($platforms, $validPlatforms);

if (empty($platforms)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid platforms specified. Use: ' . implode(', ', $validPlatforms)]);
    exit;
}

// Instagram requires an image
if (in_array('instagram', $platforms) && empty($imageUrl)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'image_url is required when posting to Instagram']);
    exit;
}

$results = [];
$allSuccess = true;

// Post to each platform
foreach ($platforms as $platform) {
    $result = postToPlatform($platform, $message, $imageUrl, $link, $providedKey);
    $results[$platform] = $result;

    if (!$result['success']) {
        $allSuccess = false;
    }
}

// Return combined results
$response = [
    'success' => $allSuccess,
    'results' => $results,
    'summary' => [
        'total' => count($platforms),
        'successful' => count(array_filter($results, function($r) { return $r['success']; })),
        'failed' => count(array_filter($results, function($r) { return !$r['success']; }))
    ]
];

if ($allSuccess) {
    echo json_encode($response);
} else {
    http_response_code(207); // Multi-Status
    echo json_encode($response);
}

/**
 * Post to a specific platform using internal API call
 */
function postToPlatform($platform, $message, $imageUrl, $link, $key) {
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
               '://' . $_SERVER['HTTP_HOST'];

    $endpoints = [
        'facebook' => '/api/post-facebook.php',
        'twitter' => '/api/post-twitter.php',
        'instagram' => '/api/post-instagram.php'
    ];

    if (!isset($endpoints[$platform])) {
        return ['success' => false, 'error' => 'Unknown platform'];
    }

    $url = $baseUrl . $endpoints[$platform] . '?key=' . urlencode($key);

    $postData = ['message' => $message];

    if (!empty($imageUrl)) {
        $postData['image_url'] = $imageUrl;
    }

    if (!empty($link) && $platform === 'facebook') {
        $postData['link'] = $link;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Internal call
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Instagram can be slow

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'platform' => $platform,
            'error' => "Connection error: {$curlError}"
        ];
    }

    $result = json_decode($response, true);

    if ($result === null) {
        return [
            'success' => false,
            'platform' => $platform,
            'error' => 'Invalid response from platform API'
        ];
    }

    return $result;
}
