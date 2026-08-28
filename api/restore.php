<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data && isset($_FILES['backup_file'])) {
    $fileContent = file_get_contents($_FILES['backup_file']['tmp_name']);
    $data = json_decode($fileContent, true);
}

if (!is_array($data) || !isset($data['tests']) || !is_array($data['tests'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid backup file format. Must be a valid UniTest JSON backup.']);
    exit();
}

// Ensure required keys exist
$restoreData = [
    'tests' => isset($data['tests']) && is_array($data['tests']) ? $data['tests'] : [],
    'sessions' => isset($data['sessions']) && is_array($data['sessions']) ? $data['sessions'] : [],
    'submissions' => isset($data['submissions']) && is_array($data['submissions']) ? $data['submissions'] : []
];

if (saveDbData($restoreData)) {
    echo json_encode(['success' => true, 'message' => 'Data restored successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save restored data to server']);
}
