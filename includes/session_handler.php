<?php
/**
 * Fullbright College - Student Management System
 * Database-backed PHP session handler.
 *
 * Container / serverless platforms do not share session files between
 * instances, and redeploys wipe the filesystem. Storing sessions in MySQL
 * keeps users logged in across instances and deploys.
 *
 * Enabled by setting SESSION_DRIVER=database (see includes/session_bootstrap.php).
 */

require_once __DIR__ . '/config.php';

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS sessions (
                id          VARCHAR(128) NOT NULL,
                data        MEDIUMTEXT   NOT NULL,
                last_access INT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                KEY idx_sessions_access (last_access)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetchColumn();
        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (id, data, last_access) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), last_access = VALUES(last_access)'
        );
        return $stmt->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE last_access < ?');
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
