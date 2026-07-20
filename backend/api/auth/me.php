<?php
/**
 * GET /backend/api/auth/me.php
 * Returns the current session user (or 401).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('GET');

$user = currentUser();
if (!$user) {
    jsonError('Not authenticated.', 401);
}

jsonResponse([
    'ok'         => true,
    'user'       => publicUser($user),
    'csrf_token' => csrfToken(),
]);
