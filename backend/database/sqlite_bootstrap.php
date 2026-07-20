<?php
/**
 * Create SQLite tables + seed demo users on first run.
 */

declare(strict_types=1);

function sqliteBootstrap(PDO $pdo, bool $forceSeedCheck = true): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            email_verified INTEGER NOT NULL DEFAULT 1,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            roll_or_id TEXT NULL,
            department TEXT DEFAULT \'CSE\',
            designation TEXT NULL,
            avatar TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            otp_code TEXT NOT NULL,
            is_verified INTEGER DEFAULT 0,
            expires_at TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS question_bank (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject_code TEXT NOT NULL,
            topic TEXT NULL,
            question_text TEXT NOT NULL,
            option_a TEXT NOT NULL,
            option_b TEXT NOT NULL,
            option_c TEXT NOT NULL,
            option_d TEXT NOT NULL,
            correct_option TEXT NOT NULL,
            created_by INTEGER NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS exams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            subject_code TEXT NOT NULL,
            duration_minutes INTEGER NOT NULL,
            total_marks INTEGER NOT NULL,
            start_time TEXT NOT NULL,
            status TEXT DEFAULT \'draft\',
            created_by INTEGER NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS exam_questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            exam_id INTEGER NOT NULL,
            question_bank_id INTEGER NULL,
            question_text TEXT NOT NULL,
            option_a TEXT NOT NULL,
            option_b TEXT NOT NULL,
            option_c TEXT NOT NULL,
            option_d TEXT NOT NULL,
            correct_option TEXT NOT NULL,
            marks INTEGER DEFAULT 1,
            order_no INTEGER DEFAULT 0,
            FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
            FOREIGN KEY (question_bank_id) REFERENCES question_bank(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS exam_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            exam_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            started_at TEXT DEFAULT CURRENT_TIMESTAMP,
            submitted_at TEXT NULL,
            score INTEGER NULL,
            status TEXT DEFAULT \'in_progress\',
            UNIQUE (exam_id, student_id),
            FOREIGN KEY (exam_id) REFERENCES exams(id),
            FOREIGN KEY (student_id) REFERENCES users(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS exam_answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            attempt_id INTEGER NOT NULL,
            exam_question_id INTEGER NOT NULL,
            selected_option TEXT NULL,
            is_correct INTEGER NULL,
            UNIQUE (attempt_id, exam_question_id),
            FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_question_id) REFERENCES exam_questions(id)
        )'
    );

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

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_sessions (
            id TEXT NOT NULL PRIMARY KEY,
            payload TEXT NOT NULL,
            expires_at TEXT NOT NULL
        )'
    );

    // Seed demo accounts if empty
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $ins = $pdo->prepare(
        'INSERT INTO users (name, email, email_verified, password_hash, role, roll_or_id, department, designation)
         VALUES (?, ?, 1, ?, ?, ?, ?, ?)'
    );

    $users = [
        ['Dr. Md. Shamim Hossain', 'shamim.hossain@iu.ac.bd', 'admin', 'EMP-0231', 'CSE', 'Associate Professor, CSE'],
        ['Tahmid Rahman', 'tahmid.rahman@student.iu.ac.bd', 'student', '20-A-114', 'CSE', null],
        ['Nusrat Jahan', 'nusrat.jahan@student.iu.ac.bd', 'student', '20-A-108', 'CSE', null],
    ];

    foreach ($users as $u) {
        $ins->execute([$u[0], $u[1], $hash, $u[2], $u[3], $u[4], $u[5]]);
    }
}
