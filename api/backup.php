<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit();
}

if ($useSqlite) {
    // Fetch tests + questions
    $stmtT = $pdo->query("SELECT * FROM tests");
    $tests = $stmtT->fetchAll();
    foreach ($tests as &$t) {
        $stmtQ = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY question_order ASC");
        $stmtQ->execute([$t['id']]);
        $t['questions'] = $stmtQ->fetchAll();
    }

    $stmtS = $pdo->query("SELECT * FROM sessions");
    $sessions = $stmtS->fetchAll();

    $stmtSub = $pdo->query("SELECT * FROM submissions");
    $submissions = $stmtSub->fetchAll();
    foreach ($submissions as &$sub) {
        $sub['answers_json'] = json_decode($sub['answers_json'], true);
    }

    $dbData = [
        'tests' => $tests,
        'sessions' => $sessions,
        'submissions' => $submissions
    ];
} else {
    $dbData = getJsonDbData();
}

$filename = 'unitest_backup_' . date('Y-m-d_H-i') . '.json';

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($dbData, JSON_PRETTY_PRINT);
