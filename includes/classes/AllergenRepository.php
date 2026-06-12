<?php

final class AllergenRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAll(): array
    {
        $statement = $this->pdo->query("SELECT * FROM allergene ORDER BY nom");

        return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function createIfMissing(string $name): bool
    {
        $statement = $this->pdo->prepare("INSERT IGNORE INTO allergene (nom) VALUES (?)");

        return $statement->execute([$name]);
    }

    public function delete(int $allergenId): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare("DELETE FROM plat_allergene WHERE id_allergene = ?")->execute([$allergenId]);
            $this->pdo->prepare("DELETE FROM allergene WHERE id_allergene = ?")->execute([$allergenId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
