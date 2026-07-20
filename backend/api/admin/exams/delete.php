<?php
/**
 * POST /backend/api/admin/exams/delete.php
 * Body: { id }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
requireRole('admin');

$body = readJsonBody();
$id = (int) ($body['id'] ?? 0);

if ($id < 1) {
    jsonError('Invalid exam id.');
}

$pdo = db();
try {
    $find = $pdo->prepare('SELECT id FROM exams WHERE id = ? LIMIT 1');
    $find->execute([$id]);
    if (!$find->fetch()) {
        jsonError('Exam not found.', 404);
    }

    $pdo->beginTransaction();

    // Remove attempts/answers first (answers cascade from attempts)
    $attempts = $pdo->prepare('SELECT id FROM exam_attempts WHERE exam_id = ?');
    $attempts->execute([$id]);
    $attemptIds = $attempts->fetchAll(PDO::FETCH_COLUMN);

    if ($attemptIds) {
        $placeholders = implode(',', array_fill(0, count($attemptIds), '?'));
        $pdo->prepare("DELETE FROM exam_answers WHERE attempt_id IN ($placeholders)")->execute($attemptIds);
        $pdo->prepare('DELETE FROM exam_attempts WHERE exam_id = ?')->execute([$id]);
    }

    $pdo->prepare('DELETE FROM exam_questions WHERE exam_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM exams WHERE id = ?')->execute([$id]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Could not delete exam.', 500);
}

jsonResponse(['ok' => true]);
