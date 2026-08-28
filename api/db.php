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

$sqliteFile = $dataDir . '/database.sqlite';
$jsonFile = $dataDir . '/store.json';

$useSqlite = false;
$pdo = null;

// Check if PDO SQLite extension is available
if (extension_loaded('pdo_sqlite')) {
    try {
        $pdo = new PDO("sqlite:" . $sqliteFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // High concurrency settings for 100+ simultaneous student submissions
        $pdo->exec("PRAGMA journal_mode = WAL;");
        $pdo->exec("PRAGMA busy_timeout = 5000;");

        // Schema Initialization
        $pdo->exec("CREATE TABLE IF NOT EXISTS tests (
            id VARCHAR(64) PRIMARY KEY,
            title TEXT NOT NULL,
            duration_minutes INTEGER NOT NULL DEFAULT 5,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS questions (
            id VARCHAR(64) PRIMARY KEY,
            test_id VARCHAR(64) NOT NULL,
            question_text TEXT NOT NULL,
            option_a TEXT NOT NULL,
            option_b TEXT NOT NULL,
            option_c TEXT NOT NULL,
            option_d TEXT NOT NULL,
            correct_option VARCHAR(10) NOT NULL,
            question_order INTEGER NOT NULL DEFAULT 0
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(64) PRIMARY KEY,
            test_id VARCHAR(64) NOT NULL,
            code VARCHAR(10) UNIQUE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
            id VARCHAR(64) PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            student_name TEXT NOT NULL,
            student_id TEXT DEFAULT '',
            score INTEGER NOT NULL,
            total_questions INTEGER NOT NULL,
            answers_json TEXT NOT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $useSqlite = true;
    } catch (Exception $e) {
        $useSqlite = false;
    }
}

// Fallback JSON File-Store Functions (if SQLite extension is disabled on basic host)
function getJsonDbData() {
    global $jsonFile;
    if (!file_exists($jsonFile)) {
        return ['tests' => [], 'sessions' => [], 'submissions' => []];
    }
    $fp = fopen($jsonFile, 'r');
    if (!$fp) return ['tests' => [], 'sessions' => [], 'submissions' => []];
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    return is_array($data) ? $data : ['tests' => [], 'sessions' => [], 'submissions' => []];
}

function saveJsonDbData($data) {
    global $jsonFile;
    $fp = fopen($jsonFile, 'w');
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
