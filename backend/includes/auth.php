<?php
/**
 * Session auth guards.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/config/db.php';

function ensureAvatarColumn(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (dbIsSqlite()) {
        return; // created in sqlite_bootstrap
    }
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER designation');
    } catch (Throwable $e) {
        // Column may already exist
    }
}

function ensureEmailVerifiedColumn(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (dbIsSqlite()) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1 AFTER email');
    } catch (Throwable $e) {
        // Column may already exist
    }
}

function currentUser(): ?array
{
    startAppSession();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cached = null;
    if (is_array($cached) && (int) $cached['id'] === (int) $_SESSION['user_id']) {
        return $cached;
    }

    ensureAvatarColumn(db());

    $stmt = db()->prepare(
        'SELECT id, name, email, role, roll_or_id, department, designation, avatar, created_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['role']);
        return null;
    }

    $cached = $user;
    return $user;
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        jsonError('Authentication required.', 401);
    }
    return $user;
}

function requireRole(string $role): array
{
    $user = requireLogin();
    if (($user['role'] ?? '') !== $role) {
        jsonError('Forbidden.', 403);
    }
    return $user;
}

function loginUser(array $user): void
{
    startAppSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    csrfToken(); // ensure token exists after login
}

function logoutUser(): void
{
    startAppSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

/** Public user payload safe for the client (no password hash). */
function publicUser(array $user): array
{
    $avatar = $user['avatar'] ?? null;
    return [
        'id'           => (int) $user['id'],
        'name'         => $user['name'],
        'email'        => $user['email'],
        'role'         => $user['role'],
        'roll_or_id'   => $user['roll_or_id'],
        'department'   => $user['department'],
        'designation'  => $user['designation'],
        'avatar'       => $avatar,
        'created_at'   => $user['created_at'],
    ];
}

function dashboardUrlForRole(string $role): string
{
    return $role === 'admin' ? '/admin/dashboard.html' : '/student/dashboard.html';
}
