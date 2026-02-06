<?php
// Assemble reel directly on server using FFmpeg

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

$input = json_decode(file_get_contents('php://input'), true);

$clips = $input['clips'] ?? [];
$audioPath = $input['audio_path'] ?? '';
$startTime = intval($input['start_time'] ?? 0);
$duration = intval($input['duration'] ?? 30);
$format = $input['format'] ?? 'square';
$trackName = $input['track_name'] ?? 'reel';

if (empty($clips)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No clips provided']);
    exit;
}

// Determine dimensions
$dimensions = [
    'square' => ['w' => 1080, 'h' => 1080],
    'vertical' => ['w' => 1080, 'h' => 1920],
    'horizontal' => ['w' => 1920, 'h' => 1080]
];
$dim = $dimensions[$format] ?? $dimensions['square'];

// Create temp directory for this job
$jobId = uniqid('reel-');
$tempDir = sys_get_temp_dir() . '/' . $jobId;
mkdir($tempDir, 0755, true);

// Download all video clips
$downloadedClips = [];
foreach ($clips as $i => $clipUrl) {
    $clipFile = $tempDir . "/clip{$i}.mp4";
    $clipData = @file_get_contents($clipUrl);
    if ($clipData === false) {
        // Cleanup and fail
        array_map('unlink', glob("$tempDir/*"));
        rmdir($tempDir);
        echo json_encode(['success' => false, 'error' => "Failed to download clip " . ($i + 1)]);
        exit;
    }
    file_put_contents($clipFile, $clipData);
    $downloadedClips[] = $clipFile;
}

// Create concat file
$concatFile = $tempDir . '/concat.txt';
$concatContent = implode("\n", array_map(fn($f) => "file '$f'", $downloadedClips));
file_put_contents($concatFile, $concatContent);

// Audio file path - try multiple locations
$audioFile = null;
$tryPaths = [
    dirname(__DIR__) . '/' . ltrim($audioPath, '/'),
    dirname(__DIR__) . '/full/' . basename($audioPath),
    dirname(__DIR__) . '/audio/full/' . basename($audioPath),
    dirname(__DIR__) . '/' . basename($audioPath)
];

foreach ($tryPaths as $tryPath) {
    if (file_exists($tryPath)) {
        $audioFile = $tryPath;
        break;
    }
}

if (!$audioFile) {
    echo json_encode(['success' => false, 'error' => 'Audio file not found: ' . $audioPath, 'tried' => $tryPaths]);
    exit;
}

// Output file
$outputDir = dirname(__DIR__) . '/generated-reels/';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}
$safeName = preg_replace('/[^a-z0-9]+/i', '-', strtolower($trackName));
$outputFile = $outputDir . $safeName . '-' . $format . '-' . time() . '.mp4';

// Build FFmpeg command - check multiple locations including user's bin
$ffmpeg = null;
$ffmpegPaths = [
    '/home2/hallmar3/bin/ffmpeg',
    $_SERVER['HOME'] . '/bin/ffmpeg',
    '/usr/bin/ffmpeg',
    '/usr/local/bin/ffmpeg'
];
foreach ($ffmpegPaths as $path) {
    if (file_exists($path) && is_executable($path)) {
        $ffmpeg = $path;
        break;
    }
}
if (!$ffmpeg) {
    echo json_encode(['success' => false, 'error' => 'FFmpeg not found on server']);
    exit;
}

$cmd = sprintf(
    '%s -y -f concat -safe 0 -i %s -ss %d -t %d -i %s ' .
    '-filter_complex "[0:v]scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:black,setsar=1[v]" ' .
    '-map "[v]" -map 1:a -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 192k -t %d -movflags +faststart %s 2>&1',
    escapeshellcmd($ffmpeg),
    escapeshellarg($concatFile),
    $startTime,
    $duration,
    escapeshellarg($audioFile),
    $dim['w'], $dim['h'], $dim['w'], $dim['h'],
    $duration,
    escapeshellarg($outputFile)
);

// Execute FFmpeg
$output = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);

// Cleanup temp files
array_map('unlink', glob("$tempDir/*"));
rmdir($tempDir);

if ($returnCode !== 0 || !file_exists($outputFile)) {
    echo json_encode([
        'success' => false,
        'error' => 'FFmpeg failed',
        'command' => $cmd,
        'output' => implode("\n", array_slice($output, -20)),
        'return_code' => $returnCode
    ]);
    exit;
}

// Success!
$reelUrl = '/generated-reels/' . basename($outputFile);
$fileSize = filesize($outputFile);

echo json_encode([
    'success' => true,
    'reel_url' => $reelUrl,
    'reel_path' => $outputFile,
    'file_size' => $fileSize,
    'duration' => $duration,
    'format' => $format,
    'dimensions' => "{$dim['w']}x{$dim['h']}"
]);
