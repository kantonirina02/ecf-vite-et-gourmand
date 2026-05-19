<?php
session_start();
require_once 'includes/db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$id_user = $_SESSION['user_id'];
$message = "";

// traitement de la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_profil'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $gsm = trim($_POST['gsm']);
    $adresse = trim($_POST['adresse_postale']);

    $update = $pdo->prepare("UPDATE utilisateur SET nom = ?, prenom = ?, gsm = ?, adresse_postale = ? WHERE id_utilisateur = ?");
    if ($update->execute([$nom, $prenom, $gsm, $adresse, $id_user])) {
        $_SESSION['prenom'] = $prenom; // On met à jour le prénom dans le header
        $message = "<div class='alert-success'>Profil mis à jour avec succès !</div>";
    } else {
        $message = "<div class='alert-error'>Erreur lors de la mise à jour du profil.</div>";
    }
}

// annulation de la commande
// uniquement si le statut est envore en attente
if (isset($_GET['annuler_commande'])) {
    $id_cmd_annuler = (int)$_GET['annuler_commande'];

    // Sécurité : On vérifie que la commande appartient bien à cet utilisateur ET est 'en attente'
    $verif_cmd = $pdo->prepare("SELECT statut FROM commande WHERE id_commande = ? AND id_utilisateur = ?");
    $verif_cmd->execute([$id_cmd_annuler, $id_user]);
    $cmd_data = $verif_cmd->fetch();

    if ($cmd_data && $cmd_data['statut'] === 'en attente') {
        $delete = $pdo->prepare("DELETE FROM commande WHERE id_commande = ?");
        $delete->execute([$id_cmd_annuler]);
        $message = "<div class='alert-success'>Commande annulée avec succès.</div>";
    } else {
        $message = "<div class='alert-error'>Impossible d'annuler cette commande (déjà acceptée ou introuvable).</div>";
    }
}

// traitement avis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_avis'])) {
    $id_cmd_avis = (int)$_POST['id_commande'];
    $note = (int)$_POST['note'];
    $commentaire = trim($_POST['commentaire']);

    // Sécurité stricte : On revérifie que la commande est 'terminée' et lui appartient
    $verif_cmd = $pdo->prepare("SELECT statut FROM commande WHERE id_commande = ? AND id_utilisateur = ?");
    $verif_cmd->execute([$id_cmd_avis, $id_user]);
    $cmd_data = $verif_cmd->fetch();

    if ($cmd_data && ($cmd_data['statut'] === 'terminée' || $cmd_data['statut'] === 'termine')) {
        // Insérer l'avis avec un statut 'en attente' pour que l'employé le valide avant publication
        $insert_avis = $pdo->prepare("INSERT INTO avis (note, commentaire, statut, id_utilisateur, id_commande) VALUES (?, ?, 'en attente', ?, ?)");
        $insert_avis->execute([$note, $commentaire, $id_user, $id_cmd_avis]);
        $message = "<div class='alert-success'>Merci pour votre avis ! Il sera visible dès sa validation par l'équipe.</div>";
    }
}


// chargement des données de la page
// données utilisateur
$req_user = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
$req_user->execute([$id_user]);
$user = $req_user->fetch(PDO::FETCH_ASSOC);

// historique des commandes
$req_orders = $pdo->prepare("
    SELECT c.*, m.titre as menu_titre
    FROM commande c
    JOIN menu m ON c.id_menu = m.id_menu
    WHERE c.id_utilisateur = ?
    ORDER BY c.date_prestation DESC
");
$req_orders->execute([$id_user]);
$commandes = $req_orders->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <h2 class="logo-font text-white mb-5 text-center">Mon Espace Personnel</h2>

    <?php if(!empty($message)) echo $message; ?>
    <?php if(isset($_GET['success']) && $_GET['success'] === 'commande_validee'): ?>
        <div class="alert-success">Votre commande a bien été enregistrée et est en attente de validation !</div>
    <?php endif; ?>

    <div class="dashboard-grid">

        <div class="glass-panel p-4">
            <h4 class="text-gold mb-4 border-bottom border-secondary pb-2">Mes Informations</h4>
            <form method="POST" action="">
                <input type="hidden" name="action_profil" value="1">

                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars($user['nom']); ?>" required>

                <label class="form-label">Prénom</label>
                <input type="text" name="prenom" class="form-control" value="<?php echo htmlspecialchars($user['prenom']); ?>" required>

                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly style="opacity: 0.5;">

                <label class="form-label">Téléphone</label>
                <input type="text" name="gsm" class="form-control" value="<?php echo htmlspecialchars($user['gsm']); ?>" required>

                <label class="form-label">Adresse de livraison par défaut</label>
                <textarea name="adresse_postale" class="form-control" required><?php echo htmlspecialchars($user['adresse_postale']); ?></textarea>

                <button type="submit" class="btn-primary w-100 border-0">Enregistrer les modifications</button>
            </form>
        </div>

        <div class="glass-panel p-4">
            <h4 class="text-gold mb-4 border-bottom border-secondary pb-2">Mes Commandes</h4>

            <?php if(empty($commandes)): ?>
                <p class="text-muted fst-italic">Vous n'avez pas encore passé de commande.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th>Date Prestation</th>
                                <th>Convives</th>
                                <th>Prix Total</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($commandes as $cmd): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($cmd['menu_titre']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($cmd['date_prestation'])); ?> à <?php echo htmlspecialchars($cmd['heure_prestation']); ?></td>
                                    <td><?php echo $cmd['nb_personnes']; ?> pers.</td>
                                    <td class="text-gold fw-bold"><?php echo number_format($cmd['prix_total'], 2, ',', ' '); ?>€</td>
                                    <td>
                                        <?php
                                        $status_class = "badge-waiting";
                                        $statut_clean = strtolower(trim($cmd['statut']));
                                        if($statut_clean === 'accepté' || $statut_clean === 'accepte' || $statut_clean === 'en préparation') $status_class = "badge-accepted";
                                        if($statut_clean === 'en cours de livraison') $status_class = "badge-delivery";
                                        if($statut_clean === 'livré' || $statut_clean === 'terminée' || $statut_clean === 'termine') $status_class = "badge-success";
                                        ?>
                                        <span class="badge-status <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($cmd['statut']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($statut_clean === 'en attente'): ?>
                                            <a href="espace_utilisateur?annuler_commande=<?php echo $cmd['id_commande']; ?>"
                                               class="btn-action-small btn-outline text-danger border-danger"
                                               onclick="return confirm('Êtes-vous sûr de vouloir annuler cette commande ?');">
                                                Annuler
                                            </a>
                                        <?php endif; ?>

                                        <?php if($statut_clean === 'terminée' || $statut_clean === 'termine'): ?>
                                            <?php
                                            $check_avis = $pdo->prepare("SELECT id_avis FROM avis WHERE id_commande = ?");
                                            $check_avis->execute([$cmd['id_commande']]);
                                            $deja_avise = $check_avis->fetch();
                                            ?>

                                            <?php if(!$deja_avise): ?>
                                                <button class="btn-action-small btn-primary border-0" onclick="document.getElementById('formAvisBox<?php echo $cmd['id_commande']; ?>').style.display = 'block';">
                                                    Laisser un avis
                                                </button>
                                            <?php else: ?>
                                                <span class="text-success small fst-italic"><i class="fa-solid fa-check"></i> Avis déposé</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <tr id="formAvisBox<?php echo $cmd['id_commande']; ?>" style="display: none;">
                                    <td colspan="6" style="background: rgba(212, 175, 55, 0.05); padding: 1.5rem; border-radius: 8px;">
                                        <form method="POST" action="">
                                            <input type="hidden" name="action_avis" value="1">
                                            <input type="hidden" name="id_commande" value="<?php echo $cmd['id_commande']; ?>">

                                            <div class="mb-4">
                                                <label class="form-label">Note (1 à 5 étoiles)</label>
                                                <select name="note" class="form-control" style="max-width: 300px;" required>
                                                    <option value="5">⭐⭐⭐⭐⭐ (Excellent)</option>
                                                    <option value="4">⭐⭐⭐⭐ (Très bon)</option>
                                                    <option value="3">⭐⭐⭐ (Correct)</option>
                                                    <option value="2">⭐⭐ (Moyen)</option>
                                                    <option value="1">⭐ (Mauvais)</option>
                                                </select>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label">Votre commentaire professionnel</label>
                                                <textarea name="commentaire" class="form-control" placeholder="Racontez votre expérience culinaire..." required rows="4" style="min-height: 150px; resize: vertical;"></textarea>
                                            </div>

                                            <div class="form-actions">
                                                <button type="button" class="btn-action-small btn-outline" onclick="document.getElementById('formAvisBox<?php echo $cmd['id_commande']; ?>').style.display = 'none';">Annuler</button>
                                                <button type="submit" class="btn-action-small btn-primary border-0">Envoyer l'avis</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
