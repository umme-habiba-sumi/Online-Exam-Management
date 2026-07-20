<?php
/**
 * POST /backend/api/profile/update-password.php
 * Body: { current_password, new_password }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
$user = requireLogin();

$body = readJsonBody();
$current = (string) ($body['current_password'] ?? '');
$new = (string) ($body['new_password'] ?? '');

if (strlen($current) < 6) {
    jsonError('Enter your current password.');
}
if (strlen($new) < 6) {
    jsonError('New password must be at least 6 characters.');
}
if ($current === $new) {
    jsonError('New password must be different from the current one.');
}

try {
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $user['id']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) {
        jsonError('Current password is incorrect.', 401);
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([$hash, (int) $user['id']]);
} catch (Throwable $e) {
    jsonError('Could not update password.', 500);
}

jsonResponse(['ok' => true, 'message' => 'Password updated.']);
