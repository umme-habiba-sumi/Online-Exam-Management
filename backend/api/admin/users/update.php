<?php
/**
 * POST /backend/api/admin/users/update.php
 * Body: { id, name, email, roll_or_id? }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
$admin = requireRole('admin');

$body = readJsonBody();
$id = (int) ($body['id'] ?? 0);
$name = sanitizeString($body['name'] ?? '');
$email = strtolower(sanitizeString($body['email'] ?? ''));
$rollOrId = sanitizeString($body['roll_or_id'] ?? '');

if ($id < 1) {
    jsonError('Invalid user id.');
}

if ($name === '' || mb_strlen($name) > 120) {
    jsonError('Enter a valid full name.');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Enter a valid email address.');
}

try {
    $find = db()->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
    $find->execute([$id]);
    $existing = $find->fetch();
    if (!$existing) {
        jsonError('User not found.', 404);
    }

    $dup = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $dup->execute([$email, $id]);
    if ($dup->fetch()) {
        jsonError('Another account already uses this email.');
    }

    $stmt = db()->prepare(
        'UPDATE users
         SET name = ?, email = ?, roll_or_id = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $name,
        $email,
        $rollOrId !== '' ? $rollOrId : null,
        $id,
    ]);

    // Keep session email/name in sync if admin edits themselves
    if ((int) $admin['id'] === $id) {
        // session only stores ids; currentUser() reloads from DB
    }

    $user = db()->prepare(
        'SELECT id, name, email, role, roll_or_id, department, designation, created_at
         FROM users WHERE id = ?'
    );
    $user->execute([$id]);
    $row = $user->fetch();
} catch (Throwable $e) {
    jsonError('Could not update user.', 500);
}

jsonResponse(['ok' => true, 'user' => publicUser($row)]);
