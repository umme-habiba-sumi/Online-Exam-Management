<?php
/**
 * GET /backend/api/admin/questions/list.php?subject=all|CSE-2103
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('GET');
requireRole('admin');

$subject = sanitizeString($_GET['subject'] ?? 'all');

try {
    if ($subject === '' || strtolower($subject) === 'all') {
        $stmt = db()->query(
            'SELECT id, subject_code, topic, question_text, option_a, option_b, option_c, option_d,
                    correct_option, created_by, created_at
             FROM question_bank
             ORDER BY created_at DESC, id DESC'
        );
        $rows = $stmt->fetchAll();
    } else {
        $stmt = db()->prepare(
            'SELECT id, subject_code, topic, question_text, option_a, option_b, option_c, option_d,
                    correct_option, created_by, created_at
             FROM question_bank
             WHERE subject_code = ?
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([strtoupper($subject)]);
        $rows = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    jsonError('Could not load question bank.', 500);
}

$out = array_map(static function (array $q): array {
    return [
        'id'              => (int) $q['id'],
        'subject'         => $q['subject_code'],
        'subject_code'    => $q['subject_code'],
        'topic'           => $q['topic'] ?? '',
        'text'            => $q['question_text'],
        'question_text'   => $q['question_text'],
        'option_a'        => $q['option_a'],
        'option_b'        => $q['option_b'],
        'option_c'        => $q['option_c'],
        'option_d'        => $q['option_d'],
        'correct_option'  => $q['correct_option'],
        'created_by'      => (int) $q['created_by'],
        'created_at'      => $q['created_at'],
    ];
}, $rows);

jsonResponse(['ok' => true, 'questions' => $out]);
