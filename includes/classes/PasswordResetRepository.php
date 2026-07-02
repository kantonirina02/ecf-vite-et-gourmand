<?php

final class PasswordResetRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function ensureTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS password_reset (
                id_reset INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_password_reset_token (email, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function markOpenRequestsAsUsed(string $email): bool
    {
        $statement = $this->pdo->prepare("UPDATE password_reset SET used_at = NOW() WHERE email = ? AND used_at IS NULL");

        return $statement->execute([$email]);
    }

    public function create(string $email, string $tokenHash, string $expiresAt): bool
    {
        $statement = $this->pdo->prepare("INSERT INTO password_reset (email, token_hash, expires_at) VALUES (?, ?, ?)");

        return $statement->execute([$email, $tokenHash, $expiresAt]);
    }

    public function findLatestByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT id_reset, token_hash, expires_at, used_at
            FROM password_reset
            WHERE email = ?
            ORDER BY id_reset DESC
            LIMIT 1
        ");
        $statement->execute([$email]);
        $reset = $statement->fetch(PDO::FETCH_ASSOC);

        return $reset ?: null;
    }

    public function markUsed(int $resetId): bool
    {
        $statement = $this->pdo->prepare("UPDATE password_reset SET used_at = NOW() WHERE id_reset = ?");

        return $statement->execute([$resetId]);
    }
}
