/**
 * vio.zece.info - Solid Frontend Architecture
 * Object-oriented JS for maintainability, stability, and clean state management.
 */

class VioApp {
    constructor() {
        this.state = {
            mode: 'teacher', // 'teacher' | 'student'
            currentCode: null,
            currentShortUrl: null,
            currentTargetUrl: null,
            qrInstance: null,
            projectorQrInstance: null
        };

        this.elements = {
            // Tabs
            tabTeacher: document.getElementById('tab-teacher'),
            tabStudent: document.getElementById('tab-student'),
            viewTeacher: document.getElementById('view-teacher'),
            viewStudent: document.getElementById('view-student'),
            
            // Teacher Form
            urlInput: document.getElementById('url-input'),
            urlHelp: document.getElementById('url-help'),
            btnGenerate: document.getElementById('btn-generate'),
            
            // Results
            resultsPanel: document.getElementById('results-panel'),
            shortCodeText: document.getElementById('short-code-text'),
            domainPrefix: document.getElementById('domain-prefix'),
            btnOpenLink: document.getElementById('btn-open-link'),
            qrContainer: document.getElementById('qrcode-container'),
            
            // Student Form
            studentInput: document.getElementById('student-code-input'),
            studentHelp: document.getElementById('student-help'),
            btnStudentSubmit: document.getElementById('btn-student-submit'),
            
            // Projector
            modal: document.getElementById('projector-modal'),
            projDomain: document.getElementById('proj-domain'),
            projCode: document.getElementById('proj-code'),
            projQr: document.getElementById('proj-qr'),
            
            // Toast
            toast: document.getElementById('toast')
        };

        this.initEventListeners();
        this.checkUrlParams();
    }

    initEventListeners() {
        // Real-time URL validation
        this.elements.urlInput.addEventListener('input', () => {
            this.validateUrl(this.elements.urlInput.value);
        });

        this.elements.urlInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.generateLink();
        });

        // Real-time Student Code validation
        this.elements.studentInput.addEventListener('input', (e) => {
            const val = e.target.value.toUpperCase();
            e.target.value = val;
            if (val.length >= 4) {
                this.studentSubmit();
            } else {
                this.setHelp('studentHelp', 'Așteaptă introducerea completă...', '');
            }
        });

        this.elements.studentInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.studentSubmit();
        });

        // Global ESC key for projector modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeProjectorMode();
        });
    }

    checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const code = urlParams.get('c') || urlParams.get('code');
        if (code) {
            window.location.href = `redirect.php?c=${encodeURIComponent(code)}`;
        }
    }

    switchMode(mode) {
        this.state.mode = mode;
        if (mode === 'teacher') {
            this.elements.tabTeacher.classList.add('active');
            this.elements.tabStudent.classList.remove('active');
            this.elements.viewTeacher.classList.remove('hidden');
            this.elements.viewStudent.classList.add('hidden');
            this.elements.urlInput.focus();
        } else {
            this.elements.tabTeacher.classList.remove('active');
            this.elements.tabStudent.classList.add('active');
            this.elements.viewTeacher.classList.add('hidden');
            this.elements.viewStudent.classList.remove('hidden');
            this.elements.studentInput.focus();
        }
    }

    setHelp(elementName, message, type = '') {
        const el = this.elements[elementName];
        el.textContent = message;
        el.className = `help-text ${type}`;
    }

    normalizeUrl(str) {
        let url = (str || '').trim();
        if (!url) return null;
        if (!/^https?:\/\//i.test(url)) {
            url = 'https://' + url;
        }
        try {
            const parsed = new URL(url);
            if (parsed.hostname && parsed.hostname.includes('.')) {
                return url;
            }
            return null;
        } catch (e) {
            return null;
        }
    }

    validateUrl(input) {
        if (!input.trim()) {
            this.setHelp('urlHelp', 'Introduceți adresa completă a testului.');
            return false;
        }
        const validUrl = this.normalizeUrl(input);
        if (validUrl) {
            this.setHelp('urlHelp', 'Adresă web validă.', 'success');
            return true;
        } else {
            this.setHelp('urlHelp', 'Format URL invalid. Verificați link-ul introdus.', 'error');
            return false;
        }
    }

    async pasteFromClipboard() {
        try {
            const text = await navigator.clipboard.readText();
            if (text) {
                this.elements.urlInput.value = text.trim();
                if (this.validateUrl(text)) {
                    this.generateLink();
                }
            }
        } catch (err) {
            this.showToast('Nu s-a putut lipi automat. Folosiți Ctrl+V.');
            this.elements.urlInput.focus();
        }
    }

    async generateLink() {
        const rawUrl = this.elements.urlInput.value;
        const validUrl = this.normalizeUrl(rawUrl);

        if (!validUrl) {
            this.validateUrl(rawUrl);
            this.elements.urlInput.focus();
            return;
        }

        this.elements.btnGenerate.disabled = true;
        this.elements.btnGenerate.textContent = 'Se procesează...';

        try {
            const response = await fetch('api/links.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url: validUrl })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                this.state.currentCode = data.code;
                this.state.currentShortUrl = data.short_url;
                this.state.currentTargetUrl = data.target_url;
                this.renderResults();
                this.showToast('Link creat cu succes!');
            } else {
                throw new Error(data.error || 'Eroare la generare.');
            }
        } catch (error) {
            console.error('API Error:', error);
            this.setHelp('urlHelp', error.message, 'error');
        } finally {
            this.elements.btnGenerate.disabled = false;
            this.elements.btnGenerate.textContent = 'Generează Cod & QR';
        }
    }

    renderResults() {
        this.elements.resultsPanel.classList.remove('hidden');
        
        // Setup text
        const host = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || !window.location.hostname
            ? 'vio.zece.info' : window.location.host;
            
        this.elements.domainPrefix.textContent = host + '/';
        this.elements.shortCodeText.textContent = this.state.currentCode;
        
        this.elements.btnOpenLink.href = this.state.currentTargetUrl;

        // Generate Standard QR
        this.elements.qrContainer.innerHTML = '';
        this.state.qrInstance = new QRCode(this.elements.qrContainer, {
            text: this.state.currentShortUrl,
            width: 280,
            height: 280,
            colorDark: '#111827',
            colorLight: '#ffffff',
            correctLevel: 2 // QRCode.CorrectLevel.H
        });

        // Scroll to results
        this.elements.resultsPanel.scrollIntoView({ behavior: 'smooth' });
    }

    resetForm() {
        this.elements.urlInput.value = '';
        this.setHelp('urlHelp', 'Introduceți adresa completă a testului.');
        this.elements.resultsPanel.classList.add('hidden');
        this.state.currentCode = null;
        this.elements.urlInput.focus();
    }

    async copyShortLink() {
        if (!this.state.currentShortUrl) return;
        try {
            await navigator.clipboard.writeText(this.state.currentShortUrl);
            this.showToast('Link copiat în clipboard!');
        } catch (err) {
            // Fallback for older browsers
            const textArea = document.createElement("textarea");
            textArea.value = this.state.currentShortUrl;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand("Copy");
            textArea.remove();
            this.showToast('Link copiat!');
        }
    }

    openProjectorMode() {
        if (!this.state.currentCode) return;
        
        const host = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || !window.location.hostname
            ? 'vio.zece.info' : window.location.host;

        this.elements.projDomain.textContent = host + '/';
        this.elements.projCode.textContent = this.state.currentCode;
        
        this.elements.projQr.innerHTML = '';
        this.state.projectorQrInstance = new QRCode(this.elements.projQr, {
            text: this.state.currentShortUrl,
            width: 500,
            height: 500,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: 2 // QRCode.CorrectLevel.H
        });

        this.elements.modal.classList.add('active');
        
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().catch(e => console.log('Fullscreen rejected.'));
        }
    }

    closeProjectorMode() {
        this.elements.modal.classList.remove('active');
        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().catch(e => {});
        }
    }

    async studentSubmit() {
        const code = this.elements.studentInput.value.trim().toUpperCase();
        if (code.length < 3) return;

        this.elements.btnStudentSubmit.disabled = true;
        this.elements.btnStudentSubmit.textContent = 'Se verifică...';
        this.setHelp('studentHelp', 'Se verifică codul...');

        try {
            const response = await fetch(`api/links.php?code=${encodeURIComponent(code)}`);
            const data = await response.json();

            if (response.ok && data.success) {
                this.setHelp('studentHelp', 'Cod valid! Redirecționare...', 'success');
                setTimeout(() => {
                    window.location.href = data.target_url;
                }, 400);
            } else {
                throw new Error(data.error || 'Codul nu a fost găsit.');
            }
        } catch (error) {
            this.setHelp('studentHelp', error.message, 'error');
            this.elements.btnStudentSubmit.disabled = false;
            this.elements.btnStudentSubmit.textContent = 'Accesează Formularul';
        }
    }

    showToast(message) {
        this.elements.toast.textContent = message;
        this.elements.toast.classList.add('visible');
        
        if (this.toastTimeout) clearTimeout(this.toastTimeout);
        this.toastTimeout = setTimeout(() => {
            this.elements.toast.classList.remove('visible');
        }, 3000);
    }
}

// Initialize Application
const app = new VioApp();
