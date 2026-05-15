<?php
session_start();
require_once 'includes/db.php';

// Si non connecté, redirection vers login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?erreur=connexion_requise');
    exit;
}

// verrification du menu
if (!isset($_GET['id_menu']) || empty($_GET['id_menu'])) {
    header('Location: menus.php');
    exit;
}

$id_menu = (int) $_GET['id_menu'];

// Récupération des Menus
$req_menu = $pdo->prepare("SELECT * FROM menu WHERE id_menu = ?");
$req_menu->execute([$id_menu]);
$menu = $req_menu->fetch(PDO::FETCH_ASSOC);

if (!$menu) {
    header('Location: menus.php');
    exit;
}

// Récupération des infos de l'Utilisateur pour le pré-remplissage
$req_user = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
$req_user->execute([$_SESSION['user_id']]);
$user = $req_user->fetch(PDO::FETCH_ASSOC);

$message = "";

// traitement du formulaire de commande
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_prestation = $_POST['date_prestation'];
    $heure_prestation = $_POST['heure_prestation'];
    $lieu_prestation = trim($_POST['lieu_prestation']);
    $nb_personnes = (int) $_POST['nb_personnes'];
    $est_hors_bordeaux = isset($_POST['hors_bordeaux']) ? true : false;
    $distance_km = $est_hors_bordeaux ? (float) $_POST['distance_km'] : 0;

    // Vérification de la règle de gestion : Minimum de personnes
    if ($nb_personnes < $menu['nb_personnes_min']) {
        $message = "<div class='alert-error'>Erreur : Le minimum pour ce menu est de {$menu['nb_personnes_min']} personnes.</div>";
    } else {
        // calcul du prix, côté serveur
        $prix_unitaire = $menu['prix_min'];
        $prix_menu_total = $prix_unitaire * $nb_personnes;

        // -10% si 5 personnes de plus que le minimum
        if ($nb_personnes >= ($menu['nb_personnes_min'] + 5)) {
            $prix_menu_total = $prix_menu_total * 0.90;
        }

        // Frais de livraison (5€ + 0.59€/km si hors Bordeaux)
        $frais_livraison = 0;
        if ($est_hors_bordeaux) {
            $frais_livraison = 5 + (0.59 * $distance_km);
        }

        $prix_total_final = $prix_menu_total + $frais_livraison;

        // enregistrement en base de données
        try {
            $insert = $pdo->prepare("
                INSERT INTO commande (date_prestation, heure_prestation, lieu_prestation, nb_personnes, prix_total, statut, id_utilisateur, id_menu)
                VALUES (?, ?, ?, ?, ?, 'en attente', ?, ?)
            ");
            $insert->execute([
                $date_prestation,
                $heure_prestation,
                $lieu_prestation,
                $nb_personnes,
                $prix_total_final,
                $_SESSION['user_id'],
                $id_menu
            ]);

            // Redirection vers l'espace utilisateur
            header('Location: espace_utilisateur.php?success=commande_validee');
            exit;
        } catch (PDOException $e) {
            $message = "<div class='alert-error'>Une erreur est survenue lors de la commande.</div>";
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <h2 class="logo-font text-white mb-5 text-center" style="font-size: 2.5rem;">Finaliser votre commande</h2>

    <?php if(!empty($message)) echo $message; ?>

    <form method="POST" action="" id="formCommande">
        <div class="row g-5">

            <div class="col-lg-7">
                <div class="glass-panel p-4 mb-4">
                    <h4 class="text-gold mb-4 border-bottom border-secondary pb-2">1. Vos informations (Pré-remplies)</h4>
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
                            <label class="form-label">Date souhaitée</label>
                            <input type="date" name="date_prestation" class="form-control" required min="<?php echo date('Y-m-d', strtotime('+3 days')); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Heure de livraison</label>
                            <input type="time" name="heure_prestation" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nombre de convives</label>
                        <input type="number" name="nb_personnes" id="inputPersonnes" class="form-control"
                               min="<?php echo $menu['nb_personnes_min']; ?>"
                               value="<?php echo $menu['nb_personnes_min']; ?>" required>
                        <small class="text-success"><i class="fa-solid fa-tags"></i> Astuce : -10% appliqués si vous commandez pour <?php echo $menu['nb_personnes_min'] + 5; ?> personnes ou plus !</small>
                    </div>

                    <div class="mb-3 mt-4">
                        <label class="form-label">Adresse de livraison complète</label>
                        <textarea name="lieu_prestation" class="form-control" required><?php echo htmlspecialchars($user['adresse_postale']); ?></textarea>
                    </div>

                    <div class="mb-3 form-check mt-3">
                        <input type="checkbox" class="form-check-input" id="checkHorsBordeaux" name="hors_bordeaux">
                        <label class="form-check-label text-white" for="checkHorsBordeaux">La livraison est en dehors de la ville de Bordeaux</label>
                    </div>

                    <div class="mb-3" id="divDistance" style="display: none;">
                        <label class="form-label text-warning"><i class="fa-solid fa-truck"></i> Distance depuis Bordeaux (en kilomètres)</label>
                        <input type="number" step="0.1" min="0" name="distance_km" id="inputDistance" class="form-control border-warning" value="0">
                        <small class="text-muted">Facturation : 5€ de base + 0.59€ par km</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="order-summary glass-panel">
                    <h3 class="logo-font text-white mb-4">Récapitulatif</h3>

                    <div class="mb-4 pb-3 border-bottom border-secondary">
                        <h5 class="text-gold"><?php echo htmlspecialchars($menu['titre']); ?></h5>
                        <small class="text-muted">Prix unitaire : <?php echo $menu['prix_min']; ?>€</small>
                    </div>

                    <div class="summary-line">
                        <span>Menus (<span id="recapNb">X</span> pers.)</span>
                        <span id="recapMenuPrix">0.00€</span>
                    </div>

                    <div class="summary-line text-success" id="divReduction" style="display:none;">
                        <span>Réduction de groupe (-10%)</span>
                        <span id="recapReduction">-0.00€</span>
                    </div>

                    <div class="summary-line text-warning" id="divLivraison" style="display:none;">
                        <span>Frais de livraison (Hors Bdx)</span>
                        <span id="recapLivraison">+0.00€</span>
                    </div>

                    <div class="summary-total">
                        <span>TOTAL</span>
                        <span id="recapTotal">0.00€</span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variables de base issues de PHP
    const prixUnitaire = <?php echo $menu['prix_min']; ?>;
    const minPersonnes = <?php echo $menu['nb_personnes_min']; ?>;

    // Éléments du formulaire
    const inputPersonnes = document.getElementById('inputPersonnes');
    const checkHorsBordeaux = document.getElementById('checkHorsBordeaux');
    const divDistance = document.getElementById('divDistance');
    const inputDistance = document.getElementById('inputDistance');

    // Éléments du récapitulatif
    const recapNb = document.getElementById('recapNb');
    const recapMenuPrix = document.getElementById('recapMenuPrix');
    const divReduction = document.getElementById('divReduction');
    const recapReduction = document.getElementById('recapReduction');
    const divLivraison = document.getElementById('divLivraison');
    const recapLivraison = document.getElementById('recapLivraison');
    const recapTotal = document.getElementById('recapTotal');

    // Fonction de calcul
    function calculerPrix() {
        let nb = parseInt(inputPersonnes.value) || minPersonnes;
        if(nb < minPersonnes) nb = minPersonnes;

        let prixMenuBase = nb * prixUnitaire;
        let reduction = 0;
        let livraison = 0;

        // réduction de 10% si +5 personnes
        if (nb >= (minPersonnes + 5)) {
            reduction = prixMenuBase * 0.10;
            divReduction.style.display = 'flex';
        } else {
            divReduction.style.display = 'none';
        }

        // livraison hors bordeaux
        if (checkHorsBordeaux.checked) {
            divDistance.style.display = 'block';
            let km = parseFloat(inputDistance.value) || 0;
            livraison = 5 + (0.59 * km);
            divLivraison.style.display = 'flex';
        } else {
            divDistance.style.display = 'none';
            divLivraison.style.display = 'none';
        }

        // Calcul final
        let total = prixMenuBase - reduction + livraison;

        // Affichage à l'écran avec 2 chiffres après la virgule
        recapNb.textContent = nb;
        recapMenuPrix.textContent = prixMenuBase.toFixed(2) + '€';
        recapReduction.textContent = '-' + reduction.toFixed(2) + '€';
        recapLivraison.textContent = '+' + livraison.toFixed(2) + '€';
        recapTotal.textContent = total.toFixed(2) + '€';
    }

    // écouter chaque changement sur le formulaire
    inputPersonnes.addEventListener('input', calculerPrix);
    checkHorsBordeaux.addEventListener('change', calculerPrix);
    inputDistance.addEventListener('input', calculerPrix);

    calculerPrix();
});
</script>

<?php include 'includes/footer.php'; ?>
