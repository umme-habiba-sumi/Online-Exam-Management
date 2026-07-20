<?php
/**
 * POST /backend/api/admin/users/send-create-otp.php
 * Body: { email, name? }
 * Sends a verification code to the email before an account is created.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/mail.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
requireRole('admin');
startAppSession();

$body = readJsonBody();
$email = strtolower(sanitizeString($body['email'] ?? ''));
$name = sanitizeString($body['name'] ?? 'there');

if (!isValidEmailAddress($email)) {
    jsonError('Enter a real, deliverable email address.');
}

try {
    $exists = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $exists->execute([$email]);
    if ($exists->fetch()) {
        jsonError('An account with this email already exists.');
    }

    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['create_user_verify'] = [
        'email'      => $email,
        'otp_hash'   => password_hash($otp, PASSWORD_DEFAULT),
        'expires_at' => time() + 600,
        'attempts'   => 0,
    ];

    $bodyText = "Hello {$name},\n\n"
        . "Your ExamPortal email verification code is: {$otp}\n\n"
        . "An administrator is creating an account with this email.\n"
        . "This code expires in 10 minutes. If you did not expect this, ignore this email.\n";

    $sent = sendAppMail($email, 'ExamPortal email verification code', $bodyText);

    // #region agent log
    $logFile = dirname(__DIR__, 3) . '/debug-20c118.log';
    @file_put_contents($logFile, json_encode([
        'sessionId' => '20c118',
        'runId' => 'email-verify',
        'hypothesisId' => 'A',
        'location' => 'admin/users/send-create-otp.php',
        'message' => 'create-otp sent',
        'data' => [
            'emailDomain' => substr(strrchr($email, '@') ?: '', 1),
            'mailSent' => $sent,
            'driver' => getenv('MAIL_DRIVER') ?: 'log',
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    // #endregion

    $payload = [
        'ok'      => true,
        'message' => 'A verification code has been sent to ' . $email . '.',
        'email'   => $email,
    ];

    if ((getenv('MAIL_DRIVER') ?: 'log') === 'log' || getenv('OTP_DEBUG') === '1') {
        $payload['debug_otp'] = $otp;
    }

    jsonResponse($payload);
} catch (Throwable $e) {
    jsonError('Could not send verification code.', 500);
}
