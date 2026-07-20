<?php
/**
 * POST /backend/api/admin/questions/update.php
 * Body: { id, question_text, topic?, option_a?, option_b?, option_c?, option_d?, correct_option?, subject_code? }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
requireRole('admin');

$body = readJsonBody();
$id = (int) ($body['id'] ?? 0);

if ($id < 1) {
    jsonError('Invalid question id.');
}

try {
    $find = db()->prepare(
        'SELECT id, subject_code, topic, question_text, option_a, option_b, option_c, option_d, correct_option
         FROM question_bank WHERE id = ? LIMIT 1'
    );
    $find->execute([$id]);
    $existing = $find->fetch();
    if (!$existing) {
        jsonError('Question not found.', 404);
    }

    $text = array_key_exists('question_text', $body) || array_key_exists('text', $body)
        ? sanitizeString($body['question_text'] ?? $body['text'] ?? '')
        : $existing['question_text'];
    $topic = array_key_exists('topic', $body)
        ? sanitizeString((string) $body['topic'])
        : ($existing['topic'] ?? '');
    $subject = array_key_exists('subject_code', $body) || array_key_exists('subject', $body)
        ? strtoupper(sanitizeString($body['subject_code'] ?? $body['subject'] ?? ''))
        : $existing['subject_code'];
    $a = array_key_exists('option_a', $body) ? sanitizeString((string) $body['option_a']) : $existing['option_a'];
    $b = array_key_exists('option_b', $body) ? sanitizeString((string) $body['option_b']) : $existing['option_b'];
    $c = array_key_exists('option_c', $body) ? sanitizeString((string) $body['option_c']) : $existing['option_c'];
    $d = array_key_exists('option_d', $body) ? sanitizeString((string) $body['option_d']) : $existing['option_d'];
    $correct = array_key_exists('correct_option', $body)
        ? strtoupper(sanitizeString((string) $body['correct_option']))
        : $existing['correct_option'];

    if ($text === '') {
        jsonError('Enter the question text.');
    }
    if ($subject === '') {
        jsonError('Enter a subject code.');
    }
    if ($a === '' || $b === '' || $c === '' || $d === '') {
        jsonError('All four options are required.');
    }
    if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
        jsonError('Correct option must be A, B, C, or D.');
    }

    $stmt = db()->prepare(
        'UPDATE question_bank
         SET subject_code = ?, topic = ?, question_text = ?,
             option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?
         WHERE id = ?'
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
        $id,
    ]);

    $row = db()->prepare(
        'SELECT id, subject_code, topic, question_text, option_a, option_b, option_c, option_d,
                correct_option, created_at
         FROM question_bank WHERE id = ?'
    );
    $row->execute([$id]);
    $q = $row->fetch();
} catch (Throwable $e) {
    jsonError('Could not update question.', 500);
}

jsonResponse([
    'ok'       => true,
    'question' => [
        'id'             => (int) $q['id'],
        'subject'        => $q['subject_code'],
        'topic'          => $q['topic'] ?? '',
        'text'           => $q['question_text'],
        'option_a'       => $q['option_a'],
        'option_b'       => $q['option_b'],
        'option_c'       => $q['option_c'],
        'option_d'       => $q['option_d'],
        'correct_option' => $q['correct_option'],
    ],
]);
