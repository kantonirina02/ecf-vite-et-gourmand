<?php
require_once 'includes/db.php';

// Récupération et sécurité de l'ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: menus.php');
    exit;
}
$id_menu = (int) $_GET['id'];

// Requête pour le Menu (Informations principales)
$requete = $pdo->prepare("SELECT * FROM menu WHERE id_menu = :id");
$requete->execute(['id' => $id_menu]);
$menu = $requete->fetch(PDO::FETCH_ASSOC);

if (!$menu) {
    header('Location: menus.php');
    exit;
}

// Requête pour les Plats et Allergènes (Triple Jointure SQL)
// relié 'plat', 'menu_plat', 'plat_allergene' et 'allergene'
$req_plats = $pdo->prepare("
    SELECT
        p.id_plat,
        p.nom,
        p.categorie,
        GROUP_CONCAT(a.nom SEPARATOR ', ') as allergenes
    FROM plat p
    JOIN menu_plat mp ON p.id_plat = mp.id_plat
    LEFT JOIN plat_allergene pa ON p.id_plat = pa.id_plat
    LEFT JOIN allergene a ON pa.id_allergene = a.id_allergene
    WHERE mp.id_menu = :id
    GROUP BY p.id_plat
    ORDER BY FIELD(LOWER(p.categorie), 'entrée', 'plat', 'dessert')
");
$req_plats->execute(['id' => $id_menu]);
$platsDuMenu = $req_plats->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="menu-detail-header">
    <img src="assets/images/<?php echo htmlspecialchars($menu['image'] ?? 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($menu['titre'] ?? 'Menu'); ?>" class="menu-detail-img">
    <h1 class="menu-detail-title logo-font"><?php echo htmlspecialchars($menu['titre'] ?? ''); ?></h1>
</div>

<div class="container pb-5">
    <div class="detail-content glass-panel p-4 p-md-5">

        <div class="row g-5 mb-5">
            <div class="col-lg-7">
                <div class="mb-4">
                    <span class="menu-tag me-2"><?php echo htmlspecialchars($menu['theme'] ?? ''); ?></span>
                    <span class="menu-tag" style="background:rgba(16, 185, 129, 0.1); color:var(--success)">
                        <?php echo strtoupper(htmlspecialchars($menu['regime'] ?? '')); ?>
                    </span>
                </div>
                <p style="font-size: 1.2rem; color: var(--text-muted); line-height:1.8;">
                    <?php echo nl2br(htmlspecialchars($menu['description'] ?? '')); ?>
                </p>
            </div>

            <div class="col-lg-5">
                <div class="p-4" style="background: rgba(0,0,0,0.3); border-radius: 12px; border: 1px solid var(--glass-border);">
                    <div style="font-size: 2.5rem; color: var(--gold-primary); font-weight:700; margin-bottom: 1rem;">
                        <?php echo number_format($menu['prix_min'] ?? 0, 0, ',', ' '); ?>€
                        <span style="font-size:1rem; font-weight:400; color:var(--text-muted)">/ personne</span>
                    </div>
                    <div class="mb-3 font-weight-bold text-white">
                        <i class="fa-solid fa-users" style="color:var(--gold-primary); margin-right:0.5rem;"></i>
                        Commande minimum : <?php echo htmlspecialchars($menu['nb_personnes_min'] ?? $menu['nb_personnes_min'] ?? '2'); ?> personnes
                    </div>
                    <div class="mb-4 font-weight-bold text-white">
                        <i class="fa-solid fa-box-open" style="color:var(--gold-primary); margin-right:0.5rem;"></i>
                        Stock restant : <?php echo htmlspecialchars($menu['stock'] ?? '0'); ?> commandes
                    </div>
                    <a href="commande.php?id_menu=<?php echo $menu['id_menu']; ?>" class="btn-primary w-100 d-block text-center text-decoration-none">
                        Commander ce menu
                    </a>
                </div>
            </div>
        </div>

        <h2 style="font-size: 2rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;" class="logo-font text-white">
            Composition du Menu
        </h2>
        <ul class="dish-list p-0 mb-5">
            <?php foreach($platsDuMenu as $plat): ?>
            <li class="dish-item d-flex justify-content-between align-items-center p-3" style="border-bottom: 1px solid var(--glass-border);">
                <div>
                    <div class="dish-type" style="color: var(--gold-primary); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">
                        <?php echo htmlspecialchars($plat['categorie'] ?? ''); ?>
                    </div>
                    <div style="font-size:1.1rem; font-weight:500; margin-top:0.3rem;" class="text-white">
                        <?php echo htmlspecialchars($plat['nom'] ?? ''); ?>
                    </div>
                </div>

                <?php if(!empty($plat['allergenes'])): ?>
                <div class="allergens" title="Allergènes" style="font-size: 0.8rem; color: var(--danger); background: rgba(239, 68, 68, 0.1); padding: 0.2rem 0.5rem; border-radius: 4px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($plat['allergenes']); ?>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>

            <?php if(empty($platsDuMenu)): ?>
                <p class="text-secondary fst-italic mt-3">La composition de ce menu n'a pas encore été renseignée.</p>
            <?php endif; ?>
        </ul>

        <?php if(!empty($menu['conditions'])): ?>
        <div class="conditions-alert">
            <h4 style="color:var(--gold-primary); margin-bottom:0.5rem;">
                <i class="fa-solid fa-circle-info"></i> Conditions Spécifiques
            </h4>
            <p class="mb-0"><?php echo nl2br(htmlspecialchars($menu['conditions'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="menus.php" class="btn-outline d-inline-block text-center text-decoration-none" style="width:auto; padding: 10px 20px;">
                <i class="fa-solid fa-arrow-left me-2"></i> Retour aux menus
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
