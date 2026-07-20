<?php
/**
 * In-app notification helpers.
 */

declare(strict_types=1);

function ensureNotificationsTable(PDO $pdo): void
{
    if (dbIsSqlite()) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS notifications (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              user_id INTEGER NOT NULL,
              type TEXT NOT NULL,
              title TEXT NOT NULL,
              body TEXT NULL,
              link TEXT NULL,
              is_read INTEGER DEFAULT 0,
              created_at TEXT DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notifications (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          type ENUM(\'exam_submitted\',\'exam_created\') NOT NULL,
          title VARCHAR(200) NOT NULL,
          body TEXT NULL,
          link VARCHAR(255) NULL,
          is_read TINYINT(1) DEFAULT 0,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          INDEX idx_user_read (user_id, is_read)
        ) ENGINE=InnoDB'
    );
}

function notifyAdminsExamSubmitted(PDO $pdo, string $examTitle, string $studentName, int $examId): void
{
    ensureNotificationsTable($pdo);
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    if (!$admins) {
        return;
    }

    $ins = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, body, link)
         VALUES (?, \'exam_submitted\', ?, ?, ?)'
    );
    $body = $studentName . ' finished the exam: ' . $examTitle;
    $link = 'dashboard.html#results';

    foreach ($admins as $admin) {
        $ins->execute([(int) $admin['id'], 'Exam submitted', $body, $link]);
    }
}

function notifyStudentsNewExam(PDO $pdo, string $examTitle, string $subjectCode, int $examId): void
{
    ensureNotificationsTable($pdo);
    $students = $pdo->query("SELECT id FROM users WHERE role = 'student'")->fetchAll();
    if (!$students) {
        return;
    }

    $ins = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, body, link)
         VALUES (?, \'exam_created\', ?, ?, ?)'
    );
    $body = $examTitle . ' (' . $subjectCode . ') is now available.';
    $link = 'dashboard.html';

    foreach ($students as $student) {
        $ins->execute([(int) $student['id'], 'New exam published', $body, $link]);
    }
}

function formatNotificationRow(array $row): array
{
    return [
        'id'         => (int) $row['id'],
        'type'       => $row['type'],
        'title'      => $row['title'],
        'body'       => $row['body'],
        'link'       => $row['link'],
        'is_read'    => (bool) $row['is_read'],
        'created_at' => $row['created_at'],
    ];
}
