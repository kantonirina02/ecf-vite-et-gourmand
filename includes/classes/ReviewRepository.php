<?php

final class ReviewRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function updateStatus(int $reviewId, string $status): bool
    {
        $statement = $this->pdo->prepare("UPDATE avis SET statut = ? WHERE id_avis = ?");

        return $statement->execute([$status, $reviewId]);
    }

    public function findPending(): array
    {
        $statement = $this->pdo->query("
            SELECT a.*, u.nom, u.prenom, m.titre as menu_titre
            FROM avis a
            JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur
            JOIN commande c ON a.id_commande = c.id_commande
            JOIN menu m ON c.id_menu = m.id_menu
            WHERE a.statut = 'en attente'
            ORDER BY a.id_avis DESC
        ");

        return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function findLatestApprovedForHome(int $limit = 3): array
    {
        $limit = max(1, min(10, $limit));
        $statement = $this->pdo->prepare("
            SELECT a.note, a.commentaire, u.prenom, LEFT(u.nom, 1) as initiale_nom
            FROM avis a
            JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur
            WHERE a.statut = 'validé'
            ORDER BY a.id_avis DESC
            LIMIT $limit
        ");
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
