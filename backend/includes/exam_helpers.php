<?php
/**
 * Exam display helpers for admin/student list views.
 */

declare(strict_types=1);

/** Map DB status + schedule to UI stamp: draft|upcoming|live|closed */
function examDisplayStatus(array $exam, ?DateTimeImmutable $now = null): string
{
    $now = $now ?? new DateTimeImmutable('now');
    $status = $exam['status'] ?? 'draft';

    if ($status === 'draft') {
        return 'draft';
    }
    if ($status === 'closed') {
        return 'closed';
    }

    $start = new DateTimeImmutable($exam['start_time']);
    $end = $start->modify('+' . (int) $exam['duration_minutes'] . ' minutes');

    if ($now < $start) {
        return 'upcoming';
    }
    if ($now >= $end) {
        return 'closed';
    }
    return 'live';
}

function formatExamRow(array $exam): array
{
    return [
        'id'               => (int) $exam['id'],
        'title'            => $exam['title'],
        'subject'          => $exam['subject_code'],
        'subject_code'     => $exam['subject_code'],
        'duration_minutes' => (int) $exam['duration_minutes'],
        'total_marks'      => (int) $exam['total_marks'],
        'start_time'       => $exam['start_time'],
        'status'           => $exam['status'],
        'display_status'   => examDisplayStatus($exam),
        'questions'        => (int) ($exam['question_count'] ?? 0),
        'marks'            => (int) $exam['total_marks'],
        'attempts'         => (int) ($exam['attempt_count'] ?? 0),
        'created_by'       => (int) ($exam['created_by'] ?? 0),
        'created_at'       => $exam['created_at'] ?? null,
    ];
}
