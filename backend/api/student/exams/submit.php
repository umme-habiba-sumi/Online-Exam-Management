<?php
/**
 * POST /backend/api/student/exams/submit.php
 * Body: { attempt_id }
 * Grades server-side; never trusts client score.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/notifications.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
$student = requireRole('student');
$studentId = (int) $student['id'];

$body = readJsonBody();
$attemptId = (int) ($body['attempt_id'] ?? 0);

if ($attemptId < 1) {
    jsonError('Invalid attempt.');
}

$pdo = db();

try {
    $stmt = $pdo->prepare(
        'SELECT a.id, a.status, a.exam_id, e.total_marks, e.title
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

    if ($attempt['status'] === 'submitted') {
        jsonResponse([
            'ok'         => true,
            'already'    => true,
            'attempt_id' => $attemptId,
            'score'      => null,
            'redirect'   => 'result.html?attempt_id=' . $attemptId,
        ]);
    }

    $pdo->beginTransaction();

    // Lock attempt row (FOR UPDATE is MySQL-only)
    if (dbIsSqlite()) {
        $lock = $pdo->prepare(
            'SELECT id, status FROM exam_attempts WHERE id = ? AND student_id = ?'
        );
    } else {
        $lock = $pdo->prepare(
            'SELECT id, status FROM exam_attempts WHERE id = ? AND student_id = ? FOR UPDATE'
        );
    }
    $lock->execute([$attemptId, $studentId]);
    $locked = $lock->fetch();
    if (!$locked || $locked['status'] !== 'in_progress') {
        $pdo->rollBack();
        jsonError('This attempt cannot be submitted.', 403);
    }

    $qStmt = $pdo->prepare(
        'SELECT eq.id, eq.correct_option, eq.marks,
                ea.selected_option
         FROM exam_questions eq
         LEFT JOIN exam_answers ea
           ON ea.exam_question_id = eq.id AND ea.attempt_id = ?
         WHERE eq.exam_id = ?'
    );
    $qStmt->execute([$attemptId, (int) $attempt['exam_id']]);
    $rows = $qStmt->fetchAll();

    $score = 0;
    $correctCount = 0;
    $totalQ = count($rows);

    $updAnsSql = dbIsSqlite()
        ? 'INSERT INTO exam_answers (attempt_id, exam_question_id, selected_option, is_correct)
           VALUES (?, ?, ?, ?)
           ON CONFLICT(attempt_id, exam_question_id) DO UPDATE SET
             is_correct = excluded.is_correct'
        : 'INSERT INTO exam_answers (attempt_id, exam_question_id, selected_option, is_correct)
           VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE is_correct = VALUES(is_correct)';
    $updAns = $pdo->prepare($updAnsSql);

    foreach ($rows as $row) {
        $selected = $row['selected_option'];
        $isCorrect = ($selected !== null && $selected === $row['correct_option']) ? 1 : 0;
        if ($isCorrect) {
            $score += (int) $row['marks'];
            $correctCount++;
        }

        // Ensure answer row exists for review (null selected if unanswered)
        $updAns->execute([
            $attemptId,
            (int) $row['id'],
            $selected,
            $selected === null ? 0 : $isCorrect,
        ]);
    }

    $fin = $pdo->prepare(
        'UPDATE exam_attempts
         SET status = \'submitted\', submitted_at = ' . sqlNow() . ', score = ?
         WHERE id = ?'
    );
    $fin->execute([$score, $attemptId]);

    notifyAdminsExamSubmitted($pdo, (string) $attempt['title'], (string) $student['name'], (int) $attempt['exam_id']);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Could not submit exam.', 500);
}

jsonResponse([
    'ok'            => true,
    'attempt_id'    => $attemptId,
    'score'         => $score,
    'total_marks'   => (int) $attempt['total_marks'],
    'correct_count' => $correctCount,
    'total_questions' => $totalQ,
    'redirect'      => 'result.html?attempt_id=' . $attemptId,
]);
