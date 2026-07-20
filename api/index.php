<?php
/**
 * Vercel PHP router — resolves API path and includes backend endpoint.
 */

declare(strict_types=1);

function apiResolvePath(): ?string
{
    // vercel.json rewrite: /api/index.php?path=auth/login.php
    if (!empty($_GET['path'])) {
        $p = str_replace(['..', '\\'], '', (string) $_GET['path']);
        $p = ltrim($p, '/');
        if ($p !== '' && str_ends_with(strtolower($p), '.php')) {
            return $p;
        }
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);

    if (preg_match('#^/backend/api/(.+\.php)$#', $path, $m)) {
        return str_replace(['..', '\\'], '', $m[1]);
    }

    if (preg_match('#^/api/(.+\.php)$#', $path, $m)) {
        return str_replace(['..', '\\'], '', $m[1]);
    }

    // Some proxies preserve original URL
    foreach (['HTTP_X_ORIGINAL_URL', 'HTTP_X_FORWARDED_URI', 'HTTP_X_REWRITE_URL'] as $h) {
        if (empty($_SERVER[$h])) {
            continue;
        }
        $orig = parse_url((string) $_SERVER[$h], PHP_URL_PATH) ?: '';
        if (preg_match('#/api/(.+\.php)$#', $orig, $m)) {
            return str_replace(['..', '\\'], '', $m[1]);
        }
        if (preg_match('#/backend/api/(.+\.php)$#', $orig, $m)) {
            return str_replace(['..', '\\'], '', $m[1]);
        }
    }

    return null;
}

function apiSendJson(int $status, array $data): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

set_exception_handler(static function (Throwable $e): void {
    apiSendJson(500, [
        'ok'    => false,
        'error' => 'Server error.',
        'debug' => (getenv('VERCEL_ENV') === 'preview' || getenv('OTP_DEBUG') === '1')
            ? $e->getMessage()
            : null,
    ]);
});

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    apiSendJson(500, [
        'ok'    => false,
        'error' => 'Server error.',
        'debug' => $err['message'] ?? null,
    ]);
});

$rel = apiResolvePath();

if ($rel === 'health.php' || $rel === null && (($_GET['path'] ?? '') === 'health' || ($_SERVER['REQUEST_URI'] ?? '') === '/api/index.php')) {
    apiSendJson(200, [
        'ok'      => true,
        'service' => 'ExamPortal API',
        'php'     => PHP_VERSION,
        'sqlite'  => extension_loaded('pdo_sqlite'),
        'vercel'  => getenv('VERCEL') === '1',
    ]);
}

if ($rel === null) {
    apiSendJson(404, [
        'ok'    => false,
        'error' => 'API route not found.',
        'uri'   => $_SERVER['REQUEST_URI'] ?? '',
        'hint'  => 'Use /backend/api/auth/login.php',
    ]);
}

$root = dirname(__DIR__);
$file = $root . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $rel);

if (!is_file($file)) {
    apiSendJson(404, [
        'ok'    => false,
        'error' => 'Endpoint file missing.',
        'file'  => $rel,
    ]);
}

require $file;
