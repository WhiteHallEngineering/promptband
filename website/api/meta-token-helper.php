<?php
/**
 * Meta Token Helper
 * Use this to get your Facebook Page ID and Instagram Business Account ID
 *
 * Prerequisites:
 * 1. Accept Instagram tester invite in Instagram app
 * 2. Generate User Access Token from Meta Developer Portal:
 *    - Go to developers.facebook.com
 *    - Open your app (PROMPT)
 *    - Tools → Graph API Explorer
 *    - Select your app from dropdown
 *    - Add permissions: pages_show_list, pages_read_engagement, pages_manage_posts,
 *                       instagram_basic, instagram_content_publish
 *    - Click "Generate Access Token"
 *    - Copy the token and pass it to this script
 *
 * Usage: https://promptband.ai/api/meta-token-helper.php?key=pr0mpt-m3ss4g3s-2026&token=YOUR_USER_ACCESS_TOKEN
 */

header('Content-Type: text/plain');

$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $validKey) {
    echo "Invalid key\n";
    exit;
}

$userToken = $_GET['token'] ?? '';

if (empty($userToken)) {
    echo "=== Meta Token Helper ===\n\n";
    echo "This script helps you get your Facebook Page ID and Instagram Business Account ID.\n\n";
    echo "Step 1: Go to https://developers.facebook.com/tools/explorer/\n";
    echo "Step 2: Select your app 'PROMPT' from the dropdown\n";
    echo "Step 3: Click 'Add a Permission' and add:\n";
    echo "        - pages_show_list\n";
    echo "        - pages_read_engagement\n";
    echo "        - pages_manage_posts\n";
    echo "        - instagram_basic\n";
    echo "        - instagram_content_publish\n";
    echo "Step 4: Click 'Generate Access Token' (blue button)\n";
    echo "Step 5: Authorize/login when prompted\n";
    echo "Step 6: Copy the access token and call this URL with:\n\n";
    echo "        ?key=pr0mpt-m3ss4g3s-2026&token=YOUR_ACCESS_TOKEN\n\n";
    exit;
}

echo "=== Meta Token Helper ===\n\n";
echo "Using token: " . substr($userToken, 0, 30) . "...\n\n";

// Get user's pages
echo "=== Fetching Your Facebook Pages ===\n\n";
$pagesUrl = "https://graph.facebook.com/v18.0/me/accounts?access_token=" . urlencode($userToken);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $pagesUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$pages = json_decode($response, true);

if (!isset($pages['data']) || empty($pages['data'])) {
    echo "No pages found or error:\n";
    echo json_encode($pages, JSON_PRETTY_PRINT) . "\n";
    echo "\nMake sure you have a Facebook Page and have granted page permissions.\n";
    exit;
}

echo "Found " . count($pages['data']) . " page(s):\n\n";

foreach ($pages['data'] as $page) {
    echo "Page Name: " . $page['name'] . "\n";
    echo "Page ID: " . $page['id'] . "\n";
    echo "Page Access Token: " . substr($page['access_token'], 0, 50) . "...\n";

    // Get Instagram Business Account connected to this page
    $igUrl = "https://graph.facebook.com/v18.0/" . $page['id'] . "?fields=instagram_business_account&access_token=" . urlencode($page['access_token']);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $igUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    $igResponse = curl_exec($ch);
    curl_close($ch);

    $igData = json_decode($igResponse, true);

    if (isset($igData['instagram_business_account']['id'])) {
        echo "Instagram Business Account ID: " . $igData['instagram_business_account']['id'] . "\n";

        // Get Instagram username
        $igInfoUrl = "https://graph.facebook.com/v18.0/" . $igData['instagram_business_account']['id'] . "?fields=username&access_token=" . urlencode($page['access_token']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $igInfoUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        $igInfoResponse = curl_exec($ch);
        curl_close($ch);
        $igInfo = json_decode($igInfoResponse, true);

        if (isset($igInfo['username'])) {
            echo "Instagram Username: @" . $igInfo['username'] . "\n";
        }
    } else {
        echo "Instagram: No business account connected to this page\n";
    }

    echo "\n---\n\n";
}

echo "\n=== Configuration Values for social-config.php ===\n\n";

// Use first page found
$firstPage = $pages['data'][0];
echo "Facebook Configuration:\n";
echo "  'page_id' => '" . $firstPage['id'] . "',\n";
echo "  'page_access_token' => '" . $firstPage['access_token'] . "'\n\n";

// Check for Instagram
$igUrl = "https://graph.facebook.com/v18.0/" . $firstPage['id'] . "?fields=instagram_business_account&access_token=" . urlencode($firstPage['access_token']);
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $igUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
]);
$igResponse = curl_exec($ch);
curl_close($ch);
$igData = json_decode($igResponse, true);

if (isset($igData['instagram_business_account']['id'])) {
    echo "Instagram Configuration:\n";
    echo "  'account_id' => '" . $igData['instagram_business_account']['id'] . "',\n";
    echo "  'access_token' => '" . $firstPage['access_token'] . "'  // Same token works for both\n";
} else {
    echo "Instagram: Connect your Instagram Business/Creator account to your Facebook Page first.\n";
    echo "Go to: Facebook Page Settings → Linked Accounts → Instagram\n";
}

echo "\n=== IMPORTANT ===\n";
echo "The Page Access Token shown above is a short-lived token.\n";
echo "For production, exchange it for a long-lived token (60 days):\n";
echo "https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived\n";
