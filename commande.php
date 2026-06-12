<?php
require_once 'includes/security.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';
require_once 'includes/order_history.php';
require_once 'includes/order_status.php';
require_once 'includes/nosql_stats.php';
require_once 'includes/classes/OrderPriceCalculator.php';
require_once 'includes/classes/UserRepository.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login?erreur=connexion_requise');
    exit;
}

if (empty($_GET['id_menu'])) {
    header('Location: menus');
    exit;
}

$id_menu = (int) $_GET['id_menu'];

$req_menu = $pdo->prepare("SELECT * FROM menu WHERE id_menu = ?");
$req_menu->execute([$id_menu]);
$menu = $req_menu->fetch(PDO::FETCH_ASSOC);

if (!$menu) {
    header('Location: menus');
    exit;
}

$userRepository = new UserRepository($pdo);
$user = $userRepository->findById((int) $_SESSION['user_id']);

if (!$user) {
    header('Location: logout');
    exit;
}

$message = "";
$date_min_prestation = date('Y-m-d', strtotime('+3 days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $date_prestation = $_POST['date_prestation'] ?? '';
    $heure_prestation = $_POST['heure_prestation'] ?? '';
    $lieu_prestation = trim($_POST['lieu_prestation'] ?? '');
    $nb_personnes = (int) ($_POST['nb_personnes'] ?? 0);
    $est_hors_bordeaux = isset($_POST['hors_bordeaux']);
    $distance_km = $est_hors_bordeaux ? (float) ($_POST['distance_km'] ?? 0) : 0;
    $adresse_semble_bordeaux = stripos($lieu_prestation, 'bordeaux') !== false;

    if (!is_valid_date_string($date_prestation) || $date_prestation < $date_min_prestation) {
        $message = "<div class='alert-error'>La date de prestation doit être au minimum dans 3 jours.</div>";
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $heure_prestation)) {
        $message = "<div class='alert-error'>L'heure de livraison est invalide.</div>";
    } elseif ($lieu_prestation === '' || mb_strlen($lieu_prestation, 'UTF-8') > 500) {
        $message = "<div class='alert-error'>L'adresse de livraison est obligatoire et doit rester lisible.</div>";
    } elseif (!$est_hors_bordeaux && !$adresse_semble_bordeaux) {
        $message = "<div class='alert-error'>Cette adresse ne semble pas être à Bordeaux. Cochez la livraison hors Bordeaux et indiquez la distance.</div>";
    } elseif ($est_hors_bordeaux && $distance_km <= 0) {
        $message = "<div class='alert-error'>La distance hors Bordeaux doit être supérieure à 0 km.</div>";
    } else {
        try {
            ensure_order_history_table($pdo);
            $pdo->beginTransaction();

            $req_menu_lock = $pdo->prepare("SELECT * FROM menu WHERE id_menu = ? FOR UPDATE");
            $req_menu_lock->execute([$id_menu]);
            $menu_lock = $req_menu_lock->fetch(PDO::FETCH_ASSOC);

            if (!$menu_lock) {
                throw new RuntimeException('menu');
            }

            if ((int) $menu_lock['stock'] <= 0) {
                throw new RuntimeException('stock');
            }

            if ($nb_personnes < (int) $menu_lock['nb_personnes_min']) {
                throw new RuntimeException('minimum');
            }

            $priceCalculator = new OrderPriceCalculator();
            $priceDetails = $priceCalculator->calculate(
                (float) $menu_lock['prix_min'],
                (int) $menu_lock['nb_personnes_min'],
                $nb_personnes,
                $est_hors_bordeaux,
                $distance_km
            );
            $prix_total_final = $priceDetails['total'];

            $update_stock = $pdo->prepare("UPDATE menu SET stock = stock - 1 WHERE id_menu = ? AND stock > 0");
            $update_stock->execute([$id_menu]);

            if ($update_stock->rowCount() !== 1) {
                throw new RuntimeException('stock');
            }

            $insert = $pdo->prepare("
                INSERT INTO commande (date_prestation, heure_prestation, lieu_prestation, nb_personnes, prix_total, statut, id_utilisateur, id_menu)
                VALUES (?, ?, ?, ?, ?, 'en_attente', ?, ?)
            ");
            $insert->execute([
                $date_prestation,
                $heure_prestation,
                $lieu_prestation,
                $nb_personnes,
                $prix_total_final,
                $_SESSION['user_id'],
                $id_menu,
            ]);

            $id_commande = (int) $pdo->lastInsertId();
            add_order_history($pdo, $id_commande, 'en_attente', 'Commande créée par le client.');

            $pdo->commit();
            nosql_sync_stats_from_sql($pdo);

            $mail_body = "Bonjour " . $user['prenom'] . ",\n\n";
            $mail_body .= "Votre commande pour le " . $date_prestation . " a bien été enregistrée.\n";
            $mail_body .= "Menu : " . $menu_lock['titre'] . "\n";
            $mail_body .= "Nombre de personnes : " . $nb_personnes . "\n";
            $mail_body .= "Montant total : " . number_format($prix_total_final, 2, ',', ' ') . " EUR\n\n";
            $mail_body .= "L'équipe Vite & Gourmand.";
            send_app_email($user['email'], "Confirmation de votre commande - Vite & Gourmand", $mail_body);

            header('Location: espace_utilisateur?success=commande_validee');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getMessage() === 'stock') {
                $message = "<div class='alert-error'>Ce menu n'est plus disponible en stock.</div>";
            } elseif ($e->getMessage() === 'minimum') {
                $message = "<div class='alert-error'>Erreur : le minimum pour ce menu est de " . (int) $menu['nb_personnes_min'] . " personnes.</div>";
            } else {
                error_log($e->getMessage());
                $message = "<div class='alert-error'>Une erreur est survenue lors de la commande.</div>";
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <h2 class="logo-font text-white mb-5 text-center" style="font-size: 2.5rem;">Finaliser votre commande</h2>

    <?php if(!empty($message)) echo $message; ?>

    <form method="POST" action="" id="formCommande"
          data-unit-price="<?php echo htmlspecialchars((string) (float) $menu['prix_min'], ENT_QUOTES, 'UTF-8'); ?>"
          data-min-people="<?php echo (int) $menu['nb_personnes_min']; ?>">
        <?php echo csrf_field(); ?>
        <div class="row g-5">

            <div class="col-lg-7">
                <div class="glass-panel p-4 mb-4">
                    <h4 class="text-gold mb-4 border-bottom border-secondary pb-2">1. Vos informations pré-remplies</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['nom']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prénom</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['prenom']); ?>" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['gsm']); ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-4">
                    <h4 class="text-gold mb-4 border-bottom border-secondary pb-2">2. Détails de la prestation</h4>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="date_prestation">Date souhaitée</label>
                            <input id="date_prestation" type="date" name="date_prestation" class="form-control" required min="<?php echo $date_min_prestation; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="heure_prestation">Heure de livraison</label>
                            <input id="heure_prestation" type="time" name="heure_prestation" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="inputPersonnes">Nombre de convives</label>
                        <input type="number" name="nb_personnes" id="inputPersonnes" class="form-control"
                               min="<?php echo (int)$menu['nb_personnes_min']; ?>"
                               value="<?php echo (int)$menu['nb_personnes_min']; ?>" required>
                        <small class="text-success"><i class="fa-solid fa-tags"></i> -10% appliqués à partir de <?php echo (int)$menu['nb_personnes_min'] + 5; ?> personnes.</small>
                    </div>

                    <div class="mb-3 mt-4">
                        <label class="form-label" for="lieu_prestation">Adresse de livraison complète</label>
                        <textarea id="lieu_prestation" name="lieu_prestation" class="form-control" required><?php echo htmlspecialchars($user['adresse_postale']); ?></textarea>
                    </div>

                    <div class="mb-3 form-check mt-3">
                        <input type="checkbox" class="form-check-input" id="checkHorsBordeaux" name="hors_bordeaux">
                        <label class="form-check-label text-white" for="checkHorsBordeaux">La livraison est en dehors de la ville de Bordeaux</label>
                    </div>

                    <div class="mb-3" id="divDistance" style="display: none;">
                        <label class="form-label text-warning" for="inputDistance"><i class="fa-solid fa-truck"></i> Distance depuis Bordeaux en kilomètres</label>
                        <input type="number" step="0.1" min="0" name="distance_km" id="inputDistance" class="form-control border-warning" value="0">
                        <small class="text-muted">Facturation : 5 EUR de base + 0.59 EUR par km</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="order-summary glass-panel">
                    <h3 class="logo-font text-white mb-4">Récapitulatif</h3>

                    <div class="mb-4 pb-3 border-bottom border-secondary">
                        <h5 class="text-gold"><?php echo htmlspecialchars($menu['titre']); ?></h5>
                        <small class="text-muted">Prix unitaire : <?php echo number_format((float)$menu['prix_min'], 2, ',', ' '); ?> EUR</small>
                    </div>

                    <div class="summary-line">
                        <span>Menus (<span id="recapNb">X</span> pers.)</span>
                        <span id="recapMenuPrix">0.00 EUR</span>
                    </div>

                    <div class="summary-line text-success" id="divReduction" style="display:none;">
                        <span>Réduction de groupe (-10%)</span>
                        <span id="recapReduction">-0.00 EUR</span>
                    </div>

                    <div class="summary-line text-warning" id="divLivraison" style="display:none;">
                        <span>Frais de livraison hors Bordeaux</span>
                        <span id="recapLivraison">+0.00 EUR</span>
                    </div>

                    <div class="summary-total">
                        <span>TOTAL</span>
                        <span id="recapTotal">0.00 EUR</span>
                    </div>

                    <button type="submit" class="btn-primary w-100 mt-4 border-0 py-3" style="font-size: 1.1rem;">
                        <i class="fa-solid fa-check-circle me-2"></i> Confirmer la commande
                    </button>
                    <p class="text-center text-muted mt-3" style="font-size: 0.8rem;">En cliquant sur confirmer, vous acceptez nos Conditions Générales de Vente.</p>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
