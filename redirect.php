<?php
$code = isset($_GET['c']) ? trim($_GET['c']) : (isset($_GET['code']) ? trim($_GET['code']) : '');

// If code was not in query string, inspect PATH_INFO or REQUEST_URI
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

$dataDir = __DIR__ . '/data';
$linksFile = $dataDir . '/links.json';

$targetUrl = null;

if (file_exists($linksFile)) {
    $fp = fopen($linksFile, 'r+');
    if ($fp) {
        flock($fp, LOCK_EX);
        $content = stream_get_contents($fp);
        $data = json_decode($content, true);
        if (is_array($data) && isset($data['codes'])) {
            $matchedKey = null;
            if (isset($data['codes'][$code])) {
                $matchedKey = $code;
            } else {
                foreach ($data['codes'] as $k => $item) {
                    if (strcasecmp($k, $code) === 0) {
                        $matchedKey = $k;
                        break;
                    }
                }
            }

            if ($matchedKey) {
                $targetUrl = $data['codes'][$matchedKey]['url'];
                $data['codes'][$matchedKey]['clicks'] = ($data['codes'][$matchedKey]['clicks'] ?? 0) + 1;
                $data['codes'][$matchedKey]['last_click'] = date('c');

                rewind($fp);
                ftruncate($fp, 0);
                fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fflush($fp);
            }
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }
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
    <title>Redirecționare către formular...</title>
    <script>window.location.replace(<?= json_encode($targetUrl) ?>);</script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
        .box { background: #1e293b; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); max-width: 420px; }
        .spinner { width: 44px; height: 44px; border: 4px solid #334155; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1.5rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
        a { color: #60a5fa; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner"></div>
        <h2 style="margin: 0 0 0.5rem;">Se deschide link-ul...</h2>
        <p style="color: #94a3b8; font-size: 0.95rem; margin-bottom: 1.25rem;">Dacă nu ești redirecționat automat în câteva secunde:</p>
        <a href="<?= htmlspecialchars($targetUrl) ?>">Apasă aici pentru a continua</a>
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
    <title>Cod Negăsit - vio.zece.info</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
        .card { background: #1e293b; padding: 2.5rem; border-radius: 20px; border: 1px solid #334155; max-width: 450px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        .badge { display: inline-block; background: #ef444422; color: #ef4444; border: 1px solid #ef444455; padding: 0.4rem 1rem; border-radius: 999px; font-weight: 700; font-size: 0.9rem; margin-bottom: 1rem; }
        h1 { margin: 0 0 1rem; font-size: 1.75rem; }
        p { color: #94a3b8; line-height: 1.6; margin-bottom: 2rem; }
        .code-display { font-family: monospace; font-size: 2rem; font-weight: 800; color: #f59e0b; background: #0f172a; padding: 0.5rem 1.5rem; border-radius: 12px; display: inline-block; margin-bottom: 1.5rem; letter-spacing: 2px; }
        .btn { display: inline-block; background: #3b82f6; color: white; text-decoration: none; padding: 0.85rem 1.75rem; border-radius: 12px; font-weight: 600; transition: all 0.2s ease; }
        .btn:hover { background: #2563eb; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">Cod Inexistent</span>
        <h1>Link negăsit</h1>
        <div class="code-display"><?= htmlspecialchars($code) ?></div>
        <p>Codul pe care l-ai introdus nu există sau a expirat. Te rugăm să verifici codul primit de la profesor.</p>
        <a href="index.html" class="btn">Înapoi la vio.zece.info</a>
    </div>
</body>
</html>
