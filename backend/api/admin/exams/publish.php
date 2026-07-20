<?php
/**
 * POST /backend/api/admin/exams/publish.php
 * Body: { id } — sets a draft exam to published.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/exam_helpers.php';
require_once dirname(__DIR__, 3) . '/includes/notifications.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
requireRole('admin');

$body = readJsonBody();
$id = (int) ($body['id'] ?? 0);

if ($id < 1) {
    jsonError('Invalid exam id.');
}

try {
    $find = db()->prepare(
        'SELECT e.id, e.title, e.subject_code, e.duration_minutes, e.total_marks,
                e.start_time, e.status, e.created_by, e.created_at,
                (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) AS question_count,
                (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.id) AS attempt_count
         FROM exams e WHERE e.id = ? LIMIT 1'
    );
    $find->execute([$id]);
    $exam = $find->fetch();
    if (!$exam) {
        jsonError('Exam not found.', 404);
    }
    if ((int) $exam['question_count'] < 1) {
        jsonError('Add at least one question before publishing.');
    }

    db()->prepare("UPDATE exams SET status = 'published' WHERE id = ?")->execute([$id]);
    $exam['status'] = 'published';

    notifyStudentsNewExam(db(), (string) $exam['title'], (string) $exam['subject_code'], $id);
} catch (Throwable $e) {
    jsonError('Could not publish exam.', 500);
}

jsonResponse(['ok' => true, 'exam' => formatExamRow($exam)]);
