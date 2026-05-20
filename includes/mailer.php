<?php
require_once __DIR__ . '/db.php';

function ensure_email_log_table(PDO $pdo): void
{
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_log (
                id_email_log INT AUTO_INCREMENT PRIMARY KEY,
                destinataire VARCHAR(255) NOT NULL,
                sujet VARCHAR(255) NOT NULL,
                contenu TEXT NOT NULL,
                statut VARCHAR(30) NOT NULL DEFAULT 'en_attente',
                date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}

function log_email(PDO $pdo, string $to, string $subject, string $body, string $status): void
{
    ensure_email_log_table($pdo);

    try {
        $stmt = $pdo->prepare("INSERT INTO email_log (destinataire, sujet, contenu, statut) VALUES (?, ?, ?, ?)");
        $stmt->execute([$to, $subject, $body, $status]);
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}

function mail_env(string $key, string $default = ''): string
{
    $value = getenv($key);

    return $value === false || $value === '' ? $default : (string) $value;
}

function smtp_read_response($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function smtp_expect($socket, array $codes): bool
{
    $response = smtp_read_response($socket);
    $code = (int) substr($response, 0, 3);

    return in_array($code, $codes, true);
}

function smtp_command($socket, string $command, array $expectedCodes): bool
{
    fwrite($socket, $command . "\r\n");

    return smtp_expect($socket, $expectedCodes);
}

function smtp_escape_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);

    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }

    return implode("\r\n", $lines);
}

function send_smtp_email(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    $host = mail_env('SMTP_HOST');

    if ($host === '') {
        return false;
    }

    $port = (int) mail_env('SMTP_PORT', '587');
    $secure = strtolower(mail_env('SMTP_SECURE', 'tls'));
    $username = mail_env('SMTP_USER');
    $password = mail_env('SMTP_PASSWORD');
    $from = mail_env('SMTP_FROM', 'no-reply@viteetgourmand.fr');
    $fromName = mail_env('SMTP_FROM_NAME', 'Vite & Gourmand');
    $serverName = $_SERVER['SERVER_NAME'] ?? 'viteetgourmand.local';
    $transport = $secure === 'ssl' ? 'ssl://' . $host : $host;

    $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);

    if (!$socket) {
        error_log("SMTP connection failed: $errstr");
        return false;
    }

    stream_set_timeout($socket, 10);

    $ok = smtp_expect($socket, [220])
        && smtp_command($socket, 'EHLO ' . $serverName, [250]);

    if ($ok && $secure === 'tls') {
        $ok = smtp_command($socket, 'STARTTLS', [220])
            && stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            && smtp_command($socket, 'EHLO ' . $serverName, [250]);
    }

    if ($ok && $username !== '' && $password !== '') {
        $ok = smtp_command($socket, 'AUTH LOGIN', [334])
            && smtp_command($socket, base64_encode($username), [334])
            && smtp_command($socket, base64_encode($password), [235]);
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . $fromName . ' <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $serverName . '>',
    ];

    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $message = implode("\r\n", $headers) . "\r\n\r\n" . smtp_escape_body($body);

    if ($ok) {
        $ok = smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250])
            && smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251])
            && smtp_command($socket, 'DATA', [354]);
    }

    if ($ok) {
        fwrite($socket, $message . "\r\n.\r\n");
        $ok = smtp_expect($socket, [250]);
    }

    smtp_command($socket, 'QUIT', [221, 250]);
    fclose($socket);

    return $ok;
}

function send_app_email(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    global $pdo;

    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $sent = send_smtp_email($to, $subject, $body, $replyTo);

    if ($sent) {
        if (isset($pdo)) {
            log_email($pdo, $to, $subject, $body, 'envoye_smtp');
        }

        return true;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: Vite & Gourmand <no-reply@viteetgourmand.fr>',
    ];

    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $sent = false;
    if (function_exists('mail')) {
        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    if (isset($pdo)) {
        log_email($pdo, $to, $subject, $body, $sent ? 'envoye' : 'a_envoyer');
    }

    return $sent;
}
?>
