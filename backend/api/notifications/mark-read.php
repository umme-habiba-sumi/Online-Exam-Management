<?php
/**
 * POST /backend/api/notifications/mark-read.php
 * Body: { id? } — omit id to mark all read.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/notifications.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
$user = requireLogin();
$userId = (int) $user['id'];

$pdo = db();
ensureNotificationsTable($pdo);

$body = readJsonBody();
$id = isset($body['id']) ? (int) $body['id'] : 0;

try {
    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
    }
} catch (Throwable $e) {
    jsonError('Could not update notifications.', 500);
}

jsonResponse(['ok' => true]);
