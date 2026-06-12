<?php

final class DishRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAll(): array
    {
        $statement = $this->pdo->query("SELECT * FROM plat ORDER BY categorie, nom");

        return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function findAllergenMap(): array
    {
        $statement = $this->pdo->query("SELECT id_plat, id_allergene FROM plat_allergene");

        if (!$statement) {
            return [];
        }

        $allergenMap = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $allergenMap[(int) $row['id_plat']][] = (int) $row['id_allergene'];
        }

        return $allergenMap;
    }
}
