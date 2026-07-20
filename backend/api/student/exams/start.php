<?php
/**
 * GET /backend/api/student/exams/start.php?exam_id=
 * Creates or resumes an attempt. Never returns correct_option.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('GET');
$student = requireRole('student');
$studentId = (int) $student['id'];
$examId = (int) ($_GET['exam_id'] ?? 0);

if ($examId < 1) {
    jsonError('Invalid exam id.');
}

$now = new DateTimeImmutable('now');
$pdo = db();

try {
    $examStmt = $pdo->prepare(
        'SELECT id, title, subject_code, duration_minutes, total_marks, start_time, status
         FROM exams WHERE id = ? LIMIT 1'
    );
    $examStmt->execute([$examId]);
    $exam = $examStmt->fetch();
    if (!$exam) {
        jsonError('Exam not found.', 404);
    }
    if ($exam['status'] === 'draft') {
        jsonError('This exam is not available.', 403);
    }

    $start = new DateTimeImmutable($exam['start_time']);
    $end = $start->modify('+' . (int) $exam['duration_minutes'] . ' minutes');

    $attStmt = $pdo->prepare(
        'SELECT id, status, started_at, score FROM exam_attempts
         WHERE exam_id = ? AND student_id = ? LIMIT 1'
    );
    $attStmt->execute([$examId, $studentId]);
    $attempt = $attStmt->fetch();

    if ($attempt && $attempt['status'] === 'submitted') {
        jsonError('You have already submitted this exam.', 403, [
            'attempt_id' => (int) $attempt['id'],
            'redirect'   => 'result.html?attempt_id=' . (int) $attempt['id'],
        ]);
    }

    if ($now < $start) {
        jsonError('This exam has not started yet.');
    }

    if ($now > $end && (!$attempt || $attempt['status'] !== 'in_progress')) {
        jsonError('The exam window has closed.');
    }

    if (!$attempt) {
        if ($now > $end) {
            jsonError('The exam window has closed.');
        }
        $ins = $pdo->prepare(
            'INSERT INTO exam_attempts (exam_id, student_id, started_at, status)
             VALUES (?, ?, ' . sqlNow() . ', \'in_progress\')'
        );
        $ins->execute([$examId, $studentId]);
        $attemptId = (int) $pdo->lastInsertId();
    } else {
        $attemptId = (int) $attempt['id'];
    }

    // If window already ended but attempt still in progress, force auto-grade path via submit
    $remaining = max(0, $end->getTimestamp() - $now->getTimestamp());

    $qStmt = $pdo->prepare(
        'SELECT id, question_text, option_a, option_b, option_c, option_d, marks, order_no
         FROM exam_questions
         WHERE exam_id = ?
         ORDER BY order_no ASC, id ASC'
    );
    $qStmt->execute([$examId]);
    $questions = $qStmt->fetchAll();

    $ansStmt = $pdo->prepare(
        'SELECT exam_question_id, selected_option FROM exam_answers WHERE attempt_id = ?'
    );
    $ansStmt->execute([$attemptId]);
    $saved = [];
    foreach ($ansStmt->fetchAll() as $a) {
        $saved[(int) $a['exam_question_id']] = $a['selected_option'];
    }

    $outQuestions = [];
    foreach ($questions as $q) {
        $qid = (int) $q['id'];
        $outQuestions[] = [
            'id'    => $qid,
            'text'  => $q['question_text'],
            'marks' => (int) $q['marks'],
            'options' => [
                'A' => $q['option_a'],
                'B' => $q['option_b'],
                'C' => $q['option_c'],
                'D' => $q['option_d'],
            ],
            'selected' => $saved[$qid] ?? null,
        ];
    }
} catch (Throwable $e) {
    jsonError('Could not start exam.', 500);
}

jsonResponse([
    'ok' => true,
    'exam' => [
        'id'               => (int) $exam['id'],
        'title'            => $exam['title'],
        'subject'          => $exam['subject_code'],
        'duration_minutes' => (int) $exam['duration_minutes'],
        'total_marks'      => (int) $exam['total_marks'],
        'start_time'       => $exam['start_time'],
        'ends_at'          => $end->format('Y-m-d H:i:s'),
    ],
    'attempt_id'         => $attemptId,
    'remaining_seconds'  => $remaining,
    'questions'          => $outQuestions,
]);
