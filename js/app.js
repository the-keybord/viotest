/**
 * vio.zece.info - Application Logic
 * Fast URL validation, big QR code generator, short code sharing, and projector mode.
 */

let currentCode = '';
let currentShortUrl = '';
let currentTargetUrl = '';
let qrInstance = null;
let projectorQrInstance = null;

const STORAGE_KEY = 'vio_zece_recent_links';

document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

function initApp() {
    const urlInput = document.getElementById('url-input');
    const studentInput = document.getElementById('student-code-input');

    // Auto-clean & validate on input
    if (urlInput) {
        urlInput.addEventListener('input', () => {
            toggleClearBtn();
            const val = urlInput.value.trim();
            if (val.length > 8) {
                validateUrlLive(val);
            } else {
                clearValidationMsg();
            }
        });

        urlInput.addEventListener('paste', (e) => {
            setTimeout(() => {
                toggleClearBtn();
                const pasted = urlInput.value.trim();
                if (validateUrlLive(pasted)) {
                    handleGenerate();
                }
            }, 50);
        });

        urlInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleGenerate();
            }
        });
    }

    // Student input handling
    if (studentInput) {
        studentInput.addEventListener('input', () => {
            const val = studentInput.value.trim().toUpperCase();
            studentInput.value = val;
            if (val.length >= 4) {
                // Auto submit when 4 or more digits typed
                handleStudentSubmit();
            }
        });

        studentInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleStudentSubmit();
            }
        });
    }

    // Keyboard shortcuts: ESC closes projector
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeProjectorMode();
        }
    });

    // Check if URL has ?code= or ?c= parameter in query string
    const urlParams = new URLSearchParams(window.location.search);
    const codeParam = urlParams.get('c') || urlParams.get('code');
    if (codeParam) {
        resolveAndRedirectCode(codeParam);
    }

    loadRecentLinks();
}

/**
 * Toggle between Teacher and Student modes
 */
function switchMode(mode) {
    const tabTeacher = document.getElementById('tab-teacher');
    const tabStudent = document.getElementById('tab-student');
    const teacherView = document.getElementById('teacher-view');
    const studentView = document.getElementById('student-view');

    if (mode === 'teacher') {
        tabTeacher.classList.add('active');
        tabStudent.classList.remove('active');
        teacherView.style.display = 'block';
        studentView.style.display = 'none';
        document.getElementById('url-input').focus();
    } else {
        tabTeacher.classList.remove('active');
        tabStudent.classList.add('active');
        teacherView.style.display = 'none';
        studentView.style.display = 'block';
        document.getElementById('student-code-input').focus();
    }
}

/**
 * Live URL validation helper
 */
function validateUrlLive(inputUrl) {
    const msgEl = document.getElementById('validation-msg');
    const container = document.getElementById('input-container');

    const normalized = normalizeUrlString(inputUrl);
    if (!normalized.isValid) {
        container.classList.add('has-error');
        msgEl.className = 'validation-message error';
        msgEl.innerHTML = '⚠️ Te rugăm să introduci un URL valid (ex: https://forms.gle/...)';
        return false;
    }

    container.classList.remove('has-error');
    msgEl.className = 'validation-message success';
    msgEl.innerHTML = '✓ Adresă web validă gata de generat';
    return true;
}

function clearValidationMsg() {
    const msgEl = document.getElementById('validation-msg');
    const container = document.getElementById('input-container');
    container.classList.remove('has-error');
    msgEl.textContent = '';
    msgEl.className = 'validation-message';
}

function normalizeUrlString(str) {
    let url = (str || '').trim();
    if (!url) return { isValid: false, url: '' };

    // Auto prepend https if missing protocol
    if (!/^https?:\/\//i.test(url)) {
        url = 'https://' + url;
    }

    try {
        const parsed = new URL(url);
        const hasValidHost = parsed.hostname && parsed.hostname.includes('.');
        return { isValid: hasValidHost, url: url };
    } catch (e) {
        return { isValid: false, url: '' };
    }
}

/**
 * Handle Paste button
 */
async function pasteFromClipboard() {
    try {
        const text = await navigator.clipboard.readText();
        if (text) {
            const input = document.getElementById('url-input');
            input.value = text.trim();
            toggleClearBtn();
            if (validateUrlLive(input.value)) {
                handleGenerate();
            }
        }
    } catch (err) {
        showToast('Apasă Ctrl+V pentru a lipi link-ul.');
        document.getElementById('url-input').focus();
    }
}

function clearInput() {
    const input = document.getElementById('url-input');
    input.value = '';
    toggleClearBtn();
    clearValidationMsg();
    input.focus();
}

function toggleClearBtn() {
    const input = document.getElementById('url-input');
    const btnClear = document.getElementById('btn-clear');
    btnClear.style.display = input.value.trim().length > 0 ? 'inline-flex' : 'none';
}

/**
 * Main action: Generate QR & Short Code
 */
async function handleGenerate() {
    const input = document.getElementById('url-input');
    const rawUrl = input.value.trim();

    const normalized = normalizeUrlString(rawUrl);
    if (!normalized.isValid) {
        validateUrlLive(rawUrl);
        input.focus();
        return;
    }

    const targetUrl = normalized.url;
    const btnGen = document.getElementById('btn-generate');
    btnGen.disabled = true;
    btnGen.innerHTML = '<span>⏳ Se generează codul...</span>';

    try {
        let code = '';
        let shortUrl = '';

        // Call backend API
        try {
            const res = await fetch('api/links.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url: targetUrl })
            });

            if (res.ok) {
                const data = await res.json();
                if (data.success) {
                    code = data.code;
                    shortUrl = data.short_url;
                }
            }
        } catch (apiErr) {
            console.warn('API call fallback to client code generator:', apiErr);
        }

        // Client-side fallback if server API is unavailable
        if (!code) {
            code = Math.floor(1000 + Math.random() * 9000).toString();
            const host = window.location.host || 'vio.zece.info';
            shortUrl = `${window.location.protocol}//${host}/${code}`;
        }

        currentCode = code;
        currentShortUrl = shortUrl;
        currentTargetUrl = targetUrl;

        // Display results
        renderResults(code, shortUrl, targetUrl);

        // Save in recent links
        saveToRecent(code, shortUrl, targetUrl);

        showToast('✨ Codul QR și link-ul au fost generate!');
    } catch (err) {
        console.error('Error generating link:', err);
        showToast('A apărut o eroare la generare.');
    } finally {
        btnGen.disabled = false;
        btnGen.innerHTML = '<span>⚡ Generează QR & Cod Scurt</span>';
    }
}

/**
 * Render the QR Code and Short Link in the UI
 */
function renderResults(code, shortUrl, targetUrl) {
    const resultsSection = document.getElementById('results-section');
    const shortCodeBadge = document.getElementById('short-code-badge');
    const domainPrefix = document.getElementById('domain-prefix');
    const destPill = document.getElementById('dest-pill');
    const destAnchor = document.getElementById('dest-link-anchor');
    const qrContainer = document.getElementById('qrcode-container');

    // Set short link display
    const hostName = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || !window.location.hostname)
        ? 'vio.zece.info'
        : (window.location.host || 'vio.zece.info');
    domainPrefix.textContent = hostName + '/';
    shortCodeBadge.textContent = code;

    // Set destination preview
    destAnchor.href = targetUrl;
    destAnchor.textContent = targetUrl.length > 55 ? targetUrl.substring(0, 52) + '...' : targetUrl;
    destPill.title = targetUrl;

    // Render Big Scannable QR Code
    // Note: We encode the short link or direct link. Encoding the short link (vio.zece.info/1234)
    // creates a lower-density, much larger-pixel QR code that is remarkably easy to scan across a classroom!
    qrContainer.innerHTML = '';
    
    // Size: 320x320 for high visibility
    qrInstance = new QRCode(qrContainer, {
        text: shortUrl,
        width: 320,
        height: 320,
        colorDark: '#0b0f19',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });

    resultsSection.style.display = 'flex';
    resultsSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Copy the short link to clipboard
 */
async function copyShortLink() {
    if (!currentShortUrl) return;
    try {
        await navigator.clipboard.writeText(currentShortUrl);
        showToast('📋 Link-ul scurt a fost copiat în clipboard!');
    } catch (err) {
        // Fallback
        const temp = document.createElement('input');
        temp.value = currentShortUrl;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        showToast('📋 Link-ul scurt a fost copiat!');
    }
}

/**
 * Open destination URL in a new tab
 */
function openDestinationUrl() {
    if (currentTargetUrl) {
        window.open(currentTargetUrl, '_blank', 'noopener,noreferrer');
    }
}

/**
 * Download generated QR Code image
 */
function downloadQrImage() {
    const qrContainer = document.getElementById('qrcode-container');
    const img = qrContainer.querySelector('img');
    const canvas = qrContainer.querySelector('canvas');

    let dataUrl = '';
    if (canvas) {
        dataUrl = canvas.toDataURL('image/png');
    } else if (img && img.src) {
        dataUrl = img.src;
    }

    if (!dataUrl) {
        showToast('Nu s-a putut descărca imaginea.');
        return;
    }

    const a = document.createElement('a');
    a.href = dataUrl;
    a.download = `vio-zece-qr-${currentCode || 'code'}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    showToast('💾 Imaginea QR a fost descărcată.');
}

/**
 * Reset form for a new link
 */
function resetForm() {
    const input = document.getElementById('url-input');
    input.value = '';
    toggleClearBtn();
    clearValidationMsg();
    document.getElementById('results-section').style.display = 'none';
    input.focus();
}

/**
 * Projector / Fullscreen Mode
 */
function openProjectorMode() {
    if (!currentCode) return;

    const modal = document.getElementById('projector-modal');
    const codeText = document.getElementById('projector-code-text');
    const projContainer = document.getElementById('projector-qr-container');

    codeText.textContent = currentCode;
    projContainer.innerHTML = '';

    // Create huge QR code for classroom projector (450px)
    projectorQrInstance = new QRCode(projContainer, {
        text: currentShortUrl,
        width: 440,
        height: 440,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });

    modal.classList.add('active');

    // Attempt native browser fullscreen if supported
    if (document.documentElement.requestFullscreen) {
        document.documentElement.requestFullscreen().catch(() => {});
    }
}

function closeProjectorMode() {
    const modal = document.getElementById('projector-modal');
    modal.classList.remove('active');
    if (document.fullscreenElement && document.exitFullscreen) {
        document.exitFullscreen().catch(() => {});
    }
}

/**
 * Student quick join: verify code and redirect
 */
async function handleStudentSubmit() {
    const input = document.getElementById('student-code-input');
    const msgEl = document.getElementById('student-code-msg');
    const btn = document.getElementById('btn-student-submit');
    const code = input.value.trim().toUpperCase();

    if (!code || code.length < 3) {
        msgEl.className = 'validation-message error';
        msgEl.textContent = 'Te rugăm să introduci codul complet primit de la profesor.';
        input.focus();
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span>⏳ Se verifică codul...</span>';
    msgEl.textContent = '';

    try {
        const res = await fetch(`api/links.php?code=${encodeURIComponent(code)}`);
        const data = await res.json();

        if (res.ok && data.success && data.target_url) {
            msgEl.className = 'validation-message success';
            msgEl.textContent = '✓ Cod valid! Se deschide formularul...';
            setTimeout(() => {
                window.location.href = data.target_url;
            }, 400);
        } else {
            msgEl.className = 'validation-message error';
            msgEl.textContent = '❌ Codul nu a fost găsit. Te rugăm să verifici codul cu profesorul.';
            btn.disabled = false;
            btn.innerHTML = '<span>🚀 Deschide Formularul</span>';
        }
    } catch (err) {
        // Direct redirect attempt via server rewrite
        window.location.href = `redirect.php?c=${encodeURIComponent(code)}`;
    }
}

async function resolveAndRedirectCode(code) {
    window.location.href = `redirect.php?c=${encodeURIComponent(code)}`;
}

/**
 * LocalStorage Recent Links Management
 */
function saveToRecent(code, shortUrl, targetUrl) {
    try {
        let recents = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        // Deduplicate
        recents = recents.filter(item => item.code !== code && item.targetUrl !== targetUrl);
        recents.unshift({
            code: code,
            shortUrl: shortUrl,
            targetUrl: targetUrl,
            timestamp: new Date().toISOString()
        });
        // Keep max 8 items
        recents = recents.slice(0, 8);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(recents));
        loadRecentLinks();
    } catch (e) {
        console.warn('LocalStorage unavailable');
    }
}

function loadRecentLinks() {
    const container = document.getElementById('recent-section');
    const list = document.getElementById('recent-list');
    if (!container || !list) return;

    try {
        const recents = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        if (recents.length === 0) {
            container.style.display = 'none';
            return;
        }

        list.innerHTML = '';
        recents.forEach(item => {
            const el = document.createElement('div');
            el.className = 'recent-item';
            el.innerHTML = `
                <div>
                    <div class="recent-code">${escapeHtml(item.code)}</div>
                    <div class="recent-url">${escapeHtml(item.targetUrl)}</div>
                </div>
                <div style="font-size: 0.85rem; color: #818cf8; font-weight: 600;">Afișează QR ↗</div>
            `;
            el.addEventListener('click', () => {
                currentCode = item.code;
                currentShortUrl = item.shortUrl;
                currentTargetUrl = item.targetUrl;
                document.getElementById('url-input').value = item.targetUrl;
                toggleClearBtn();
                renderResults(item.code, item.shortUrl, item.targetUrl);
            });
            list.appendChild(el);
        });

        container.style.display = 'block';
    } catch (e) {
        container.style.display = 'none';
    }
}

function clearRecentLinks() {
    localStorage.removeItem(STORAGE_KEY);
    document.getElementById('recent-section').style.display = 'none';
    showToast('Istoricul a fost șters.');
}

/**
 * Toast Notification Utility
 */
let toastTimeout = null;
function showToast(text) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = text;
    toast.classList.add('show');

    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 2800);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}
