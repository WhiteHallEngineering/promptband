<?php
/**
 * Assemble Video Clips into a Reel with Audio
 *
 * POST parameters (JSON body):
 * - clips: array of {path, duration} objects (video files to concatenate)
 * - audio_path: path to audio file
 * - start_time: where to start in audio (seconds, default 0)
 * - duration: total reel duration (seconds, default 30)
 * - format: 'square', 'vertical', or 'horizontal' (default 'square')
 * - output_name: optional custom output filename (without extension)
 *
 * Returns: FFmpeg command and output path (execution happens via Claude Code)
 *
 * Requires: key=pr0mpt-m3ss4g3s-2026
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// Authentication
$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

// Extract and validate parameters
$clips = $input['clips'] ?? [];
$audioPath = $input['audio_path'] ?? '';
$startTime = floatval($input['start_time'] ?? 0);
$duration = floatval($input['duration'] ?? 30);
$format = $input['format'] ?? 'square';
$outputName = $input['output_name'] ?? '';

// Validate clips array
if (empty($clips) || !is_array($clips)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'clips array is required and must not be empty']);
    exit;
}

// Validate each clip has path
foreach ($clips as $index => $clip) {
    if (empty($clip['path'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Clip at index {$index} is missing 'path'"]);
        exit;
    }
}

// Validate audio path
if (empty($audioPath)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'audio_path is required']);
    exit;
}

// Validate format
$validFormats = ['square', 'vertical', 'horizontal'];
if (!in_array($format, $validFormats)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => "Invalid format. Must be one of: " . implode(', ', $validFormats)
    ]);
    exit;
}

// Get dimensions based on format
$dimensions = [
    'square' => ['width' => 1080, 'height' => 1080],
    'vertical' => ['width' => 1080, 'height' => 1920],
    'horizontal' => ['width' => 1920, 'height' => 1080]
];

$width = $dimensions[$format]['width'];
$height = $dimensions[$format]['height'];

// Generate output filename
$outputDir = dirname(__DIR__) . '/generated-reels';
if (empty($outputName)) {
    $outputName = 'reel-' . date('Y-m-d-His') . '-' . substr(md5(uniqid()), 0, 6);
}
// Sanitize output name
$outputName = preg_replace('/[^a-zA-Z0-9_-]/', '-', $outputName);
$outputPath = $outputDir . '/' . $outputName . '.mp4';

// Build FFmpeg filter complex for concatenation with scaling/padding
$filterInputs = [];
$filterChains = [];
$inputFiles = [];

// Add video inputs
foreach ($clips as $index => $clip) {
    $inputFiles[] = '-i ' . escapeshellarg($clip['path']);

    // Scale and pad each input to match target dimensions
    // scale2ref scales to fit within target while maintaining aspect ratio
    // pad centers the video with black bars if needed
    $filterChains[] = "[{$index}:v]scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1[v{$index}]";
    $filterInputs[] = "[v{$index}]";
}

// Add audio input (last input)
$audioInputIndex = count($clips);
$inputFiles[] = '-ss ' . escapeshellarg($startTime) . ' -i ' . escapeshellarg($audioPath);

// Build concat filter
$numClips = count($clips);
$concatInputs = implode('', $filterInputs);
$filterChains[] = "{$concatInputs}concat=n={$numClips}:v=1:a=0[vout]";

$filterComplex = implode(';', $filterChains);

// Build the FFmpeg command
$ffmpegParts = [
    'ffmpeg',
    '-y', // Overwrite output
];

// Add all input files
foreach ($inputFiles as $input) {
    $ffmpegParts[] = $input;
}

// Add filter complex
$ffmpegParts[] = '-filter_complex ' . escapeshellarg($filterComplex);

// Map video and audio outputs
$ffmpegParts[] = '-map "[vout]"';
$ffmpegParts[] = "-map {$audioInputIndex}:a";

// Output options
$ffmpegParts[] = '-c:v libx264';
$ffmpegParts[] = '-preset medium';
$ffmpegParts[] = '-crf 23';
$ffmpegParts[] = '-c:a aac';
$ffmpegParts[] = '-b:a 192k';
$ffmpegParts[] = '-t ' . escapeshellarg($duration);
$ffmpegParts[] = '-movflags +faststart';
$ffmpegParts[] = escapeshellarg($outputPath);

$ffmpegCommand = implode(' ', $ffmpegParts);

// Also create a mkdir command for the output directory
$mkdirCommand = 'mkdir -p ' . escapeshellarg($outputDir);

// Calculate expected clip usage info
$clipInfo = [];
$totalClipDuration = 0;
foreach ($clips as $index => $clip) {
    $clipDuration = floatval($clip['duration'] ?? 5); // Default 5 seconds if not specified
    $clipInfo[] = [
        'index' => $index,
        'path' => $clip['path'],
        'duration' => $clipDuration
    ];
    $totalClipDuration += $clipDuration;
}

// Return the command and metadata
echo json_encode([
    'success' => true,
    'mkdir_command' => $mkdirCommand,
    'ffmpeg_command' => $ffmpegCommand,
    'output_path' => $outputPath,
    'metadata' => [
        'format' => $format,
        'dimensions' => "{$width}x{$height}",
        'duration' => $duration,
        'audio_path' => $audioPath,
        'audio_start_time' => $startTime,
        'num_clips' => $numClips,
        'total_clip_duration' => $totalClipDuration,
        'clips' => $clipInfo
    ],
    'instructions' => 'Run mkdir_command first, then ffmpeg_command to generate the reel.'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
