# 🎓 University Quick-Test Platform

A lightweight, zero-framework post-lesson testing web application designed for university professors and teachers. Host quick single-choice (1-of-4) tests, project QR codes on classroom screens, enforce time limits with automatic test submission, and view student results live.

Designed to easily handle **up to 100 simultaneous students** on free or low-cost basic PHP hosting.

---

## 🌟 Key Features

* **🎓 Teacher Control Panel**:
  * Create & manage tests with custom title, duration limit, and 4-choice questions (`A`, `B`, `C`, `D`).
  * **Big Screen Projector Mode**: Display a large 6-digit session code, join URL, and dynamic QR Code for student scanning.
  * **Live Results Dashboard**: Real-time auto-refreshing monitor showing student names, student IDs, exact scores, percentages, and submission timestamps.

* **📱 Student Portal**:
  * **Instant QR Access**: Scan QR code on smartphone or laptop to join immediately.
  * **Timed Auto-Submit**: Visual countdown timer badge. When time runs out (`00:00`), the test auto-submits answers.
  * **Instant Feedback**: View final score, percentage badge, and detailed question-by-question review right after submission.

* **⚡ Ultra-Lightweight & Easy Hosting**:
  * Built with **Vanilla HTML5, CSS3, JavaScript**, and a **Portable PHP Backend**.
  * File-locked JSON store (`flock`) eliminates the need for database server configuration—works out-of-the-box on **any free or basic PHP web host** (e.g. InfinityFree, Freehostia, 000webhost, Render, or cPanel).

---

## 🚀 Quick Start (Local Setup)

### Prerequisites
* PHP 7.4+ installed locally.

### Running Locally
1. Clone or download the repository.
2. Open a terminal in the project directory and start PHP's built-in server:
   ```bash
   php -S localhost:8000
   ```
3. Access the platform:
   * **Teacher Dashboard**: `http://localhost:8000/index.html`
   * **Student Portal**: `http://localhost:8000/student.html`

---

## 🌐 Deploying to Basic / Free Hosting

1. Sign up for any basic PHP hosting service (e.g., InfinityFree, 000webhost, Freehostia, or standard cPanel shared host).
2. Upload all repository files to your web server's public folder (`public_html` or `htdocs`).
3. Ensure the `/data` directory has write permissions.
4. Share the URL with students!

---

## 📁 File Structure

```text
├── api/
│   ├── db.php           # Database & JSON file-store initialization (flock)
│   ├── tests.php        # Test creation & retrieval API
│   ├── sessions.php     # Live session launcher & QR session code API
│   ├── submit.php       # Student answer submission & server-side grading
│   └── results.php      # Teacher live results feed API
├── css/
│   └── styles.css       # Glassmorphism design system & mobile responsiveness
├── js/
│   ├── admin.js         # Teacher dashboard logic & result polling
│   ├── student.js       # Student test runner, timer, & submission logic
│   └── qrcode.min.js    # Client-side QR code generator
├── data/                # Data directory (auto-created)
├── index.html           # Teacher control panel & projector mode
└── student.html         # Student test taking page
```

---

## 📜 License

MIT License - feel free to customize and use for your university classes!
