<?php
/**
 * GET /backend/api/admin/exams/list.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/exam_helpers.php';

sendCorsHeaders();
requireMethod('GET');
requireRole('admin');

try {
    $stmt = db()->query(
        'SELECT e.id, e.title, e.subject_code, e.duration_minutes, e.total_marks,
                e.start_time, e.status, e.created_by, e.created_at,
                (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) AS question_count,
                (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.id) AS attempt_count
         FROM exams e
         ORDER BY e.start_time DESC, e.id DESC'
    );
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    jsonError('Could not load exams.', 500);
}

jsonResponse([
    'ok'    => true,
    'exams' => array_map('formatExamRow', $rows),
]);
