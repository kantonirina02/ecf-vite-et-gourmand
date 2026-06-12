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

    public function findById(int $idMenu): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM menu WHERE id_menu = :id");
        $statement->execute(['id' => $idMenu]);
        $menu = $statement->fetch(PDO::FETCH_ASSOC);

        return $menu ?: null;
    }

    public function findByIdForUpdate(int $idMenu): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM menu WHERE id_menu = ? FOR UPDATE");
        $statement->execute([$idMenu]);
        $menu = $statement->fetch(PDO::FETCH_ASSOC);

        return $menu ?: null;
    }

    public function decreaseStockIfAvailable(int $idMenu): bool
    {
        $statement = $this->pdo->prepare("UPDATE menu SET stock = stock - 1 WHERE id_menu = ? AND stock > 0");
        $statement->execute([$idMenu]);

        return $statement->rowCount() === 1;
    }

    public function findDishesWithAllergens(int $idMenu): array
    {
        $statement = $this->pdo->prepare("
            SELECT
                p.id_plat,
                p.nom,
                p.categorie,
                GROUP_CONCAT(a.nom SEPARATOR ', ') as allergenes
            FROM plat p
            JOIN menu_plat mp ON p.id_plat = mp.id_plat
            LEFT JOIN plat_allergene pa ON p.id_plat = pa.id_plat
            LEFT JOIN allergene a ON pa.id_allergene = a.id_allergene
            WHERE mp.id_menu = :id
            GROUP BY p.id_plat
            ORDER BY FIELD(LOWER(p.categorie), 'entrée', 'plat', 'dessert')
        ");
        $statement->execute(['id' => $idMenu]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findImagePaths(int $idMenu): array
    {
        $statement = $this->pdo->prepare("SELECT chemin FROM menu_image WHERE id_menu = ? ORDER BY id_image ASC");
        $statement->execute([$idMenu]);

        return array_column($statement->fetchAll(PDO::FETCH_ASSOC), 'chemin');
    }
}
