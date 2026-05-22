<?php
function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "img-src 'self' data: https://images.unsplash.com",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "connect-src 'self'",
    ];

    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function secure_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

send_security_headers();
secure_session_start();

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Action refusee.');
    }
}

function require_role(array $roles): void
{
    if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $roles, true)) {
        header('Location: index');
        exit;
    }
}

function clean_text_input(string $value, int $maxLength = 255): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

function is_valid_phone(string $value): bool
{
    return (bool) preg_match('/^[0-9 +().-]{8,20}$/', trim($value));
}

function is_valid_date_string(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value;
}

function client_ip_address(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $ip = trim($parts[0]);

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function ensure_login_attempt_table(PDO $pdo): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempt (
            id_attempt INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_login_attempt (email, ip_address),
            INDEX idx_login_attempt_locked (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $done = true;
}

function login_is_blocked(PDO $pdo, string $email): bool
{
    ensure_login_attempt_table($pdo);

    $stmt = $pdo->prepare("
        SELECT locked_until
        FROM login_attempt
        WHERE email = ? AND ip_address = ?
        LIMIT 1
    ");
    $stmt->execute([mb_strtolower($email, 'UTF-8'), client_ip_address()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['locked_until'])) {
        return false;
    }

    if (strtotime($row['locked_until']) <= time()) {
        clear_login_attempts($pdo, $email);
        return false;
    }

    return true;
}

function record_login_failure(PDO $pdo, string $email): void
{
    ensure_login_attempt_table($pdo);

    $email = mb_strtolower($email, 'UTF-8');
    $ip = client_ip_address();

    $stmt = $pdo->prepare("SELECT attempts, locked_until FROM login_attempt WHERE email = ? AND ip_address = ? LIMIT 1");
    $stmt->execute([$email, $ip]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->prepare("INSERT INTO login_attempt (email, ip_address, attempts, last_attempt) VALUES (?, ?, 1, NOW())")->execute([$email, $ip]);
        return;
    }

    $attempts = (int) $row['attempts'];

    if (!empty($row['locked_until']) && strtotime($row['locked_until']) <= time()) {
        $attempts = 0;
    }

    $attempts++;
    $lockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;

    $pdo->prepare("
        UPDATE login_attempt
        SET attempts = ?, locked_until = ?, last_attempt = NOW()
        WHERE email = ? AND ip_address = ?
    ")->execute([$attempts, $lockedUntil, $email, $ip]);
}

function clear_login_attempts(PDO $pdo, string $email): void
{
    ensure_login_attempt_table($pdo);

    $pdo->prepare("DELETE FROM login_attempt WHERE email = ? AND ip_address = ?")
        ->execute([mb_strtolower($email, 'UTF-8'), client_ip_address()]);
}
?>
