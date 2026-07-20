<?php
/**
 * POST /backend/api/admin/questions/create.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
$admin = requireRole('admin');

$body = readJsonBody();
$subject = strtoupper(sanitizeString($body['subject_code'] ?? $body['subject'] ?? ''));
$topic = sanitizeString($body['topic'] ?? '');
$text = sanitizeString($body['question_text'] ?? $body['text'] ?? '');
$a = sanitizeString($body['option_a'] ?? '');
$b = sanitizeString($body['option_b'] ?? '');
$c = sanitizeString($body['option_c'] ?? '');
$d = sanitizeString($body['option_d'] ?? '');
$correct = strtoupper(sanitizeString($body['correct_option'] ?? ''));

if ($subject === '') {
    jsonError('Enter a subject code.');
}
if ($text === '') {
    jsonError('Enter the question text.');
}
if ($a === '' || $b === '' || $c === '' || $d === '') {
    jsonError('All four options are required.');
}
if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
    jsonError('Correct option must be A, B, C, or D.');
}

try {
    $stmt = db()->prepare(
        'INSERT INTO question_bank
           (subject_code, topic, question_text, option_a, option_b, option_c, option_d, correct_option, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $subject,
        $topic !== '' ? $topic : null,
        $text,
        $a,
        $b,
        $c,
        $d,
        $correct,
        (int) $admin['id'],
    ]);
    $id = (int) db()->lastInsertId();

    $row = db()->prepare(
        'SELECT id, subject_code, topic, question_text, option_a, option_b, option_c, option_d,
                correct_option, created_by, created_at
         FROM question_bank WHERE id = ?'
    );
    $row->execute([$id]);
    $q = $row->fetch();
} catch (Throwable $e) {
    jsonError('Could not create question.', 500);
}

jsonResponse([
    'ok'       => true,
    'question' => [
        'id'             => (int) $q['id'],
        'subject'        => $q['subject_code'],
        'topic'          => $q['topic'] ?? '',
        'text'           => $q['question_text'],
        'correct_option' => $q['correct_option'],
        'created_at'     => $q['created_at'],
    ],
], 201);
