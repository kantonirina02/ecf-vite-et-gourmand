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

    public function findAllForEmployeeBoard(): array
    {
        $statement = $this->pdo->query("SELECT * FROM menu ORDER BY id_menu DESC");

        return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
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

    public function increaseStock(int $idMenu): bool
    {
        $statement = $this->pdo->prepare("UPDATE menu SET stock = stock + 1 WHERE id_menu = ?");
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

    public function findAllImageFiles(int $idMenu): array
    {
        $images = [];

        $statement = $this->pdo->prepare("SELECT image FROM menu WHERE id_menu = ?");
        $statement->execute([$idMenu]);
        $mainImage = $statement->fetchColumn();

        if ($mainImage) {
            $images[] = basename((string) $mainImage);
        }

        foreach ($this->findImagePaths($idMenu) as $image) {
            $image = basename((string) $image);

            if ($image !== '') {
                $images[] = $image;
            }
        }

        return array_values(array_unique($images));
    }

    public function isImageReferenced(string $image): bool
    {
        $image = basename($image);

        if ($image === '') {
            return false;
        }

        $statement = $this->pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM menu WHERE image = ?)
                + (SELECT COUNT(*) FROM menu_image WHERE chemin = ?) AS total_refs
        ");
        $statement->execute([$image, $image]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function saveWithRelations(
        int $idMenu,
        string $titre,
        string $image,
        string $description,
        string $theme,
        int $nbPersonnesMin,
        float $prixMin,
        string $conditions,
        string $regime,
        int $stock,
        array $dishIds,
        array $galleryImages
    ): int {
        $this->pdo->beginTransaction();

        try {
            if ($idMenu > 0) {
                $statement = $this->pdo->prepare("
                    UPDATE menu
                    SET titre = ?, image = ?, description = ?, theme = ?, nb_personnes_min = ?, prix_min = ?, conditions = ?, regime = ?, stock = ?
                    WHERE id_menu = ?
                ");
                $statement->execute([$titre, $image, $description, $theme, $nbPersonnesMin, $prixMin, $conditions, $regime, $stock, $idMenu]);
            } else {
                $statement = $this->pdo->prepare("
                    INSERT INTO menu (titre, image, description, theme, nb_personnes_min, prix_min, conditions, regime, stock)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $statement->execute([$titre, $image, $description, $theme, $nbPersonnesMin, $prixMin, $conditions, $regime, $stock]);
                $idMenu = (int) $this->pdo->lastInsertId();
            }

            $this->syncImages($idMenu, $image, $galleryImages);
            $this->syncDishes($idMenu, $dishIds);
            $this->pdo->commit();

            return $idMenu;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function deleteWithRelations(int $idMenu): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare("DELETE FROM menu_image WHERE id_menu = ?")->execute([$idMenu]);
            $this->pdo->prepare("DELETE FROM menu_plat WHERE id_menu = ?")->execute([$idMenu]);
            $this->pdo->prepare("DELETE FROM menu WHERE id_menu = ?")->execute([$idMenu]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function findDishMap(): array
    {
        $statement = $this->pdo->query("SELECT id_menu, id_plat FROM menu_plat");

        if (!$statement) {
            return [];
        }

        $dishMap = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dishMap[(int) $row['id_menu']][] = (int) $row['id_plat'];
        }

        return $dishMap;
    }

    public function findImageMap(): array
    {
        $statement = $this->pdo->query("SELECT id_menu, chemin FROM menu_image ORDER BY id_image ASC");

        if (!$statement) {
            return [];
        }

        $imageMap = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $imageMap[(int) $row['id_menu']][] = $row['chemin'];
        }

        return $imageMap;
    }

    private function syncImages(int $idMenu, string $mainImage, array $galleryImages): void
    {
        $images = [];

        foreach ($galleryImages as $image) {
            $image = basename((string) $image);

            if ($image !== '') {
                $images[] = $image;
            }
        }

        if ($mainImage !== '' && !in_array($mainImage, $images, true)) {
            array_unshift($images, $mainImage);
        }

        $images = array_values(array_unique($images));

        $this->pdo->prepare("DELETE FROM menu_image WHERE id_menu = ?")->execute([$idMenu]);
        $insert = $this->pdo->prepare("INSERT INTO menu_image (chemin, id_menu) VALUES (?, ?)");

        foreach ($images as $image) {
            $insert->execute([$image, $idMenu]);
        }
    }

    private function syncDishes(int $idMenu, array $dishIds): void
    {
        $dishIds = $this->cleanIds($dishIds);

        $this->pdo->prepare("DELETE FROM menu_plat WHERE id_menu = ?")->execute([$idMenu]);
        $insert = $this->pdo->prepare("INSERT INTO menu_plat (id_menu, id_plat) VALUES (?, ?)");

        foreach ($dishIds as $dishId) {
            $insert->execute([$idMenu, $dishId]);
        }
    }

    private function cleanIds(array $ids): array
    {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($ids));
    }
}
