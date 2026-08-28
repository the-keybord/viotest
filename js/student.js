let currentSession = null;
let studentAnswers = {};
let timerInterval = null;
let remainingSeconds = 0;
let isSubmitted = false;

document.addEventListener('DOMContentLoaded', () => {
    // Auto-fill session code from URL parameter ?code=...
    const urlParams = new URLSearchParams(window.location.search);
    const codeParam = urlParams.get('code');
    if (codeParam) {
        document.getElementById('session-code').value = codeParam;
    }
});

async function handleJoinTest(e) {
    e.preventDefault();
    const code = document.getElementById('session-code').value.trim();
    const name = document.getElementById('student-name').value.trim();
    const studentId = document.getElementById('student-id-input').value.trim();

    if (!code || !name) {
        alert('Please fill in session code and your name.');
        return;
    }

    try {
        const res = await fetch(`api/sessions.php?code=${encodeURIComponent(code)}`);
        const sessionData = await res.json();

        if (sessionData.error) {
            alert('Invalid session code or session closed.');
            return;
        }

        currentSession = {
            ...sessionData,
            student_name: name,
            student_id: studentId
        };

        // Render test screen
        document.getElementById('header-student-info').innerText = `Student: ${name}`;
        document.getElementById('join-card').style.display = 'none';
        document.getElementById('test-view').style.display = 'block';

        document.getElementById('test-title-display').innerText = sessionData.title;

        renderQuestions(sessionData.questions);

        // Start countdown timer
        const durationMins = sessionData.duration_minutes || 5;
        startTimer(durationMins * 60);

    } catch (err) {
        alert('Connection error joining test session.');
        console.error(err);
    }
}

function renderQuestions(questions) {
    const container = document.getElementById('questions-list');
    container.innerHTML = '';

    if (!questions || questions.length === 0) {
        container.innerHTML = '<p style="color: var(--text-secondary);">No questions found for this test.</p>';
        return;
    }

    questions.forEach((q, idx) => {
        const qCard = document.createElement('div');
        qCard.className = 'question-card';
        qCard.innerHTML = `
            <div class="question-title">
                <strong style="color: var(--accent-blue);">Q${idx + 1}.</strong> ${escapeHtml(q.question_text)}
            </div>
            <div class="options-grid">
                <button type="button" class="option-btn" onclick="selectOption('${q.id}', 'A', this)">
                    <span class="option-badge">A</span>
                    <span>${escapeHtml(q.option_a)}</span>
                </button>
                <button type="button" class="option-btn" onclick="selectOption('${q.id}', 'B', this)">
                    <span class="option-badge">B</span>
                    <span>${escapeHtml(q.option_b)}</span>
                </button>
                <button type="button" class="option-btn" onclick="selectOption('${q.id}', 'C', this)">
                    <span class="option-badge">C</span>
                    <span>${escapeHtml(q.option_c)}</span>
                </button>
                <button type="button" class="option-btn" onclick="selectOption('${q.id}', 'D', this)">
                    <span class="option-badge">D</span>
                    <span>${escapeHtml(q.option_d)}</span>
                </button>
            </div>
        `;
        container.appendChild(qCard);
    });
}

function selectOption(questionId, optionLetter, btnElement) {
    studentAnswers[questionId] = optionLetter;

    // Highlight selected button within the question card
    const grid = btnElement.closest('.options-grid');
    grid.querySelectorAll('.option-btn').forEach(btn => btn.classList.remove('selected'));
    btnElement.classList.add('selected');
}

function startTimer(totalSecs) {
    remainingSeconds = totalSecs;
    updateTimerDisplay();

    timerInterval = setInterval(() => {
        remainingSeconds--;
        updateTimerDisplay();

        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            alert('⏱️ Time has expired! Your test is submitting automatically...');
            autoSubmitTest();
        }
    }, 1000);
}

function updateTimerDisplay() {
    const clockEl = document.getElementById('timer-clock');
    const badgeEl = document.getElementById('timer-badge');

    const mins = Math.floor(remainingSeconds / 60);
    const secs = remainingSeconds % 60;
    const formatted = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;

    clockEl.innerText = formatted;

    if (remainingSeconds <= 60) {
        badgeEl.classList.add('warning');
    } else {
        badgeEl.classList.remove('warning');
    }
}

function confirmAndSubmitTest() {
    if (confirm('Are you sure you want to submit your test answers now?')) {
        submitAnswers();
    }
}

function handleManualSubmit(e) {
    e.preventDefault();
    confirmAndSubmitTest();
}

function autoSubmitTest() {
    submitAnswers();
}

async function submitAnswers() {
    if (isSubmitted) return;
    isSubmitted = true;

    if (timerInterval) clearInterval(timerInterval);

    try {
        const payload = {
            session_id: currentSession.id,
            student_name: currentSession.student_name,
            student_id: currentSession.student_id,
            answers: studentAnswers
        };

        const res = await fetch('api/submit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();

        if (data.success) {
            displayScoreCard(data);
        } else {
            alert('Error submitting test: ' + data.error);
        }
    } catch (err) {
        alert('Server error submitting test. Please contact teacher.');
        console.error(err);
    }
}

function displayScoreCard(data) {
    document.getElementById('test-view').style.display = 'none';
    const scoreCard = document.getElementById('score-card');
    scoreCard.style.display = 'block';

    document.getElementById('score-student-name').innerText = data.student_name;
    document.getElementById('score-display').innerText = `${data.score} / ${data.total_questions}`;

    const pctBadge = document.getElementById('score-percentage-badge');
    pctBadge.innerText = `${data.percentage}%`;

    if (data.percentage >= 70) {
        pctBadge.className = 'score-tag high';
    } else if (data.percentage >= 50) {
        pctBadge.className = 'score-tag medium';
    } else {
        pctBadge.className = 'score-tag low';
    }

    // Render Answer Review
    const reviewContainer = document.getElementById('answers-review-container');
    reviewContainer.innerHTML = '';

    if (data.details && Array.isArray(data.details)) {
        data.details.forEach((item, idx) => {
            const div = document.createElement('div');
            div.style.cssText = 'background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 0.75rem;';
            
            const isCorrect = item.is_correct;
            const statusIcon = isCorrect ? '✅ Correct' : '❌ Incorrect';
            const statusColor = isCorrect ? 'var(--accent-green)' : 'var(--accent-red)';

            div.innerHTML = `
                <div style="display: flex; justify-content: space-between; font-weight: 600; margin-bottom: 0.5rem;">
                    <span>Q${idx + 1}: ${escapeHtml(item.question_text)}</span>
                    <span style="color: ${statusColor}; font-size: 0.9rem;">${statusIcon}</span>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-secondary);">
                    Your Choice: <strong style="color: var(--text-primary);">${item.chosen_option || 'None'}</strong> | 
                    Correct Answer: <strong style="color: var(--accent-green);">${item.correct_option}</strong>
                </div>
            `;
            reviewContainer.appendChild(div);
        });
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
