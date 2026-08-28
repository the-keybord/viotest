<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$sessionId = isset($_GET['session_id']) ? trim($_GET['session_id']) : null;

if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['error' => 'session_id parameter is required']);
    exit();
}

if ($useSqlite) {
    // --- SQLITE LOGIC ---
    $stmtS = $pdo->prepare("SELECT s.*, t.title, t.duration_minutes FROM sessions s JOIN tests t ON s.test_id = t.id WHERE s.id = ?");
    $stmtS->execute([$sessionId]);
    $session = $stmtS->fetch();

    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        exit();
    }

    $stmtQCount = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE test_id = ?");
    $stmtQCount->execute([$session['test_id']]);
    $session['total_questions'] = intval($stmtQCount->fetchColumn());

    $stmtSub = $pdo->prepare("SELECT * FROM submissions WHERE session_id = ? ORDER BY submitted_at DESC");
    $stmtSub->execute([$sessionId]);
    $submissions = $stmtSub->fetchAll();

    $totalStudents = count($submissions);
    $totalScoreSum = 0;

    foreach ($submissions as &$sub) {
        $totalScoreSum += $sub['score'];
        $sub['answers_json'] = json_decode($sub['answers_json'], true);
    }

    $avgScore = $totalStudents > 0 ? round($totalScoreSum / $totalStudents, 1) : 0;

    echo json_encode([
        'session' => $session,
        'total_students' => $totalStudents,
        'average_score' => $avgScore,
        'submissions' => $submissions
    ]);

} else {
    // --- JSON FALLBACK LOGIC ---
    $db = getJsonDbData();

    $foundSession = null;
    foreach ($db['sessions'] as $s) {
        if ($s['id'] === $sessionId) {
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

    $foundSession['title'] = $foundTest ? $foundTest['title'] : 'Test Session';
    $foundSession['duration_minutes'] = $foundTest ? $foundTest['duration_minutes'] : 5;
    $foundSession['total_questions'] = $foundTest ? count($foundTest['questions']) : 0;

    $submissions = [];
    $totalScoreSum = 0;

    foreach ($db['submissions'] as $sub) {
        if ($sub['session_id'] === $sessionId) {
            $submissions[] = $sub;
            $totalScoreSum += $sub['score'];
        }
    }

    usort($submissions, function($a, $b) {
        return strtotime($b['submitted_at']) - strtotime($a['submitted_at']);
    });

    $totalStudents = count($submissions);
    $avgScore = $totalStudents > 0 ? round($totalScoreSum / $totalStudents, 1) : 0;

    echo json_encode([
        'session' => $foundSession,
        'total_students' => $totalStudents,
        'average_score' => $avgScore,
        'submissions' => $submissions
    ]);
}
