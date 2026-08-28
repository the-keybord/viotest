<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['session_id']) || empty($input['student_name']) || !isset($input['answers'])) {
    http_response_code(400);
    echo json_encode(['error' => 'session_id, student_name, and answers array are required']);
    exit();
}

$sessionId = trim($input['session_id']);
$studentName = trim($input['student_name']);
$studentId = isset($input['student_id']) ? trim($input['student_id']) : '';
$studentAnswers = is_array($input['answers']) ? $input['answers'] : [];

$db = getDbData();

// Locate session
$foundSession = null;
foreach ($db['sessions'] as $s) {
    if ($s['id'] === $sessionId) {
        $foundSession = $s;
        break;
    }
}

if (!$foundSession) {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid test session']);
    exit();
}

// Locate test
$foundTest = null;
foreach ($db['tests'] as $t) {
    if ($t['id'] === $foundSession['test_id']) {
        $foundTest = $t;
        break;
    }
}

if (!$foundTest) {
    http_response_code(404);
    echo json_encode(['error' => 'Test data not found']);
    exit();
}

// Grade answers
$totalQuestions = count($foundTest['questions']);
$score = 0;
$detailedResults = [];

foreach ($foundTest['questions'] as $q) {
    $qId = $q['id'];
    $chosenOption = isset($studentAnswers[$qId]) ? strtoupper(trim($studentAnswers[$qId])) : '';
    $correctOption = strtoupper(trim($q['correct_option']));
    $isCorrect = ($chosenOption === $correctOption);

    if ($isCorrect) {
        $score++;
    }

    $detailedResults[] = [
        'question_id' => $qId,
        'question_text' => $q['question_text'],
        'chosen_option' => $chosenOption,
        'correct_option' => $correctOption,
        'is_correct' => $isCorrect
    ];
}

$submissionId = generateUuid();
$newSubmission = [
    'id' => $submissionId,
    'session_id' => $sessionId,
    'student_name' => $studentName,
    'student_id' => $studentId,
    'score' => $score,
    'total_questions' => $totalQuestions,
    'answers_json' => [
        'submitted_answers' => $studentAnswers,
        'detailed' => $detailedResults
    ],
    'submitted_at' => date('Y-m-d H:i:s')
];

$db['submissions'][] = $newSubmission;
saveDbData($db);

$percentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100, 1) : 0;

echo json_encode([
    'success' => true,
    'submission_id' => $submissionId,
    'student_name' => $studentName,
    'score' => $score,
    'total_questions' => $totalQuestions,
    'percentage' => $percentage,
    'details' => $detailedResults
]);
