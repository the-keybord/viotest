/**
 * Minimalist URL Shortener & QR Generator
 * Vanilla JavaScript - No plugins, no external dependencies
 */

(function () {
    'use strict';

    const container = document.getElementById('main-container');
    const card = document.getElementById('main-card');
    const form = document.getElementById('url-form');
    const input = document.getElementById('url-input');
    const submitBtn = document.getElementById('btn-submit');
    const errorMsg = document.getElementById('error-message');
    const shortLinkSection = document.getElementById('short-link-section');
    const qrPanel = document.getElementById('qr-panel');
    const shortLinkAnchor = document.getElementById('short-link-anchor');
    const copyBtn = document.getElementById('btn-copy');
    const copyBtnText = document.getElementById('copy-btn-text');
    const qrContainer = document.getElementById('qrcode');
    const downloadQrBtn = document.getElementById('btn-download-qr');

    let currentQrCode = null;
    let currentShortUrl = '';

    // URL validation helper
    function isValidUrl(string) {
        try {
            let urlToCheck = string.trim();
            if (!/^https?:\/\//i.test(urlToCheck)) {
                urlToCheck = 'https://' + urlToCheck;
            }
            const parsed = new URL(urlToCheck);
            return (parsed.protocol === 'http:' || parsed.protocol === 'https:') && parsed.hostname.includes('.');
        } catch (_) {
            return false;
        }
    }

    function showError(message) {
        errorMsg.textContent = message;
        errorMsg.classList.add('visible');
        input.classList.add('input-error');
    }

    function clearError() {
        errorMsg.textContent = '';
        errorMsg.classList.remove('visible');
        input.classList.remove('input-error');
    }

    // Handle form submit
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearError();

        let rawUrl = input.value.trim();
        if (!rawUrl) {
            showError('Vă rugăm să introduceți un link.');
            input.focus();
            return;
        }

        if (!isValidUrl(rawUrl)) {
            showError('Introduceți un link web valid (ex: https://exemplu.ro).');
            input.focus();
            return;
        }

        // Disable button while processing
        submitBtn.disabled = true;
        submitBtn.textContent = 'Se generează...';

        try {
            const response = await fetch('api/shorten.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ url: rawUrl })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'A apărut o eroare la generare.');
            }

            // Display short link
            currentShortUrl = data.short_url;
            shortLinkAnchor.href = data.short_url;
            shortLinkAnchor.textContent = data.short_url;

            // Generate high-resolution QR Code (scales cleanly to big landscape display)
            qrContainer.innerHTML = '';
            currentQrCode = new QRCode(qrContainer, {
                text: data.short_url,
                width: 480,
                height: 480,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });

            // Activate result state & layout expansion
            container.classList.add('has-results');
            card.classList.add('has-results');
            shortLinkSection.classList.remove('hidden');
            qrPanel.classList.remove('hidden');

        } catch (err) {
            showError(err.message || 'Nu s-a putut genera link-ul.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Generează';
        }
    });

    // Clear error state when typing
    input.addEventListener('input', function () {
        if (errorMsg.classList.contains('visible')) {
            clearError();
        }
    });

    // Copy to clipboard
    copyBtn.addEventListener('click', async function () {
        if (!currentShortUrl) return;

        try {
            await navigator.clipboard.writeText(currentShortUrl);
            copyBtn.classList.add('copied');
            copyBtnText.textContent = 'Copiat!';
        } catch (_) {
            // Fallback for older browsers
            const temp = document.createElement('textarea');
            temp.value = currentShortUrl;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            copyBtn.classList.add('copied');
            copyBtnText.textContent = 'Copiat!';
        }

        setTimeout(function () {
            copyBtn.classList.remove('copied');
            copyBtnText.textContent = 'Copiază';
        }, 2000);
    });

    // Download QR Code
    downloadQrBtn.addEventListener('click', function () {
        const canvas = qrContainer.querySelector('canvas');
        const img = qrContainer.querySelector('img');

        let dataUrl = '';
        if (canvas) {
            dataUrl = canvas.toDataURL('image/png');
        } else if (img && img.src) {
            dataUrl = img.src;
        }

        if (!dataUrl) return;

        const a = document.createElement('a');
        a.href = dataUrl;
        a.download = 'qrcode.png';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

})();
