<?php
/**
 * POST /backend/api/auth/request-otp.php
 * Body: { email, role }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/mail.php';

sendCorsHeaders();
requireMethod('POST');
startAppSession();

$body = readJsonBody();
$email = strtolower(sanitizeString($body['email'] ?? ''));
$role = sanitizeString($body['role'] ?? '');

if (!isValidEmailAddress($email)) {
    jsonError('Enter a real, valid email address.');
}
if (!in_array($role, ['student', 'admin'], true)) {
    jsonError('Invalid role selected.');
}

try {
    $stmt = db()->prepare(
        'SELECT id, name, email FROM users WHERE email = ? AND role = ? LIMIT 1'
    );
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if (!$user) {
        // #region agent log
        $logFile = dirname(__DIR__, 2) . '/debug-20c118.log';
        @file_put_contents($logFile, json_encode([
            'sessionId' => '20c118',
            'runId' => 'email-verify',
            'hypothesisId' => 'C',
            'location' => 'auth/request-otp.php',
            'message' => 'forgot password unknown email',
            'data' => [
                'role' => $role,
                'emailDomain' => substr(strrchr($email, '@') ?: '', 1),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        // #endregion
        jsonError('No account found with this email for the selected role.', 404);
    }

    // Rate limit: one OTP per 5 minutes for this user
    $recent = db()->prepare(
        'SELECT id, created_at FROM password_resets
         WHERE user_id = ? AND created_at > ' . sqlNowMinusMinutes(5) . '
         ORDER BY id DESC LIMIT 1'
    );
    $recent->execute([(int) $user['id']]);
    if ($recent->fetch()) {
        jsonError('Please wait a few minutes before requesting another code.');
    }

    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $ins = db()->prepare(
        'INSERT INTO password_resets (user_id, otp_code, is_verified, expires_at)
         VALUES (?, ?, 0, ' . sqlNowPlusSeconds(600) . ')'
    );
    $ins->execute([(int) $user['id'], $otp]);

    $_SESSION['password_reset_email'] = $email;
    $_SESSION['password_reset_role'] = $role;
    unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_id']);

    $bodyText = "Hello {$user['name']},\n\n"
        . "Your ExamPortal password reset code is: {$otp}\n\n"
        . "This code expires in 10 minutes. If you did not request this, ignore this email.\n";

    sendAppMail($email, 'ExamPortal password reset code', $bodyText);

    // #region agent log
    $logFile = dirname(__DIR__, 2) . '/debug-20c118.log';
    @file_put_contents($logFile, json_encode([
        'sessionId' => '20c118',
        'runId' => 'email-verify',
        'hypothesisId' => 'C',
        'location' => 'auth/request-otp.php',
        'message' => 'forgot password otp sent',
        'data' => ['userId' => (int) $user['id'], 'role' => $role],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    // #endregion

    $payload = [
        'ok'         => true,
        'message'    => 'A verification code has been sent to your email.',
        'csrf_token' => csrfToken(),
    ];

    if ((getenv('MAIL_DRIVER') ?: 'log') === 'log' || getenv('OTP_DEBUG') === '1') {
        $payload['debug_otp'] = $otp;
    }

    jsonResponse($payload);
} catch (Throwable $e) {
    jsonError('Could not process reset request.', 500);
}
