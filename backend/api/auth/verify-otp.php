<?php
/**
 * POST /backend/api/auth/verify-otp.php
 * Body: { email, role, otp }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
startAppSession();

$body = readJsonBody();
$email = strtolower(sanitizeString($body['email'] ?? ''));
$role = sanitizeString($body['role'] ?? '');
$otp = sanitizeString($body['otp'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Enter a valid email address.');
}
if (!in_array($role, ['student', 'admin'], true)) {
    jsonError('Invalid role.');
}
if (!preg_match('/^\d{6}$/', $otp)) {
    jsonError('Enter the 6-digit code from your email.');
}

try {
    $userStmt = db()->prepare(
        'SELECT id FROM users WHERE email = ? AND role = ? LIMIT 1'
    );
    $userStmt->execute([$email, $role]);
    $user = $userStmt->fetch();
    if (!$user) {
        jsonError('Invalid or expired code.', 401);
    }

    $otpStmt = db()->prepare(
        'SELECT id, otp_code, expires_at, is_verified
         FROM password_resets
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $otpStmt->execute([(int) $user['id']]);
    $row = $otpStmt->fetch();

    if (!$row || $row['otp_code'] !== $otp) {
        jsonError('Invalid or expired code.', 401);
    }
    if (strtotime($row['expires_at']) < time()) {
        jsonError('This code has expired. Request a new one.', 401);
    }

    db()->prepare('UPDATE password_resets SET is_verified = 1 WHERE id = ?')
        ->execute([(int) $row['id']]);

    $_SESSION['password_reset_user_id'] = (int) $user['id'];
    $_SESSION['password_reset_id'] = (int) $row['id'];
    $_SESSION['password_reset_email'] = $email;
    $_SESSION['password_reset_role'] = $role;

    jsonResponse([
        'ok'         => true,
        'message'    => 'Code verified. Set a new password.',
        'csrf_token' => csrfToken(),
    ]);
} catch (Throwable $e) {
    jsonError('Could not verify code.', 500);
}
