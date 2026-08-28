let activeSessionId = null;
let activeSessionCode = null;
let activeSessionTitle = null;
let resultsPollInterval = null;
let questionCount = 0;

document.addEventListener('DOMContentLoaded', () => {
    loadTests();
});

// Load available tests
async function loadTests() {
    const loadingEl = document.getElementById('tests-loading');
    const listEl = document.getElementById('tests-list');
    
    try {
        const res = await fetch('api/tests.php');
        const tests = await res.json();

        loadingEl.style.display = 'none';
        listEl.innerHTML = '';

        if (!Array.isArray(tests) || tests.length === 0) {
            listEl.innerHTML = `
                <div style="grid-column: 1/-1; text-align: center; color: var(--text-secondary); padding: 3rem; background: #ffffff; border-radius: var(--radius-lg); border: 2px dashed var(--border-color);">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📝</div>
                    <div style="font-weight: 700; font-size: 1.15rem; color: var(--text-primary);">Nu au fost create teste încă</div>
                    <p style="font-size: 0.95rem; margin-top: 0.25rem; color: var(--text-muted);">Apasă pe "+ Creează Test Nou" de mai sus pentru a construi primul tău test de lecție!</p>
                </div>
            `;
            return;
        }

        tests.forEach(test => {
            const card = document.createElement('div');
            card.style.cssText = 'background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; gap: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);';
            card.innerHTML = `
                <div>
                    <h3 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">${escapeHtml(test.title)}</h3>
                    <div style="display: flex; gap: 1rem; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;">
                        <span>⏱️ ${test.duration_minutes} min</span>
                        <span>❓ ${test.question_count} întrebări</span>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-primary btn-full" onclick="launchSession('${test.id}')">🖥️ Lansează Testul</button>
                </div>
            `;
            listEl.appendChild(card);
        });
    } catch (err) {
        loadingEl.innerText = 'Eroare la încărcarea testelor. Verifică conexiunea.';
        console.error(err);
    }
}

// Modal Handlers
function openCreateTestModal() {
    document.getElementById('create-modal').style.display = 'block';
    const container = document.getElementById('questions-container');
    container.innerHTML = '';
    questionCount = 0;
    
    // Pre-populate with 2 default question fields
    addQuestionField();
    addQuestionField();
}

function closeCreateTestModal() {
    document.getElementById('create-modal').style.display = 'none';
}

function addQuestionField() {
    questionCount++;
    const container = document.getElementById('questions-container');
    const qDiv = document.createElement('div');
    qDiv.className = 'question-card';
    qDiv.id = `q-card-${questionCount}`;
    qDiv.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <strong style="font-family: var(--font-heading); color: var(--accent-blue); font-size: 1.05rem;">Întrebarea #${questionCount}</strong>
            ${questionCount > 1 ? `<button type="button" onclick="removeQuestionField(${questionCount})" style="background:none; border:none; color: var(--accent-red); cursor:pointer; font-weight:600;">Șterge</button>` : ''}
        </div>
        <div class="form-group">
            <input type="text" class="form-input q-text" placeholder="Introdu textul întrebării..." required>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <div>
                <label class="form-label">Opțiunea A</label>
                <input type="text" class="form-input q-opt-a" placeholder="Varianta A" required>
            </div>
            <div>
                <label class="form-label">Opțiunea B</label>
                <input type="text" class="form-input q-opt-b" placeholder="Varianta B" required>
            </div>
            <div>
                <label class="form-label">Opțiunea C</label>
                <input type="text" class="form-input q-opt-c" placeholder="Varianta C" required>
            </div>
            <div>
                <label class="form-label">Opțiunea D</label>
                <input type="text" class="form-input q-opt-d" placeholder="Varianta D" required>
            </div>
        </div>
        <div class="form-group" style="margin-top: 0.75rem;">
            <label class="form-label">Opțiunea Corectă</label>
            <select class="form-select q-correct" required>
                <option value="A">Opțiunea A</option>
                <option value="B">Opțiunea B</option>
                <option value="C">Opțiunea C</option>
                <option value="D">Opțiunea D</option>
            </select>
        </div>
    `;
    container.appendChild(qDiv);
}

function removeQuestionField(id) {
    const el = document.getElementById(`q-card-${id}`);
    if (el) el.remove();
}

async function handleCreateTest(e) {
    e.preventDefault();
    const title = document.getElementById('test-title').value;
    const duration = document.getElementById('test-duration').value;

    const qCards = document.querySelectorAll('#questions-container .question-card');
    const questions = [];

    qCards.forEach(card => {
        const text = card.querySelector('.q-text').value;
        const a = card.querySelector('.q-opt-a').value;
        const b = card.querySelector('.q-opt-b').value;
        const c = card.querySelector('.q-opt-c').value;
        const d = card.querySelector('.q-opt-d').value;
        const correct = card.querySelector('.q-correct').value;

        if (text && a && b && c && d) {
            questions.push({ text, a, b, c, d, correct });
        }
    });

    if (questions.length === 0) {
        alert('Te rugăm să adaugi cel puțin o întrebare completă.');
        return;
    }

    try {
        const res = await fetch('api/tests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, duration_minutes: duration, questions })
        });
        const data = await res.json();
        if (data.success) {
            closeCreateTestModal();
            loadTests();
        } else {
            alert('Eroare la crearea testului: ' + (data.error || 'Eroare necunoscută'));
        }
    } catch (err) {
        alert('Eroare de conexiune cu serverul.');
        console.error(err);
    }
}

// LAUNCH SESSION & BIG SCREEN PROJECTOR MODE
async function launchSession(testId) {
    try {
        const res = await fetch('api/sessions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ test_id: testId })
        });
        const data = await res.json();

        if (data.success) {
            activeSessionId = data.session_id;
            activeSessionCode = data.code;
            activeSessionTitle = data.title;

            showActiveResultsSection();
            openBigScreenModal();
            startPollingResults();
        } else {
            alert('Eșec la lansarea sesiunii: ' + data.error);
        }
    } catch (err) {
        alert('Eroare de conexiune la lansarea sesiunii.');
        console.error(err);
    }
}

function openBigScreenModal() {
    if (!activeSessionCode) return;
    
    document.getElementById('big-screen-title').innerText = activeSessionTitle;
    document.getElementById('big-screen-code').innerText = activeSessionCode;

    // Build Student Join URL
    const studentUrl = window.location.origin + window.location.pathname.replace('index.html', '') + 'student.html?code=' + activeSessionCode;
    document.getElementById('big-screen-url').innerText = studentUrl;

    // Generate QR Code
    const qrContainer = document.getElementById('big-screen-qr');
    qrContainer.innerHTML = '';
    new QRCode(qrContainer, {
        text: studentUrl,
        width: 260,
        height: 260
    });

    document.getElementById('big-screen-modal').style.display = 'flex';
}

function openBigScreenForActiveSession() {
    openBigScreenModal();
}

function closeBigScreenModal() {
    document.getElementById('big-screen-modal').style.display = 'none';
}

function showActiveResultsSection() {
    const sec = document.getElementById('active-results-section');
    sec.style.display = 'block';
    document.getElementById('results-session-title').innerText = `📊 Rezultate în Direct: ${activeSessionTitle}`;
    document.getElementById('results-session-code').innerText = activeSessionCode;
    sec.scrollIntoView({ behavior: 'smooth' });
}

// RESULTS POLLING & DISPLAY
function startPollingResults() {
    refreshResults();
    if (resultsPollInterval) clearInterval(resultsPollInterval);
    resultsPollInterval = setInterval(refreshResults, 3000);
}

async function refreshResults() {
    if (!activeSessionId) return;

    try {
        const res = await fetch(`api/results.php?session_id=${activeSessionId}`);
        const data = await res.json();

        if (data.error) return;

        // Update stats
        document.getElementById('stat-total-students').innerText = data.total_students;
        document.getElementById('big-screen-count').innerText = data.total_students;

        document.getElementById('stat-avg-score').innerText = `${data.average_score}`;

        // Populate table
        const tbody = document.getElementById('results-table-body');
        if (data.submissions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                        Se așteaptă ca studenții să se alăture și să trimită răspunsurile...
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = '';
        data.submissions.forEach(sub => {
            const tr = document.createElement('tr');
            const pct = sub.total_questions > 0 ? Math.round((sub.score / sub.total_questions) * 100) : 0;
            
            let scoreClass = 'medium';
            if (pct >= 70) scoreClass = 'high';
            if (pct < 50) scoreClass = 'low';

            const timeStr = new Date(sub.submitted_at).toLocaleTimeString();

            tr.innerHTML = `
                <td style="font-weight: 600;">${escapeHtml(sub.student_name)}</td>
                <td style="color: var(--text-secondary);">${escapeHtml(sub.student_id || '-')}</td>
                <td><strong style="color: var(--text-primary);">${sub.score} / ${sub.total_questions}</strong></td>
                <td><span class="score-tag ${scoreClass}">${pct}%</span></td>
                <td style="color: var(--text-secondary); font-size: 0.9rem;">${timeStr}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (err) {
        console.error('Error fetching live results:', err);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;")
              .replace(/</g, "&lt;")
              .replace(/>/g, "&gt;")
              .replace(/"/g, "&quot;")
              .replace(/'/g, "&#039;");
}

async function handleRestoreFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (!confirm(`Ești sigur că vrei să restabilești datele din "${file.name}"? Aceasta va actualiza baza de date cu teste și rezultate.`)) {
        event.target.value = '';
        return;
    }

    try {
        const fileText = await file.text();
        const jsonData = JSON.parse(fileText);

        const res = await fetch('api/restore.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(jsonData)
        });

        const data = await res.json();
        if (data.success) {
            alert('✅ Datele au fost restabilite cu succes!');
            loadTests();
        } else {
            alert('❌ Restabilirea a eșuat: ' + (data.error || 'Eroare necunoscută'));
        }
    } catch (err) {
        alert('Fișier JSON nevalid sau eroare de server.');
        console.error(err);
    } finally {
        event.target.value = '';
    }
}
