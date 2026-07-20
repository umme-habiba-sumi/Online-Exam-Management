<?php
/**
 * POST /backend/api/admin/exams/create.php
 * Body: title, subject_code, duration_minutes, total_marks, start_time,
 *       status (draft|published), questions: [{question_text, option_a..d, correct_option, marks?}]
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/exam_helpers.php';
require_once dirname(__DIR__, 3) . '/includes/notifications.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
$admin = requireRole('admin');

$body = readJsonBody();
$title = sanitizeString($body['title'] ?? '');
$subject = strtoupper(sanitizeString($body['subject_code'] ?? ''));
$duration = (int) ($body['duration_minutes'] ?? 0);
$totalMarks = (int) ($body['total_marks'] ?? 0);
$startTime = sanitizeString($body['start_time'] ?? '');
$status = sanitizeString($body['status'] ?? 'published');
$questions = $body['questions'] ?? [];

if ($title === '' || mb_strlen($title) > 150) {
    jsonError('Enter a valid exam title.');
}
if ($subject === '' || mb_strlen($subject) > 20) {
    jsonError('Enter a valid subject code.');
}
if ($duration < 1) {
    jsonError('Duration must be at least 1 minute.');
}
if ($totalMarks < 1) {
    jsonError('Total marks must be at least 1.');
}
if ($startTime === '' || strtotime($startTime) === false) {
    jsonError('Enter a valid start time.');
}
if (!in_array($status, ['draft', 'published'], true)) {
    jsonError('Invalid exam status.');
}
if (!is_array($questions) || count($questions) < 1) {
    jsonError('Add at least one question.');
}

$normalized = [];
foreach ($questions as $i => $q) {
    if (!is_array($q)) {
        jsonError('Invalid question at index ' . $i . '.');
    }
    $text = sanitizeString($q['question_text'] ?? '');
    $a = sanitizeString($q['option_a'] ?? '');
    $b = sanitizeString($q['option_b'] ?? '');
    $c = sanitizeString($q['option_c'] ?? '');
    $d = sanitizeString($q['option_d'] ?? '');
    $correct = strtoupper(sanitizeString($q['correct_option'] ?? ''));
    $marks = (int) ($q['marks'] ?? 1);

    if ($text === '' || $a === '' || $b === '' || $c === '' || $d === '') {
        jsonError('Question ' . ($i + 1) . ' needs text and all four options.');
    }
    if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
        jsonError('Question ' . ($i + 1) . ' needs a correct option (A–D).');
    }
    if ($marks < 1) {
        $marks = 1;
    }

    $normalized[] = [
        'text'     => $text,
        'a'        => $a,
        'b'        => $b,
        'c'        => $c,
        'd'        => $d,
        'correct'  => $correct,
        'marks'    => $marks,
        'order_no' => $i + 1,
    ];
}

$startMysql = date('Y-m-d H:i:s', strtotime($startTime));
$adminId = (int) $admin['id'];
$pdo = db();

try {
    $pdo->beginTransaction();

    $insExam = $pdo->prepare(
        'INSERT INTO exams (title, subject_code, duration_minutes, total_marks, start_time, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insExam->execute([$title, $subject, $duration, $totalMarks, $startMysql, $status, $adminId]);
    $examId = (int) $pdo->lastInsertId();

    $insBank = $pdo->prepare(
        'INSERT INTO question_bank
           (subject_code, topic, question_text, option_a, option_b, option_c, option_d, correct_option, created_by)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insQ = $pdo->prepare(
        'INSERT INTO exam_questions
           (exam_id, question_bank_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks, order_no)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($normalized as $q) {
        $insBank->execute([
            $subject,
            $q['text'],
            $q['a'],
            $q['b'],
            $q['c'],
            $q['d'],
            $q['correct'],
            $adminId,
        ]);
        $bankId = (int) $pdo->lastInsertId();

        $insQ->execute([
            $examId,
            $bankId,
            $q['text'],
            $q['a'],
            $q['b'],
            $q['c'],
            $q['d'],
            $q['correct'],
            $q['marks'],
            $q['order_no'],
        ]);
    }

    $pdo->commit();

    if ($status === 'published') {
        notifyStudentsNewExam($pdo, $title, $subject, $examId);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Could not create exam.', 500);
}

$list = $pdo->prepare(
    'SELECT e.id, e.title, e.subject_code, e.duration_minutes, e.total_marks,
            e.start_time, e.status, e.created_by, e.created_at,
            (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) AS question_count,
            (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.id) AS attempt_count
     FROM exams e WHERE e.id = ?'
);
$list->execute([$examId]);
$row = $list->fetch();

jsonResponse([
    'ok'   => true,
    'exam' => formatExamRow($row),
], 201);
