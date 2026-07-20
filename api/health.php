<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'      => true,
    'service' => 'ExamPortal API',
    'php'     => PHP_VERSION,
    'sqlite'  => extension_loaded('pdo_sqlite'),
], JSON_UNESCAPED_UNICODE);
