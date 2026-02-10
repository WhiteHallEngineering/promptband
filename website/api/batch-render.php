<?php
// Batch render script for Your Data or Mine (Full LTX version)
// Submits all renders and saves prediction IDs for polling

set_time_limit(600);
header('Content-Type: application/json');

$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? '';
$action = $_GET['action'] ?? 'submit'; // submit, status, apply

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid key']);
    exit;
}

$configFile = __DIR__ . '/social-config.php';
$config = require $configFile;
$replicateToken = $config['replicate']['api_token'] ?? '';

$edlFile = __DIR__ . '/../analytics/edl-data.json';
$batchFile = __DIR__ . '/../analytics/batch-render-status.json';

// Load EDL
$edlData = json_decode(file_get_contents($edlFile), true);
$track = $edlData['no-skin-to-touch-2'] ?? null;
if (!$track) die(json_encode(['error' => 'Track not found']));

$segments = $track['segments'] ?? [];

// Helper: read local image as data URI
function imageToDataUri($imgUrl) {
    $parsed = parse_url($imgUrl);
    $localPath = $_SERVER['DOCUMENT_ROOT'] . ($parsed['path'] ?? $imgUrl);
    if (!file_exists($localPath)) {
        // Try without document root (relative path)
        $localPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($imgUrl, '/');
    }
    if (!file_exists($localPath)) return null;
    $data = file_get_contents($localPath);
    $mime = mime_content_type($localPath) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

// Helper: submit to Replicate
function submitReplicate($token, $version, $input) {
    $ch = curl_init('https://api.replicate.com/v1/predictions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'version' => $version,
        'input' => $input
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $result = json_decode($response, true);
    return ['http' => $httpCode, 'id' => $result['id'] ?? null, 'status' => $result['status'] ?? 'error', 'error' => $result['detail'] ?? $result['error'] ?? null];
}

// Helper: check prediction status
function checkPrediction($token, $predictionId) {
    $ch = curl_init('https://api.replicate.com/v1/predictions/' . $predictionId);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Token ' . $token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// KLING version
$KLING_VERSION = '974c9c5bc69f8f9c178ddea80d8936ba46c48081ad6b6ccca8843d44010c0642';
// LTX version
$LTX_VERSION = '8c47da666861d081eeb4d1261853087de23923a268a69b63febdf5dc1dee08e4';

// Define the render plan - Full LTX version (all 84 clips)
$renderPlan = [];

// Dynamically build plan from all clips in all acts
$actKeys = ['act1', 'act2', 'act3', 'act4'];
foreach ($actKeys as $ak) {
    foreach ($segments as $seg) {
        if ($seg['actKey'] !== $ak) continue;
        $clipCount = count($seg['clips'] ?? []);
        for ($i = 0; $i < $clipCount; $i++) {
            if (!empty($seg['clips'][$i]['imageUrl'])) {
                $renderPlan[] = ['act' => $ak, 'clips' => [$i], 'model' => 'ltx'];
            }
        }
    }
}

// ============================================================
// ACTION: SUBMIT
// ============================================================
if ($action === 'submit') {
    $results = [];
    $submitted = 0;
    $errors = 0;

    foreach ($renderPlan as $i => $plan) {
        $actKey = $plan['act'];
        $seg = null;
        foreach ($segments as $s) {
            if ($s['actKey'] === $actKey) { $seg = $s; break; }
        }
        if (!$seg) { $results[] = ['error' => "Segment $actKey not found"]; continue; }

        $clipIndices = $plan['clips'];
        $model = $plan['model'];
        $clip1 = $seg['clips'][$clipIndices[0]] ?? null;
        $clip2 = (count($clipIndices) > 1) ? ($seg['clips'][$clipIndices[1]] ?? null) : null;

        if (!$clip1 || !$clip1['imageUrl']) {
            $results[] = ['error' => "Clip {$clipIndices[0]} in $actKey has no image"];
            $errors++;
            continue;
        }

        $img1 = imageToDataUri($clip1['imageUrl']);
        if (!$img1) {
            $results[] = ['error' => "Could not read image for {$clip1['name']}"];
            $errors++;
            continue;
        }

        $prompt = ($clip1['imagePrompt'] ?? $clip1['description'] ?? $clip1['name'])
            . ' Cinematic motion, neon magenta and cyan lighting, cyberpunk atmosphere.';

        $desc = $clip1['name'] . ($clip2 ? ' → ' . $clip2['name'] : '');

        if ($model === 'kling10' && $clip2) {
            // Kling 10s pair with start + end image
            $img2 = imageToDataUri($clip2['imageUrl']);
            if (!$img2) {
                $results[] = ['error' => "Could not read end image for {$clip2['name']}"];
                $errors++;
                continue;
            }
            $result = submitReplicate($replicateToken, $KLING_VERSION, [
                'prompt' => $prompt,
                'start_image' => $img1,
                'end_image' => $img2,
                'duration' => 10,
                'cfg_scale' => 0.5,
                'negative_prompt' => 'blurry, low quality, distorted faces, text, watermark'
            ]);
        } elseif ($model === 'kling5') {
            // Kling 5s single
            $result = submitReplicate($replicateToken, $KLING_VERSION, [
                'prompt' => $prompt,
                'start_image' => $img1,
                'duration' => 5,
                'cfg_scale' => 0.5,
                'negative_prompt' => 'blurry, low quality, distorted faces, text, watermark'
            ]);
        } else {
            // LTX single
            $result = submitReplicate($replicateToken, $LTX_VERSION, [
                'prompt' => $prompt,
                'image' => $img1,
                'image_noise_scale' => 0.15,
                'target_size' => 768,
                'aspect_ratio' => '16:9',
                'cfg' => 3,
                'steps' => 30,
                'length' => 129,
                'negative_prompt' => 'low quality, worst quality, deformed, distorted, blurry, text, watermark'
            ]);
        }

        if ($result['id']) {
            $submitted++;
        } else {
            $errors++;
        }

        $results[] = [
            'desc' => $desc,
            'model' => $model,
            'act' => $actKey,
            'clipIndices' => $clipIndices,
            'predictionId' => $result['id'],
            'status' => $result['status'],
            'error' => $result['error']
        ];

        // Delay to avoid rate limiting
        sleep(2);
    }

    // Save batch status
    file_put_contents($batchFile, json_encode([
        'createdAt' => date('c'),
        'submitted' => $submitted,
        'errors' => $errors,
        'renders' => $results
    ], JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'submitted' => $submitted,
        'errors' => $errors,
        'message' => "Submitted $submitted renders ($errors errors). Poll with ?action=status"
    ]);
}

// ============================================================
// ACTION: STATUS - Check all predictions
// ============================================================
elseif ($action === 'status') {
    if (!file_exists($batchFile)) {
        echo json_encode(['error' => 'No batch found. Submit first.']);
        exit;
    }

    $batch = json_decode(file_get_contents($batchFile), true);
    if (!isset($batch['renders'])) $batch['renders'] = [];

    $counts = ['succeeded' => 0, 'failed' => 0, 'processing' => 0, 'starting' => 0];
    $totalCost = 0;

    foreach ($batch['renders'] as &$r) {
        if (!isset($r['predictionId']) || !$r['predictionId']) continue;
        if (isset($r['status']) && ($r['status'] === 'succeeded' || $r['status'] === 'failed')) {
            $counts[$r['status']]++;
            continue;
        }

        $pred = checkPrediction($replicateToken, $r['predictionId']);
        $r['status'] = $pred['status'] ?? 'unknown';

        if ($pred['status'] === 'succeeded') {
            $r['output'] = $pred['output'] ?? null;
            $r['metrics'] = $pred['metrics'] ?? null;
            $counts['succeeded']++;
        } elseif ($pred['status'] === 'failed') {
            $r['error'] = $pred['error'] ?? 'Unknown error';
            $counts['failed']++;
        } else {
            $counts[$pred['status'] ?? 'processing']++;
        }
    }
    unset($r);

    // Save updated status
    file_put_contents($batchFile, json_encode($batch, JSON_PRETTY_PRINT));

    $renders = $batch['renders'];
    $total = count(array_filter($renders, fn($r) => isset($r['predictionId']) && $r['predictionId']));
    $done = $counts['succeeded'] + $counts['failed'];

    echo json_encode([
        'total' => $total,
        'done' => $done,
        'succeeded' => $counts['succeeded'],
        'failed' => $counts['failed'],
        'processing' => $counts['processing'] + ($counts['starting'] ?? 0),
        'complete' => $done >= $total,
        'renders' => array_map(fn($r) => [
            'desc' => $r['desc'] ?? '',
            'model' => $r['model'] ?? '',
            'status' => $r['status'] ?? '',
            'output' => $r['output'] ?? null,
            'error' => $r['error'] ?? null
        ], $renders)
    ]);
}

// ============================================================
// ACTION: APPLY - Write video URLs back to EDL data
// ============================================================
elseif ($action === 'apply') {
    if (!file_exists($batchFile)) {
        echo json_encode(['error' => 'No batch found']);
        exit;
    }

    $batch = json_decode(file_get_contents($batchFile), true);
    $renders = $batch['renders'] ?? [];
    $applied = 0;

    foreach ($renders as $r) {
        if (($r['status'] ?? '') !== 'succeeded' || empty($r['output'])) continue;

        $actKey = $r['act'] ?? '';
        $clipIndices = $r['clipIndices'] ?? [];
        $output = $r['output'];
        // Output could be string (Kling) or array (LTX)
        $videoUrl = is_array($output) ? $output[0] : $output;

        $seg = null;
        foreach ($edlData['no-skin-to-touch-2']['segments'] as &$s) {
            if ($s['actKey'] === $actKey) { $seg = &$s; break; }
        }
        unset($s);
        if (!$seg) continue;

        // Apply video URL to all clips in this render
        foreach ($clipIndices as $ci) {
            if (!isset($seg['clips'][$ci])) continue;
            $clip = &$seg['clips'][$ci];
            if (!isset($clip['videos'])) $clip['videos'] = [];
            if ($clip['videoUrl'] && !in_array($clip['videoUrl'], $clip['videos'])) {
                $clip['videos'][] = $clip['videoUrl'];
            }
            $clip['videoUrl'] = $videoUrl;
            if (!in_array($videoUrl, $clip['videos'])) {
                $clip['videos'][] = $videoUrl;
            }
            $clip['status'] = 'video-ready';
            $clip['predictionId'] = $r['predictionId'] ?? null;
            $applied++;
            unset($clip);
        }
        unset($seg);
    }

    // Save EDL
    file_put_contents($edlFile, json_encode($edlData, JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'applied' => $applied,
        'message' => "Applied video URLs to $applied clips"
    ]);
}
