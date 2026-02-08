<?php
/**
 * Signal 0 Radio — Save Band
 * Actions: approve, reject, set_priority, reset, bulk_approve
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
$action = $input['action'] ?? '';

$bandsFile = __DIR__ . '/signal-zero-bands.json';

if (!file_exists($bandsFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Bands data not found. Run import first.']);
    exit;
}

$bands = json_decode(file_get_contents($bandsFile), true) ?: [];

$updated = 0;

switch ($action) {
    case 'approve':
        $slug = $input['slug'] ?? '';
        foreach ($bands as &$band) {
            if ($band['slug'] === $slug) {
                $band['status'] = 'approved';
                $band['approvedAt'] = date('c');
                $updated++;
                break;
            }
        }
        unset($band);
        break;

    case 'reject':
        $slug = $input['slug'] ?? '';
        foreach ($bands as &$band) {
            if ($band['slug'] === $slug) {
                $band['status'] = 'rejected';
                $band['approvedAt'] = null;
                $updated++;
                break;
            }
        }
        unset($band);
        break;

    case 'reset':
        $slug = $input['slug'] ?? '';
        foreach ($bands as &$band) {
            if ($band['slug'] === $slug) {
                $band['status'] = 'pending';
                $band['approvedAt'] = null;
                $band['priority'] = false;
                $updated++;
                break;
            }
        }
        unset($band);
        break;

    case 'set_priority':
        $slug = $input['slug'] ?? '';
        $priority = $input['priority'] ?? true;
        foreach ($bands as &$band) {
            if ($band['slug'] === $slug) {
                $band['priority'] = (bool)$priority;
                $updated++;
                break;
            }
        }
        unset($band);
        break;

    case 'bulk_approve':
        // Approve by region or array of slugs
        $region = $input['region'] ?? '';
        $slugs = $input['slugs'] ?? [];

        foreach ($bands as &$band) {
            $match = false;
            if ($region && $band['region'] === $region && $band['status'] === 'pending') {
                $match = true;
            }
            if (!empty($slugs) && in_array($band['slug'], $slugs) && $band['status'] === 'pending') {
                $match = true;
            }
            if ($match) {
                $band['status'] = 'approved';
                $band['approvedAt'] = date('c');
                $updated++;
            }
        }
        unset($band);
        break;

    case 'bulk_reject':
        $region = $input['region'] ?? '';
        $slugs = $input['slugs'] ?? [];

        foreach ($bands as &$band) {
            $match = false;
            if ($region && $band['region'] === $region && $band['status'] === 'pending') {
                $match = true;
            }
            if (!empty($slugs) && in_array($band['slug'], $slugs)) {
                $match = true;
            }
            if ($match) {
                $band['status'] = 'rejected';
                $band['approvedAt'] = null;
                $updated++;
            }
        }
        unset($band);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Use: approve, reject, reset, set_priority, bulk_approve, bulk_reject']);
        exit;
}

file_put_contents($bandsFile, json_encode($bands, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Return updated counts
$meta = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'priority' => 0];
foreach ($bands as $band) {
    $meta['total']++;
    $meta[$band['status']]++;
    if ($band['priority']) $meta['priority']++;
}

echo json_encode([
    'success' => true,
    'action' => $action,
    'updated' => $updated,
    'meta' => $meta
], JSON_PRETTY_PRINT);
