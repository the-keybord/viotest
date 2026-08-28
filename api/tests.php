<?php
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbData();

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

        // If requested for student, sanitize questions to remove correct_option
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
                'created_at' => $t['created_at']
            ];
        }
        echo json_encode($summaryList);
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
    $formattedQuestions = [];

    foreach ($input['questions'] as $index => $q) {
        if (empty($q['text']) || empty($q['a']) || empty($q['b']) || empty($q['c']) || empty($q['d']) || empty($q['correct'])) {
            continue;
        }

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
        'title' => $title,
        'duration_minutes' => $duration,
        'questions' => $formattedQuestions,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $db['tests'][] = $newTest;
    saveDbData($db);

    echo json_encode(['success' => true, 'test_id' => $testId]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
