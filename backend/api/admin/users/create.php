<?php
/**
 * POST /backend/api/admin/users/create.php
 * Body: { name, email, password, role, roll_or_id?, designation?, otp }
 * Requires a prior send-create-otp.php for the same email.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
requireRole('admin');
startAppSession();
ensureEmailVerifiedColumn(db());

$body = readJsonBody();
$name = sanitizeString($body['name'] ?? '');
$email = strtolower(sanitizeString($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');
$role = sanitizeString($body['role'] ?? '');
$rollOrId = sanitizeString($body['roll_or_id'] ?? '');
$designation = sanitizeString($body['designation'] ?? '');
$otp = sanitizeString($body['otp'] ?? '');

if ($name === '' || mb_strlen($name) > 120) {
    jsonError('Enter a valid full name.');
}

if (!isValidEmailAddress($email)) {
    jsonError('Enter a real, deliverable email address.');
}

if (strlen($password) < 6) {
    jsonError('Password must be at least 6 characters.');
}

if (!in_array($role, ['student', 'admin'], true)) {
    jsonError('Invalid role.');
}

if (!preg_match('/^\d{6}$/', $otp)) {
    jsonError('Enter the 6-digit verification code sent to the email.');
}

$pending = $_SESSION['create_user_verify'] ?? null;
if (
    !is_array($pending)
    || ($pending['email'] ?? '') !== $email
    || (int) ($pending['expires_at'] ?? 0) < time()
) {
    jsonError('Email verification expired. Request a new code.');
}

$attempts = (int) ($pending['attempts'] ?? 0);
if ($attempts >= 5) {
    unset($_SESSION['create_user_verify']);
    jsonError('Too many incorrect codes. Request a new verification code.');
}

if (!password_verify($otp, (string) ($pending['otp_hash'] ?? ''))) {
    $_SESSION['create_user_verify']['attempts'] = $attempts + 1;
    // #region agent log
    $logFile = dirname(__DIR__, 3) . '/debug-20c118.log';
    @file_put_contents($logFile, json_encode([
        'sessionId' => '20c118',
        'runId' => 'email-verify',
        'hypothesisId' => 'B',
        'location' => 'admin/users/create.php',
        'message' => 'create otp mismatch',
        'data' => ['attempts' => $attempts + 1],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    // #endregion
    jsonError('Incorrect verification code.');
}

unset($_SESSION['create_user_verify']);

if ($role === 'admin' && $designation === '') {
    $designation = 'Teacher, CSE';
}

if ($role === 'student') {
    $designation = null;
}

try {
    $exists = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $exists->execute([$email]);
    if ($exists->fetch()) {
        jsonError('An account with this email already exists.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare(
        'INSERT INTO users (name, email, email_verified, password_hash, role, roll_or_id, department, designation)
         VALUES (?, ?, 1, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $email,
        $hash,
        $role,
        $rollOrId !== '' ? $rollOrId : null,
        'CSE',
        $designation,
    ]);

    $id = (int) db()->lastInsertId();
    $user = db()->prepare(
        'SELECT id, name, email, role, roll_or_id, department, designation, created_at
         FROM users WHERE id = ?'
    );
    $user->execute([$id]);
    $row = $user->fetch();

    // #region agent log
    $logFile = dirname(__DIR__, 3) . '/debug-20c118.log';
    @file_put_contents($logFile, json_encode([
        'sessionId' => '20c118',
        'runId' => 'email-verify',
        'hypothesisId' => 'A',
        'location' => 'admin/users/create.php',
        'message' => 'user created after email verify',
        'data' => ['userId' => $id, 'role' => $role],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    // #endregion
} catch (Throwable $e) {
    jsonError('Could not create user.', 500);
}

jsonResponse([
    'ok'   => true,
    'user' => publicUser($row),
], 201);
