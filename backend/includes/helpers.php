<?php
/**
 * Shared helpers — JSON responses, input reading, CSRF, session bootstrap.
 */

declare(strict_types=1);

// Ensure .env is loaded before timezone (db.php is idempotent).
require_once dirname(__DIR__) . '/config/db.php';
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Dhaka');

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Serverless (Vercel): filesystem sessions do not persist — use MySQL.
    $useDbSessions = (getenv('SESSION_DRIVER') ?: '') === 'database'
        || (getenv('VERCEL') === '1')
        || (getenv('VERCEL_ENV') !== false && getenv('VERCEL_ENV') !== '');

    if ($useDbSessions) {
        require_once __DIR__ . '/DbSessionHandler.php';
        try {
            ensureAppSessionsTable(db());
            $handler = new DbSessionHandler(db(), 86400);
            session_set_save_handler($handler, true);
        } catch (Throwable $e) {
            // Fall through to default sessions if DB is unavailable during setup
        }
    }

    $secure = (getenv('SESSION_SECURE') === '1')
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((getenv('VERCEL') ?: '') === '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly'  => true,
        'samesite'  => 'Lax',
        'secure'    => $secure,
    ]);

    session_start();
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400, array $extra = []): void
{
    jsonResponse(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

/**
 * JSON body reader that survives a prior requireCsrf() call.
 * @return array<string, mixed>
 */
function readJsonBody(): array
{
    if (isset($GLOBALS['__JSON_BODY']) && is_array($GLOBALS['__JSON_BODY'])) {
        $cached = $GLOBALS['__JSON_BODY'];
        unset($GLOBALS['__JSON_BODY']);
        return $cached;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function requireMethod(string $method): void
{
    if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? '', $method) !== 0) {
        jsonError('Method not allowed.', 405);
    }
}

function sanitizeString(?string $value): string
{
    return trim((string) $value);
}

/**
 * Format + domain DNS check so disposable/typo domains are rejected when possible.
 */
function isValidEmailAddress(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $at = strrpos($email, '@');
    if ($at === false) {
        return false;
    }
    $domain = substr($email, $at + 1);
    if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
        return false;
    }
    // Soft DNS check — skip if DNS functions unavailable
    if (function_exists('checkdnsrr')) {
        if (!@checkdnsrr($domain, 'MX') && !@checkdnsrr($domain, 'A') && !@checkdnsrr($domain, 'AAAA')) {
            return false;
        }
    }
    return true;
}

function csrfToken(): string
{
    startAppSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token): bool
{
    startAppSession();
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $token);
}

/**
 * Reject mutating requests without a valid X-CSRF-Token header
 * (or csrf_token body field). Call BEFORE readJsonBody() — body is cached.
 */
function requireCsrf(): void
{
    startAppSession();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);

    if ($token === null || $token === '') {
        $body = readJsonBody();
        $GLOBALS['__JSON_BODY'] = $body;
        $token = $body['csrf_token'] ?? null;
    }

    if (!verifyCsrf(is_string($token) ? $token : null)) {
        jsonError('Invalid or missing CSRF token. Reload the page and try again.', 419);
    }
}

/** Soft CORS for local XAMPP (same-origin preferred). */
function sendCorsHeaders(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

    if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? '', 'OPTIONS') === 0) {
        http_response_code(204);
        exit;
    }
}
