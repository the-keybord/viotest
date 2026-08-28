<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

if ($useSqlite) {
    // --- SQLITE LOGIC ---
    $stmt = $pdo->query("
        SELECT 
            s.id as session_id,
            s.code as session_code,
            s.status as status,
            s.created_at as created_at,
            t.id as test_id,
            t.title as test_title,
            t.duration_minutes as duration_minutes,
            COUNT(sub.id) as total_students,
            COALESCE(ROUND(AVG(sub.score), 1), 0) as average_score
        FROM sessions s
        JOIN tests t ON s.test_id = t.id
        LEFT JOIN submissions sub ON s.id = sub.session_id
        GROUP BY s.id
        ORDER BY s.created_at DESC
    ");
    $history = $stmt->fetchAll();

    echo json_encode($history);
} else {
    // --- JSON FALLBACK LOGIC ---
    $db = getJsonDbData();
    $history = [];

    foreach ($db['sessions'] as $s) {
        $foundTest = null;
        foreach ($db['tests'] as $t) {
            if ($t['id'] === $s['test_id']) {
                $foundTest = $t;
                break;
            }
        }

        $subCount = 0;
        $scoreSum = 0;
        foreach ($db['submissions'] as $sub) {
            if ($sub['session_id'] === $s['id']) {
                $subCount++;
                $scoreSum += $sub['score'];
            }
        }

        $avgScore = $subCount > 0 ? round($scoreSum / $subCount, 1) : 0;

        $history[] = [
            'session_id' => $s['id'],
            'session_code' => $s['code'],
            'status' => $s['status'],
            'created_at' => $s['created_at'],
            'test_id' => $s['test_id'],
            'test_title' => $foundTest ? $foundTest['title'] : 'Test',
            'duration_minutes' => $foundTest ? $foundTest['duration_minutes'] : 5,
            'total_students' => $subCount,
            'average_score' => $avgScore
        ];
    }

    usort($history, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    echo json_encode($history);
}
