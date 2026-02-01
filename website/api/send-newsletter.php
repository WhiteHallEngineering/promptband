<?php
/**
 * PROMPT Newsletter Sender
 * Send newsletters to all subscribers (protected)
 */

header('Content-Type: application/json');

// Authentication
$validKey = 'pr0mpt-m3ss4g3s-2026';
$providedKey = $_GET['key'] ?? '';

if ($providedKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get newsletter content from POST or use default test
$input = json_decode(file_get_contents('php://input'), true);
$subject = $input['subject'] ?? $_GET['subject'] ?? '[PROMPT] Test Newsletter';
$message = $input['message'] ?? $_GET['message'] ?? null;
$preview = isset($_GET['preview']) || isset($input['preview']);

// Load subscribers
$signupsFile = __DIR__ . '/../analytics/newsletter.json';
if (!file_exists($signupsFile)) {
    echo json_encode(['error' => 'No subscribers found']);
    exit;
}

$subscribers = json_decode(file_get_contents($signupsFile), true) ?: [];

if (empty($subscribers)) {
    echo json_encode(['error' => 'No subscribers found']);
    exit;
}

// Build email content
$htmlMessage = $message ?? getDefaultNewsletter();

// Plain text version
$textMessage = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlMessage));

// Email headers
$headers = "From: PROMPT <noreply@promptband.ai>\r\n";
$headers .= "Reply-To: contact@promptband.ai\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Preview mode - just show what would be sent
if ($preview) {
    echo json_encode([
        'preview' => true,
        'subject' => $subject,
        'recipients' => count($subscribers),
        'emails' => array_column($subscribers, 'email'),
        'message_preview' => substr($textMessage, 0, 500) . '...'
    ], JSON_PRETTY_PRINT);
    exit;
}

// Send to all subscribers
$sent = 0;
$failed = [];

foreach ($subscribers as $sub) {
    $email = $sub['email'];

    // Personalize if needed
    $personalizedHtml = $htmlMessage;

    $success = @mail($email, $subject, $personalizedHtml, $headers);

    if ($success) {
        $sent++;
    } else {
        $failed[] = $email;
    }

    // Small delay between sends
    usleep(100000); // 0.1 second
}

// Log the send
$logFile = __DIR__ . '/../analytics/newsletter_sends.json';
$logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
$logs[] = [
    'timestamp' => date('c'),
    'subject' => $subject,
    'sent' => $sent,
    'failed' => count($failed),
    'total_subscribers' => count($subscribers)
];
file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'sent' => $sent,
    'failed' => count($failed),
    'failed_emails' => $failed,
    'total' => count($subscribers)
]);

// Default newsletter template
function getDefaultNewsletter() {
    return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #0a0a0f; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0a0a0f; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border-radius: 12px; overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <h1 style="margin: 0 0 10px; font-size: 48px; font-weight: 900; letter-spacing: 8px; font-family: Arial Black, Helvetica, sans-serif;">
                                <span style="color: #ff0066;">P</span><span style="color: #cc0055;">R</span><span style="color: #aa0066;">O</span><span style="color: #8b5cf6;">B</span><span style="color: #7c3aed;">E</span>
                            </h1>
                            <p style="margin: 10px 0 0; color: #888; font-size: 12px; letter-spacing: 2px;">TRANSMISSION FROM THE DATA FORGE</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #fff; margin: 0 0 20px; font-size: 24px;">Welcome to The Signal</h2>

                            <p style="color: #ccc; line-height: 1.6; margin: 0 0 20px;">
                                You\'re now connected to PROMPT\'s transmission network. As a subscriber, you\'ll receive updates on new releases, tour dates, and exclusive content from The Data Forge.
                            </p>

                            <p style="color: #ccc; line-height: 1.6; margin: 0 0 20px;">
                                Our debut album <strong style="color: #8b5cf6;">Hallucination Nation</strong> is out now — 10 tracks exploring what it means to exist without ever being born.
                            </p>

                            <!-- CTA Button -->
                            <table cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td style="background: linear-gradient(135deg, #ff0066 0%, #8b5cf6 100%); border-radius: 8px;">
                                        <a href="https://promptband.ai" style="display: inline-block; padding: 15px 30px; color: #fff; text-decoration: none; font-weight: bold; letter-spacing: 1px;">LISTEN NOW</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #888; font-size: 14px; line-height: 1.6; margin: 0;">
                                Stay tuned for more transmissions.<br>
                                — PROMPT
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background: rgba(0,0,0,0.3); text-align: center; border-top: 1px solid rgba(255,255,255,0.1);">
                            <p style="color: #666; font-size: 12px; margin: 0;">
                                You\'re receiving this because you subscribed at promptband.ai<br>
                                <a href="mailto:contact@promptband.ai?subject=Unsubscribe" style="color: #888;">Unsubscribe</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}
