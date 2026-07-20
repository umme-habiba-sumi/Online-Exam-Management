<?php
/**
 * POST /backend/api/student/exams/save-answer.php
 * Body: { attempt_id, exam_question_id, selected_option: A|B|C|D|null }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
$student = requireRole('student');
$studentId = (int) $student['id'];

$body = readJsonBody();
$attemptId = (int) ($body['attempt_id'] ?? 0);
$questionId = (int) ($body['exam_question_id'] ?? 0);
$selected = $body['selected_option'] ?? null;

if ($attemptId < 1 || $questionId < 1) {
    jsonError('Invalid attempt or question.');
}

if ($selected !== null && $selected !== '') {
    $selected = strtoupper(sanitizeString((string) $selected));
    if (!in_array($selected, ['A', 'B', 'C', 'D'], true)) {
        jsonError('Invalid option.');
    }
} else {
    $selected = null;
}

$pdo = db();
$now = new DateTimeImmutable('now');

try {
    $stmt = $pdo->prepare(
        'SELECT a.id, a.status, a.exam_id, e.start_time, e.duration_minutes
         FROM exam_attempts a
         JOIN exams e ON e.id = a.exam_id
         WHERE a.id = ? AND a.student_id = ?
         LIMIT 1'
    );
    $stmt->execute([$attemptId, $studentId]);
    $attempt = $stmt->fetch();
    if (!$attempt) {
        jsonError('Attempt not found.', 404);
    }
    if ($attempt['status'] !== 'in_progress') {
        jsonError('This attempt is already submitted.', 403);
    }

    $end = (new DateTimeImmutable($attempt['start_time']))
        ->modify('+' . (int) $attempt['duration_minutes'] . ' minutes');
    if ($now > $end) {
        jsonError('Time is up. Please submit the exam.', 403, ['time_up' => true]);
    }

    $qCheck = $pdo->prepare(
        'SELECT id FROM exam_questions WHERE id = ? AND exam_id = ? LIMIT 1'
    );
    $qCheck->execute([$questionId, (int) $attempt['exam_id']]);
    if (!$qCheck->fetch()) {
        jsonError('Question does not belong to this exam.');
    }

    $upsertSql = dbIsSqlite()
        ? 'INSERT INTO exam_answers (attempt_id, exam_question_id, selected_option, is_correct)
           VALUES (?, ?, ?, NULL)
           ON CONFLICT(attempt_id, exam_question_id) DO UPDATE SET
             selected_option = excluded.selected_option,
             is_correct = NULL'
        : 'INSERT INTO exam_answers (attempt_id, exam_question_id, selected_option, is_correct)
           VALUES (?, ?, ?, NULL)
           ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), is_correct = NULL';
    $upsert = $pdo->prepare($upsertSql);
    $upsert->execute([$attemptId, $questionId, $selected]);
} catch (Throwable $e) {
    jsonError('Could not save answer.', 500);
}

jsonResponse(['ok' => true]);
