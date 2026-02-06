<?php
// Generate image via OpenAI DALL-E 3

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

$config = require $configFile;
$openaiKey = $config['openai']['api_key'] ?? '';

if (empty($openaiKey)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'OpenAI API key not configured']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$prompt = $input['prompt'] ?? '';
$size = $input['size'] ?? '1024x1024'; // 1024x1024, 1024x1792, 1792x1024
$quality = $input['quality'] ?? 'standard'; // standard, hd
$style = $input['style'] ?? 'vivid'; // vivid, natural
$track = $input['track'] ?? ''; // e.g., "02"
$clipName = $input['clip_name'] ?? ''; // e.g., "Love or Illusion"

if (empty($prompt)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'prompt is required']);
    exit;
}

// Load visual style guide for consistent imagery
$styleGuideFile = __DIR__ . '/visual-style-guide.json';
$styleGuide = null;
if (file_exists($styleGuideFile)) {
    $styleGuide = json_decode(file_get_contents($styleGuideFile), true);
}

// Build enhanced prompt using style guide
$enhancedPrompt = $prompt;

if ($styleGuide) {
    $masterStyle = $styleGuide['master_style'] ?? [];

    // Add track-specific style if available
    $trackKey = $track ? sprintf('%02d_', intval($track)) : '';
    $trackStyles = $styleGuide['track_styles'] ?? [];
    $trackStyle = null;
    foreach ($trackStyles as $key => $trackStyleData) {
        if (strpos($key, $trackKey) === 0) {
            $trackStyle = $trackStyleData;
            break;
        }
    }

    // Check if prompt mentions a band member and add their description
    $bandMembers = $styleGuide['band_members'] ?? [];
    $memberDescriptions = [];
    foreach ($bandMembers as $name => $member) {
        if (stripos($prompt, $name) !== false && !empty($member['description']) && $member['description'] !== 'TO BE FILLED') {
            $memberDescriptions[] = ucfirst($name) . ': ' . $member['description'];
        }
    }

    // Build the enhanced prompt
    $promptParts = [$prompt];

    // Add member descriptions if any
    if (!empty($memberDescriptions)) {
        $promptParts[] = 'Character reference: ' . implode('. ', $memberDescriptions);
    }

    // Add track-specific mood if available
    if ($trackStyle && !empty($trackStyle['mood'])) {
        $promptParts[] = 'Mood: ' . $trackStyle['mood'];
    }

    // Add master style suffix
    if (!empty($masterStyle['prompt_suffix'])) {
        $promptParts[] = $masterStyle['prompt_suffix'];
    }

    // Add things to avoid as negative guidance
    if (!empty($masterStyle['avoid'])) {
        $promptParts[] = 'Avoid: ' . implode(', ', $masterStyle['avoid']);
    }

    $enhancedPrompt = implode('. ', $promptParts);
} else {
    // Fallback if no style guide
    $enhancedPrompt = $prompt . ". Digital art style, neon magenta and cyan lighting, dark cyberpunk atmosphere, cinematic composition, high quality.";
}

// Call OpenAI DALL-E 3 API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/images/generations');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $openaiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'dall-e-3',
    'prompt' => $enhancedPrompt,
    'n' => 1,
    'size' => $size,
    'quality' => $quality,
    'style' => $style,
    'response_format' => 'url'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API request failed: ' . $curlError]);
    exit;
}

$result = json_decode($response, true);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => $result['error']['message'] ?? 'OpenAI API error',
        'details' => $result
    ]);
    exit;
}

$imageUrl = $result['data'][0]['url'] ?? null;
$revisedPrompt = $result['data'][0]['revised_prompt'] ?? null;

if (!$imageUrl) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No image URL in response']);
    exit;
}

// Optionally save the image locally
$savedPath = null;
if ($input['save'] ?? false) {
    $imageData = file_get_contents($imageUrl);
    if ($imageData) {
        // Use clip name in filename if provided for easier identification
        $safeClipName = $clipName ? preg_replace('/[^a-z0-9]+/i', '-', strtolower($clipName)) : substr(md5($prompt), 0, 8);
        $filename = ($track ? "track{$track}-" : '') . $safeClipName . '-' . time() . '.png';
        $savePath = dirname(__DIR__) . '/generated-images/';
        if (!is_dir($savePath)) {
            mkdir($savePath, 0755, true);
        }
        file_put_contents($savePath . $filename, $imageData);
        $savedPath = '/generated-images/' . $filename;

        // Register in asset registry if track and clip name provided
        if ($track && $clipName) {
            $registryFile = dirname(__DIR__) . '/analytics/asset-registry.json';
            $registry = [];
            if (file_exists($registryFile)) {
                $registry = json_decode(file_get_contents($registryFile), true) ?? [];
            }
            if (!isset($registry[$track])) {
                $registry[$track] = [];
            }
            $registry[$track][$clipName] = [
                'image_path' => $savedPath,
                'image_url' => $imageUrl,
                'created_at' => date('c')
            ];
            file_put_contents($registryFile, json_encode($registry, JSON_PRETTY_PRINT));
        }
    }
}

echo json_encode([
    'success' => true,
    'image_url' => $imageUrl,
    'revised_prompt' => $revisedPrompt,
    'saved_path' => $savedPath,
    'size' => $size,
    'quality' => $quality,
    'track' => $track,
    'clip_name' => $clipName
]);
