<?php
session_start();
require_once 'includes/db.php';

// verification du rôle emplye/admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'employe' && $_SESSION['role'] !== 'admin')) {
    header('Location: index.php');
    exit;
}

$message = "";

// Action de l'employé (traitement des formulaires)
// mise à jour du statut de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_statut_commande'])) {
    $id_cmd = (int)$_POST['id_commande'];
    $nouveau_statut = $_POST['nouveau_statut'];

    $update_statut = $pdo->prepare("UPDATE commande SET statut = ? WHERE id_commande = ?");
    if($update_statut->execute([$nouveau_statut, $id_cmd])) {
        $message = "<div class='alert-success'>Le statut de la commande #$id_cmd a été mis à jour avec succès.</div>";
    }
}

// valider ou refuser un avis
if (isset($_GET['action_avis']) && isset($_GET['id_avis'])) {
    $id_avis = (int)$_GET['id_avis'];
    $action = $_GET['action_avis'];

    if ($action === 'valider') {
        $pdo->prepare("UPDATE avis SET statut = 'validé' WHERE id_avis = ?")->execute([$id_avis]);
        $message = "<div class='alert-success'>L'avis a été validé et sera visible sur l'accueil.</div>";
    } elseif ($action === 'refuser') {
        $pdo->prepare("UPDATE avis SET statut = 'refusé' WHERE id_avis = ?")->execute([$id_avis]);
        $message = "<div class='alert-success'>L'avis a été refusé et masqué.</div>";
    }
}

// modifier un horaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_modifier_horaire'])) {
    $id_horaire = (int)$_POST['id_horaire'];
    $jour = trim($_POST['jour']);
    $heures = trim($_POST['heures']);

    $update_horaire = $pdo->prepare("UPDATE horaire SET jour = ?, heures = ? WHERE id_horaire = ?");
    if($update_horaire->execute([$jour, $heures, $id_horaire])) {
        $message = "<div class='alert-success'>L'horaire a été mis à jour pour le footer.</div>";
    }
}

// récupération des données pour l'affichage
// Les commandes avec les détails du client et du menu
$req_commandes = $pdo->query("
    SELECT c.*, m.titre as menu_titre, u.nom, u.prenom, u.gsm
    FROM commande c
    JOIN menu m ON c.id_menu = m.id_menu
    JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
    ORDER BY c.date_prestation ASC
");

if ($req_commandes) {
    $commandes = $req_commandes->fetchAll(PDO::FETCH_ASSOC);
} else {
    $commandes = [];
}

// Les avis en attente de validation
$req_avis = $pdo->query("
    SELECT a.*, u.nom, u.prenom, m.titre as menu_titre
    FROM avis a
    JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur
    JOIN commande c ON a.id_commande = c.id_commande
    JOIN menu m ON c.id_menu = m.id_menu
    WHERE a.statut = 'en attente'
    ORDER BY a.id_avis DESC
");
$avis_en_attente = $req_avis->fetchAll(PDO::FETCH_ASSOC);

// Les horaires
$req_horaires = $pdo->query("SELECT * FROM horaire");
$horaires = $req_horaires->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <h2 class="logo-font text-gold mb-5 text-center">Tableau de Bord Employé</h2>

    <?php if(!empty($message)) echo $message; ?>

    <div class="dashboard-grid">

        <div class="glass-panel p-4" style="grid-column: 1 / -1;">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-solid fa-bell-concierge"></i> Gestion des Commandes</h4>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client & Contact</th>
                            <th>Prestation (Date & Menu)</th>
                            <th>Statut Actuel</th>
                            <th>Action (Mettre à jour)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($commandes as $cmd): ?>
                            <tr>
                                <td>#<?php echo $cmd['id_commande']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($cmd['nom'] . ' ' . $cmd['prenom']); ?></strong><br>
                                    <small class="text-muted"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($cmd['gsm']); ?></small>
                                </td>
                                <td>
                                    <span class="text-gold"><?php echo htmlspecialchars($cmd['menu_titre']); ?></span><br>
                                    <small><?php echo date('d/m/Y', strtotime($cmd['date_prestation'])); ?> à <?php echo htmlspecialchars($cmd['heure_prestation']); ?></small>
                                </td>
                                <td>
                                    <?php
                                        $status_class = "badge-waiting";
                                        $statut_clean = strtolower(trim($cmd['statut']));
                                        if(in_array($statut_clean, ['accepté', 'accepte', 'en préparation'])) $status_class = "badge-accepted";
                                        if(in_array($statut_clean, ['en cours de livraison', 'en attente du retour de matériel'])) $status_class = "badge-delivery";
                                        if(in_array($statut_clean, ['livré', 'terminée', 'termine'])) $status_class = "badge-success";
                                    ?>
                                    <span class="badge-status <?php echo $status_class; ?>"><?php echo htmlspecialchars($cmd['statut']); ?></span>
                                </td>
                                <td>
                                    <form method="POST" action="" class="d-flex" style="gap: 0.5rem; align-items: center;">
                                        <input type="hidden" name="action_statut_commande" value="1">
                                        <input type="hidden" name="id_commande" value="<?php echo $cmd['id_commande']; ?>">
                                        <select name="nouveau_statut" class="form-control" style="width: auto; padding: 0.2rem 0.5rem;" required>
                                            <option value="" disabled selected>Modifier...</option>
                                            <option value="en attente">En attente</option>
                                            <option value="accepté">Accepté</option>
                                            <option value="en préparation">En préparation</option>
                                            <option value="en cours de livraison">En cours de livraison</option>
                                            <option value="livré">Livré</option>
                                            <option value="en attente du retour de matériel">Attente retour matériel</option>
                                            <option value="terminée">Terminée</option>
                                        </select>
                                        <button type="submit" class="btn-action-small btn-primary border-0"><i class="fa-solid fa-check"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-solid fa-comments"></i> Modération des Avis</h4>

            <?php if(empty($avis_en_attente)): ?>
                <p class="text-muted fst-italic">Aucun avis en attente de modération.</p>
            <?php else: ?>
                <?php foreach($avis_en_attente as $avis): ?>
                    <div class="mb-4 pb-3 border-bottom border-secondary">
                        <div class="d-flex justify-content-between mb-2">
                            <strong class="text-gold"><?php echo htmlspecialchars($avis['nom'] . ' ' . $avis['prenom']); ?></strong>
                            <span class="text-warning"><?php echo str_repeat('⭐', $avis['note']); ?></span>
                        </div>
                        <p class="small text-muted mb-2">Menu : <?php echo htmlspecialchars($avis['menu_titre']); ?></p>
                        <p class="fst-italic mb-3">"<?php echo htmlspecialchars($avis['commentaire']); ?>"</p>
                        <div class="form-actions" style="justify-content: flex-start;">
                            <a href="?action_avis=valider&id_avis=<?php echo $avis['id_avis']; ?>" class="btn-action-small btn-outline text-success border-success">Valider</a>
                            <a href="?action_avis=refuser&id_avis=<?php echo $avis['id_avis']; ?>" class="btn-action-small btn-outline text-danger border-danger">Refuser</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-regular fa-clock"></i> Horaires (Pied de page)</h4>

            <?php foreach($horaires as $h): ?>
                <form method="POST" action="" class="mb-3 pb-3 border-bottom border-secondary">
                    <input type="hidden" name="action_modifier_horaire" value="1">
                    <input type="hidden" name="id_horaire" value="<?php echo $h['id_horaire']; ?>">

                    <div class="mb-2">
                        <label class="form-label small">Jour(s)</label>
                        <input type="text" name="jour" class="form-control" value="<?php echo htmlspecialchars($h['jour']); ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Plage horaire</label>
                        <input type="text" name="heures" class="form-control" value="<?php echo htmlspecialchars($h['heures']); ?>" required>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn-action-small btn-primary border-0 mt-2">Mettre à jour</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>

