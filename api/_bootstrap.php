<?php
/**
 * Shared bootstrap for Vercel PHP entrypoints under /api.
 */
declare(strict_types=1);

function api_route(string $rel): void
{
    $rel = str_replace(['..', '\\'], '', $rel);
    $file = dirname(__DIR__) . '/backend/api/' . $rel;
    if (!is_file($file)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'API endpoint not found.', 'file' => $rel]);
        exit;
    }
    require $file;
}

function api_find_bootstrap(): string
{
    $dir = __DIR__;
    while ($dir !== dirname($dir)) {
        if (is_file($dir . '/_bootstrap.php')) {
            return $dir . '/_bootstrap.php';
        }
        $dir = dirname($dir);
    }
    return __DIR__ . '/_bootstrap.php';
}
