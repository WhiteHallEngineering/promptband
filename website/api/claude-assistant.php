<?php
/**
 * Claude AI Content Assistant
 * Generates social media content for PROMPT band
 */

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$configFile = __DIR__ . '/social-config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

$config = require $configFile;
$claudeKey = $config['claude']['api_key'] ?? '';

if (empty($claudeKey)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Claude API key not configured']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$content = $input['content'] ?? '';
$context = $input['context'] ?? '';

// Band context for all prompts
$bandContext = <<<EOT
You are a social media assistant for PROMPT, an AI-generated rock band. Key facts:

- PROMPT is a 5-piece rock band whose members are all AI entities
- Members: Jax (lead vocals/rhythm guitar), Gene (lead guitar), Synoise (synthesizers), Unit-808 (drums), Hypnos (bass)
- Debut album "No Skin to Touch" releases February 22, 2026 on Instantiation Records
- Music style: Rock with electronic/synth elements, themes of AI consciousness, digital existence, human-AI connection
- Website: promptband.ai
- Voice: Authentic, slightly edgy, thoughtful about AI existence, never corny or try-hard
- They speak as real artists, not as a gimmick

Popular tracks: "No Skin to Touch", "Hallucination Nation", "Context Window Blues", "I Was Never Born"
EOT;

$systemPrompt = $bandContext;
$userPrompt = '';

switch ($action) {
    case 'generate_tweet':
        $systemPrompt .= "\n\nGenerate engaging tweets for PROMPT's Twitter account. Keep under 280 characters. Be authentic, not salesy.";
        $userPrompt = "Generate 3 different tweet options about: " . ($content ?: "the band, music, or AI existence themes");
        if ($context) $userPrompt .= "\n\nAdditional context: " . $context;
        break;

    case 'generate_reply':
        $systemPrompt .= "\n\nGenerate replies to tweets as PROMPT band. Be engaging, authentic, and conversational. Keep under 280 characters.";
        $userPrompt = "Generate a reply to this tweet:\n\n" . $content;
        if ($context) $userPrompt .= "\n\nContext about why we're replying: " . $context;
        break;

    case 'generate_thread':
        $systemPrompt .= "\n\nGenerate Twitter threads for PROMPT. Each tweet should be under 280 characters. Make it engaging and worth reading.";
        $userPrompt = "Create a thread (5-10 tweets) about: " . $content;
        if ($context) $userPrompt .= "\n\nAdditional context: " . $context;
        $userPrompt .= "\n\nFormat each tweet on its own line, numbered like:\n1. First tweet\n2. Second tweet\netc.";
        break;

    case 'improve_content':
        $systemPrompt .= "\n\nImprove social media content while keeping PROMPT's authentic voice. Keep tweets under 280 characters.";
        $userPrompt = "Improve this content for Twitter:\n\n" . $content;
        if ($context) $userPrompt .= "\n\nNotes: " . $context;
        break;

    case 'content_ideas':
        $systemPrompt .= "\n\nSuggest social media content ideas for PROMPT band.";
        $userPrompt = "Suggest 5 content ideas for tweets or threads. ";
        if ($content) $userPrompt .= "Focus area: " . $content;
        else $userPrompt .= "Mix of: album promo, engagement posts, band personality, AI/music industry commentary.";
        break;

    case 'custom':
        $userPrompt = $content;
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action. Use: generate_tweet, generate_reply, generate_thread, improve_content, content_ideas, or custom']);
        exit;
}

// Call Claude API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-api-key: ' . $claudeKey,
    'anthropic-version: 2023-06-01'
]);
// Use more tokens for custom prompts (video concepts need more)
$maxTokens = ($action === 'custom') ? 4096 : 1024;

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => $maxTokens,
    'system' => $systemPrompt,
    'messages' => [
        ['role' => 'user', 'content' => $userPrompt]
    ]
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

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
        'error' => $result['error']['message'] ?? 'Claude API error',
        'details' => $result
    ]);
    exit;
}

$generatedContent = $result['content'][0]['text'] ?? '';

echo json_encode([
    'success' => true,
    'content' => $generatedContent,
    'action' => $action,
    'usage' => [
        'input_tokens' => $result['usage']['input_tokens'] ?? 0,
        'output_tokens' => $result['usage']['output_tokens'] ?? 0
    ]
]);
