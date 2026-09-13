<?php
/**
 * vio.zece.info - Links API
 * Handles creation and retrieval of short links.
 */

require_once __DIR__ . '/db.php';
global $db;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function normalizeUrl($url) {
    $url = trim($url);
    if ($url === '') return '';
    if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
        $url = 'https://' . $url;
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
}

function generateUniqueCode($db) {
    for ($i = 0; $i < 100; $i++) {
        $code = (string)mt_rand(1000, 9999);
        if (!$db->codeExists($code)) {
            return $code;
        }
    }
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    for ($i = 0; $i < 100; $i++) {
        $code = '';
        for ($j = 0; $j < 5; $j++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        if (!$db->codeExists($code)) {
            return $code;
        }
    }
    throw new Exception("Unable to generate unique code.");
}

function getAppHost() {
    $reqHost = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($reqHost) || str_contains($reqHost, '127.0.0.1') || str_contains($reqHost, 'localhost')) {
        return 'vio.zece.info';
    }
    return $reqHost;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $code = isset($_GET['code']) ? trim($_GET['code']) : '';
    if (!$code) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Codul lipsește.']);
        exit();
    }

    $link = $db->getLinkByCode($code);

    if ($link) {
        echo json_encode([
            'success' => true,
            'code' => $link['code'],
            'target_url' => $link['target_url'],
            'created_at' => $link['created_at'],
            'clicks' => (int)($link['clicks'] ?? 0)
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Codul nu a fost găsit.']);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    $url = $input['url'] ?? ($_POST['url'] ?? '');
    $validatedUrl = normalizeUrl($url);

    if (!$validatedUrl) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Link invalid. Vă rugăm să introduceți un URL corect.']);
        exit();
    }

    try {
        $recent = $db->getRecentLinkByUrl($validatedUrl);
        
        $code = null;
        if ($recent) {
            $code = $recent['code'];
        } else {
            $code = generateUniqueCode($db);
            $db->insertLink($code, $validatedUrl);
        }

        $host = getAppHost();
        $shortUrl = "https://{$host}/{$code}";

        echo json_encode([
            'success' => true,
            'code' => $code,
            'short_url' => $shortUrl,
            'target_url' => $validatedUrl
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'A apărut o eroare la generare.']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Metodă nepermisă.']);
