<?php
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($useSqlite) {
    // --- SQLITE LOGIC ---
    if ($method === 'GET') {
        $code = isset($_GET['code']) ? trim($_GET['code']) : null;
        $sessionId = isset($_GET['id']) ? trim($_GET['id']) : null;

        if ($code) {
            $stmt = $pdo->prepare("SELECT s.*, t.title, t.duration_minutes FROM sessions s JOIN tests t ON s.test_id = t.id WHERE s.code = ?");
            $stmt->execute([$code]);
            $session = $stmt->fetch();
        } elseif ($sessionId) {
            $stmt = $pdo->prepare("SELECT s.*, t.title, t.duration_minutes FROM sessions s JOIN tests t ON s.test_id = t.id WHERE s.id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Code or ID parameter required']);
            exit();
        }

        if (!$session) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
            exit();
        }

        $stmtQ = $pdo->prepare("SELECT id, test_id, question_text, option_a, option_b, option_c, option_d, question_order FROM questions WHERE test_id = ? ORDER BY question_order ASC");
        $stmtQ->execute([$session['test_id']]);
        $session['questions'] = $stmtQ->fetchAll();

        echo json_encode($session);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['test_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'test_id is required']);
            exit();
        }

        $testId = $input['test_id'];
        $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
        $stmt->execute([$testId]);
        $test = $stmt->fetch();

        if (!$test) {
            http_response_code(404);
            echo json_encode(['error' => 'Test not found']);
            exit();
        }

        $sessionId = generateUuid();
        $code = strval(mt_rand(100000, 999999));

        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE code = ?");
        $checkStmt->execute([$code]);
        while ($checkStmt->fetchColumn() > 0) {
            $code = strval(mt_rand(100000, 999999));
            $checkStmt->execute([$code]);
        }

        $stmtInsert = $pdo->prepare("INSERT INTO sessions (id, test_id, code, status) VALUES (?, ?, ?, 'active')");
        $stmtInsert->execute([$sessionId, $testId, $code]);

        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'code' => $code,
            'title' => $test['title'],
            'duration_minutes' => $test['duration_minutes']
        ]);
    }
} else {
    // --- JSON FALLBACK LOGIC ---
    $db = getJsonDbData();
    if ($method === 'GET') {
        $code = isset($_GET['code']) ? trim($_GET['code']) : null;
        $sessionId = isset($_GET['id']) ? trim($_GET['id']) : null;
        $foundSession = null;
        foreach ($db['sessions'] as $s) {
            if (($code && $s['code'] === $code) || ($sessionId && $s['id'] === $sessionId)) {
                $foundSession = $s;
                break;
            }
        }
        if (!$foundSession) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
            exit();
        }
        $foundTest = null;
        foreach ($db['tests'] as $t) {
            if ($t['id'] === $foundSession['test_id']) {
                $foundTest = $t;
                break;
            }
        }
        if (!$foundTest) {
            http_response_code(404);
            echo json_encode(['error' => 'Associated test not found']);
            exit();
        }
        $studentQuestions = [];
        foreach ($foundTest['questions'] as $q) {
            $studentQuestions[] = [
                'id' => $q['id'],
                'question_text' => $q['question_text'],
                'option_a' => $q['option_a'],
                'option_b' => $q['option_b'],
                'option_c' => $q['option_c'],
                'option_d' => $q['option_d'],
                'question_order' => $q['question_order']
            ];
        }
        echo json_encode([
            'id' => $foundSession['id'],
            'test_id' => $foundSession['test_id'],
            'code' => $foundSession['code'],
            'status' => $foundSession['status'],
            'title' => $foundTest['title'],
            'duration_minutes' => $foundTest['duration_minutes'],
            'questions' => $studentQuestions
        ]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['test_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'test_id is required']);
            exit();
        }
        $testId = $input['test_id'];
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
        $sessionId = generateUuid();
        $existingCodes = array_column($db['sessions'], 'code');
        do {
            $code = strval(mt_rand(100000, 999999));
        } while (in_array($code, $existingCodes));
        $newSession = [
            'id' => $sessionId,
            'test_id' => $testId,
            'code' => $code,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        $db['sessions'][] = $newSession;
        saveJsonDbData($db);
        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'code' => $code,
            'title' => $foundTest['title'],
            'duration_minutes' => $foundTest['duration_minutes']
        ]);
    }
}
