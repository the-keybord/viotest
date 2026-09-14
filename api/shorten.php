<?php
/**
 * API Endpoint: Shorten URL
 * Validates the input URL, generates a unique straight code (e.g. /1234),
 * stores it in the database, and returns the short URL and code.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/db.php';
global $db;

// Read input from POST or JSON payload
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$url = '';
if (isset($jsonData['url'])) {
    $url = trim($jsonData['url']);
} elseif (isset($_POST['url'])) {
    $url = trim($_POST['url']);
}

if (empty($url)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Vă rugăm să introduceți un link.']);
    exit;
}

// Auto-prefix protocol if missing (e.g. google.com -> https://google.com)
if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
    $url = 'https://' . $url;
}

// Validate URL structure
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Link-ul introdus nu este valid.']);
    exit;
}

// Check URL scheme and host
$parsed = parse_url($url);
if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sunt permise doar adrese HTTP și HTTPS.']);
    exit;
}

if (empty($parsed['host']) || !str_contains($parsed['host'], '.')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Domeniul specificat nu este valid.']);
    exit;
}

// Generate unique code (e.g. 1234)
$code = $db->generateCode();

// Store in database
$saved = $db->insertLink($code, $url);
if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Eroare la salvarea în baza de date.']);
    exit;
}

// Determine base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = trim(str_replace('\\', '/', $scriptDir), '/');
$baseUrl = $protocol . $host . ($basePath ? '/' . $basePath : '');
$shortUrl = $baseUrl . '/' . $code;

echo json_encode([
    'success' => true,
    'code' => $code,
    'short_url' => $shortUrl,
    'original_url' => $url
], JSON_UNESCAPED_SLASHES);
