<?php
/**
 * Image Assets API
 * Scans specified directories for images and returns metadata
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Directories to scan (relative to web root)
$directories = [
    [
        'name' => 'Website Images',
        'path' => '../images/',
        'webPath' => '/images/'
    ],
    [
        'name' => 'Website Images - Press',
        'path' => '../images/press/',
        'webPath' => '/images/press/'
    ],
    [
        'name' => 'Website Images - Tour',
        'path' => '../images/tour/',
        'webPath' => '/images/tour/'
    ],
    [
        'name' => 'Website Images - Gallery',
        'path' => '../images/gallery/',
        'webPath' => '/images/gallery/'
    ],
    [
        'name' => 'Website Assets',
        'path' => '../assets/',
        'webPath' => '/assets/'
    ],
    [
        'name' => 'AI Generated (ChatGPT/Grok)',
        'path' => '../images/ai-generated/',
        'webPath' => '/images/ai-generated/'
    ]
];

// Supported image extensions
$imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

$allImages = [];
$totalSize = 0;
$dirCount = 0;

foreach ($directories as $dir) {
    $fullPath = realpath(__DIR__ . '/' . $dir['path']);

    if (!$fullPath || !is_dir($fullPath)) {
        continue;
    }

    $dirCount++;
    $files = scandir($fullPath);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $filePath = $fullPath . '/' . $file;

        // Skip directories (we handle subdirs explicitly above)
        if (is_dir($filePath)) continue;

        // Check extension
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $imageExtensions)) continue;

        // Get file info
        $fileSize = filesize($filePath);
        $totalSize += $fileSize;

        // Try to get image dimensions
        $width = 0;
        $height = 0;
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo) {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
        }

        $allImages[] = [
            'name' => $file,
            'directory' => $dir['name'],
            'path' => $dir['webPath'] . $file,
            'url' => $dir['webPath'] . rawurlencode($file),
            'extension' => $ext,
            'size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'modified' => filemtime($filePath)
        ];
    }
}

// Sort by directory, then by name
usort($allImages, function($a, $b) {
    $dirCmp = strcmp($a['directory'], $b['directory']);
    if ($dirCmp !== 0) return $dirCmp;
    return strcmp($a['name'], $b['name']);
});

echo json_encode([
    'success' => true,
    'total' => count($allImages),
    'totalSize' => $totalSize,
    'directories' => $dirCount,
    'images' => $allImages
], JSON_PRETTY_PRINT);
