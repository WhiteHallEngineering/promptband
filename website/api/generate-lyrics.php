<?php
/**
 * PROMPT Lyrics Generation API
 * Uses OpenAI API to generate lyrics based on PROMPT's style profile
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Simple key-based authentication
$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Load config for API keys
$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Config not found']);
    exit;
}

$config = require $configFile;
$openaiKey = $config['openai']['api_key'] ?? '';

if (empty($openaiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'OpenAI API key not configured']);
    exit;
}

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$title = trim($input['title'] ?? '');
$theme = trim($input['theme'] ?? '');
$customInstructions = trim($input['customInstructions'] ?? '');
$alternatives = intval($input['alternatives'] ?? 1);

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['error' => 'Title is required']);
    exit;
}

$alternatives = max(1, min(3, $alternatives)); // Clamp between 1 and 3

// Load PROMPT's sound profile
$profileFile = __DIR__ . '/prompt-profile.json';
$profile = [];
if (file_exists($profileFile)) {
    $profile = json_decode(file_get_contents($profileFile), true) ?? [];
}

// Build the system prompt with PROMPT's style
$lyricGuidelines = $profile['lyricGuidelines'] ?? [];
$style = $profile['style'] ?? [];
$exampleLyrics = $profile['exampleLyrics'] ?? [];

$systemPrompt = <<<EOT
You are a lyricist for PROMPT, an AI rock band with a distinctive sound and voice.

MUSICAL STYLE:
- Genres: {$style['genres'][0]}, {$style['genres'][1]}, {$style['genres'][2]}
- Vocal style: {$style['vocalStyle']}
- Mood: {$style['mood']}
- Production feel: {$style['production']}

LYRICAL APPROACH:
- Perspective: {$lyricGuidelines['perspective']}
- Tone: {$lyricGuidelines['tone']}
- Structure: {$lyricGuidelines['structure']}
- Rhyme scheme: {$lyricGuidelines['rhymeScheme']}

CORE THEMES (draw from these):
EOT;

if (!empty($lyricGuidelines['themes'])) {
    foreach ($lyricGuidelines['themes'] as $t) {
        $systemPrompt .= "\n- $t";
    }
}

$systemPrompt .= "\n\nAVOID THESE CLICHES:";
if (!empty($lyricGuidelines['avoidCliches'])) {
    foreach ($lyricGuidelines['avoidCliches'] as $c) {
        $systemPrompt .= "\n- $c";
    }
}

if (!empty($exampleLyrics)) {
    $systemPrompt .= "\n\nEXAMPLE LYRICS FROM THE BAND:";
    foreach ($exampleLyrics as $ex) {
        $systemPrompt .= "\n\nFrom \"{$ex['track']}\":\n{$ex['snippet']}";
    }
}

$maxChars = $lyricGuidelines['maxCharacters'] ?? 1200;
$systemPrompt .= "\n\nIMPORTANT: Keep total lyrics under {$maxChars} characters (for Suno compatibility).";
$systemPrompt .= "\n\nFormat your lyrics with clear sections: [Verse 1], [Chorus], [Verse 2], [Bridge], etc.";

// Build user prompt
$userPrompt = "Write lyrics for a song titled \"{$title}\"";
if (!empty($theme)) {
    $userPrompt .= " with the theme/vibe of: {$theme}";
}
if (!empty($customInstructions)) {
    $userPrompt .= "\n\nAdditional instructions: {$customInstructions}";
}

if ($alternatives > 1) {
    $userPrompt .= "\n\nProvide {$alternatives} different versions, each with a distinct approach. Separate each version with '---VERSION---' on its own line.";
}

// Call OpenAI API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $openaiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'gpt-4o',
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt]
    ],
    'max_tokens' => 2000,
    'temperature' => 0.8
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'API request failed: ' . $curlError]);
    exit;
}

$result = json_decode($response, true);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode([
        'error' => $result['error']['message'] ?? 'OpenAI API error',
        'details' => $result
    ]);
    exit;
}

$generatedContent = $result['choices'][0]['message']['content'] ?? '';

// Parse alternatives if requested
$versions = [$generatedContent];
if ($alternatives > 1 && strpos($generatedContent, '---VERSION---') !== false) {
    $versions = array_map('trim', explode('---VERSION---', $generatedContent));
    $versions = array_filter($versions); // Remove empty entries
}

// Calculate character count for each version
$versionsWithMeta = [];
foreach ($versions as $i => $lyrics) {
    $versionsWithMeta[] = [
        'version' => $i + 1,
        'lyrics' => $lyrics,
        'characterCount' => strlen($lyrics),
        'withinLimit' => strlen($lyrics) <= $maxChars
    ];
}

echo json_encode([
    'success' => true,
    'title' => $title,
    'theme' => $theme,
    'versions' => $versionsWithMeta,
    'characterLimit' => $maxChars,
    'usage' => [
        'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
        'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
        'total_tokens' => $result['usage']['total_tokens'] ?? 0
    ]
], JSON_PRETTY_PRINT);
