<?php
/**
 * POST /backend/api/admin/questions/delete.php
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
    jsonError('Invalid question id.');
}

try {
    $find = db()->prepare('SELECT id FROM question_bank WHERE id = ? LIMIT 1');
    $find->execute([$id]);
    if (!$find->fetch()) {
        jsonError('Question not found.', 404);
    }

    // Keep exam_questions copies; just detach bank reference
    db()->prepare('UPDATE exam_questions SET question_bank_id = NULL WHERE question_bank_id = ?')
        ->execute([$id]);

    db()->prepare('DELETE FROM question_bank WHERE id = ?')->execute([$id]);
} catch (Throwable $e) {
    jsonError('Could not delete question.', 500);
}

jsonResponse(['ok' => true]);
