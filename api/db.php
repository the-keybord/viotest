<?php
header('Content-Type: application/json');
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

$dbFile = $dataDir . '/store.json';

if (!file_exists($dbFile)) {
    $initialData = [
        'tests' => [],
        'sessions' => [],
        'submissions' => []
    ];
    file_put_contents($dbFile, json_encode($initialData, JSON_PRETTY_PRINT), LOCK_EX);
}

function getDbData() {
    global $dbFile;
    if (!file_exists($dbFile)) {
        return ['tests' => [], 'sessions' => [], 'submissions' => []];
    }
    $fp = fopen($dbFile, 'r');
    if (!$fp) return ['tests' => [], 'sessions' => [], 'submissions' => []];
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    return is_array($data) ? $data : ['tests' => [], 'sessions' => [], 'submissions' => []];
}

function saveDbData($data) {
    global $dbFile;
    $fp = fopen($dbFile, 'w');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
