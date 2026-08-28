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

// Trim UTF-8 BOM if present
$xmlContent = trim(preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $xmlContent));

if (empty($xmlContent)) {
    http_response_code(400);
    echo json_encode(['error' => 'No XML content provided']);
    exit();
}

libxml_use_internal_errors(true);
$xml = simplexml_load_string($xmlContent);

if (!$xml) {
    $errors = libxml_get_errors();
    libxml_clear_errors();
    $errMsg = 'Invalid XML format.';
    if (!empty($errors)) {
        $errMsg .= ' Error line ' . $errors[0]->line . ': ' . trim($errors[0]->message);
    }
    http_response_code(400);
    echo json_encode(['error' => $errMsg]);
    exit();
}

// Ultra-flexible Title Extraction
$title = '';
if (isset($xml->title)) $title = (string)$xml->title;
elseif (isset($xml->name)) $title = (string)$xml->name;
elseif (isset($xml->subject)) $title = (string)$xml->subject;
elseif (isset($xml->topic)) $title = (string)$xml->topic;
$title = trim($title);

if (empty($title)) {
    $title = 'Test Importat XML (' . date('d.m.Y H:i') . ')';
}

// Duration Extraction
$duration = 5;
if (isset($xml->duration_minutes)) $duration = intval((string)$xml->duration_minutes);
elseif (isset($xml->duration)) $duration = intval((string)$xml->duration);
elseif (isset($xml->time_limit)) $duration = intval((string)$xml->time_limit);
if ($duration < 1) $duration = 5;

// Ultra-flexible Question Extraction
$questions = [];

// Locate question nodes under root or under <questions>
$qNodes = [];
if (isset($xml->questions) && isset($xml->questions->question)) {
    $qNodes = $xml->questions->question;
} elseif (isset($xml->question)) {
    $qNodes = $xml->question;
} elseif (isset($xml->item)) {
    $qNodes = $xml->item;
}

foreach ($qNodes as $qNode) {
    // Question Text
    $text = '';
    if (isset($qNode->text)) $text = (string)$qNode->text;
    elseif (isset($qNode->question_text)) $text = (string)$qNode->question_text;
    elseif (isset($qNode->title)) $text = (string)$qNode->title;
    elseif (isset($qNode->q)) $text = (string)$qNode->q;
    $text = trim($text);

    // Option A
    $optA = '';
    if (isset($qNode->option_a)) $optA = (string)$qNode->option_a;
    elseif (isset($qNode->optionA)) $optA = (string)$qNode->optionA;
    elseif (isset($qNode->a)) $optA = (string)$qNode->a;
    elseif (isset($qNode->choice_a)) $optA = (string)$qNode->choice_a;
    $optA = trim($optA);

    // Option B
    $optB = '';
    if (isset($qNode->option_b)) $optB = (string)$qNode->option_b;
    elseif (isset($qNode->optionB)) $optB = (string)$qNode->optionB;
    elseif (isset($qNode->b)) $optB = (string)$qNode->b;
    elseif (isset($qNode->choice_b)) $optB = (string)$qNode->choice_b;
    $optB = trim($optB);

    // Option C
    $optC = '';
    if (isset($qNode->option_c)) $optC = (string)$qNode->option_c;
    elseif (isset($qNode->optionC)) $optC = (string)$qNode->optionC;
    elseif (isset($qNode->c)) $optC = (string)$qNode->c;
    elseif (isset($qNode->choice_c)) $optC = (string)$qNode->choice_c;
    $optC = trim($optC);

    // Option D
    $optD = '';
    if (isset($qNode->option_d)) $optD = (string)$qNode->option_d;
    elseif (isset($qNode->optionD)) $optD = (string)$qNode->optionD;
    elseif (isset($qNode->d)) $optD = (string)$qNode->d;
    elseif (isset($qNode->choice_d)) $optD = (string)$qNode->choice_d;
    $optD = trim($optD);

    // Correct Option
    $correct = '';
    if (isset($qNode->correct_option)) $correct = (string)$qNode->correct_option;
    elseif (isset($qNode->correctOption)) $correct = (string)$qNode->correctOption;
    elseif (isset($qNode->correct)) $correct = (string)$qNode->correct;
    elseif (isset($qNode->answer)) $correct = (string)$qNode->answer;
    $correct = strtoupper(trim($correct));

    // Normalize correct option if 1, 2, 3, 4 -> A, B, C, D
    if ($correct === '1') $correct = 'A';
    if ($correct === '2') $correct = 'B';
    if ($correct === '3') $correct = 'C';
    if ($correct === '4') $correct = 'D';

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

if (count($questions) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Nu s-au găsit întrebări valide în fișierul XML. Verificați structura etichetelor <question>.']);
    exit();
}

// Save Test
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
        echo json_encode(['error' => 'Eroare la salvarea în baza de date: ' . $e->getMessage()]);
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
