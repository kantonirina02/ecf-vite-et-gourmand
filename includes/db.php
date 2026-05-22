<?php
function env_value(string $key, string $default = ''): string
{
    $value = getenv($key);

    return $value === false || $value === '' ? $default : (string) $value;
}

$host = env_value('DB_HOST', 'localhost');
$dbname = env_value('DB_NAME', 'vite_et_gourmand');
$username = env_value('DB_USER', 'root');
$password = env_value('DB_PASSWORD', '');
$port = env_value('DB_PORT', '3306');

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Erreur serveur. Impossible de se connecter a la base de donnees.");
}
?>
