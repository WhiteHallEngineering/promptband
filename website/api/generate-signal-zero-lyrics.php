<?php
/**
 * Signal 0 Radio — Generate Lyrics
 * Uses OpenAI GPT-4o to generate lyrics with per-band style context
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

$songId = $input['songId'] ?? '';
$bandSlug = $input['bandSlug'] ?? '';
$customInstructions = trim($input['customInstructions'] ?? '');

if (empty($songId) || empty($bandSlug)) {
    http_response_code(400);
    echo json_encode(['error' => 'songId and bandSlug are required']);
    exit;
}

// Load band and song data
$bandsFile = __DIR__ . '/signal-zero-bands.json';
$songsFile = __DIR__ . '/signal-zero-songs.json';

if (!file_exists($bandsFile) || !file_exists($songsFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Data files not found. Run import first.']);
    exit;
}

$bands = json_decode(file_get_contents($bandsFile), true) ?: [];
$songs = json_decode(file_get_contents($songsFile), true) ?: [];

// Find band
$band = null;
foreach ($bands as $b) {
    if ($b['slug'] === $bandSlug) {
        $band = $b;
        break;
    }
}

if (!$band) {
    http_response_code(404);
    echo json_encode(['error' => 'Band not found: ' . $bandSlug]);
    exit;
}

// Find song
$song = null;
foreach ($songs as $s) {
    if ($s['id'] === $songId) {
        $song = $s;
        break;
    }
}

if (!$song) {
    http_response_code(404);
    echo json_encode(['error' => 'Song not found: ' . $songId]);
    exit;
}

// Region context map — sonic/thematic descriptions from station-bible.md Section 7
$regionContext = [
    'Core Worlds' => 'Polished, anthemic, big-production. Arena rock, power pop, classic rock, glam. Music from wealthy, stable worlds with access to the best studios. Slick and confident — critics call it sanitized, fans call it professional.',
    'Mid-Rim Colonies' => 'Honest, gritty, blue-collar. Blues rock, hard rock, southern rock, garage. Songs about work, fatigue, pride, and the dream of something better. Guitars are loud because the factories are louder.',
    'Outer Territories' => 'Raw, aggressive, desperate. Punk, hardcore, thrash, garage punk. Where civilization thins out and survival gets personal. Bands play fast because life is short. Equipment is scavenged and modified. DIY because there is no other option.',
    'The Outer Territories' => 'Raw, aggressive, desperate. Punk, hardcore, thrash, garage punk. Where civilization thins out and survival gets personal. Bands play fast because life is short. Equipment is scavenged and modified. DIY because there is no other option.',
    'Kepler Void' => 'Spacious, hypnotic, lonely. Space rock, psychedelic, ambient rock, experimental. The emptiness and silence gets inside your music. Slow, reverb-drenched, meditative rock that sounds like staring into infinity.',
    'The Kepler Void' => 'Spacious, hypnotic, lonely. Space rock, psychedelic, ambient rock, experimental. The emptiness and silence gets inside your music. Slow, reverb-drenched, meditative rock that sounds like staring into infinity.',
    'Gas Giant Moons' => 'Heavy. Crushing. Slow. Doom, sludge, stoner rock, heavy blues. Everything is heavier at 1.5G — body, tools, guitar strings. Low tunings, slow tempos, monolithic riffs. Living under a planet that could swallow your world gives you perspective.',
    'The Asteroid Belts' => 'Fast, chaotic, communal. Punk, ska-punk, pop punk, party rock. Close quarters, shared resources, constant motion. Music designed for tiny venues and zero-G mosh pits. Resourceful musicians trading instruments between rocks.',
    'Asteroid Belts' => 'Fast, chaotic, communal. Punk, ska-punk, pop punk, party rock. Close quarters, shared resources, constant motion. Music designed for tiny venues and zero-G mosh pits. Resourceful musicians trading instruments between rocks.',
    'Nebula Regions' => 'Atmospheric, gorgeous, hazy. Shoegaze, dream pop, post-rock, ethereal. Living inside color — constant, diffuse, surreal light. Layers of sound, walls of reverb, vocals that disappear into texture. Rock music that wants to become light.',
    'Binary & Trinary Star Systems' => 'Complex, intellectual, layered. Progressive rock, math rock, jazz fusion, art rock. Two suns dancing in complex orbital patterns make your sense of rhythm interesting. Odd time signatures by instinct. Cerebral, technically demanding, structurally ambitious.',
    'Dying Star Systems' => 'Heavy, sorrowful, defiant. Doom metal, gothic rock, post-metal, darkwave. When your sun is dying, everything becomes about time. Music that stares directly at mortality with fierce, terrible beauty. Every performance could be the last.',
    'Generation Ships' => 'Folk-influenced, narrative, tradition-rich. Folk rock, indie rock, acoustic storytelling. Born, live, and die without seeing your destination. Music is how history survives. Songs passed down, modified, reinterpreted. Younger generations push back with punk and noise.',
    'Ai Hubs & Digital Realms' => 'Electronic-influenced, processed, uncanny. Electronic rock, industrial, synthwave, glitch rock. AI musicians experience reality differently — time, space, sensation mediated through code. Rock music played by beings who learned it from data but feel it in their own way.',
    'AI Hubs & Digital Realms' => 'Electronic-influenced, processed, uncanny. Electronic rock, industrial, synthwave, glitch rock. AI musicians experience reality differently — time, space, sensation mediated through code. Rock music played by beings who learned it from data but feel it in their own way.',
    'Rogue Planets & Deep Space' => 'Dark, extreme, isolated. Black metal, drone, dark ambient rock, post-metal. Permanent darkness — no sun, no dawn, no seasons. Insular, hardy, strange communities. Music extreme by default.',
    'Trade Routes & Nomadic Bands' => 'Eclectic, adaptable, road-worn. Rock and roll, blues rock, party rock. Touring bands on ships, playing every port, never staying long. Absorb influences from every region, creating hybrid sounds that belong to no single world.'
];

// Build system prompt with band context
$regionDesc = $regionContext[$band['region']] ?? 'A unique corner of the galaxy with its own musical traditions.';

$systemPrompt = "You are a songwriter for {$band['name']}, a rock band from {$band['origin']} in the {$band['region']}.

BAND IDENTITY:
{$band['description']}

MUSICAL STYLE: {$band['style']}

REGION CHARACTER:
{$regionDesc}

Write lyrics for a song titled \"{$song['title']}\".

RULES:
- Write in the voice and perspective authentic to this band's identity
- Use [Verse 1], [Chorus], [Bridge] etc. section markers for Suno compatibility
- CRITICAL: Total output MUST be under 1200 characters including section markers and line breaks. Count carefully. Aim for 900-1100 characters. This is a hard limit — Suno will truncate anything over 1200.
- Keep it tight: 2 verses, 1 chorus (repeated), 1 bridge max. No outros or long intros.
- Do NOT add trailing spaces at end of lines
- The lyrics should feel like they could only come from a band with this specific origin and style
- No generic rock clichés — make the lyrics specific to the band's world and experience
- Do NOT include the song title as a header — jump straight into the section markers";

// Build user prompt
$userPrompt = "Write lyrics for \"{$song['title']}\" by {$band['name']}.";

if (!empty($customInstructions)) {
    $userPrompt .= "\n\nAdditional instructions: {$customInstructions}";
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
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt]
    ],
    'max_tokens' => 1500,
    'temperature' => 0.85
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

$versionsWithMeta = [[
    'version' => 1,
    'lyrics' => $generatedContent,
    'characterCount' => strlen($generatedContent),
    'withinLimit' => strlen($generatedContent) <= 1200
]];

echo json_encode([
    'success' => true,
    'songId' => $songId,
    'bandSlug' => $bandSlug,
    'title' => $song['title'],
    'bandName' => $band['name'],
    'versions' => $versionsWithMeta,
    'characterLimit' => 1200,
    'usage' => [
        'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
        'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
        'total_tokens' => $result['usage']['total_tokens'] ?? 0
    ]
], JSON_PRETTY_PRINT);
