<?php
/**
 * GET /backend/api/student/exams/list.php
 * Published exams with student-facing status + attempt info.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/exam_helpers.php';

sendCorsHeaders();
requireMethod('GET');
$student = requireRole('student');
$studentId = (int) $student['id'];
$now = new DateTimeImmutable('now');

try {
    $stmt = db()->prepare(
        'SELECT e.id, e.title, e.subject_code, e.duration_minutes, e.total_marks,
                e.start_time, e.status, e.created_at,
                (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) AS question_count,
                a.id AS attempt_id, a.status AS attempt_status, a.score AS attempt_score,
                a.submitted_at
         FROM exams e
         LEFT JOIN exam_attempts a
           ON a.exam_id = e.id AND a.student_id = ?
         WHERE e.status IN (\'published\', \'closed\')
         ORDER BY e.start_time DESC, e.id DESC'
    );
    $stmt->execute([$studentId]);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    jsonError('Could not load exams.', 500);
}

$exams = [];
foreach ($rows as $row) {
    $start = new DateTimeImmutable($row['start_time']);
    $end = $start->modify('+' . (int) $row['duration_minutes'] . ' minutes');
    $attemptId = $row['attempt_id'] !== null ? (int) $row['attempt_id'] : null;
    $attemptStatus = $row['attempt_status'];

    if ($attemptStatus === 'submitted') {
        $studentStatus = 'closed';
        $window = 'Submitted' . ($row['submitted_at'] ? ' · ' . date('j M, g:i A', strtotime($row['submitted_at'])) : '');
    } elseif ($now < $start) {
        $studentStatus = 'upcoming';
        $window = 'Opens ' . $start->format('j M, g:i A');
    } elseif ($now <= $end && ($attemptStatus === 'in_progress' || $attemptId === null)) {
        $studentStatus = 'live';
        $window = 'Ends ' . $end->format('g:i A') . ' today';
        if ($end->format('Y-m-d') !== $now->format('Y-m-d')) {
            $window = 'Ends ' . $end->format('j M, g:i A');
        }
    } else {
        $studentStatus = 'missed';
        $window = 'Window ended' . ($end ? ', not attempted' : '');
        if ($attemptStatus === 'in_progress') {
            $window = 'Window ended — incomplete';
        }
    }

    $exams[] = [
        'id'               => (int) $row['id'],
        'title'            => $row['title'],
        'subject'          => $row['subject_code'],
        'duration'         => (int) $row['duration_minutes'],
        'duration_minutes' => (int) $row['duration_minutes'],
        'marks'            => (int) $row['total_marks'],
        'total_marks'      => (int) $row['total_marks'],
        'questions'        => (int) $row['question_count'],
        'start_time'       => $row['start_time'],
        'status'           => $studentStatus,
        'window'           => $window,
        'attempt_id'       => $attemptId,
        'attempt_status'   => $attemptStatus,
        'score'            => $row['attempt_score'] !== null ? (int) $row['attempt_score'] : null,
    ];
}

jsonResponse(['ok' => true, 'exams' => $exams]);
