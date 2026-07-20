<?php
/**
 * GET /backend/api/auth/csrf.php
 * Bootstrap CSRF token for pages that need it before login/me.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';

sendCorsHeaders();
requireMethod('GET');
startAppSession();

jsonResponse([
    'ok'         => true,
    'csrf_token' => csrfToken(),
]);
