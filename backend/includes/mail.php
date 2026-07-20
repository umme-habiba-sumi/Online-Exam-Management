<?php
/**
 * Mail helper.
 * MAIL_DRIVER=log   -> backend/storage/mail.log
 * MAIL_DRIVER=mail  -> PHP mail()
 * MAIL_DRIVER=smtp  -> SMTP via env (SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_SECURE=tls|ssl|none)
 */

declare(strict_types=1);

function sendAppMail(string $to, string $subject, string $body): bool
{
    $driver = strtolower(getenv('MAIL_DRIVER') ?: 'log');
    $from = getenv('MAIL_FROM') ?: 'noreply@examportal.local';
    $fromName = getenv('MAIL_FROM_NAME') ?: 'ExamPortal';

    if ($driver === 'log') {
        return mailLogToFile($to, $subject, $body);
    }

    if ($driver === 'smtp') {
        return mailSendSmtp($to, $subject, $body, $from, $fromName);
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=utf-8',
        'From: ' . sprintf('%s <%s>', $fromName, $from),
    ];

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
}

function mailLogToFile(string $to, string $subject, string $body): bool
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $line = sprintf(
        "[%s] TO:%s | SUBJECT:%s | BODY:%s%s",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        str_replace(["\r", "\n"], ' ', $body),
        PHP_EOL
    );
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'mail.log', $line, FILE_APPEND | LOCK_EX);
    return true;
}

function mailSendSmtp(string $to, string $subject, string $body, string $from, string $fromName): bool
{
    $host = getenv('SMTP_HOST') ?: '';
    $port = (int) (getenv('SMTP_PORT') ?: 587);
    $user = getenv('SMTP_USER') ?: '';
    $pass = getenv('SMTP_PASS') ?: '';
    $secure = strtolower(getenv('SMTP_SECURE') ?: 'tls');

    if ($host === '') {
        return mailLogToFile($to, '[SMTP MISCONFIGURED] ' . $subject, $body);
    }

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 20);
    if (!$fp) {
        mailLogToFile($to, '[SMTP CONNECT FAIL] ' . $subject, $body . " | $errstr");
        return false;
    }

    stream_set_timeout($fp, 20);

    $read = static function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };

    $expect = static function (string $resp, string $code): bool {
        return str_starts_with($resp, $code);
    };

    try {
        $banner = $read();
        if (!$expect($banner, '220')) {
            throw new RuntimeException('Bad banner: ' . $banner);
        }

        $write('EHLO examportal.local');
        $ehlo = $read();
        if (!$expect($ehlo, '250')) {
            $write('HELO examportal.local');
            $ehlo = $read();
            if (!$expect($ehlo, '250')) {
                throw new RuntimeException('EHLO/HELO failed');
            }
        }

        if ($secure === 'tls') {
            $write('STARTTLS');
            $tls = $read();
            if (!$expect($tls, '220')) {
                throw new RuntimeException('STARTTLS failed');
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS handshake failed');
            }
            $write('EHLO examportal.local');
            $read();
        }

        if ($user !== '') {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($user));
            $read();
            $write(base64_encode($pass));
            $auth = $read();
            if (!$expect($auth, '235')) {
                throw new RuntimeException('AUTH failed: ' . $auth);
            }
        }

        $write('MAIL FROM:<' . $from . '>');
        if (!$expect($read(), '250')) {
            throw new RuntimeException('MAIL FROM rejected');
        }
        $write('RCPT TO:<' . $to . '>');
        $rcpt = $read();
        if (!$expect($rcpt, '250') && !$expect($rcpt, '251')) {
            throw new RuntimeException('RCPT TO rejected');
        }

        $write('DATA');
        if (!$expect($read(), '354')) {
            throw new RuntimeException('DATA rejected');
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $msg = [
            'Date: ' . date('r'),
            'From: ' . sprintf('%s <%s>', $fromName, $from),
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            str_replace(["\r\n", "\r"], "\n", $body),
            '.',
        ];
        $write(implode("\r\n", $msg));
        if (!$expect($read(), '250')) {
            throw new RuntimeException('Message not accepted');
        }

        $write('QUIT');
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        fclose($fp);
        mailLogToFile($to, '[SMTP ERROR] ' . $subject, $body . ' | ' . $e->getMessage());
        return false;
    }
}
