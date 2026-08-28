<?php
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbData();

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

    // Find test details
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

    // Prepare questions for student view (remove correct_option)
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
    
    // Generate unique 6-digit code
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
    saveDbData($db);

    echo json_encode([
        'success' => true,
        'session_id' => $sessionId,
        'code' => $code,
        'title' => $foundTest['title'],
        'duration_minutes' => $foundTest['duration_minutes']
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
