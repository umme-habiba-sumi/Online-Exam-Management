<?php
/**
 * POST /backend/api/auth/reset-password.php
 * Body: { password }
 * Requires a verified OTP in the session.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
startAppSession();

$body = readJsonBody();
$password = (string) ($body['password'] ?? '');

if (strlen($password) < 6) {
    jsonError('Password must be at least 6 characters.');
}

$userId = (int) ($_SESSION['password_reset_user_id'] ?? 0);
$resetId = (int) ($_SESSION['password_reset_id'] ?? 0);

if ($userId < 1 || $resetId < 1) {
    jsonError('Verify your email code before resetting the password.', 403);
}

try {
    $check = db()->prepare(
        'SELECT id, is_verified, expires_at FROM password_resets
         WHERE id = ? AND user_id = ? LIMIT 1'
    );
    $check->execute([$resetId, $userId]);
    $row = $check->fetch();

    if (!$row || !(int) $row['is_verified']) {
        jsonError('Verify your email code before resetting the password.', 403);
    }
    if (strtotime($row['expires_at']) < time()) {
        jsonError('This reset session has expired. Start again.', 401);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([$hash, $userId]);

    // Invalidate used / leftover reset rows for this user
    db()->prepare('DELETE FROM password_resets WHERE user_id = ?')
        ->execute([$userId]);

    unset(
        $_SESSION['password_reset_user_id'],
        $_SESSION['password_reset_id'],
        $_SESSION['password_reset_email'],
        $_SESSION['password_reset_role']
    );

    jsonResponse([
        'ok'       => true,
        'message'  => 'Password updated. You can sign in now.',
        'redirect' => '/login.html',
    ]);
} catch (Throwable $e) {
    jsonError('Could not reset password.', 500);
}
