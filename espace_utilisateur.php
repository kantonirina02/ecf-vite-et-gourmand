<?php
require_once 'includes/security.php';
require_once 'includes/db.php';
require_once 'includes/order_history.php';
require_once 'includes/order_status.php';
require_once 'includes/nosql_stats.php';
require_once 'includes/classes/UserRepository.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$id_user = (int) $_SESSION['user_id'];
$userRepository = new UserRepository($pdo);
$message = "";
$date_min_prestation = date('Y-m-d', strtotime('+3 days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_profil'])) {
    $nom = clean_text_input($_POST['nom'] ?? '', 50);
    $prenom = clean_text_input($_POST['prenom'] ?? '', 50);
    $gsm = clean_text_input($_POST['gsm'] ?? '', 20);
    $adresse = trim($_POST['adresse_postale'] ?? '');

    if ($nom === '' || $prenom === '' || !is_valid_phone($gsm) || $adresse === '') {
        $message = "<div class='alert-error'>Vérifiez votre nom, prénom, téléphone et adresse.</div>";
    } else {
        if ($userRepository->updateProfile($id_user, $nom, $prenom, $gsm, $adresse)) {
            $_SESSION['prenom'] = $prenom;
            $message = "<div class='alert-success'>Profil mis à jour avec succès.</div>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_commande'])) {
    $idCmd = (int) $_POST['annuler_commande'];

    try {
        ensure_order_history_table($pdo);
        $pdo->beginTransaction();

        $verif = $pdo->prepare("SELECT id_commande, id_menu, statut FROM commande WHERE id_commande = ? AND id_utilisateur = ? FOR UPDATE");
        $verif->execute([$idCmd, $id_user]);
        $commande = $verif->fetch(PDO::FETCH_ASSOC);

        if (!$commande || !order_status_is_waiting($commande['statut'])) {
            throw new RuntimeException('Cette commande ne peut plus être annulée.');
        }

        $pdo->prepare("UPDATE commande SET statut = 'annulee' WHERE id_commande = ? AND id_utilisateur = ?")->execute([$idCmd, $id_user]);
        $pdo->prepare("UPDATE menu SET stock = stock + 1 WHERE id_menu = ?")->execute([(int) $commande['id_menu']]);
        add_order_history($pdo, $idCmd, 'annulee', 'Commande annulée par le client.');

        $pdo->commit();
        nosql_sync_stats_from_sql($pdo);
        $message = "<div class='alert-success'>Commande annulée avec succès.</div>";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "<div class='alert-error'>" . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_modifier_commande'])) {
    $idCmd = (int) ($_POST['id_commande'] ?? 0);
    $datePrestation = $_POST['date_prestation'] ?? '';
    $heurePrestation = $_POST['heure_prestation'] ?? '';
    $lieuPrestation = trim($_POST['lieu_prestation'] ?? '');
    $nbPersonnes = (int) ($_POST['nb_personnes'] ?? 0);
    $horsBordeaux = isset($_POST['hors_bordeaux']);
    $distanceKm = $horsBordeaux ? (float) ($_POST['distance_km'] ?? 0) : 0;

    if (!is_valid_date_string($datePrestation) || $datePrestation < $date_min_prestation) {
        $message = "<div class='alert-error'>La date de prestation doit être au minimum dans 3 jours.</div>";
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $heurePrestation)) {
        $message = "<div class='alert-error'>L'heure est invalide.</div>";
    } elseif ($lieuPrestation === '') {
        $message = "<div class='alert-error'>Le lieu de prestation est obligatoire.</div>";
    } elseif (!$horsBordeaux && stripos($lieuPrestation, 'bordeaux') === false) {
        $message = "<div class='alert-error'>Cette adresse ne semble pas être à Bordeaux. Cochez hors Bordeaux et indiquez la distance.</div>";
    } elseif ($horsBordeaux && $distanceKm <= 0) {
        $message = "<div class='alert-error'>La distance hors Bordeaux doit être supérieure à 0 km.</div>";
    } else {
        try {
            $req = $pdo->prepare("
                SELECT c.*, m.prix_min, m.nb_personnes_min
                FROM commande c
                JOIN menu m ON c.id_menu = m.id_menu
                WHERE c.id_commande = ? AND c.id_utilisateur = ?
            ");
            $req->execute([$idCmd, $id_user]);
            $commande = $req->fetch(PDO::FETCH_ASSOC);

            if (!$commande || !order_status_is_waiting($commande['statut'])) {
                throw new RuntimeException('Cette commande ne peut plus être modifiée.');
            }

            if ($nbPersonnes < (int) $commande['nb_personnes_min']) {
                throw new RuntimeException('Le nombre de personnes est inférieur au minimum du menu.');
            }

            $prixMenuTotal = (float) $commande['prix_min'] * $nbPersonnes;

            if ($nbPersonnes >= ((int) $commande['nb_personnes_min'] + 5)) {
                $prixMenuTotal *= 0.90;
            }

            $fraisLivraison = $horsBordeaux ? 5 + (0.59 * $distanceKm) : 0;
            $prixTotal = round($prixMenuTotal + $fraisLivraison, 2);

            $update = $pdo->prepare("
                UPDATE commande
                SET date_prestation = ?, heure_prestation = ?, lieu_prestation = ?, nb_personnes = ?, prix_total = ?
                WHERE id_commande = ? AND id_utilisateur = ?
            ");
            $update->execute([$datePrestation, $heurePrestation, $lieuPrestation, $nbPersonnes, $prixTotal, $idCmd, $id_user]);

            add_order_history($pdo, $idCmd, 'modifiee', 'Commande modifiée par le client.');
            nosql_sync_stats_from_sql($pdo);
            $message = "<div class='alert-success'>Commande modifiée avec succès.</div>";
        } catch (Throwable $e) {
            $message = "<div class='alert-error'>" . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_avis'])) {
    $idCmd = (int) ($_POST['id_commande'] ?? 0);
    $note = max(1, min(5, (int) ($_POST['note'] ?? 1)));
    $commentaire = trim($_POST['commentaire'] ?? '');

    $verif = $pdo->prepare("SELECT statut FROM commande WHERE id_commande = ? AND id_utilisateur = ?");
    $verif->execute([$idCmd, $id_user]);
    $commande = $verif->fetch(PDO::FETCH_ASSOC);

    if ($commande && order_status_is_finished($commande['statut']) && $commentaire !== '') {
        $check = $pdo->prepare("SELECT id_avis FROM avis WHERE id_commande = ?");
        $check->execute([$idCmd]);

        if (!$check->fetch()) {
            $insert = $pdo->prepare("INSERT INTO avis (note, commentaire, statut, id_utilisateur, id_commande) VALUES (?, ?, 'en attente', ?, ?)");
            $insert->execute([$note, $commentaire, $id_user, $idCmd]);
            $message = "<div class='alert-success'>Merci pour votre avis. Il sera visible après validation.</div>";
        }
    }
}

$user = $userRepository->findById($id_user);

if (!$user) {
    header('Location: logout');
    exit;
}

$req_orders = $pdo->prepare("
    SELECT c.*, m.titre as menu_titre
    FROM commande c
    JOIN menu m ON c.id_menu = m.id_menu
    WHERE c.id_utilisateur = ?
    ORDER BY c.date_prestation DESC
");
$req_orders->execute([$id_user]);
$commandes = $req_orders->fetchAll(PDO::FETCH_ASSOC);

ensure_order_history_table($pdo);
$historiques = [];
if (!empty($commandes)) {
    $ids = array_column($commandes, 'id_commande');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $req_hist = $pdo->prepare("SELECT * FROM commande_statut_historique WHERE id_commande IN ($placeholders) ORDER BY date_modification ASC");
    $req_hist->execute($ids);

    foreach ($req_hist->fetchAll(PDO::FETCH_ASSOC) as $hist) {
        $historiques[$hist['id_commande']][] = $hist;
    }
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <h2 class="logo-font text-white mb-5 text-center">Mon Espace Personnel</h2>

    <?php if(!empty($message)) echo $message; ?>
    <?php if(isset($_GET['success']) && $_GET['success'] === 'commande_validee'): ?>
        <div class="alert-success">Votre commande a bien été enregistrée et est en attente de validation.</div>
    <?php endif; ?>

    <div class="dashboard-grid">

        <div class="glass-panel p-4">
            <h4 class="text-gold mb-4 border-bottom border-secondary pb-2">Mes Informations</h4>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_profil" value="1">

                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars($user['nom']); ?>" required>

                <label class="form-label">Prénom</label>
                <input type="text" name="prenom" class="form-control" value="<?php echo htmlspecialchars($user['prenom']); ?>" required>

                <label class="form-label">Email</label>
                <input type="email" class="form-control readonly-muted" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>

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
                                <?php $statutClean = normalize_order_status($cmd['statut']); ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($cmd['menu_titre']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($cmd['date_prestation'])); ?> à <?php echo htmlspecialchars($cmd['heure_prestation']); ?></td>
                                    <td><?php echo (int)$cmd['nb_personnes']; ?> pers.</td>
                                    <td class="text-gold fw-bold"><?php echo number_format((float)$cmd['prix_total'], 2, ',', ' '); ?> EUR</td>
                                    <td>
                                        <span class="badge-status <?php echo order_status_badge_class($statutClean); ?>">
                                            <?php echo htmlspecialchars(order_status_label($statutClean)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($statutClean === 'en_attente'): ?>
                                            <button class="btn-action-small btn-outline js-toggle-row" type="button" data-target="formModifBox<?php echo (int)$cmd['id_commande']; ?>" data-display="table-row">Modifier</button>
                                            <form method="POST" action="" class="inline-form" data-confirm="Êtes-vous sûr de vouloir annuler cette commande ?">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="annuler_commande" value="<?php echo (int)$cmd['id_commande']; ?>">
                                                <button type="submit" class="btn-action-small btn-outline text-danger border-danger">Annuler</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if($statutClean === 'terminee'): ?>
                                            <?php
                                            $check_avis = $pdo->prepare("SELECT id_avis FROM avis WHERE id_commande = ?");
                                            $check_avis->execute([$cmd['id_commande']]);
                                            $deja_avise = $check_avis->fetch();
                                            ?>
                                            <?php if(!$deja_avise): ?>
                                                <button class="btn-action-small btn-primary border-0 js-toggle-row" type="button" data-target="formAvisBox<?php echo (int)$cmd['id_commande']; ?>" data-display="table-row">Laisser un avis</button>
                                            <?php else: ?>
                                                <span class="text-success small fst-italic"><i class="fa-solid fa-check"></i> Avis déposé</span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if(!empty($historiques[$cmd['id_commande']])): ?>
                                            <button class="btn-action-small btn-outline js-toggle-row" type="button" data-target="suiviBox<?php echo (int)$cmd['id_commande']; ?>" data-display="table-row">Suivi</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <tr id="suiviBox<?php echo (int)$cmd['id_commande']; ?>" class="is-hidden">
                                    <td colspan="6" class="order-history-cell">
                                        <strong class="text-gold">Suivi de commande</strong>
                                        <ul class="order-history-list">
                                            <?php foreach(($historiques[$cmd['id_commande']] ?? []) as $hist): ?>
                                                <li>
                                                    <?php echo htmlspecialchars(order_status_label($hist['statut'])); ?> -
                                                    <?php echo date('d/m/Y H:i', strtotime($hist['date_modification'])); ?>
                                                    <?php if(!empty($hist['commentaire'])): ?>
                                                        : <?php echo htmlspecialchars($hist['commentaire']); ?>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <button class="btn-action-small btn-outline js-toggle-row" type="button" data-target="suiviBox<?php echo (int)$cmd['id_commande']; ?>" data-display="none">Fermer</button>
                                    </td>
                                </tr>

                                <tr id="formModifBox<?php echo (int)$cmd['id_commande']; ?>" class="is-hidden">
                                    <td colspan="6" class="order-form-cell">
                                        <form method="POST" action="">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action_modifier_commande" value="1">
                                            <input type="hidden" name="id_commande" value="<?php echo (int)$cmd['id_commande']; ?>">

                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Date</label>
                                                    <input type="date" name="date_prestation" class="form-control" min="<?php echo $date_min_prestation; ?>" value="<?php echo htmlspecialchars($cmd['date_prestation']); ?>" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Heure</label>
                                                    <input type="time" name="heure_prestation" class="form-control" value="<?php echo htmlspecialchars($cmd['heure_prestation']); ?>" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Convives</label>
                                                    <input type="number" name="nb_personnes" class="form-control" min="1" value="<?php echo (int)$cmd['nb_personnes']; ?>" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Distance hors Bordeaux</label>
                                                    <input type="number" name="distance_km" class="form-control" min="0" step="0.1" value="0">
                                                    <label class="text-muted small"><input type="checkbox" name="hors_bordeaux"> Hors Bordeaux</label>
                                                </div>
                                            </div>

                                            <label class="form-label">Lieu de prestation</label>
                                            <textarea name="lieu_prestation" class="form-control" required><?php echo htmlspecialchars($cmd['lieu_prestation']); ?></textarea>

                                            <div class="form-actions">
                                                <button type="button" class="btn-action-small btn-outline js-toggle-row" data-target="formModifBox<?php echo (int)$cmd['id_commande']; ?>" data-display="none">Fermer</button>
                                                <button type="submit" class="btn-action-small btn-primary border-0">Enregistrer</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>

                                <tr id="formAvisBox<?php echo (int)$cmd['id_commande']; ?>" class="is-hidden">
                                    <td colspan="6" class="order-form-cell">
                                        <form method="POST" action="">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action_avis" value="1">
                                            <input type="hidden" name="id_commande" value="<?php echo (int)$cmd['id_commande']; ?>">

                                            <label class="form-label">Note</label>
                                            <select name="note" class="form-control review-note-select" required>
                                                <option value="5">5 - Excellent</option>
                                                <option value="4">4 - Très bon</option>
                                                <option value="3">3 - Correct</option>
                                                <option value="2">2 - Moyen</option>
                                                <option value="1">1 - Mauvais</option>
                                            </select>

                                            <label class="form-label">Votre commentaire</label>
                                            <textarea name="commentaire" class="form-control" required rows="4"></textarea>

                                            <div class="form-actions">
                                                <button type="button" class="btn-action-small btn-outline js-toggle-row" data-target="formAvisBox<?php echo (int)$cmd['id_commande']; ?>" data-display="none">Annuler</button>
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
