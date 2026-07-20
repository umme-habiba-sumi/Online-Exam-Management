<?php
/**
 * GET /backend/api/student/results/list.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('GET');
$student = requireRole('student');

try {
    $stmt = db()->prepare(
        'SELECT a.id AS attempt_id, a.score, a.submitted_at, a.exam_id,
                e.title, e.subject_code, e.total_marks
         FROM exam_attempts a
         JOIN exams e ON e.id = a.exam_id
         WHERE a.student_id = ? AND a.status = \'submitted\'
         ORDER BY a.submitted_at DESC, a.id DESC'
    );
    $stmt->execute([(int) $student['id']]);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    jsonError('Could not load results.', 500);
}

$results = array_map(static function (array $r): array {
    return [
        'attempt_id'   => (int) $r['attempt_id'],
        'exam_id'      => (int) $r['exam_id'],
        'exam'         => $r['title'],
        'subject'      => $r['subject_code'],
        'score'        => (int) $r['score'],
        'total'        => (int) $r['total_marks'],
        'submitted_at' => $r['submitted_at'],
        'submitted'    => $r['submitted_at']
            ? date('j M, g:i A', strtotime($r['submitted_at']))
            : '',
    ];
}, $rows);

jsonResponse(['ok' => true, 'results' => $results]);
