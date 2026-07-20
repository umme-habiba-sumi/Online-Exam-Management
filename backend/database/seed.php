<?php
/**
 * Idempotent seed: creates demo admin + student accounts if missing.
 * Run: http://localhost/Exam%20Management/backend/database/seed.php
 * Or:  php seed.php
 *
 * Password for all demo accounts: password123
 *
 * Optional cleanup: ?cleanup=1 deletes orphaned password_resets older than 1 day
 * and removes known test emails that are not part of the seed set.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: text/plain; charset=utf-8');

$demoPassword = 'password123';
$hash = password_hash($demoPassword, PASSWORD_DEFAULT);

$users = [
    [
        'name'         => 'Dr. Md. Shamim Hossain',
        'email'        => 'shamim.hossain@iu.ac.bd',
        'role'         => 'admin',
        'roll_or_id'   => 'EMP-0231',
        'department'   => 'CSE',
        'designation'  => 'Associate Professor, CSE',
    ],
    [
        'name'         => 'Tahmid Rahman',
        'email'        => 'tahmid.rahman@student.iu.ac.bd',
        'role'         => 'student',
        'roll_or_id'   => '20-A-114',
        'department'   => 'CSE',
        'designation'  => null,
    ],
    [
        'name'         => 'Nusrat Jahan',
        'email'        => 'nusrat.jahan@student.iu.ac.bd',
        'role'         => 'student',
        'roll_or_id'   => '20-A-108',
        'department'   => 'CSE',
        'designation'  => null,
    ],
];

$seedEmails = array_column($users, 'email');

try {
    $pdo = db();
    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $insert = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, roll_or_id, department, designation)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $created = 0;
    $skipped = 0;
    $resetPasswords = isset($_GET['reset_passwords'])
        || (PHP_SAPI === 'cli' && in_array('--reset-passwords', $argv ?? [], true));
    $updPass = $pdo->prepare('UPDATE users SET password_hash = ?, roll_or_id = ? WHERE email = ?');

    foreach ($users as $u) {
        $check->execute([$u['email']]);
        if ($check->fetch()) {
            if ($resetPasswords) {
                $updPass->execute([$hash, $u['roll_or_id'], $u['email']]);
                echo "RESET {$u['email']} (password + id refreshed)\n";
            } else {
                echo "SKIP  {$u['email']} (already exists)\n";
            }
            $skipped++;
            continue;
        }

        $insert->execute([
            $u['name'],
            $u['email'],
            $hash,
            $u['role'],
            $u['roll_or_id'],
            $u['department'],
            $u['designation'],
        ]);
        echo "OK    {$u['role']}: {$u['email']}\n";
        $created++;
    }

    if (isset($_GET['cleanup']) || (PHP_SAPI === 'cli' && in_array('--cleanup', $argv ?? [], true))) {
        $expired = $pdo->exec(
            'DELETE FROM password_resets WHERE expires_at < ' . sqlNowMinusMinutes(1440)
        );
        echo "CLEAN expired password_resets: {$expired}\n";

        // Remove disposable test accounts created during API tests (safe whitelist)
        $testEmails = ['test.student@iu.ac.bd'];
        $del = $pdo->prepare('DELETE FROM users WHERE email = ?');
        foreach ($testEmails as $te) {
            if (in_array($te, $seedEmails, true)) {
                continue;
            }
            $del->execute([$te]);
            if ($del->rowCount()) {
                echo "CLEAN removed {$te}\n";
            }
        }
    }

    echo "\nDone. Created {$created}, skipped {$skipped}.\n";
    echo "Demo password for all seed accounts: {$demoPassword}\n";
    if (!$resetPasswords) {
        echo "Tip: add ?reset_passwords=1 to refresh demo passwords.\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Make sure you imported schema.sql first and MySQL is running.\n";
    exit(1);
}
