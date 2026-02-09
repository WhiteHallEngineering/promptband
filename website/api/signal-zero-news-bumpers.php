<?php
/**
 * Signal 0 Radio — Automated Space News Bumpers
 * Fetches real space/astronomy news, rewrites as galactic lore via OpenAI,
 * generates TTS audio via ElevenLabs (The Archivist voice).
 *
 * POST: Generate news bumpers
 *   ?count=3    Number of bulletins to generate (default 3)
 *   ?dryrun=1   Text only, no audio generation
 *
 * GET: View generation history
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// History file for deduplication
$historyFile = __DIR__ . '/signal-zero-news-history.json';

// GET: return history
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $history = [];
    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true) ?: [];
    }
    echo json_encode([
        'totalGenerated' => count($history),
        'history' => array_slice(array_reverse($history), 0, 50)
    ], JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST or GET required']);
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

$count = max(1, min(5, intval($_GET['count'] ?? 3)));
$dryrun = isset($_GET['dryrun']) && $_GET['dryrun'] === '1';

// ---- Step 1: Fetch news from APIs ----

$headlines = [];

// Spaceflight News API (free, no auth)
$sfnUrl = 'https://api.spaceflightnewsapi.net/v4/articles/?limit=10&ordering=-published_at';
$ch = curl_init($sfnUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Accept: application/json']
]);
$sfnResponse = curl_exec($ch);
$sfnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($sfnCode === 200 && $sfnResponse) {
    $sfnData = json_decode($sfnResponse, true);
    if (!empty($sfnData['results'])) {
        foreach ($sfnData['results'] as $article) {
            $headlines[] = [
                'source' => 'spaceflight-news',
                'title' => $article['title'],
                'summary' => $article['summary'] ?? '',
                'url' => $article['url'] ?? '',
                'published' => $article['published_at'] ?? ''
            ];
        }
    }
}

// NASA APOD (free with DEMO_KEY)
$apodUrl = 'https://api.nasa.gov/planetary/apod?api_key=DEMO_KEY';
$ch = curl_init($apodUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Accept: application/json']
]);
$apodResponse = curl_exec($ch);
$apodCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($apodCode === 200 && $apodResponse) {
    $apod = json_decode($apodResponse, true);
    if (!empty($apod['title'])) {
        $headlines[] = [
            'source' => 'nasa-apod',
            'title' => $apod['title'],
            'summary' => $apod['explanation'] ?? '',
            'url' => $apod['url'] ?? '',
            'published' => $apod['date'] ?? ''
        ];
    }
}

if (empty($headlines)) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch any news headlines']);
    exit;
}

// ---- Step 2: Deduplicate against history ----

$history = [];
if (file_exists($historyFile)) {
    $history = json_decode(file_get_contents($historyFile), true) ?: [];
}

$usedTitles = array_column($history, 'originalTitle');
$newHeadlines = array_filter($headlines, function($h) use ($usedTitles) {
    return !in_array($h['title'], $usedTitles);
});
$newHeadlines = array_values($newHeadlines);

if (empty($newHeadlines)) {
    echo json_encode([
        'success' => true,
        'message' => 'No new headlines to process (all already used)',
        'bumpers' => [],
        'totalAvailable' => count($headlines),
        'totalNew' => 0
    ]);
    exit;
}

// Select up to $count headlines
$selected = array_slice($newHeadlines, 0, $count);

// ---- Step 3: OpenAI rewrite to galactic lore ----

$headlineList = '';
foreach ($selected as $i => $h) {
    $num = $i + 1;
    // Truncate summary to 200 chars to keep prompt manageable
    $summary = mb_substr($h['summary'], 0, 200);
    $headlineList .= "{$num}. {$h['title']}\n   Context: {$summary}\n\n";
}

$systemPrompt = <<<'PROMPT'
You are the news desk writer for Signal 0 Radio, a galactic rock radio station broadcasting across the cosmos. You rewrite real space and astronomy news into in-universe galactic news bulletins.

TRANSLATION RULES:
- NASA → "Core Worlds Science Directorate"
- SpaceX, Rocket Lab, or any rocket company → "Tachyon Transit" or other freight/transit company names
- Mars missions → "Mars Colony" operations
- ISS / space station → "Hub Station 7"
- Moon / lunar → "Luna Base" or "the Lunar Installations"
- Earth → "Old Earth" or "the Origin World"
- James Webb / Hubble / telescopes → "Deep Array scanners" or "the Signal Grid"
- Astronauts → "void-walkers" or "station crew"
- Satellites → "relay nodes" or "comm buoys"
- Any specific company or institution → invent a galactic equivalent

BULLETIN FORMAT:
- Start with a location tag in brackets, e.g. [Hub Station 7], [Mars Colony], [Core Worlds]
- 2-3 sentences, 40-70 words total
- End with: "This has been a Signal 0 news bulletin."
- Tone: factual but with a sense of wonder. The Archivist is precise and measured.
- Do NOT use the word "breaking" — Signal 0 doesn't do hype.

Return ONLY valid JSON: an array of objects with "index" (1-based) and "bulletin" (the text).
PROMPT;

$userPrompt = "Rewrite these {$count} real headlines into Signal 0 Radio galactic news bulletins:\n\n{$headlineList}";

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        'max_tokens' => 800,
        'temperature' => 0.8,
        'response_format' => ['type' => 'json_object']
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60
]);

$openaiResponse = curl_exec($ch);
$openaiCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$openaiError = curl_error($ch);
curl_close($ch);

if ($openaiError) {
    http_response_code(500);
    echo json_encode(['error' => 'OpenAI request failed', 'detail' => $openaiError]);
    exit;
}

if ($openaiCode !== 200) {
    $errBody = json_decode($openaiResponse, true);
    http_response_code(502);
    echo json_encode([
        'error' => 'OpenAI returned HTTP ' . $openaiCode,
        'detail' => $errBody['error']['message'] ?? $openaiResponse
    ]);
    exit;
}

$openaiResult = json_decode($openaiResponse, true);
$content = $openaiResult['choices'][0]['message']['content'] ?? '';
$parsed = json_decode($content, true);


// Handle various JSON structures OpenAI might return
$bulletins = [];
if (is_array($parsed)) {
    // Try common wrapper keys
    foreach (['bulletins', 'news_bulletins', 'results', 'items'] as $key) {
        if (isset($parsed[$key]) && is_array($parsed[$key])) {
            $bulletins = $parsed[$key];
            break;
        }
    }
    // If no wrapper key found, check if it's already an indexed array
    if (empty($bulletins)) {
        if (isset($parsed[0])) {
            $bulletins = $parsed;
        } elseif (isset($parsed['index']) || isset($parsed['bulletin'])) {
            // Single object
            $bulletins = [$parsed];
        } else {
            // Try all numeric-ish keys like "1", "2", etc.
            $numbered = [];
            foreach ($parsed as $k => $v) {
                if (is_array($v) && (isset($v['bulletin']) || isset($v['text']))) {
                    $numbered[] = $v;
                }
            }
            if (!empty($numbered)) {
                $bulletins = $numbered;
            }
        }
    }
}

if (empty($bulletins)) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Failed to parse OpenAI response',
        'raw' => $content
    ]);
    exit;
}

// ---- Step 4: Generate TTS audio for each bulletin ----

$results = [];
$archivist = 'Xb7hH8MSUJpSbSDYk0k2';  // The Archivist voice

foreach ($bulletins as $i => $b) {
    $bulletinText = $b['bulletin'] ?? $b['text'] ?? '';
    if (empty($bulletinText)) continue;

    $idx = ($b['index'] ?? $i + 1) - 1;
    $headline = $selected[$idx] ?? $selected[$i] ?? $selected[0];

    $result = [
        'originalTitle' => $headline['title'],
        'source' => $headline['source'],
        'bulletin' => $bulletinText,
        'characterCount' => strlen($bulletinText),
        'generatedAt' => date('c')
    ];

    if (!$dryrun) {
        // Call signal-zero-bumper.php internally via curl to localhost
        $bumperUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST']
            . '/api/signal-zero-bumper.php?key=' . $validKey;

        $ch = curl_init($bumperUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'djSlug' => 'the-archivist',
                'type' => 'news-bulletin',
                'text' => $bulletinText,
                'voiceId' => $archivist
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: humans_21909=1'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60
        ]);

        $bumperResponse = curl_exec($ch);
        $bumperCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $bumperResult = json_decode($bumperResponse, true);

        if ($bumperCode === 200 && !empty($bumperResult['success'])) {
            $result['audioUrl'] = $bumperResult['bumper']['audioUrl'] ?? null;
            $result['bumperId'] = $bumperResult['bumper']['id'] ?? null;
            $result['audioGenerated'] = true;
        } else {
            $result['audioGenerated'] = false;
            $result['audioError'] = $bumperResult['error'] ?? 'HTTP ' . $bumperCode;
        }
    } else {
        $result['audioGenerated'] = false;
        $result['dryrun'] = true;
    }

    $results[] = $result;

    // Save to history
    $history[] = [
        'originalTitle' => $headline['title'],
        'source' => $headline['source'],
        'bulletin' => $bulletinText,
        'audioUrl' => $result['audioUrl'] ?? null,
        'bumperId' => $result['bumperId'] ?? null,
        'generatedAt' => date('c')
    ];
}

// Save updated history
file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Usage stats from OpenAI
$usage = [
    'prompt_tokens' => $openaiResult['usage']['prompt_tokens'] ?? 0,
    'completion_tokens' => $openaiResult['usage']['completion_tokens'] ?? 0,
    'total_tokens' => $openaiResult['usage']['total_tokens'] ?? 0
];

echo json_encode([
    'success' => true,
    'bumpers' => $results,
    'totalAvailable' => count($headlines),
    'totalNew' => count($newHeadlines),
    'totalGenerated' => count($results),
    'dryrun' => $dryrun,
    'openaiUsage' => $usage
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
