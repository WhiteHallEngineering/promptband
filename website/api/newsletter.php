<?php
/**
 * PROMPT Newsletter Signup API
 * Stores email signups in a JSON file
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

// Data directory (protected)
$dataDir = __DIR__ . '/../analytics';
$signupsFile = $dataDir . '/newsletter.json';

// Ensure directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Load existing signups
$signups = [];
if (file_exists($signupsFile)) {
    $signups = json_decode(file_get_contents($signupsFile), true) ?: [];
}

// Check for duplicate
$isDuplicate = false;
foreach ($signups as $signup) {
    if (strtolower($signup['email']) === strtolower($email)) {
        $isDuplicate = true;
        break;
    }
}

if (!$isDuplicate) {
    // Add new signup
    $signups[] = [
        'email' => $email,
        'timestamp' => date('c'),
        'ip_hash' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    // Save signups
    file_put_contents($signupsFile, json_encode($signups, JSON_PRETTY_PRINT));

    // Send notification email to steve@promptband.ai
    $to = 'steve@promptband.ai';
    $subject = '[PROMPT] New Newsletter Subscriber';
    $body = "New subscriber to The Signal!\n\n";
    $body .= "Email: $email\n";
    $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $body .= "Total subscribers: " . count($signups) . "\n\n";
    $body .= "View all subscribers:\n";
    $body .= "https://promptband.ai/api/get-subscribers.php?key=pr0mpt-m3ss4g3s-2026";

    $headers = "From: PROMPT Website <noreply@promptband.ai>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    @mail($to, $subject, $body, $headers);
}

echo json_encode([
    'success' => true,
    'message' => $isDuplicate ? 'Already subscribed' : 'Successfully subscribed',
    'total' => count($signups)
]);
