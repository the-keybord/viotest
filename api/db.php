<?php
/**
 * vio.zece.info - Database Configuration
 * Solid Architecture using a robust JSON File-based DB with strict file locking.
 * Fallback mechanism used because pdo_sqlite is not available on this server.
 */

class FileDB {
    private $file;

    public function __construct($filename) {
        $dataDir = __DIR__ . '/../data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $this->file = $dataDir . '/' . $filename;
        if (!file_exists($this->file)) {
            $this->save(['links' => []]);
        }
    }

    private function read() {
        $fp = fopen($this->file, 'r');
        if (!$fp) return ['links' => []];
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $data = json_decode($content, true);
        return is_array($data) ? $data : ['links' => []];
    }

    private function save($data) {
        $fp = fopen($this->file, 'c+');
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

    public function getLinkByCode($code) {
        $data = $this->read();
        foreach ($data['links'] as $link) {
            if (strcasecmp($link['code'], $code) === 0) {
                return $link;
            }
        }
        return null;
    }

    public function getRecentLinkByUrl($url) {
        $data = $this->read();
        $recent = null;
        foreach ($data['links'] as $link) {
            if ($link['target_url'] === $url) {
                // Return if created within 48h
                $createdAt = strtotime($link['created_at']);
                if (time() - $createdAt < 172800) {
                    $recent = $link;
                }
            }
        }
        return $recent;
    }

    public function insertLink($code, $url) {
        $data = $this->read();
        $data['links'][] = [
            'code' => $code,
            'target_url' => $url,
            'created_at' => date('Y-m-d H:i:s'),
            'clicks' => 0
        ];
        return $this->save($data);
    }

    public function incrementClick($code) {
        // Read, update, and write in one exclusive lock to prevent race conditions
        $fp = fopen($this->file, 'c+');
        if (!$fp) return false;
        flock($fp, LOCK_EX);
        $content = stream_get_contents($fp);
        $data = json_decode($content, true);
        if (is_array($data) && isset($data['links'])) {
            foreach ($data['links'] as &$link) {
                if (strcasecmp($link['code'], $code) === 0) {
                    $link['clicks'] = ($link['clicks'] ?? 0) + 1;
                    $link['last_accessed'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    public function codeExists($code) {
        $data = $this->read();
        foreach ($data['links'] as $link) {
            if (strcasecmp($link['code'], $code) === 0) {
                return true;
            }
        }
        return false;
    }
}

// Instantiate Global DB Object
$db = new FileDB('database.json');
