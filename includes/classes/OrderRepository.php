<?php

final class OrderRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createPendingOrder(
        string $serviceDate,
        string $serviceTime,
        string $serviceAddress,
        int $people,
        float $totalPrice,
        int $userId,
        int $menuId
    ): int {
        $statement = $this->pdo->prepare("
            INSERT INTO commande (date_prestation, heure_prestation, lieu_prestation, nb_personnes, prix_total, statut, id_utilisateur, id_menu)
            VALUES (?, ?, ?, ?, ?, 'en_attente', ?, ?)
        ");
        $statement->execute([
            $serviceDate,
            $serviceTime,
            $serviceAddress,
            $people,
            $totalPrice,
            $userId,
            $menuId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
