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

    public function saveWithAllergens(int $dishId, string $name, string $category, array $allergenIds): int
    {
        $this->pdo->beginTransaction();

        try {
            if ($dishId > 0) {
                $statement = $this->pdo->prepare("UPDATE plat SET nom = ?, categorie = ? WHERE id_plat = ?");
                $statement->execute([$name, $category, $dishId]);
            } else {
                $statement = $this->pdo->prepare("INSERT INTO plat (nom, categorie) VALUES (?, ?)");
                $statement->execute([$name, $category]);
                $dishId = (int) $this->pdo->lastInsertId();
            }

            $this->syncAllergens($dishId, $allergenIds);
            $this->pdo->commit();

            return $dishId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function delete(int $dishId): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare("DELETE FROM menu_plat WHERE id_plat = ?")->execute([$dishId]);
            $this->pdo->prepare("DELETE FROM plat_allergene WHERE id_plat = ?")->execute([$dishId]);
            $this->pdo->prepare("DELETE FROM plat WHERE id_plat = ?")->execute([$dishId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function syncAllergens(int $dishId, array $allergenIds): void
    {
        $allergenIds = $this->cleanIds($allergenIds);

        $this->pdo->prepare("DELETE FROM plat_allergene WHERE id_plat = ?")->execute([$dishId]);
        $insert = $this->pdo->prepare("INSERT INTO plat_allergene (id_plat, id_allergene) VALUES (?, ?)");

        foreach ($allergenIds as $allergenId) {
            $insert->execute([$dishId, $allergenId]);
        }
    }

    private function cleanIds(array $ids): array
    {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($ids));
    }
}
