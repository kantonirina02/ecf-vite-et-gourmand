<?php
require_once __DIR__ . '/db.php';

function ensure_order_history_table(PDO $pdo): void
{
    static $done = false;

    if ($done) {
        return;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS commande_statut_historique (
                id_historique INT AUTO_INCREMENT PRIMARY KEY,
                id_commande INT NOT NULL,
                statut VARCHAR(80) NOT NULL,
                commentaire TEXT NULL,
                date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_commande_historique (id_commande)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $done = true;
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}

function add_order_history(PDO $pdo, int $idCommande, string $statut, ?string $commentaire = null): void
{
    ensure_order_history_table($pdo);

    try {
        $stmt = $pdo->prepare("INSERT INTO commande_statut_historique (id_commande, statut, commentaire) VALUES (?, ?, ?)");
        $stmt->execute([$idCommande, $statut, $commentaire]);
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}
?>
