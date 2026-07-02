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

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM utilisateur WHERE email = ?");
        $statement->execute([$email]);
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

    public function emailExists(string $email): bool
    {
        $statement = $this->pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
        $statement->execute([$email]);

        return (bool) $statement->fetchColumn();
    }

    public function createEmployee(string $nom, string $prenom, string $email, string $hashedPassword): bool
    {
        $statement = $this->pdo->prepare("
            INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, statut_compte)
            VALUES (?, ?, ?, ?, 'employe', 'actif')
        ");

        return $statement->execute([$nom, $prenom, $email, $hashedPassword]);
    }

    public function createCustomer(
        string $nom,
        string $prenom,
        string $email,
        string $gsm,
        string $adresse,
        string $hashedPassword
    ): bool {
        $statement = $this->pdo->prepare("
            INSERT INTO utilisateur (nom, prenom, email, gsm, adresse_postale, mot_de_passe, role)
            VALUES (?, ?, ?, ?, ?, ?, 'utilisateur')
        ");

        return $statement->execute([$nom, $prenom, $email, $gsm, $adresse, $hashedPassword]);
    }

    public function updatePasswordByEmail(string $email, string $hashedPassword): bool
    {
        $statement = $this->pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE email = ?");

        return $statement->execute([$hashedPassword, $email]);
    }

    public function updateEmployeeStatus(int $idEmployee, string $status): bool
    {
        $statement = $this->pdo->prepare("
            UPDATE utilisateur
            SET statut_compte = ?
            WHERE id_utilisateur = ? AND role = 'employe'
        ");
        $statement->execute([$status, $idEmployee]);

        return $statement->rowCount() === 1;
    }

    public function findEmployees(): array
    {
        $statement = $this->pdo->query("
            SELECT *
            FROM utilisateur
            WHERE role = 'employe'
            ORDER BY nom ASC
        ");

        return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
