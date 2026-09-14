<?php
/**
 * Local development router for PHP built-in web server:
 * php -S localhost:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// Block direct access to data files and sensitive extensions
if (str_starts_with($uri, '/data') || preg_match('/\.(db|sqlite|json|lock)$/i', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden: Direct access to data files is restricted.";
    exit;
}

// If static file exists, serve it with no-cache headers
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimes = [
        'js' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png'
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    readfile($filePath);
    exit;
}

// Check for straight short code (e.g. /1234)
$trimmed = trim($uri, '/');
if (!empty($trimmed) && preg_match('/^[a-zA-Z0-9_-]+$/', $trimmed)) {
    $_GET['c'] = $trimmed;
    require __DIR__ . '/redirect.php';
    exit;
}

// Default to index.php (or index.html fallback)
if (file_exists(__DIR__ . '/index.php')) {
    require __DIR__ . '/index.php';
} else {
    require __DIR__ . '/index.html';
}
