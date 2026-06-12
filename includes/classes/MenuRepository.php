<?php

final class MenuRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAllForCatalog(): array
    {
        $statement = $this->pdo->query("
            SELECT id_menu, titre, description, image, theme, regime, nb_personnes_min, prix_min, stock
            FROM menu
            ORDER BY id_menu ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
