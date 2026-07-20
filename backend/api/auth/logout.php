<?php
/**
 * POST /backend/api/auth/logout.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
logoutUser();

jsonResponse(['ok' => true, 'redirect' => '/login.html']);
