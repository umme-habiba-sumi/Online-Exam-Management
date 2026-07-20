<?php
/**
 * GET /backend/api/notifications/list.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/notifications.php';

sendCorsHeaders();
requireMethod('GET');
$user = requireLogin();
$userId = (int) $user['id'];

$pdo = db();
ensureNotificationsTable($pdo);

try {
    $stmt = $pdo->prepare(
        'SELECT id, type, title, body, link, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 40'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    jsonError('Could not load notifications.', 500);
}

$unread = 0;
$items = [];
foreach ($rows as $row) {
    if (!(int) $row['is_read']) {
        $unread++;
    }
    $items[] = formatNotificationRow($row);
}

jsonResponse([
    'ok'            => true,
    'notifications' => $items,
    'unread_count'  => $unread,
]);
