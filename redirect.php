<?php
/**
 * vio.zece.info - Short URL Redirect
 * Uses FileDB for fast, robust routing and analytics.
 */

$code = isset($_GET['c']) ? trim($_GET['c']) : (isset($_GET['code']) ? trim($_GET['code']) : '');

if (!$code && !empty($_SERVER['REQUEST_URI'])) {
    $uriPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $parts = explode('/', $uriPath);
    $lastPart = end($parts);
    if ($lastPart && $lastPart !== 'redirect.php') {
        $code = $lastPart;
    }
}

if (!$code) {
    header('Location: index.html');
    exit();
}

require_once __DIR__ . '/api/db.php';
global $db;

$targetUrl = null;

try {
    $link = $db->getLinkByCode($code);
    if ($link) {
        $targetUrl = $link['target_url'];
        // Increment click count safely using flock
        $db->incrementClick($code);
    }
} catch (Exception $e) {
    error_log("Redirect Error: " . $e->getMessage());
}

if ($targetUrl) {
    // Fast 302 redirect
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
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #fafafa; color: #333; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
        .box { background: #fff; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #eaeaea; }
        .spinner { width: 32px; height: 32px; border: 3px solid #f3f3f3; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1.5rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
        a { color: #2563eb; text-decoration: none; font-weight: 500; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner"></div>
        <h2 style="margin: 0 0 0.5rem; font-size: 1.25rem;">Se deschide...</h2>
        <a href="<?= htmlspecialchars($targetUrl) ?>">Apasă aici dacă redirecționarea nu funcționează automat</a>
    </div>
</body>
</html>
<?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Negăsit - vio.zece.info</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f9fafb; color: #111827; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
        .card { background: #ffffff; padding: 3rem 2rem; border-radius: 16px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.01); border: 1px solid #f3f4f6; max-width: 420px; width: 100%; }
        .icon { font-size: 3rem; margin-bottom: 1rem; color: #ef4444; }
        h1 { margin: 0 0 0.5rem; font-size: 1.5rem; font-weight: 700; }
        p { color: #6b7280; line-height: 1.5; margin-bottom: 2rem; font-size: 1rem; }
        .code-display { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 1.25rem; font-weight: 700; color: #4b5563; background: #f3f4f6; padding: 0.5rem 1rem; border-radius: 8px; display: inline-block; margin-bottom: 1.5rem; letter-spacing: 2px; }
        .btn { display: inline-block; background: #2563eb; color: white; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 500; transition: background-color 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠️</div>
        <h1>Link-ul nu există</h1>
        <div class="code-display"><?= htmlspecialchars($code) ?></div>
        <p>Codul pe care l-ai introdus nu a fost găsit. Te rugăm să verifici dacă l-ai scris corect.</p>
        <a href="index.html" class="btn">Întoarce-te la pagina principală</a>
    </div>
</body>
</html>
