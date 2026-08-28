<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$xmlContent = '';

if (isset($_FILES['xml_file']) && $_FILES['xml_file']['error'] === UPLOAD_ERR_OK) {
    $xmlContent = file_get_contents($_FILES['xml_file']['tmp_name']);
} else {
    $xmlContent = file_get_contents('php://input');
}

if (empty($xmlContent)) {
    http_response_code(400);
    echo json_encode(['error' => 'No XML content provided']);
    exit();
}

// Disable external entity loading for security
libxml_use_internal_errors(true);
$xml = simplexml_load_string($xmlContent);

if (!$xml) {
    $errors = libxml_get_errors();
    libxml_clear_errors();
    http_response_code(400);
    echo json_encode(['error' => 'Invalid XML format. Please check structure.']);
    exit();
}

$title = isset($xml->title) ? trim((string)$xml->title) : '';
$duration = isset($xml->duration_minutes) ? intval((string)$xml->duration_minutes) : 5;
if ($duration < 1) $duration = 5;

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['error' => 'Test title (<title>) is missing in XML']);
    exit();
}

$questions = [];
if (isset($xml->questions) && isset($xml->questions->question)) {
    foreach ($xml->questions->question as $qNode) {
        $text = trim((string)$qNode->text);
        $optA = trim((string)$qNode->option_a);
        $optB = trim((string)$qNode->option_b);
        $optC = trim((string)$qNode->option_c);
        $optD = trim((string)$qNode->option_d);
        $correct = strtoupper(trim((string)$qNode->correct_option));

        if (!empty($text) && !empty($optA) && !empty($optB) && !empty($optC) && !empty($optD) && !empty($correct)) {
            $questions[] = [
                'text' => $text,
                'a' => $optA,
                'b' => $optB,
                'c' => $optC,
                'd' => $optD,
                'correct' => $correct
            ];
        }
    }
}

if (count($questions) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid questions found in XML file.']);
    exit();
}

// Save Test to Database
$testId = generateUuid();

if ($useSqlite) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO tests (id, title, duration_minutes) VALUES (?, ?, ?)");
        $stmt->execute([$testId, $title, $duration]);

        $stmtQ = $pdo->prepare("INSERT INTO questions (id, test_id, question_text, option_a, option_b, option_c, option_d, correct_option, question_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($questions as $idx => $q) {
            $stmtQ->execute([
                generateUuid(),
                $testId,
                $q['text'],
                $q['a'],
                $q['b'],
                $q['c'],
                $q['d'],
                $q['correct'],
                $idx
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'test_id' => $testId, 'title' => $title, 'question_count' => count($questions)]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Database error importing XML: ' . $e->getMessage()]);
    }
} else {
    $db = getJsonDbData();
    $formattedQuestions = [];
    foreach ($questions as $idx => $q) {
        $formattedQuestions[] = [
            'id' => generateUuid(),
            'question_text' => $q['text'],
            'option_a' => $q['a'],
            'option_b' => $q['b'],
            'option_c' => $q['c'],
            'option_d' => $q['d'],
            'correct_option' => $q['correct'],
            'question_order' => $idx
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
    saveJsonDbData($db);
    echo json_encode(['success' => true, 'test_id' => $testId, 'title' => $title, 'question_count' => count($questions)]);
}
