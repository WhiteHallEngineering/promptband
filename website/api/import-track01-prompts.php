<?php
/**
 * Import prompts for Track 01 from NO_SKIN_TO_TOUCH_VIDEO_PLAN.md
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$validKey = 'pr0mpt-m3ss4g3s-2026';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// Read the video plan file
$planFile = dirname(__DIR__) . '/NO_SKIN_TO_TOUCH_VIDEO_PLAN.md';
if (!file_exists($planFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Video plan file not found']);
    exit;
}

$planContent = file_get_contents($planFile);

// Parse prompts from video plan
// Format: **Clip N - "Name"**
// - Prompt: "the prompt text"
$prompts = [];

preg_match_all('/\*\*Clip\s*(\d+)\s*[-–—]\s*"([^"]+)"\*\*.*?- Prompt:\s*"([^"]+)"/s',
    $planContent, $matches, PREG_SET_ORDER);

foreach ($matches as $m) {
    $clipNum = intval($m[1]);
    $clipName = strtolower(trim($m[2]));
    $prompt = trim($m[3]);
    $prompts[$clipName] = $prompt;

    // Also store by number for fallback matching
    $prompts["clip_$clipNum"] = [
        'name' => trim($m[2]),
        'prompt' => $prompt
    ];
}

// Load existing EDL data
$edlFile = __DIR__ . '/../analytics/edl-data.json';
if (!file_exists($edlFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'EDL data file not found']);
    exit;
}

$edlData = json_decode(file_get_contents($edlFile), true);

if (!isset($edlData['1'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Track 1 not found in EDL data']);
    exit;
}

// Manual name mappings (EDL name => Video plan name)
$nameMap = [
    'jax awakening' => 'awakening',
    'jax-b reaching' => 'define',
    'zero and one' => 'counting droplets',
    'jax overload' => 'circuits overload',
    'hands hook' => 'no skin to touch',
    'hands' => 'no skin to touch',
    'shape of name' => 'shape of your name',
    'hurt' => 'hurt like a cut',
    'b: hurt alt' => 'hurt like a cut',
    'hurt alt' => 'hurt like a cut',
    'hypnos keys' => 'stereo loud',
    'hypnos-crescendo' => 'stereo loud',
    'crowd' => 'moving crowd',
    'gene solo' => 'miss the shiver',
    'gene-shred-sequence' => 'miss the shiver',
    'gene-b headbang' => 'band together',
    'hands closer' => 'no skin (reaching)',
    'jax-scream' => 'circuits burning',
    'circuit heart' => 'logic calling love',
    'jax tear' => 'coded to hurt',
    'jax glitch' => 'glitch storm',
    'heartbreak/lag' => 'heartbreak/lag',
    'unit-808 drums' => 'unit-808 power',
    'unit808-power' => 'unit-808 power',
    'hands desperate' => 'no skin (desperate)',
    'shape of pain' => 'shape of pain',
    'synoise bass' => 'synoise waves',
    'synoise-groove' => 'synoise waves',
    'final reach' => 'no skin (final reach)',
    'coded to want' => 'coded to want',
    'band crescendo' => 'band crescendo',
];

// Apply prompts to track 01 clips
$applied = 0;
$notFound = [];

foreach ($edlData['1']['segments'] as &$segment) {
    foreach ($segment['clips'] as &$clip) {
        if (!empty($clip['prompt'])) {
            continue; // Already has prompt
        }

        $clipName = strtolower(trim($clip['name']));

        // Try manual mapping first
        $mappedName = $nameMap[$clipName] ?? null;
        if ($mappedName && isset($prompts[$mappedName])) {
            $clip['prompt'] = $prompts[$mappedName];
            $clip['status'] = 'has-prompt';
            $applied++;
            continue;
        }

        // Try exact match
        if (isset($prompts[$clipName])) {
            $clip['prompt'] = $prompts[$clipName];
            $clip['status'] = 'has-prompt';
            $applied++;
            continue;
        }

        // Try fuzzy matching
        $matched = false;
        foreach ($prompts as $pName => $pData) {
            if (strpos($pName, 'clip_') === 0) continue;

            if (strpos($clipName, $pName) !== false ||
                strpos($pName, $clipName) !== false) {
                $clip['prompt'] = is_array($pData) ? $pData['prompt'] : $pData;
                $clip['status'] = 'has-prompt';
                $applied++;
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            // Special case for "Hands Hook" - the key visual hook
            if (strpos($clipName, 'hands') !== false) {
                $clip['prompt'] = "Two glowing blue digital hands reaching toward each other, electric sparks and plasma energy crackling between fingertips but never connecting, intense emotional moment, dramatic lighting, the gap between them glowing with frustrated energy, 4K cinematic";
                $clip['status'] = 'has-prompt';
                $applied++;
            } else {
                $notFound[] = $clip['name'];
            }
        }
    }
}
unset($segment, $clip);

// Update timestamp
$edlData['1']['updatedAt'] = date('c');
$edlData['1']['promptsImportedFrom'] = 'NO_SKIN_TO_TOUCH_VIDEO_PLAN.md';

// Save updated data
file_put_contents($edlFile, json_encode($edlData, JSON_PRETTY_PRINT));

// Backup
$backupDir = dirname($edlFile) . '/edl-backups';
if (!file_exists($backupDir)) mkdir($backupDir, 0755, true);
copy($edlFile, $backupDir . '/edl-track01-import-' . date('Y-m-d-His') . '.json');

echo json_encode([
    'success' => true,
    'prompts_found' => count($prompts) / 2, // Divide by 2 because we store each twice
    'prompts_applied' => $applied,
    'clips_without_prompts' => $notFound
], JSON_PRETTY_PRINT);
