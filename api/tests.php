<?php
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($useSqlite) {
    // --- SQLITE DATABASE LOGIC ---
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $testId = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
            $stmt->execute([$testId]);
            $test = $stmt->fetch();

            if (!$test) {
                http_response_code(404);
                echo json_encode(['error' => 'Test not found']);
                exit();
            }

            $stmtQ = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY question_order ASC");
            $stmtQ->execute([$testId]);
            $questions = $stmtQ->fetchAll();

            if (isset($_GET['for_student']) && $_GET['for_student'] === '1') {
                foreach ($questions as &$q) {
                    unset($q['correct_option']);
                }
            }

            $test['questions'] = $questions;
            echo json_encode($test);
        } else {
            $stmt = $pdo->prepare("SELECT t.*, COUNT(q.id) as question_count FROM tests t LEFT JOIN questions q ON t.id = q.test_id GROUP BY t.id ORDER BY t.created_at DESC");
            $stmt->execute();
            $tests = $stmt->fetchAll();
            echo json_encode($tests);
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['title']) || empty($input['questions']) || !is_array($input['questions'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input. Title and questions array are required.']);
            exit();
        }

        $title = trim($input['title']);
        $duration = isset($input['duration_minutes']) ? intval($input['duration_minutes']) : 5;
        if ($duration < 1) $duration = 1;

        $testId = generateUuid();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("INSERT INTO tests (id, title, duration_minutes) VALUES (?, ?, ?)");
            $stmt->execute([$testId, $title, $duration]);

            $stmtQ = $pdo->prepare("INSERT INTO questions (id, test_id, question_text, option_a, option_b, option_c, option_d, correct_option, question_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($input['questions'] as $index => $q) {
                if (empty($q['text']) || empty($q['a']) || empty($q['b']) || empty($q['c']) || empty($q['d']) || empty($q['correct'])) {
                    continue;
                }
                $qId = generateUuid();
                $stmtQ->execute([
                    $qId,
                    $testId,
                    trim($q['text']),
                    trim($q['a']),
                    trim($q['b']),
                    trim($q['c']),
                    trim($q['d']),
                    strtoupper(trim($q['correct'])),
                    $index
                ]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'test_id' => $testId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create test: ' . $e->getMessage()]);
        }
    }
} else {
    // --- JSON STORE FALLBACK LOGIC ---
    $db = getJsonDbData();
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $testId = $_GET['id'];
            $foundTest = null;
            foreach ($db['tests'] as $t) {
                if ($t['id'] === $testId) {
                    $foundTest = $t;
                    break;
                }
            }
            if (!$foundTest) {
                http_response_code(404);
                echo json_encode(['error' => 'Test not found']);
                exit();
            }
            if (isset($_GET['for_student']) && $_GET['for_student'] === '1') {
                foreach ($foundTest['questions'] as &$q) {
                    unset($q['correct_option']);
                }
            }
            echo json_encode($foundTest);
        } else {
            $summaryList = [];
            foreach ($db['tests'] as $t) {
                $summaryList[] = [
                    'id' => $t['id'],
                    'title' => $t['title'],
                    'duration_minutes' => $t['duration_minutes'],
                    'question_count' => count($t['questions'] ?? []),
                    'created_at' => $t['created_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($summaryList);
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['title']) || empty($input['questions']) || !is_array($input['questions'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit();
        }
        $testId = generateUuid();
        $formattedQuestions = [];
        foreach ($input['questions'] as $index => $q) {
            if (empty($q['text']) || empty($q['a']) || empty($q['b']) || empty($q['c']) || empty($q['d']) || empty($q['correct'])) continue;
            $formattedQuestions[] = [
                'id' => generateUuid(),
                'question_text' => trim($q['text']),
                'option_a' => trim($q['a']),
                'option_b' => trim($q['b']),
                'option_c' => trim($q['c']),
                'option_d' => trim($q['d']),
                'correct_option' => strtoupper(trim($q['correct'])),
                'question_order' => $index
            ];
        }
        $newTest = [
            'id' => $testId,
            'title' => trim($input['title']),
            'duration_minutes' => intval($input['duration_minutes'] ?? 5),
            'questions' => $formattedQuestions,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $db['tests'][] = $newTest;
        saveJsonDbData($db);
        echo json_encode(['success' => true, 'test_id' => $testId]);
    }
}
