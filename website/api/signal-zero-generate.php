<?php
/**
 * Signal 0 Radio — Audio Generation via Suno
 * Wraps suno-proxy.php to generate audio for Signal 0 songs
 *
 * Actions:
 * - generate (POST): Start audio generation for a song
 * - status (GET): Check generation status for a song
 * - download (POST): Download completed audio and save locally
 * - reject (POST): Reset audio status back to 'none'
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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

$songsFile = __DIR__ . '/signal-zero-songs.json';
$bandsFile = __DIR__ . '/signal-zero-bands.json';

if (!file_exists($songsFile) || !file_exists($bandsFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Data files not found. Run import first.']);
    exit;
}

$action = $_GET['action'] ?? 'generate';

switch ($action) {
    case 'generate':
        handleGenerate($songsFile, $bandsFile);
        break;
    case 'status':
        handleStatus($songsFile);
        break;
    case 'download':
        handleDownload($songsFile, $bandsFile);
        break;
    case 'reject':
        handleReject($songsFile);
        break;
    default:
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid action',
            'validActions' => ['generate', 'status', 'download', 'reject']
        ]);
        exit;
}

/**
 * Start audio generation for a song via Suno
 */
function handleGenerate($songsFile, $bandsFile) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    $songId = $input['songId'] ?? '';
    $bandSlug = $input['bandSlug'] ?? '';

    if (empty($songId) || empty($bandSlug)) {
        http_response_code(400);
        echo json_encode(['error' => 'songId and bandSlug are required']);
        exit;
    }

    // Load songs
    $songs = json_decode(file_get_contents($songsFile), true) ?: [];

    // Find the song
    $songIndex = null;
    $song = null;
    foreach ($songs as $idx => $s) {
        if ($s['id'] === $songId) {
            $songIndex = $idx;
            $song = $s;
            break;
        }
    }

    if ($song === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Song not found: ' . $songId]);
        exit;
    }

    // Verify lyrics are ready
    if (!in_array($song['lyricsStatus'], ['approved', 'pending'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Song lyrics must be approved or pending before generating audio',
            'currentStatus' => $song['lyricsStatus']
        ]);
        exit;
    }

    if (empty($song['lyrics'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Song has no lyrics']);
        exit;
    }

    // Check if already generating
    if ($song['audioStatus'] === 'generating') {
        http_response_code(409);
        echo json_encode([
            'error' => 'Song is already generating',
            'sunoJobId' => $song['sunoJobId'] ?? null
        ]);
        exit;
    }

    // Load band to get style tags
    $bands = json_decode(file_get_contents($bandsFile), true) ?: [];
    $band = null;
    foreach ($bands as $b) {
        if ($b['slug'] === $bandSlug) {
            $band = $b;
            break;
        }
    }

    if ($band === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Band not found: ' . $bandSlug]);
        exit;
    }

    // Build style tags from band's style field (keep under 200 chars for Suno)
    $styleTags = $band['style'];
    if (strlen($styleTags) > 200) {
        $styleTags = substr($styleTags, 0, 197) . '...';
    }

    // Call suno-proxy.php internally via curl
    $sunoProxyUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/api/suno-proxy.php?key=pr0mpt-m3ss4g3s-2026&action=generate';

    $sunoBody = [
        'lyrics' => $song['lyrics'],
        'prompt' => $styleTags,
        'title' => $song['title']
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sunoProxyUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sunoBody));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reach Suno proxy: ' . $curlError]);
        exit;
    }

    $sunoResult = json_decode($response, true);

    if ($httpCode >= 400 || !isset($sunoResult['success']) || !$sunoResult['success']) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Suno generation failed',
            'details' => $sunoResult['error'] ?? "HTTP $httpCode",
            'raw' => $sunoResult
        ]);
        exit;
    }

    // Extract job IDs from response
    $jobs = $sunoResult['jobs'] ?? [];
    $firstJobId = null;
    $jobIds = [];

    foreach ($jobs as $job) {
        if (!empty($job['id'])) {
            $jobIds[] = $job['id'];
            if ($firstJobId === null) {
                $firstJobId = $job['id'];
            }
        }
    }

    if (empty($firstJobId)) {
        http_response_code(500);
        echo json_encode([
            'error' => 'No job IDs returned from Suno',
            'raw' => $sunoResult
        ]);
        exit;
    }

    // Update song in JSON
    $songs[$songIndex]['audioStatus'] = 'generating';
    $songs[$songIndex]['sunoJobId'] = $firstJobId;
    $songs[$songIndex]['sunoJobIds'] = $jobIds;
    $songs[$songIndex]['audioGenerateStartedAt'] = date('c');

    file_put_contents($songsFile, json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'Audio generation started',
        'songId' => $songId,
        'sunoJobId' => $firstJobId,
        'sunoJobIds' => $jobIds,
        'styleTags' => $styleTags,
        'title' => $song['title'],
        'band' => $band['name']
    ], JSON_PRETTY_PRINT);
}

/**
 * Check generation status for a song
 */
function handleStatus($songsFile) {
    $songId = $_GET['songId'] ?? '';

    if (empty($songId)) {
        http_response_code(400);
        echo json_encode(['error' => 'songId parameter is required']);
        exit;
    }

    // Load songs
    $songs = json_decode(file_get_contents($songsFile), true) ?: [];

    // Find the song
    $song = null;
    foreach ($songs as $s) {
        if ($s['id'] === $songId) {
            $song = $s;
            break;
        }
    }

    if ($song === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Song not found: ' . $songId]);
        exit;
    }

    $sunoJobId = $song['sunoJobId'] ?? '';
    if (empty($sunoJobId)) {
        echo json_encode([
            'success' => true,
            'songId' => $songId,
            'audioStatus' => $song['audioStatus'] ?? 'none',
            'audioUrl' => $song['audioUrl'] ?? null,
            'sunoStatus' => null,
            'message' => 'No Suno job ID found for this song'
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Query all job IDs if available, otherwise just the primary one
    $jobIds = $song['sunoJobIds'] ?? [$sunoJobId];
    $idsParam = implode(',', $jobIds);

    // Call suno-proxy.php status endpoint
    $sunoProxyUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/api/suno-proxy.php?key=pr0mpt-m3ss4g3s-2026&action=status&ids=' . urlencode($idsParam);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sunoProxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to check Suno status',
            'details' => $curlError ?: "HTTP $httpCode"
        ]);
        exit;
    }

    $sunoResult = json_decode($response, true);
    $statuses = $sunoResult['statuses'] ?? [];

    // Check if any generation is complete
    $completeJob = null;
    foreach ($statuses as $status) {
        if ($status['status'] === 'complete' && !empty($status['audioUrl'])) {
            $completeJob = $status;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'songId' => $songId,
        'audioStatus' => $song['audioStatus'] ?? 'none',
        'audioUrl' => $song['audioUrl'] ?? null,
        'sunoJobId' => $sunoJobId,
        'sunoStatuses' => $statuses,
        'isComplete' => $completeJob !== null,
        'completeAudioUrl' => $completeJob ? $completeJob['audioUrl'] : null,
        'completeDuration' => $completeJob ? ($completeJob['duration'] ?? null) : null
    ], JSON_PRETTY_PRINT);
}

/**
 * Download completed audio and save locally
 */
function handleDownload($songsFile, $bandsFile) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    $songId = $input['songId'] ?? '';
    $sunoAudioUrl = $input['sunoAudioUrl'] ?? '';

    if (empty($songId) || empty($sunoAudioUrl)) {
        http_response_code(400);
        echo json_encode(['error' => 'songId and sunoAudioUrl are required']);
        exit;
    }

    // Load songs
    $songs = json_decode(file_get_contents($songsFile), true) ?: [];

    // Find the song
    $songIndex = null;
    $song = null;
    foreach ($songs as $idx => $s) {
        if ($s['id'] === $songId) {
            $songIndex = $idx;
            $song = $s;
            break;
        }
    }

    if ($song === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Song not found: ' . $songId]);
        exit;
    }

    $bandSlug = $song['bandSlug'];

    // Extract songSlug from songId by removing bandSlug-- prefix
    $songSlug = $songId;
    $prefix = $bandSlug . '--';
    if (strpos($songId, $prefix) === 0) {
        $songSlug = substr($songId, strlen($prefix));
    }

    // Create directory for band's audio
    $audioDir = __DIR__ . '/../audio/signal-zero/' . $bandSlug;
    if (!is_dir($audioDir)) {
        mkdir($audioDir, 0755, true);
    }

    $filename = $songSlug . '.mp3';
    $savePath = $audioDir . '/' . $filename;

    // Download the MP3 from Suno CDN
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sunoAudioUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $audioData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to download audio from Suno',
            'details' => $curlError ?: "HTTP $httpCode"
        ]);
        exit;
    }

    if (empty($audioData)) {
        http_response_code(500);
        echo json_encode(['error' => 'Downloaded audio data is empty']);
        exit;
    }

    // Save the file
    $saved = file_put_contents($savePath, $audioData);

    if ($saved === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save audio file to disk']);
        exit;
    }

    // Update song in JSON
    $localAudioUrl = '/audio/signal-zero/' . $bandSlug . '/' . $filename;
    $songs[$songIndex]['audioUrl'] = $localAudioUrl;
    $songs[$songIndex]['audioStatus'] = 'complete';
    $songs[$songIndex]['audioGeneratedAt'] = date('c');

    file_put_contents($songsFile, json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'Audio downloaded and saved',
        'songId' => $songId,
        'audioUrl' => $localAudioUrl,
        'fileSize' => strlen($audioData),
        'fileSizeMB' => round(strlen($audioData) / 1048576, 2)
    ], JSON_PRETTY_PRINT);
}

/**
 * Reject/reset audio for a song
 */
function handleReject($songsFile) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    $songId = $input['songId'] ?? '';

    if (empty($songId)) {
        http_response_code(400);
        echo json_encode(['error' => 'songId is required']);
        exit;
    }

    // Load songs
    $songs = json_decode(file_get_contents($songsFile), true) ?: [];

    // Find the song
    $songIndex = null;
    $song = null;
    foreach ($songs as $idx => $s) {
        if ($s['id'] === $songId) {
            $songIndex = $idx;
            $song = $s;
            break;
        }
    }

    if ($song === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Song not found: ' . $songId]);
        exit;
    }

    // Reset audio fields
    $songs[$songIndex]['audioStatus'] = 'none';
    $songs[$songIndex]['audioUrl'] = null;
    $songs[$songIndex]['sunoJobId'] = null;
    $songs[$songIndex]['sunoJobIds'] = null;
    $songs[$songIndex]['audioGenerateStartedAt'] = null;
    $songs[$songIndex]['audioGeneratedAt'] = null;

    file_put_contents($songsFile, json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'Audio rejected and reset',
        'songId' => $songId
    ], JSON_PRETTY_PRINT);
}
