<?php
/**
 * PDO connection — SQLite by default (no separate DB server).
 * Set DB_DRIVER=mysql in .env to use MySQL/XAMPP instead.
 */

declare(strict_types=1);

function loadEnv(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

function dbDriver(): string
{
    $driver = strtolower((string) (getenv('DB_DRIVER') ?: ''));
    if ($driver === 'mysql' || $driver === 'sqlite') {
        return $driver;
    }
    // Auto: MySQL only if DB_HOST is explicitly set to a remote/non-default deploy intent
    // Default for zero-config deploy = sqlite
    return 'sqlite';
}

function dbIsSqlite(): bool
{
    return dbDriver() === 'sqlite';
}

/** SQL expression for current timestamp (portable). */
function sqlNow(): string
{
    return dbIsSqlite() ? "datetime('now')" : 'NOW()';
}

/** Timestamp N seconds from now (SQL expression). */
function sqlNowPlusSeconds(int $seconds): string
{
    $seconds = (int) $seconds;
    if (dbIsSqlite()) {
        return "datetime('now', '+{$seconds} seconds')";
    }
    return "DATE_ADD(NOW(), INTERVAL {$seconds} SECOND)";
}

/** Timestamp N minutes ago (SQL expression). */
function sqlNowMinusMinutes(int $minutes): string
{
    $minutes = (int) $minutes;
    if (dbIsSqlite()) {
        return "datetime('now', '-{$minutes} minutes')";
    }
    return "(NOW() - INTERVAL {$minutes} MINUTE)";
}

function isVercel(): bool
{
    return getenv('VERCEL') === '1'
        || (getenv('VERCEL_ENV') !== false && getenv('VERCEL_ENV') !== '');
}

function sqlitePath(): string
{
    $custom = getenv('SQLITE_PATH');
    if (is_string($custom) && $custom !== '') {
        return $custom;
    }

    $storageDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
    $seedFile = $storageDir . DIRECTORY_SEPARATOR . 'exam_portal.sqlite';

    if (isVercel()) {
        $tmp = '/tmp/exam_portal.sqlite';
        // Copy bundled seed DB on each cold start (Vercel /tmp is ephemeral)
        if (is_file($seedFile) && (!is_file($tmp) || filesize($tmp) === 0)) {
            @copy($seedFile, $tmp);
        }
        return $tmp;
    }

    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }
    return $seedFile;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (dbIsSqlite()) {
        $path = sqlitePath();
        $isNew = !is_file($path) || filesize($path) === 0;
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        require_once dirname(__DIR__) . '/database/sqlite_bootstrap.php';
        sqliteBootstrap($pdo, $isNew);
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'exam_portal';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
