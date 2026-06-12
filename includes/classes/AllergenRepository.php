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
}
