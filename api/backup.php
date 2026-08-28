<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit();
}

$dbData = getDbData();
$filename = 'unitest_backup_' . date('Y-m-d_H-i') . '.json';

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($dbData, JSON_PRETTY_PRINT);
