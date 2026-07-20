<?php
/**
 * POST /backend/api/auth/login.php
 * Body: { email?, student_id?, password, role }  role = student|admin
 * Students may sign in with student_id (roll_or_id) instead of email.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
startAppSession();
ensureAvatarColumn(db());

$body = readJsonBody();
$password = (string) ($body['password'] ?? '');
$role = sanitizeString($body['role'] ?? '');

if (strlen($password) < 6) {
    jsonError('Password must be at least 6 characters.');
}

if (!in_array($role, ['student', 'admin'], true)) {
    jsonError('Invalid role selected.');
}

$user = null;
$studentId = '';

try {
    if ($role === 'student') {
        $studentId = trim(sanitizeString($body['student_id'] ?? ''));
        $email = strtolower(trim(sanitizeString($body['email'] ?? '')));

        if ($studentId !== '') {
            $stmt = db()->prepare(
                'SELECT id, name, email, password_hash, role, roll_or_id, department, designation, avatar, created_at
                 FROM users
                 WHERE UPPER(TRIM(roll_or_id)) = UPPER(TRIM(?)) AND role = ?
                 LIMIT 1'
            );
            $stmt->execute([$studentId, $role]);
            $user = $stmt->fetch();
        } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = db()->prepare(
                'SELECT id, name, email, password_hash, role, roll_or_id, department, designation, avatar, created_at
                 FROM users
                 WHERE email = ? AND role = ?
                 LIMIT 1'
            );
            $stmt->execute([$email, $role]);
            $user = $stmt->fetch();
        } else {
            jsonError('Enter your student ID.');
        }
    } else {
        $email = strtolower(trim(sanitizeString($body['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Enter a valid email address.');
        }

        $stmt = db()->prepare(
            'SELECT id, name, email, password_hash, role, roll_or_id, department, designation, avatar, created_at
             FROM users
             WHERE email = ? AND role = ?
             LIMIT 1'
        );
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch();
    }
} catch (Throwable $e) {
    jsonError('Database error. Check that the exam_portal database is set up.', 500);
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    // #region agent log
    $logFile = dirname(__DIR__, 2) . '/debug-20c118.log';
    $log = [
        'sessionId' => '20c118',
        'runId' => 'login-attempt',
        'hypothesisId' => 'D',
        'location' => 'auth/login.php:fail',
        'message' => 'login rejected',
        'data' => [
            'role' => $role,
            'userFound' => (bool) $user,
            'studentIdLen' => strlen((string) $studentId),
            'passwordLen' => strlen($password),
            'passwordOk' => $user ? password_verify($password, $user['password_hash']) : false,
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ];
    @file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    // #endregion
    jsonError($role === 'student' ? 'Invalid student ID, password, or role.' : 'Invalid email, password, or role.', 401);
}

loginUser($user);

jsonResponse([
    'ok'         => true,
    'user'       => publicUser($user),
    'redirect'   => dashboardUrlForRole($user['role']),
    'csrf_token' => csrfToken(),
]);
