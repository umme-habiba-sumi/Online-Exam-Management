<?php
/**
 * GET /backend/api/admin/results/list.php?exam_id=all|123
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('GET');
requireRole('admin');

$examId = sanitizeString($_GET['exam_id'] ?? 'all');

try {
    if ($examId === '' || strtolower($examId) === 'all') {
        $stmt = db()->query(
            'SELECT a.id AS attempt_id, a.score, a.submitted_at,
                    u.name AS student_name, u.roll_or_id,
                    e.id AS exam_id, e.title AS exam_title, e.total_marks
             FROM exam_attempts a
             JOIN users u ON u.id = a.student_id
             JOIN exams e ON e.id = a.exam_id
             WHERE a.status = \'submitted\'
             ORDER BY a.submitted_at DESC, a.id DESC'
        );
        $rows = $stmt->fetchAll();
    } else {
        $id = (int) $examId;
        $stmt = db()->prepare(
            'SELECT a.id AS attempt_id, a.score, a.submitted_at,
                    u.name AS student_name, u.roll_or_id,
                    e.id AS exam_id, e.title AS exam_title, e.total_marks
             FROM exam_attempts a
             JOIN users u ON u.id = a.student_id
             JOIN exams e ON e.id = a.exam_id
             WHERE a.status = \'submitted\' AND a.exam_id = ?
             ORDER BY a.submitted_at DESC, a.id DESC'
        );
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    jsonError('Could not load results.', 500);
}

$results = array_map(static function (array $r): array {
    return [
        'attempt_id' => (int) $r['attempt_id'],
        'exam_id'    => (int) $r['exam_id'],
        'student'    => $r['student_name'],
        'roll'       => $r['roll_or_id'] ?? '—',
        'exam'       => $r['exam_title'],
        'score'      => (int) $r['score'],
        'total'      => (int) $r['total_marks'],
        'submitted'  => $r['submitted_at']
            ? date('j M, g:i A', strtotime($r['submitted_at']))
            : '',
        'submitted_at' => $r['submitted_at'],
    ];
}, $rows);

jsonResponse(['ok' => true, 'results' => $results]);
