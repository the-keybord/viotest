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
                <div style="grid-column: 1/-1; text-align: center; color: var(--text-secondary); padding: 3rem; background: rgba(15,23,42,0.4); border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📝</div>
                    <div style="font-weight: 600; font-size: 1.1rem; color: var(--text-primary);">No tests created yet</div>
                    <p style="font-size: 0.9rem; margin-top: 0.25rem;">Click "+ Create New Test" above to build your first post-lesson test!</p>
                </div>
            `;
            return;
        }

        tests.forEach(test => {
            const card = document.createElement('div');
            card.style.cssText = 'background: rgba(15,23,42,0.6); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; gap: 1rem;';
            card.innerHTML = `
                <div>
                    <h3 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">${escapeHtml(test.title)}</h3>
                    <div style="display: flex; gap: 1rem; color: var(--text-secondary); font-size: 0.85rem;">
                        <span>⏱️ ${test.duration_minutes} mins</span>
                        <span>❓ ${test.question_count} questions</span>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-primary btn-full" onclick="launchSession('${test.id}')">🖥️ Launch Test</button>
                </div>
            `;
            listEl.appendChild(card);
        });
    } catch (err) {
        loadingEl.innerText = 'Failed to load tests. Please check connection.';
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
            <strong style="font-family: var(--font-heading); color: var(--accent-blue);">Question #${questionCount}</strong>
            ${questionCount > 1 ? `<button type="button" onclick="removeQuestionField(${questionCount})" style="background:none; border:none; color: var(--accent-red); cursor:pointer;">Remove</button>` : ''}
        </div>
        <div class="form-group">
            <input type="text" class="form-input q-text" placeholder="Enter question text..." required>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
            <div>
                <label class="form-label">Option A</label>
                <input type="text" class="form-input q-opt-a" placeholder="Answer choice A" required>
            </div>
            <div>
                <label class="form-label">Option B</label>
                <input type="text" class="form-input q-opt-b" placeholder="Answer choice B" required>
            </div>
            <div>
                <label class="form-label">Option C</label>
                <input type="text" class="form-input q-opt-c" placeholder="Answer choice C" required>
            </div>
            <div>
                <label class="form-label">Option D</label>
                <input type="text" class="form-input q-opt-d" placeholder="Answer choice D" required>
            </div>
        </div>
        <div class="form-group" style="margin-top: 0.75rem;">
            <label class="form-label">Correct Option</label>
            <select class="form-select q-correct" required>
                <option value="A">Option A</option>
                <option value="B">Option B</option>
                <option value="C">Option C</option>
                <option value="D">Option D</option>
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
        alert('Please add at least one complete question.');
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
            alert('Error creating test: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Server connection error');
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
            alert('Failed to launch session: ' + data.error);
        }
    } catch (err) {
        alert('Connection error launching session.');
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
    document.getElementById('results-session-title').innerText = `📊 Live Results: ${activeSessionTitle}`;
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

        let totalQ = data.session ? data.session.total_questions || 1 : 1;
        document.getElementById('stat-avg-score').innerText = `${data.average_score}`;

        // Populate table
        const tbody = document.getElementById('results-table-body');
        if (data.submissions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                        Waiting for students to join and submit test answers...
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
