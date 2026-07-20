<?php
/**
 * GET /backend/api/admin/users/list.php?role=all|student|admin
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('GET');
requireRole('admin');

$role = sanitizeString($_GET['role'] ?? 'all');
if (!in_array($role, ['all', 'student', 'admin'], true)) {
    jsonError('Invalid role filter.');
}

try {
    if ($role === 'all') {
        $stmt = db()->query(
            'SELECT id, name, email, role, roll_or_id, department, designation, created_at
             FROM users
             ORDER BY created_at DESC, id DESC'
        );
        $users = $stmt->fetchAll();
    } else {
        $stmt = db()->prepare(
            'SELECT id, name, email, role, roll_or_id, department, designation, created_at
             FROM users
             WHERE role = ?
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$role]);
        $users = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    jsonError('Could not load users.', 500);
}

$out = array_map(static function (array $u): array {
    return [
        'id'          => (int) $u['id'],
        'name'        => $u['name'],
        'email'       => $u['email'],
        'role'        => $u['role'],
        'roll_or_id'  => $u['roll_or_id'],
        'department'  => $u['department'],
        'designation' => $u['designation'],
        'created_at'  => $u['created_at'],
    ];
}, $users);

jsonResponse(['ok' => true, 'users' => $out]);
