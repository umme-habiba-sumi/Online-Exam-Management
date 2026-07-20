<?php
/**
 * GET /backend/api/profile/get.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('GET');
$user = requireLogin();

    jsonResponse([
        'ok'         => true,
        'user'       => publicUser($user),
        'csrf_token' => csrfToken(),
    ]);
