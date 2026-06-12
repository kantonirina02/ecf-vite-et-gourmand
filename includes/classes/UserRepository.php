<?php

final class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $idUser): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
        $statement->execute([$idUser]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function updateProfile(int $idUser, string $nom, string $prenom, string $gsm, string $adresse): bool
    {
        $statement = $this->pdo->prepare("
            UPDATE utilisateur
            SET nom = ?, prenom = ?, gsm = ?, adresse_postale = ?
            WHERE id_utilisateur = ?
        ");

        return $statement->execute([$nom, $prenom, $gsm, $adresse, $idUser]);
    }
}
