<?php
require_once 'includes/db.php';
require_once 'includes/classes/MenuRepository.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: menus');
    exit;
}
$id_menu = (int) $_GET['id'];
$menuRepository = new MenuRepository($pdo);

$menu = $menuRepository->findById($id_menu);

if (!$menu) {
    header('Location: menus');
    exit;
}

$platsDuMenu = $menuRepository->findDishesWithAllergens($id_menu);
$imagesMenu = $menuRepository->findImagePaths($id_menu);

if (empty($imagesMenu) && !empty($menu['image'])) {
    $imagesMenu = [$menu['image']];
}

include 'includes/header.php';
?>

<div class="menu-detail-header">
    <img src="assets/images/<?php echo htmlspecialchars(basename($menu['image'] ?? 'default.jpg')); ?>" alt="<?php echo htmlspecialchars($menu['titre'] ?? 'Menu'); ?>" class="menu-detail-img">
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
                <p class="text-muted" style="font-size: 1.1rem; line-height: 1.8;">
                    <?php echo nl2br(htmlspecialchars($menu['description'] ?? '')); ?>
                </p>
            </div>

            <div class="col-lg-5">
                <div class="glass-panel p-4" style="background: rgba(0,0,0,0.3);">
                    <div class="text-gold fw-bold mb-3" style="font-size: 2.5rem;">
                        <?php echo number_format($menu['prix_min'] ?? 0, 0, ',', ' '); ?>€
                        <span class="text-muted fw-normal" style="font-size: 1rem;">/ personne</span>
                    </div>

                    <div class="text-white fw-bold mb-3">
                        <i class="fa-solid fa-users text-gold me-2"></i>
                        Commande minimum : <?php echo htmlspecialchars($menu['nb_personnes_min'] ?? '2'); ?> personnes
                    </div>

                    <div class="text-white fw-bold mb-4">
                        <i class="fa-solid fa-box-open text-gold me-2"></i>
                        Stock restant : <?php echo htmlspecialchars($menu['stock'] ?? '0'); ?> commandes
                    </div>

                    <?php if((int)($menu['stock'] ?? 0) > 0): ?>
                        <a href="commande?id_menu=<?php echo (int)$menu['id_menu']; ?>" class="btn-primary w-100 d-block text-center text-decoration-none">
                            Commander ce menu
                        </a>
                    <?php else: ?>
                        <button class="btn-primary w-100 border-0" type="button" disabled style="opacity:.55; cursor:not-allowed;">
                            Stock indisponible
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h2 class="logo-font text-white border-bottom border-secondary pb-2 mb-4">
            Composition du Menu
        </h2>

        <?php if(!empty($imagesMenu)): ?>
        <div class="menu-gallery mb-5" aria-label="Galerie du menu">
            <?php foreach($imagesMenu as $image): ?>
                <img src="assets/images/<?php echo htmlspecialchars(basename($image)); ?>" alt="<?php echo htmlspecialchars($menu['titre'] . ' - image du menu'); ?>">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <ul class="dish-list p-0 mb-5">
            <?php foreach($platsDuMenu as $plat): ?>
            <li class="dish-item">
                <div>
                    <div class="dish-type">
                        <?php echo htmlspecialchars($plat['categorie'] ?? ''); ?>
                    </div>
                    <div class="text-white fw-bold mt-1" style="font-size:1.1rem;">
                        <?php echo htmlspecialchars($plat['nom'] ?? ''); ?>
                    </div>
                </div>

                <?php if(!empty($plat['allergenes'])): ?>
                <div class="allergens" title="Allergènes">
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
            <h4 class="text-gold mb-2">
                <i class="fa-solid fa-circle-info"></i> Conditions Spécifiques
            </h4>
            <p class="mb-0"><?php echo nl2br(htmlspecialchars($menu['conditions'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="menus" class="btn-outline d-inline-block text-decoration-none">
                <i class="fa-solid fa-arrow-left me-2"></i> Retour aux menus
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
