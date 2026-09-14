<?php
/**
 * Short URL Redirection Handler
 * Resolves short code (e.g. /1234) and redirects to the original URL.
 */

$code = isset($_GET['c']) ? trim($_GET['c']) : (isset($_GET['code']) ? trim($_GET['code']) : '');

if (!$code && !empty($_SERVER['REQUEST_URI'])) {
    $uriPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $parts = explode('/', $uriPath);
    $lastPart = end($parts);
    if ($lastPart && $lastPart !== 'redirect.php' && $lastPart !== 'index.html' && $lastPart !== 'index.php') {
        $code = $lastPart;
    }
}

if (!$code) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/api/db.php';
global $db;

$targetUrl = $db->getLinkByCode($code);

if ($targetUrl) {
    header("Location: " . $targetUrl, true, 302);
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    ?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($targetUrl) ?>">
    <title>Redirecționare...</title>
    <script>window.location.replace(<?= json_encode($targetUrl) ?>);</script>
</head>
<body>
    <p>Redirecționare către <a href="<?= htmlspecialchars($targetUrl) ?>"><?= htmlspecialchars($targetUrl) ?></a>...</p>
</body>
</html>
<?php
    exit;
} else {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link inexistent</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #fafafa;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 400px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        h1 { font-size: 1.5rem; margin: 0 0 0.5rem; }
        p { color: #6b7280; margin: 0 0 1.5rem; font-size: 0.95rem; }
        a {
            display: inline-block;
            background: #111827;
            color: #ffffff;
            text-decoration: none;
            padding: 0.625rem 1.25rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        a:hover { background: #374151; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Link negăsit</h1>
        <p>Codul solicitat (<strong><?= htmlspecialchars($code) ?></strong>) nu a fost găsit sau a expirat.</p>
        <a href="/">Mergi la pagina principală</a>
    </div>
</body>
</html>
<?php
    exit;
}
