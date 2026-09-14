<?php
// Prevent caching of the main page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Dynamic cache-busting timestamps for scripts and styles
$vCss = file_exists(__DIR__ . '/css/styles.css') ? filemtime(__DIR__ . '/css/styles.css') : time();
$vQr = file_exists(__DIR__ . '/js/qrcode.min.js') ? filemtime(__DIR__ . '/js/qrcode.min.js') : time();
$vApp = file_exists(__DIR__ . '/js/app.js') ? filemtime(__DIR__ . '/js/app.js') : time();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Link & QR Generator</title>
    <meta name="description" content="Platformă minimalistă pentru scurtarea link-urilor și generare QR code.">
    <link rel="stylesheet" href="css/styles.css?v=<?= $vCss ?>">
</head>
<body>

    <main class="container">
        <div class="card">
            <header class="card-header">
                <h1 class="title">Link & QR</h1>
                <p class="subtitle">Introdu un link pentru a genera codul scurt și codul QR.</p>
            </header>

            <form id="url-form" class="url-form" novalidate>
                <div class="input-group">
                    <input 
                        type="url" 
                        id="url-input" 
                        name="url"
                        class="url-input" 
                        placeholder="Lipește link-ul aici (ex: https://exemplu.ro)" 
                        autocomplete="off" 
                        spellcheck="false"
                        required
                    >
                    <button type="submit" id="btn-submit" class="btn-submit">
                        Generează
                    </button>
                </div>
                <div id="error-message" class="error-message" aria-live="polite"></div>
            </form>

            <!-- Results Section (hidden by default) -->
            <div id="result-container" class="result-container hidden">
                <div class="result-divider"></div>

                <!-- Short Link Result -->
                <div class="result-block">
                    <span class="result-label">Link Scurt:</span>
                    <div class="short-link-box">
                        <a id="short-link-anchor" href="#" target="_blank" class="short-link-text"></a>
                        <button type="button" id="btn-copy" class="btn-copy" title="Copiază link-ul">
                            <svg class="copy-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span id="copy-btn-text">Copiază</span>
                        </button>
                    </div>
                </div>

                <!-- QR Code Result -->
                <div class="result-block qr-block">
                    <span class="result-label">Cod QR:</span>
                    <div class="qr-wrapper">
                        <div id="qrcode" class="qrcode"></div>
                    </div>
                    <button type="button" id="btn-download-qr" class="btn-download">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Descarcă QR
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script src="js/qrcode.min.js?v=<?= $vQr ?>"></script>
    <script src="js/app.js?v=<?= $vApp ?>"></script>
</body>
</html>
