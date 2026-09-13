<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataDir = __DIR__ . '/../data';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}
$linksFile = $dataDir . '/links.json';

function getLinksData($file) {
    if (!file_exists($file)) {
        return ['codes' => []];
    }
    $fp = fopen($file, 'r');
    if (!$fp) return ['codes' => []];
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    return is_array($data) && isset($data['codes']) ? $data : ['codes' => []];
}

function saveLinksData($file, $data) {
    $fp = fopen($file, 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function normalizeUrl($url) {
    $url = trim($url);
    if ($url === '') return '';
    if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
        $url = 'https://' . $url;
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
}

function generateCode($existingCodes) {
    // Generate a clean 4-digit code (1000 - 9999)
    for ($i = 0; $i < 500; $i++) {
        $code = (string)mt_rand(1000, 9999);
        if (!isset($existingCodes[$code])) {
            return $code;
        }
    }
    // Fallback if numbers are dense: 4-character uppercase alphanumeric (excluding confusing 0/O, 1/I)
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    for ($i = 0; $i < 1000; $i++) {
        $code = '';
        for ($j = 0; $j < 4; $j++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        if (!isset($existingCodes[$code])) {
            return $code;
        }
    }
    return substr(md5(uniqid(mt_rand(), true)), 0, 5);
}

// Handle GET: Lookup code
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $code = isset($_GET['code']) ? trim($_GET['code']) : '';
    if (!$code) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Codul lipsește.']);
        exit();
    }

    $data = getLinksData($linksFile);
    $normalizedCode = strtoupper($code);
    
    // Check direct or case-insensitive
    $found = null;
    if (isset($data['codes'][$code])) {
        $found = $data['codes'][$code];
    } elseif (isset($data['codes'][$normalizedCode])) {
        $found = $data['codes'][$normalizedCode];
    } else {
        foreach ($data['codes'] as $k => $item) {
            if (strcasecmp($k, $code) === 0) {
                $found = $item;
                break;
            }
        }
    }

    if ($found) {
        echo json_encode([
            'success' => true,
            'code' => $found['code'],
            'target_url' => $found['url'],
            'created_at' => $found['created_at'] ?? null,
            'clicks' => $found['clicks'] ?? 0
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Codul nu a fost găsit sau a expirat.']);
    }
    exit();
}

// Handle POST: Create short link
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    $url = $input['url'] ?? ($_POST['url'] ?? '');
    $validatedUrl = normalizeUrl($url);

    if (!$validatedUrl) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Link-ul introdus nu este valid. Te rugăm să introduci o adresă web corectă.']);
        exit();
    }

    $data = getLinksData($linksFile);

    // Check if the exact URL was already shortened recently (within 48 hours) to preserve the code
    $now = time();
    $existingCode = null;
    foreach ($data['codes'] as $c => $item) {
        if ($item['url'] === $validatedUrl) {
            $createdTime = isset($item['created_at']) ? strtotime($item['created_at']) : 0;
            if (($now - $createdTime) < 172800) { // 48h
                $existingCode = $c;
                break;
            }
        }
    }

    if ($existingCode) {
        $code = $existingCode;
    } else {
        $code = generateCode($data['codes']);
        $data['codes'][$code] = [
            'code' => $code,
            'url' => $validatedUrl,
            'created_at' => date('c'),
            'clicks' => 0
        ];
        saveLinksData($linksFile, $data);
    }

    // Determine host for short URL
    $reqHost = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($reqHost) || str_contains($reqHost, '127.0.0.1') || str_contains($reqHost, 'localhost')) {
        $host = 'vio.zece.info';
    } else {
        $host = $reqHost;
    }
    $shortUrl = "https://{$host}/{$code}";

    echo json_encode([
        'success' => true,
        'code' => $code,
        'short_url' => $shortUrl,
        'target_url' => $validatedUrl,
        'created_at' => $data['codes'][$code]['created_at']
    ]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Metodă nepermisă.']);
