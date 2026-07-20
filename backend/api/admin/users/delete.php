<?php
/**
 * POST /backend/api/admin/users/delete.php
 * Body: { id }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
$admin = requireRole('admin');

$body = readJsonBody();
$id = (int) ($body['id'] ?? 0);

if ($id < 1) {
    jsonError('Invalid user id.');
}

if ((int) $admin['id'] === $id) {
    jsonError('You cannot remove your own account.');
}

try {
    $find = db()->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
    $find->execute([$id]);
    $existing = $find->fetch();
    if (!$existing) {
        jsonError('User not found.', 404);
    }

    // Keep at least one admin in the system
    if ($existing['role'] === 'admin') {
        $count = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($count <= 1) {
            jsonError('Cannot remove the last admin account.');
        }
    }

    $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
} catch (Throwable $e) {
    jsonError('Could not remove user. They may have related exam data.', 500);
}

jsonResponse(['ok' => true]);
