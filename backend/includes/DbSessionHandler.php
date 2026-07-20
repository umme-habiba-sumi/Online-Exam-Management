<?php
/**
 * Database-backed session handler (required on Vercel / serverless).
 */

declare(strict_types=1);

final class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $ttl;

    public function __construct(PDO $pdo, int $ttl = 86400)
    {
        $this->pdo = $pdo;
        $this->ttl = $ttl;
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
        $stmt = $this->pdo->prepare(
            'SELECT payload FROM app_sessions WHERE id = ? AND expires_at > ' . sqlNow() . ' LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string) $row['payload'] : '';
    }

    public function write(string $id, string $data): bool
    {
        if (dbIsSqlite()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO app_sessions (id, payload, expires_at)
                 VALUES (?, ?, ' . sqlNowPlusSeconds($this->ttl) . ')
                 ON CONFLICT(id) DO UPDATE SET
                   payload = excluded.payload,
                   expires_at = excluded.expires_at'
            );
            return $stmt->execute([$id, $data]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO app_sessions (id, payload, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
               payload = VALUES(payload),
               expires_at = VALUES(expires_at)'
        );
        return $stmt->execute([$id, $data, $this->ttl]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $n = $this->pdo->exec('DELETE FROM app_sessions WHERE expires_at < ' . sqlNow());
        return $n === false ? false : $n;
    }
}

function ensureAppSessionsTable(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (dbIsSqlite()) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_sessions (
               id TEXT NOT NULL PRIMARY KEY,
               payload TEXT NOT NULL,
               expires_at TEXT NOT NULL
             )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_sessions (
               id VARCHAR(128) NOT NULL PRIMARY KEY,
               payload MEDIUMTEXT NOT NULL,
               expires_at DATETIME NOT NULL,
               INDEX idx_expires (expires_at)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
    $done = true;
}
