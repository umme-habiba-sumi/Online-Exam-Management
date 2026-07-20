<?php
/**
 * POST /backend/api/admin/exams/update.php
 * Body: { id, title, subject_code, duration_minutes?, total_marks?, start_time?, status? }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/exam_helpers.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
requireRole('admin');

$body = readJsonBody();
$id = (int) ($body['id'] ?? 0);
$title = sanitizeString($body['title'] ?? '');
$subject = strtoupper(sanitizeString($body['subject_code'] ?? ''));

if ($id < 1) {
    jsonError('Invalid exam id.');
}
if ($title === '' || mb_strlen($title) > 150) {
    jsonError('Enter a valid exam title.');
}
if ($subject === '' || mb_strlen($subject) > 20) {
    jsonError('Enter a valid subject code.');
}

try {
    $find = db()->prepare(
        'SELECT id, duration_minutes, total_marks, start_time, status FROM exams WHERE id = ? LIMIT 1'
    );
    $find->execute([$id]);
    $existing = $find->fetch();
    if (!$existing) {
        jsonError('Exam not found.', 404);
    }

    $duration = array_key_exists('duration_minutes', $body)
        ? (int) $body['duration_minutes']
        : (int) $existing['duration_minutes'];
    $totalMarks = array_key_exists('total_marks', $body)
        ? (int) $body['total_marks']
        : (int) $existing['total_marks'];
    $startTime = array_key_exists('start_time', $body)
        ? sanitizeString((string) $body['start_time'])
        : $existing['start_time'];
    $status = array_key_exists('status', $body)
        ? sanitizeString((string) $body['status'])
        : $existing['status'];

    if ($duration < 1) {
        jsonError('Duration must be at least 1 minute.');
    }
    if ($totalMarks < 1) {
        jsonError('Total marks must be at least 1.');
    }
    if ($startTime === '' || strtotime($startTime) === false) {
        jsonError('Enter a valid start time.');
    }
    if (!in_array($status, ['draft', 'published', 'closed'], true)) {
        jsonError('Invalid status.');
    }

    $startMysql = date('Y-m-d H:i:s', strtotime($startTime));

    $stmt = db()->prepare(
        'UPDATE exams
         SET title = ?, subject_code = ?, duration_minutes = ?, total_marks = ?, start_time = ?, status = ?
         WHERE id = ?'
    );
    $stmt->execute([$title, $subject, $duration, $totalMarks, $startMysql, $status, $id]);

    $list = db()->prepare(
        'SELECT e.id, e.title, e.subject_code, e.duration_minutes, e.total_marks,
                e.start_time, e.status, e.created_by, e.created_at,
                (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) AS question_count,
                (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.id) AS attempt_count
         FROM exams e WHERE e.id = ?'
    );
    $list->execute([$id]);
    $row = $list->fetch();
} catch (Throwable $e) {
    jsonError('Could not update exam.', 500);
}

jsonResponse(['ok' => true, 'exam' => formatExamRow($row)]);
