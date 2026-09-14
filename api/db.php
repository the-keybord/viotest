<?php
/**
 * Database abstraction layer
 * Designed to run on any basic PHP hosting without external plugins or drivers.
 * Uses PDO SQLite when available; falls back to an atomic file-locked store.
 */

class LinkDB {
    private $pdo = null;
    private $jsonFile = null;
    private $isSqlite = false;

    public function __construct() {
        $dataDir = __DIR__ . '/../data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }

        // Check if PDO SQLite is available
        if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers())) {
            try {
                $dbPath = $dataDir . '/links.sqlite';
                $this->pdo = new PDO('sqlite:' . $dbPath);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS links (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        code TEXT UNIQUE NOT NULL,
                        target_url TEXT NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE INDEX IF NOT EXISTS idx_links_code ON links(code);
                ");
                $this->isSqlite = true;
                return;
            } catch (Exception $e) {
                // Fallback to JSON FileDB
                $this->pdo = null;
                $this->isSqlite = false;
            }
        }

        // File-based fallback for environments without SQLite driver
        $this->jsonFile = $dataDir . '/database.json';
        if (!file_exists($this->jsonFile)) {
            $this->writeJsonFile(['links' => []]);
        }
    }

    private function readJsonFile() {
        if (!file_exists($this->jsonFile)) {
            return ['links' => []];
        }
        $fp = fopen($this->jsonFile, 'r');
        if (!$fp) return ['links' => []];
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $data = json_decode($content, true);
        return is_array($data) ? $data : ['links' => []];
    }

    private function writeJsonFile($data) {
        $fp = fopen($this->jsonFile, 'c+');
        if (!$fp) return false;
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    public function codeExists($code) {
        if ($this->isSqlite && $this->pdo) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM links WHERE code = :code LIMIT 1");
            $stmt->execute([':code' => $code]);
            return (bool)$stmt->fetchColumn();
        }

        $data = $this->readJsonFile();
        foreach ($data['links'] as $link) {
            if ($link['code'] === $code) {
                return true;
            }
        }
        return false;
    }

    public function getLinkByCode($code) {
        if ($this->isSqlite && $this->pdo) {
            $stmt = $this->pdo->prepare("SELECT target_url FROM links WHERE code = :code LIMIT 1");
            $stmt->execute([':code' => $code]);
            $url = $stmt->fetchColumn();
            return $url ? $url : null;
        }

        $data = $this->readJsonFile();
        foreach ($data['links'] as $link) {
            if ($link['code'] === $code) {
                return $link['target_url'];
            }
        }
        return null;
    }

    public function insertLink($code, $url) {
        if ($this->isSqlite && $this->pdo) {
            $stmt = $this->pdo->prepare("INSERT INTO links (code, target_url, created_at) VALUES (:code, :url, :created_at)");
            return $stmt->execute([
                ':code' => $code,
                ':url' => $url,
                ':created_at' => date('Y-m-d H:i:s')
            ]);
        }

        $fp = fopen($this->jsonFile, 'c+');
        if (!$fp) return false;
        flock($fp, LOCK_EX);
        $content = stream_get_contents($fp);
        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['links'])) {
            $data = ['links' => []];
        }

        $data['links'][] = [
            'code' => $code,
            'target_url' => $url,
            'created_at' => date('Y-m-d H:i:s')
        ];

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    /**
     * Generate unique straight numeric code (e.g. 1234, 5829)
     */
    public function generateCode() {
        $min = 1000;
        $max = 9999;
        $attempts = 0;

        while ($attempts < 200) {
            $code = (string)mt_rand($min, $max);
            if (!$this->codeExists($code)) {
                return $code;
            }
            $attempts++;
            if ($attempts === 50) {
                // If 4-digit space is dense, expand to 5 digits
                $min = 10000;
                $max = 99999;
            }
        }

        return (string)time();
    }
}

$db = new LinkDB();
