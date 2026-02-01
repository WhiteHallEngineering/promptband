<?php
/**
 * PROMPT Contact Form API
 * Secure contact form submission with rate limiting and validation
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

// Configuration
$dataDir = __DIR__ . '/../analytics';
$messagesFile = $dataDir . '/messages.json';
$rateLimitFile = $dataDir . '/rate_limits.json';
$maxMessagesPerHour = 5;
$maxMessageLength = 5000;
$honeypotField = 'website_url'; // Hidden field that should be empty

// Ensure directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Get client IP hash for rate limiting
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ipHash = hash('sha256', $clientIP . date('Y-m-d-H'));

// Check rate limit
$rateLimits = [];
if (file_exists($rateLimitFile)) {
    $rateLimits = json_decode(file_get_contents($rateLimitFile), true) ?: [];
}

// Clean old entries (older than 1 hour)
$oneHourAgo = time() - 3600;
$rateLimits = array_filter($rateLimits, function($entry) use ($oneHourAgo) {
    return $entry['timestamp'] > $oneHourAgo;
});

// Count requests from this IP
$ipRequests = array_filter($rateLimits, function($entry) use ($ipHash) {
    return $entry['ip_hash'] === $ipHash;
});

if (count($ipRequests) >= $maxMessagesPerHour) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Too many messages. Please try again later.',
        'retry_after' => 3600
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$name = isset($input['name']) ? trim($input['name']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$subject = isset($input['subject']) ? trim($input['subject']) : 'General Inquiry';
$message = isset($input['message']) ? trim($input['message']) : '';
$honeypot = isset($input[$honeypotField]) ? trim($input[$honeypotField]) : '';

// Honeypot check (spam protection)
if (!empty($honeypot)) {
    // Silently reject but return success to confuse bots
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Message sent']);
    exit;
}

// Validate name
if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a valid name (2-100 characters)']);
    exit;
}

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a valid email address']);
    exit;
}

// Validate message
if (empty($message) || strlen($message) < 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a message (at least 10 characters)']);
    exit;
}

if (strlen($message) > $maxMessageLength) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is too long (max ' . $maxMessageLength . ' characters)']);
    exit;
}

// Basic spam detection
$spamPatterns = [
    '/\b(viagra|cialis|casino|lottery|winner|prize|click here|buy now)\b/i',
    '/https?:\/\/[^\s]+/i', // URLs
    '/\b[A-Z]{10,}\b/', // All caps words
];

foreach ($spamPatterns as $pattern) {
    if (preg_match($pattern, $message) || preg_match($pattern, $name)) {
        // Silently reject spam
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Message sent']);
        exit;
    }
}

// Load existing messages
$messages = [];
if (file_exists($messagesFile)) {
    $messages = json_decode(file_get_contents($messagesFile), true) ?: [];
}

// Create message entry
$messageEntry = [
    'id' => uniqid('msg_'),
    'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    'email' => $email,
    'subject' => htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'),
    'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
    'timestamp' => date('c'),
    'ip_hash' => hash('sha256', $clientIP),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'read' => false
];

// Add to messages
$messages[] = $messageEntry;

// Save messages
file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT));

// Update rate limit
$rateLimits[] = [
    'ip_hash' => $ipHash,
    'timestamp' => time()
];
file_put_contents($rateLimitFile, json_encode($rateLimits, JSON_PRETTY_PRINT));

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Your message has been transmitted successfully.',
    'id' => $messageEntry['id']
]);
