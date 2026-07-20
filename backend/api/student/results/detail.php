<?php
/**
 * GET /backend/api/student/results/detail.php?attempt_id=
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('GET');
$student = requireRole('student');
$attemptId = (int) ($_GET['attempt_id'] ?? 0);

if ($attemptId < 1) {
    jsonError('Invalid attempt id.');
}

try {
    $stmt = db()->prepare(
        'SELECT a.id, a.score, a.submitted_at, a.status, a.exam_id,
                e.title, e.subject_code, e.total_marks
         FROM exam_attempts a
         JOIN exams e ON e.id = a.exam_id
         WHERE a.id = ? AND a.student_id = ?
         LIMIT 1'
    );
    $stmt->execute([$attemptId, (int) $student['id']]);
    $attempt = $stmt->fetch();
    if (!$attempt) {
        jsonError('Result not found.', 404);
    }
    if ($attempt['status'] !== 'submitted') {
        jsonError('This exam has not been submitted yet.', 403);
    }

    $qStmt = db()->prepare(
        'SELECT eq.id, eq.question_text, eq.option_a, eq.option_b, eq.option_c, eq.option_d,
                eq.correct_option, eq.marks, eq.order_no,
                ea.selected_option, ea.is_correct
         FROM exam_questions eq
         LEFT JOIN exam_answers ea
           ON ea.exam_question_id = eq.id AND ea.attempt_id = ?
         WHERE eq.exam_id = ?
         ORDER BY eq.order_no ASC, eq.id ASC'
    );
    $qStmt->execute([$attemptId, (int) $attempt['exam_id']]);
    $rows = $qStmt->fetchAll();
} catch (Throwable $e) {
    jsonError('Could not load result detail.', 500);
}

$optionMap = static function (array $q, ?string $letter): string {
    if ($letter === null || $letter === '') {
        return '—';
    }
    $key = 'option_' . strtolower($letter);
    return $q[$key] ?? $letter;
};

$review = [];
$correctCount = 0;
foreach ($rows as $q) {
    $ok = (int) ($q['is_correct'] ?? 0) === 1;
    if ($ok) {
        $correctCount++;
    }
    $review[] = [
        'question_text'   => $q['question_text'],
        'your_option'     => $q['selected_option'],
        'correct_option'  => $q['correct_option'],
        'your'            => $optionMap($q, $q['selected_option']),
        'correct'         => $optionMap($q, $q['correct_option']),
        'ok'              => $ok,
        'marks'           => (int) $q['marks'],
    ];
}

$totalQ = count($review);
$score = (int) $attempt['score'];
$total = (int) $attempt['total_marks'];
$pct = $total > 0 ? (int) round(($score / $total) * 100) : 0;

jsonResponse([
    'ok' => true,
    'result' => [
        'attempt_id'      => $attemptId,
        'exam_id'         => (int) $attempt['exam_id'],
        'title'           => $attempt['title'],
        'subject'         => $attempt['subject_code'],
        'score'           => $score,
        'total_marks'     => $total,
        'percent'         => $pct,
        'correct_count'   => $correctCount,
        'incorrect_count' => $totalQ - $correctCount,
        'total_questions' => $totalQ,
        'submitted_at'    => $attempt['submitted_at'],
        'submitted'       => $attempt['submitted_at']
            ? date('j M, g:i A', strtotime($attempt['submitted_at']))
            : '',
        'review'          => $review,
    ],
]);
