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

    public function findUserOrderForUpdate(int $orderId, int $userId): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT id_commande, id_menu, statut
            FROM commande
            WHERE id_commande = ? AND id_utilisateur = ?
            FOR UPDATE
        ");
        $statement->execute([$orderId, $userId]);
        $order = $statement->fetch(PDO::FETCH_ASSOC);

        return $order ?: null;
    }

    public function cancelForUser(int $orderId, int $userId): bool
    {
        $statement = $this->pdo->prepare("
            UPDATE commande
            SET statut = 'annulee'
            WHERE id_commande = ? AND id_utilisateur = ?
        ");
        $statement->execute([$orderId, $userId]);

        return $statement->rowCount() === 1;
    }

    public function findEditableUserOrder(int $orderId, int $userId): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT c.*, m.prix_min, m.nb_personnes_min
            FROM commande c
            JOIN menu m ON c.id_menu = m.id_menu
            WHERE c.id_commande = ? AND c.id_utilisateur = ?
        ");
        $statement->execute([$orderId, $userId]);
        $order = $statement->fetch(PDO::FETCH_ASSOC);

        return $order ?: null;
    }

    public function updateForUser(
        int $orderId,
        int $userId,
        string $serviceDate,
        string $serviceTime,
        string $serviceAddress,
        int $people,
        float $totalPrice
    ): bool {
        $statement = $this->pdo->prepare("
            UPDATE commande
            SET date_prestation = ?, heure_prestation = ?, lieu_prestation = ?, nb_personnes = ?, prix_total = ?
            WHERE id_commande = ? AND id_utilisateur = ?
        ");
        $statement->execute([$serviceDate, $serviceTime, $serviceAddress, $people, $totalPrice, $orderId, $userId]);

        return $statement->rowCount() === 1;
    }

    public function findUserOrderStatus(int $orderId, int $userId): ?string
    {
        $statement = $this->pdo->prepare("SELECT statut FROM commande WHERE id_commande = ? AND id_utilisateur = ?");
        $statement->execute([$orderId, $userId]);
        $status = $statement->fetchColumn();

        return $status === false ? null : (string) $status;
    }

    public function hasReview(int $orderId): bool
    {
        $statement = $this->pdo->prepare("SELECT id_avis FROM avis WHERE id_commande = ?");
        $statement->execute([$orderId]);

        return (bool) $statement->fetchColumn();
    }

    public function createReview(int $orderId, int $userId, int $note, string $comment): bool
    {
        $statement = $this->pdo->prepare("
            INSERT INTO avis (note, commentaire, statut, id_utilisateur, id_commande)
            VALUES (?, ?, 'en attente', ?, ?)
        ");

        return $statement->execute([$note, $comment, $userId, $orderId]);
    }

    public function findByUser(int $userId): array
    {
        $statement = $this->pdo->prepare("
            SELECT c.*, m.titre as menu_titre
            FROM commande c
            JOIN menu m ON c.id_menu = m.id_menu
            WHERE c.id_utilisateur = ?
            ORDER BY c.date_prestation DESC
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findHistoriesByOrderIds(array $orderIds): array
    {
        $orderIds = $this->cleanIds($orderIds);

        if ($orderIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $statement = $this->pdo->prepare("
            SELECT *
            FROM commande_statut_historique
            WHERE id_commande IN ($placeholders)
            ORDER BY date_modification ASC
        ");
        $statement->execute($orderIds);

        $histories = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $history) {
            $histories[(int) $history['id_commande']][] = $history;
        }

        return $histories;
    }

    public function findReviewedOrderIds(array $orderIds): array
    {
        $orderIds = $this->cleanIds($orderIds);

        if ($orderIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $statement = $this->pdo->prepare("
            SELECT id_commande
            FROM avis
            WHERE id_commande IN ($placeholders)
        ");
        $statement->execute($orderIds);

        $reviewedOrderIds = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $review) {
            $reviewedOrderIds[(int) $review['id_commande']] = true;
        }

        return $reviewedOrderIds;
    }

    public function findForEmployeeStatusUpdate(int $orderId): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT c.*, u.email, u.prenom, m.titre
            FROM commande c
            JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
            JOIN menu m ON c.id_menu = m.id_menu
            WHERE c.id_commande = ?
            FOR UPDATE
        ");
        $statement->execute([$orderId]);
        $order = $statement->fetch(PDO::FETCH_ASSOC);

        return $order ?: null;
    }

    public function updateStatus(int $orderId, string $status): bool
    {
        $statement = $this->pdo->prepare("UPDATE commande SET statut = ? WHERE id_commande = ?");

        return $statement->execute([$status, $orderId]);
    }

    public function findForEmployeeBoard(array $statusValues, string $clientSearch): array
    {
        $where = [];
        $params = [];

        if ($statusValues !== []) {
            $where[] = "c.statut IN (" . implode(',', array_fill(0, count($statusValues), '?')) . ")";
            foreach ($statusValues as $statusValue) {
                $params[] = $statusValue;
            }
        }

        if ($clientSearch !== '') {
            $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
            $search = '%' . $clientSearch . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql = "
            SELECT c.*, m.titre as menu_titre, u.nom, u.prenom, u.gsm, u.email
            FROM commande c
            JOIN menu m ON c.id_menu = m.id_menu
            JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
        ";

        if ($where !== []) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY c.date_prestation ASC";

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function cleanIds(array $ids): array
    {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($ids));
    }
}
