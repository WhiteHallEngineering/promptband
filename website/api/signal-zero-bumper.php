<?php
/**
 * Signal 0 Radio — Generate DJ Bumper
 * Creates TTS audio bumpers via ElevenLabs API
 *
 * POST: Generate a bumper (station-id, show-intro, band-intro, song-outro, ad-read, time-check)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$password = $_GET['key'] ?? '';
$validKey = 'pr0mpt-m3ss4g3s-2026';

if ($password !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$djSlug = $input['djSlug'] ?? '';
$type = $input['type'] ?? '';
$text = $input['text'] ?? '';
$voiceId = $input['voiceId'] ?? '';

// Validate required fields
if (empty($djSlug) || empty($type) || empty($text) || empty($voiceId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Required fields: djSlug, type, text, voiceId']);
    exit;
}

// Validate bumper type
$validTypes = ['station-id', 'show-intro', 'band-intro', 'song-outro', 'ad-read', 'time-check', 'news-bulletin'];
if (!in_array($type, $validTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid type. Use: ' . implode(', ', $validTypes)]);
    exit;
}

// Load ElevenLabs API key from social-config.php, with hardcoded fallback
$elevenLabsKey = null;
$configFile = __DIR__ . '/social-config.php';
if (file_exists($configFile)) {
    $config = require $configFile;
    $elevenLabsKey = $config['elevenlabs']['api_key'] ?? null;
}
if (empty($elevenLabsKey)) {
    $elevenLabsKey = 'sk_77a5b05b69ce0075754b9d3c660a6cfd9735c90b6e697b85';
}

// Call ElevenLabs TTS API
$ttsUrl = 'https://api.elevenlabs.io/v1/text-to-speech/' . urlencode($voiceId);

$ttsBody = json_encode([
    'text' => $text,
    'model_id' => 'eleven_v3',
    'voice_settings' => [
        'stability' => 0.5,
        'similarity_boost' => 0.75
    ]
]);

$ch = curl_init($ttsUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $ttsBody,
    CURLOPT_HTTPHEADER => [
        'xi-api-key: ' . $elevenLabsKey,
        'Content-Type: application/json',
        'Accept: audio/mpeg'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60
]);

$audioData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'ElevenLabs request failed', 'detail' => $curlError]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code(502);
    // Try to decode error response from ElevenLabs
    $errorBody = json_decode($audioData, true);
    echo json_encode([
        'error' => 'ElevenLabs returned HTTP ' . $httpCode,
        'detail' => $errorBody ?? $audioData
    ]);
    exit;
}

if (empty($audioData)) {
    http_response_code(502);
    echo json_encode(['error' => 'ElevenLabs returned empty response']);
    exit;
}

// Generate bumper ID
$bumperId = $type . '-' . time();

// Create directory structure if needed
$bumperDir = __DIR__ . '/../audio/signal-zero/bumpers/' . $djSlug;
if (!is_dir($bumperDir)) {
    mkdir($bumperDir, 0755, true);
}

// Save MP3 file
$mp3Path = $bumperDir . '/' . $bumperId . '.mp3';
$bytesWritten = file_put_contents($mp3Path, $audioData);

if ($bytesWritten === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save audio file', 'path' => $mp3Path]);
    exit;
}

// Build bumper metadata
$bumper = [
    'id' => $bumperId,
    'djSlug' => $djSlug,
    'type' => $type,
    'text' => $text,
    'voiceId' => $voiceId,
    'audioUrl' => '/audio/signal-zero/bumpers/' . $djSlug . '/' . $bumperId . '.mp3',
    'fileSize' => $bytesWritten,
    'createdAt' => date('c')
];

// Append to bumpers JSON file
$bumpersFile = __DIR__ . '/signal-zero-bumpers.json';
$bumpers = [];
if (file_exists($bumpersFile)) {
    $bumpers = json_decode(file_get_contents($bumpersFile), true) ?: [];
}
$bumpers[] = $bumper;
file_put_contents($bumpersFile, json_encode($bumpers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'success' => true,
    'bumper' => $bumper
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
