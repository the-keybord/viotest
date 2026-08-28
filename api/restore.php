<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
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
    echo json_encode(['error' => 'Invalid backup file format. Must be a valid VioTest JSON backup.']);
    exit();
}

$restoreTests = isset($data['tests']) && is_array($data['tests']) ? $data['tests'] : [];
$restoreSessions = isset($data['sessions']) && is_array($data['sessions']) ? $data['sessions'] : [];
$restoreSubmissions = isset($data['submissions']) && is_array($data['submissions']) ? $data['submissions'] : [];

if ($useSqlite) {
    try {
        $pdo->beginTransaction();

        $pdo->exec("DELETE FROM submissions");
        $pdo->exec("DELETE FROM sessions");
        $pdo->exec("DELETE FROM questions");
        $pdo->exec("DELETE FROM tests");

        $stmtT = $pdo->prepare("INSERT INTO tests (id, title, duration_minutes, created_at) VALUES (?, ?, ?, ?)");
        $stmtQ = $pdo->prepare("INSERT INTO questions (id, test_id, question_text, option_a, option_b, option_c, option_d, correct_option, question_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($restoreTests as $t) {
            $stmtT->execute([
                $t['id'],
                $t['title'],
                $t['duration_minutes'] ?? 5,
                $t['created_at'] ?? date('Y-m-d H:i:s')
            ]);

            if (isset($t['questions']) && is_array($t['questions'])) {
                foreach ($t['questions'] as $q) {
                    $stmtQ->execute([
                        $q['id'],
                        $t['id'],
                        $q['question_text'],
                        $q['option_a'],
                        $q['option_b'],
                        $q['option_c'],
                        $q['option_d'],
                        $q['correct_option'] ?? 'A',
                        $q['question_order'] ?? 0
                    ]);
                }
            }
        }

        $stmtS = $pdo->prepare("INSERT INTO sessions (id, test_id, code, status, created_at) VALUES (?, ?, ?, ?, ?)");
        foreach ($restoreSessions as $s) {
            $stmtS->execute([
                $s['id'],
                $s['test_id'],
                $s['code'],
                $s['status'] ?? 'active',
                $s['created_at'] ?? date('Y-m-d H:i:s')
            ]);
        }

        $stmtSub = $pdo->prepare("INSERT INTO submissions (id, session_id, student_name, student_id, score, total_questions, answers_json, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($restoreSubmissions as $sub) {
            $answersJson = is_array($sub['answers_json']) ? json_encode($sub['answers_json']) : $sub['answers_json'];
            $stmtSub->execute([
                $sub['id'],
                $sub['session_id'],
                $sub['student_name'],
                $sub['student_id'] ?? '',
                $sub['score'],
                $sub['total_questions'],
                $answersJson,
                $sub['submitted_at'] ?? date('Y-m-d H:i:s')
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'SQLite Database restored successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to restore SQLite database: ' . $e->getMessage()]);
    }
} else {
    $restoreData = [
        'tests' => $restoreTests,
        'sessions' => $restoreSessions,
        'submissions' => $restoreSubmissions
    ];
    if (saveJsonDbData($restoreData)) {
        echo json_encode(['success' => true, 'message' => 'Data restored successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save restored data to server']);
    }
}
