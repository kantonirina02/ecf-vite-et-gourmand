<?php
require_once 'includes/security.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';
require_once 'includes/order_history.php';
require_once 'includes/order_status.php';
require_once 'includes/nosql_stats.php';
require_once 'includes/classes/AllergenRepository.php';
require_once 'includes/classes/DishRepository.php';
require_once 'includes/classes/MenuRepository.php';
require_once 'includes/classes/OrderRepository.php';
require_once 'includes/classes/ReviewRepository.php';
require_once 'includes/classes/ScheduleRepository.php';
require_once 'includes/classes/OrderPriceCalculator.php';

require_role(['employe', 'admin']);

$allergenRepository = new AllergenRepository($pdo);
$dishRepository = new DishRepository($pdo);
$menuRepository = new MenuRepository($pdo);
$orderRepository = new OrderRepository($pdo);
$priceCalculator = new OrderPriceCalculator();
$reviewRepository = new ReviewRepository($pdo);
$scheduleRepository = new ScheduleRepository($pdo);
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

function post_int_array(string $name): array
{
    $values = $_POST[$name] ?? [];
    $values = is_array($values) ? $values : [$values];

    return array_values(array_unique(array_filter(array_map('intval', $values), fn($id) => $id > 0)));
}

function option_selected(int $id, array $selected): string
{
    return in_array($id, $selected, true) ? 'selected' : '';
}

function menu_images_directory(): string
{
    $directory = __DIR__ . '/assets/images';

    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException("Impossible de creer le dossier des images.");
    }

    if (!is_writable($directory)) {
        throw new RuntimeException("Le dossier assets/images n'est pas accessible en ecriture.");
    }

    return $directory;
}

function uploaded_file_or_null(string $field): ?array
{
    if (!isset($_FILES[$field]) || is_array($_FILES[$field]['name'] ?? null)) {
        return null;
    }

    return ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? null : $_FILES[$field];
}

function uploaded_files_array(string $field): array
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) {
        return [];
    }

    $files = [];
    $count = count($_FILES[$field]['name']);

    for ($i = 0; $i < $count; $i++) {
        if (($_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name' => $_FILES[$field]['name'][$i] ?? '',
            'type' => $_FILES[$field]['type'][$i] ?? '',
            'tmp_name' => $_FILES[$field]['tmp_name'][$i] ?? '',
            'error' => $_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES[$field]['size'][$i] ?? 0,
        ];
    }

    return $files;
}

function upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "L'image est trop volumineuse.",
        UPLOAD_ERR_PARTIAL => "L'image n'a pas ete envoyee completement.",
        UPLOAD_ERR_NO_TMP_DIR => "Le dossier temporaire d'upload est indisponible.",
        UPLOAD_ERR_CANT_WRITE => "Impossible d'ecrire l'image sur le serveur.",
        UPLOAD_ERR_EXTENSION => "L'upload a ete bloque par une extension PHP.",
        default => "Impossible de recevoir l'image.",
    };
}

function save_menu_image_upload(array $file): string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message($errorCode));
    }

    $size = (int) ($file['size'] ?? 0);
    $maxSize = 5 * 1024 * 1024;

    if ($size <= 0 || $size > $maxSize) {
        throw new RuntimeException("L'image doit peser moins de 5 Mo.");
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException("Image envoyee invalide.");
    }

    $mime = '';

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpPath);
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException("Format d'image refuse. Formats acceptes : JPG, PNG, WEBP, AVIF.");
    }

    $fileName = 'menu_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    $targetPath = menu_images_directory() . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException("Impossible d'enregistrer l'image.");
    }

    return $fileName;
}

function posted_image_names(string $field): array
{
    $values = $_POST[$field] ?? [];
    $values = is_array($values) ? $values : [$values];
    $images = [];

    foreach ($values as $value) {
        $image = basename(clean_text_input((string) $value, 255));

        if ($image !== '') {
            $images[] = $image;
        }
    }

    return array_values(array_unique($images));
}

function is_managed_menu_image(string $image): bool
{
    return (bool) preg_match('/^menu_[0-9]{8}_[0-9]{6}_[a-f0-9]{16}\.(jpg|png|webp|avif)$/', basename($image));
}

function delete_uploaded_menu_image_if_unused(MenuRepository $menuRepository, string $image): void
{
    $image = basename($image);

    if ($image === '' || !is_managed_menu_image($image)) {
        return;
    }

    if ($menuRepository->isImageReferenced($image)) {
        return;
    }

    $path = __DIR__ . '/assets/images/' . $image;

    if (is_file($path)) {
        @unlink($path);
    }
}

function delete_uploaded_menu_images_if_unused(MenuRepository $menuRepository, array $images): void
{
    foreach (array_unique(array_map('basename', $images)) as $image) {
        delete_uploaded_menu_image_if_unused($menuRepository, $image);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_statut_commande'])) {
    $idCmd = (int) $_POST['id_commande'];
    $nouveauStatut = normalize_order_status($_POST['nouveau_statut'] ?? '');
    $modeContact = clean_text_input($_POST['mode_contact'] ?? '', 30);
    $motif = clean_text_input($_POST['motif'] ?? '', 500);

    if (!order_status_is_allowed($nouveauStatut)) {
        $message = "<div class='alert-error'>Statut invalide.</div>";
    } elseif ($nouveauStatut === 'annulee' && ($modeContact === '' || $motif === '')) {
        $message = "<div class='alert-error'>Pour annuler une commande, renseignez le mode de contact et le motif.</div>";
    } else {
        try {
            ensure_order_history_table($pdo);
            $pdo->beginTransaction();

            $commande = $orderRepository->findForEmployeeStatusUpdate($idCmd);

            if (!$commande) {
                throw new RuntimeException('Commande introuvable.');
            }

            $ancienStatut = normalize_order_status($commande['statut'] ?? '');

            if (!$orderRepository->updateStatus($idCmd, $nouveauStatut)) {
                throw new RuntimeException('Impossible de mettre a jour le statut.');
            }

            if ($nouveauStatut === 'annulee' && $ancienStatut !== 'annulee') {
                $menuRepository->increaseStock((int) $commande['id_menu']);
            }

            $commentaire = $motif !== '' ? "Contact: $modeContact. Motif: $motif" : 'Mise à jour par un employé.';
            add_order_history($pdo, $idCmd, $nouveauStatut, $commentaire);

            $pdo->commit();
            nosql_sync_stats_from_sql($pdo);

            if (in_array($nouveauStatut, ['terminee', 'attente_retour_materiel', 'annulee'], true)) {
                $body = "Bonjour " . $commande['prenom'] . ",\n\n";

                if ($nouveauStatut === 'attente_retour_materiel') {
                    $body .= "Votre commande " . $commande['titre'] . " est en attente du retour de matériel.\n";
                    $body .= "Si le matériel n'est pas restitué sous 10 jours ouvrés, des frais de 600 EUR pourront être appliqués comme indiqué dans les CGV.\n";
                } elseif ($nouveauStatut === 'annulee') {
                    $body .= "Votre commande " . $commande['titre'] . " a été annulée.\n";
                    $body .= "Motif : $motif\n";
                } else {
                    $body .= "Votre commande " . $commande['titre'] . " est terminée. Vous pouvez vous connecter pour laisser un avis.\n";
                }

                $body .= "\nL'équipe Vite & Gourmand.";
                send_app_email($commande['email'], "Suivi de votre commande Vite & Gourmand", $body);
            }

            $message = "<div class='alert-success'>Le statut de la commande #$idCmd a été mis à jour.</div>";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $message = "<div class='alert-error'>Impossible de mettre à jour cette commande.</div>";
        }
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

    if (!is_valid_date_string($datePrestation)) {
        $message = "<div class='alert-error'>La date de prestation est invalide.</div>";
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
            $commande = $orderRepository->findEditableOrder($idCmd);

            if (!$commande) {
                throw new RuntimeException('Commande introuvable.');
            }

            if ($nbPersonnes < (int) $commande['nb_personnes_min']) {
                throw new RuntimeException('Le nombre de personnes est inférieur au minimum du menu.');
            }

            $priceDetails = $priceCalculator->calculate(
                (float) $commande['prix_min'],
                (int) $commande['nb_personnes_min'],
                $nbPersonnes,
                $horsBordeaux,
                $distanceKm
            );
            $prixTotal = $priceDetails['total'];

            $orderRepository->updateOrderDetails(
                $idCmd,
                $datePrestation,
                $heurePrestation,
                $lieuPrestation,
                $nbPersonnes,
                $prixTotal
            );

            ensure_order_history_table($pdo);
            add_order_history($pdo, $idCmd, normalize_order_status($commande['statut']), 'Détails de la commande modifiés par un employé.');
            nosql_sync_stats_from_sql($pdo);
            $message = "<div class='alert-success'>Détails de la commande modifiés avec succès.</div>";
        } catch (Throwable $e) {
            $message = "<div class='alert-error'>" . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_avis'], $_POST['id_avis'])) {
    $idAvis = (int) $_POST['id_avis'];
    $action = $_POST['action_avis'];

    if ($action === 'valider') {
        $reviewRepository->updateStatus($idAvis, 'validé');
        $message = "<div class='alert-success'>L'avis a été validé et sera visible sur l'accueil.</div>";
    } elseif ($action === 'refuser') {
        $reviewRepository->updateStatus($idAvis, 'refusé');
        $message = "<div class='alert-success'>L'avis a été refusé et masqué.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_modifier_horaire'])) {
    $idHoraire = (int) $_POST['id_horaire'];
    $jour = clean_text_input($_POST['jour'] ?? '', 50);
    $heures = clean_text_input($_POST['heures'] ?? '', 50);

    if ($jour !== '' && $heures !== '') {
        $scheduleRepository->update($idHoraire, $jour, $heures);
        $message = "<div class='alert-success'>L'horaire a été mis à jour.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_menu_save'])) {
    $idMenu = (int) ($_POST['id_menu'] ?? 0);
    $titre = clean_text_input($_POST['titre'] ?? '', 100);
    $description = trim($_POST['description'] ?? '');
    $theme = clean_text_input($_POST['theme'] ?? '', 50);
    $regime = clean_text_input($_POST['regime'] ?? '', 50);
    $nbMin = max(1, (int) ($_POST['nb_personnes_min'] ?? 1));
    $prixMin = max(0, (float) ($_POST['prix_min'] ?? 0));
    $stock = max(0, (int) ($_POST['stock'] ?? 0));
    $conditions = trim($_POST['conditions'] ?? '');
    $platsMenu = post_int_array('plats_menu');
    $imagesGalerieExistantes = posted_image_names('images_galerie_existantes');
    $imagesUploadees = [];
    $anciennesImages = [];

    try {
        if ($idMenu > 0) {
            $menuActuel = $menuRepository->findById($idMenu);

            if (!$menuActuel) {
                throw new RuntimeException('Menu introuvable.');
            }

            $image = basename((string) $menuActuel['image']);
            $anciennesImages = $menuRepository->findAllImageFiles($idMenu);
        } else {
            $image = '';
        }

        $imagePrincipale = uploaded_file_or_null('image_principale');

        if ($imagePrincipale !== null) {
            $image = save_menu_image_upload($imagePrincipale);
            $imagesUploadees[] = $image;
        } elseif ($idMenu === 0) {
            throw new RuntimeException("L'image principale est obligatoire.");
        }

        $nouvellesImagesGalerie = [];

        foreach (uploaded_files_array('images_galerie') as $fichierImage) {
            $imageGalerie = save_menu_image_upload($fichierImage);
            $nouvellesImagesGalerie[] = $imageGalerie;
            $imagesUploadees[] = $imageGalerie;
        }

        $imagesGalerie = array_merge($imagesGalerieExistantes, $nouvellesImagesGalerie);

        $idMenu = $menuRepository->saveWithRelations(
            $idMenu,
            $titre,
            $image,
            $description,
            $theme,
            $nbMin,
            $prixMin,
            $conditions,
            $regime,
            $stock,
            $platsMenu,
            $imagesGalerie
        );

        try {
            delete_uploaded_menu_images_if_unused($menuRepository, $anciennesImages);
        } catch (Throwable $cleanupError) {
            error_log($cleanupError->getMessage());
        }

        $message = "<div class='alert-success'>Menu enregistré.</div>";
    } catch (Throwable $e) {
        try {
            delete_uploaded_menu_images_if_unused($menuRepository, $imagesUploadees);
        } catch (Throwable $cleanupError) {
            error_log($cleanupError->getMessage());
        }

        error_log($e->getMessage());
        $message = "<div class='alert-error'>Impossible d'enregistrer le menu : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_menu_delete'])) {
    $idMenu = (int) $_POST['id_menu'];
    $imagesASupprimer = $menuRepository->findAllImageFiles($idMenu);

    try {
        $menuRepository->deleteWithRelations($idMenu);

        try {
            delete_uploaded_menu_images_if_unused($menuRepository, $imagesASupprimer);
        } catch (Throwable $cleanupError) {
            error_log($cleanupError->getMessage());
        }
        $message = "<div class='alert-success'>Menu supprimé.</div>";
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $message = "<div class='alert-error'>Impossible de supprimer ce menu car il est peut-être lié à une commande.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_plat_save'])) {
    $idPlat = (int) ($_POST['id_plat'] ?? 0);
    $nom = clean_text_input($_POST['nom'] ?? '', 100);
    $categorie = $_POST['categorie'] ?? 'plat';
    $categories = ['entrée', 'plat', 'dessert'];
    $allergenesPlat = post_int_array('allergenes_plat');

    if (!in_array($categorie, $categories, true)) {
        $categorie = 'plat';
    }

    try {
        $dishRepository->saveWithAllergens($idPlat, $nom, $categorie, $allergenesPlat);
        $message = "<div class='alert-success'>Plat enregistré.</div>";
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $message = "<div class='alert-error'>Impossible d'enregistrer le plat.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_plat_delete'])) {
    $idPlat = (int) $_POST['id_plat'];

    try {
        $dishRepository->delete($idPlat);
        $message = "<div class='alert-success'>Plat supprimé.</div>";
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $message = "<div class='alert-error'>Impossible de supprimer ce plat.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_allergene_save'])) {
    $nom = clean_text_input($_POST['nom_allergene'] ?? '', 100);

    if ($nom !== '') {
        $allergenRepository->createIfMissing($nom);
        $message = "<div class='alert-success'>Allergène enregistré.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_allergene_delete'])) {
    $idAllergene = (int) $_POST['id_allergene'];

    try {
        $allergenRepository->delete($idAllergene);
        $message = "<div class='alert-success'>Allergène supprimé.</div>";
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $message = "<div class='alert-error'>Impossible de supprimer cet allergène.</div>";
    }
}

$statusValues = [];

if (!empty($_GET['filtre_statut'])) {
    $statusValues = order_status_database_values($_GET['filtre_statut']);
}

$filtreClient = trim($_GET['filtre_client'] ?? '');
$commandes = $orderRepository->findForEmployeeBoard($statusValues, $filtreClient);

$avisEnAttente = $reviewRepository->findPending();
$horaires = $scheduleRepository->findAll();
$menus = $menuRepository->findAllForEmployeeBoard();
$plats = $dishRepository->findAll();
$allergenes = $allergenRepository->findAll();
$menuPlatMap = $menuRepository->findDishMap();
$menuImageMap = $menuRepository->findImageMap();
$platAllergeneMap = $dishRepository->findAllergenMap();

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <h2 class="logo-font text-gold mb-5 text-center">Tableau de Bord Employé</h2>

    <?php if(!empty($message)) echo $message; ?>

    <div class="dashboard-grid">
        <div class="glass-panel p-4 dashboard-panel-full">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-solid fa-bell-concierge"></i> Gestion des Commandes</h4>

            <form method="GET" action="" class="mb-4 employee-filter-form">
                <label class="visually-hidden" for="filtre_statut">Filtrer par statut</label>
                <select id="filtre_statut" name="filtre_statut" class="form-control employee-filter-field">
                    <option value="">Tous les statuts</option>
                    <?php foreach(ORDER_STATUSES as $statut): ?>
                        <option value="<?php echo htmlspecialchars($statut); ?>" <?php echo (normalize_order_status($_GET['filtre_statut'] ?? '') === $statut) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(order_status_label($statut)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="visually-hidden" for="filtre_client">Filtrer par client</label>
                <input id="filtre_client" type="text" name="filtre_client" class="form-control employee-filter-field" placeholder="Client, email..." value="<?php echo htmlspecialchars($_GET['filtre_client'] ?? ''); ?>">
                <button type="submit" class="btn-action-small btn-primary border-0">Filtrer</button>
                <a href="espace_employe" class="btn-action-small btn-outline">Réinitialiser</a>
            </form>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client & Contact</th>
                            <th>Prestation</th>
                            <th>Statut</th>
                            <th>Mise à jour</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($commandes as $cmd): ?>
                            <tr>
                                <td>#<?php echo (int)$cmd['id_commande']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($cmd['nom'] . ' ' . $cmd['prenom']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($cmd['gsm']); ?> - <?php echo htmlspecialchars($cmd['email']); ?></small>
                                </td>
                                <td>
                                    <span class="text-gold"><?php echo htmlspecialchars($cmd['menu_titre']); ?></span><br>
                                    <small><?php echo date('d/m/Y', strtotime($cmd['date_prestation'])); ?> à <?php echo htmlspecialchars($cmd['heure_prestation']); ?></small>
                                </td>
                                <td><span class="badge-status <?php echo order_status_badge_class($cmd['statut']); ?>"><?php echo htmlspecialchars(order_status_label($cmd['statut'])); ?></span></td>
                                <td>
                                    <form method="POST" action="" class="status-update-form">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action_statut_commande" value="1">
                                        <input type="hidden" name="id_commande" value="<?php echo (int)$cmd['id_commande']; ?>">
                                        <label class="visually-hidden" for="statut_<?php echo (int)$cmd['id_commande']; ?>">Nouveau statut</label>
                                        <select id="statut_<?php echo (int)$cmd['id_commande']; ?>" name="nouveau_statut" class="form-control" required>
                                            <?php foreach(ORDER_STATUSES as $statut): ?>
                                                <option value="<?php echo htmlspecialchars($statut); ?>" <?php echo normalize_order_status($cmd['statut']) === $statut ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(order_status_label($statut)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="mode_contact" class="form-control">
                                            <option value="">Mode de contact si annulation</option>
                                            <option value="telephone">Téléphone</option>
                                            <option value="email">Email</option>
                                        </select>
                                        <input type="text" name="motif" class="form-control" placeholder="Motif si annulation">
                                        <button type="submit" class="btn-action-small btn-primary border-0">Mettre à jour</button>
                                    </form>
                                    <button class="btn-action-small btn-outline js-toggle-row mt-2 w-100" type="button" data-target="formModifCmd<?php echo (int)$cmd['id_commande']; ?>" data-display="table-row">Modifier détails</button>
                                </td>
                            </tr>
                            <tr id="formModifCmd<?php echo (int)$cmd['id_commande']; ?>" class="is-hidden">
                                <td colspan="5" class="order-form-cell">
                                    <form method="POST" action="">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action_modifier_commande" value="1">
                                        <input type="hidden" name="id_commande" value="<?php echo (int)$cmd['id_commande']; ?>">

                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Date</label>
                                                <input type="date" name="date_prestation" class="form-control" value="<?php echo htmlspecialchars($cmd['date_prestation']); ?>" required>
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

                                        <div class="form-actions mt-3">
                                            <button type="button" class="btn-action-small btn-outline js-toggle-row" data-target="formModifCmd<?php echo (int)$cmd['id_commande']; ?>" data-display="none">Fermer</button>
                                            <button type="submit" class="btn-action-small btn-primary border-0">Enregistrer détails</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-panel p-4 dashboard-panel-full">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2">Gestion des Menus</h4>
            <form method="POST" action="" class="mb-4" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_menu_save" value="1">
                <input type="hidden" name="id_menu" value="">
                <label class="form-label">Titre</label>
                <input type="text" name="titre" class="form-control" required>
                <label class="form-label">Image principale</label>
                <input type="file" name="image_principale" class="form-control" accept="image/jpeg,image/png,image/webp,image/avif" required>
                <label class="form-label">Galerie d'images</label>
                <input type="file" name="images_galerie[]" class="form-control" accept="image/jpeg,image/png,image/webp,image/avif" multiple>
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" required></textarea>
                <label class="form-label">Thème</label>
                <input type="text" name="theme" class="form-control" required>
                <label class="form-label">Régime</label>
                <input type="text" name="regime" class="form-control" required>
                <label class="form-label">Plats du menu</label>
                <select name="plats_menu[]" class="form-control" multiple size="6">
                    <?php foreach($plats as $plat): ?>
                        <option value="<?php echo (int)$plat['id_plat']; ?>"><?php echo htmlspecialchars($plat['categorie'] . ' - ' . $plat['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">Nombre minimum</label>
                <input type="number" name="nb_personnes_min" class="form-control" min="1" required>
                <label class="form-label">Prix par personne</label>
                <input type="number" step="0.01" name="prix_min" class="form-control" min="0" required>
                <label class="form-label">Stock</label>
                <input type="number" name="stock" class="form-control" min="0" required>
                <label class="form-label">Conditions du menu</label>
                <textarea name="conditions" class="form-control"></textarea>
                <button type="submit" class="btn-primary border-0">Ajouter un menu</button>
            </form>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead><tr><th>Titre</th><th>Thème</th><th>Prix</th><th>Stock</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($menus as $menu): ?>
                            <?php $selectedPlats = $menuPlatMap[(int)$menu['id_menu']] ?? []; ?>
                            <?php $selectedImages = $menuImageMap[(int)$menu['id_menu']] ?? []; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($menu['titre']); ?></td>
                                <td><?php echo htmlspecialchars($menu['theme']); ?></td>
                                <td><?php echo htmlspecialchars($menu['prix_min']); ?> EUR</td>
                                <td><?php echo (int)($menu['stock'] ?? 0); ?></td>
                                <td>
                                    <form method="POST" action="" class="inline-form" data-confirm="Supprimer ce menu ?">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action_menu_delete" value="1">
                                        <input type="hidden" name="id_menu" value="<?php echo (int)$menu['id_menu']; ?>">
                                        <button type="submit" class="btn-action-small btn-outline text-danger border-danger">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="5" class="menu-edit-cell">
                                    <form method="POST" action="" enctype="multipart/form-data">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action_menu_save" value="1">
                                        <input type="hidden" name="id_menu" value="<?php echo (int)$menu['id_menu']; ?>">
                                        <label class="form-label">Titre</label>
                                        <input type="text" name="titre" class="form-control" value="<?php echo htmlspecialchars($menu['titre']); ?>" required>
                                        <label class="form-label">Image principale</label>
                                        <?php if(!empty($menu['image'])): ?>
                                            <div class="mb-2">
                                                <img src="assets/images/<?php echo htmlspecialchars(basename($menu['image'])); ?>" alt="<?php echo htmlspecialchars($menu['titre'] . ' - image principale'); ?>" class="menu-main-thumb">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="image_principale" class="form-control" accept="image/jpeg,image/png,image/webp,image/avif">
                                        <label class="form-label">Galerie d'images</label>
                                        <?php if(!empty($selectedImages)): ?>
                                            <div class="mb-3 menu-gallery-editor">
                                                <?php foreach($selectedImages as $imageExistante): ?>
                                                    <?php $imageExistante = basename($imageExistante); ?>
                                                    <label class="menu-gallery-option">
                                                        <input type="checkbox" name="images_galerie_existantes[]" value="<?php echo htmlspecialchars($imageExistante); ?>" checked>
                                                        <img src="assets/images/<?php echo htmlspecialchars($imageExistante); ?>" alt="<?php echo htmlspecialchars($menu['titre'] . ' - galerie'); ?>" class="menu-gallery-thumb">
                                                        <span class="small text-muted"><?php echo htmlspecialchars($imageExistante); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="images_galerie[]" class="form-control" accept="image/jpeg,image/png,image/webp,image/avif" multiple>
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" required><?php echo htmlspecialchars($menu['description']); ?></textarea>
                                        <label class="form-label">Thème</label>
                                        <input type="text" name="theme" class="form-control" value="<?php echo htmlspecialchars($menu['theme']); ?>" required>
                                        <label class="form-label">Régime</label>
                                        <input type="text" name="regime" class="form-control" value="<?php echo htmlspecialchars($menu['regime']); ?>" required>
                                        <label class="form-label">Plats du menu</label>
                                        <select name="plats_menu[]" class="form-control" multiple size="6">
                                            <?php foreach($plats as $plat): ?>
                                                <option value="<?php echo (int)$plat['id_plat']; ?>" <?php echo option_selected((int)$plat['id_plat'], $selectedPlats); ?>>
                                                    <?php echo htmlspecialchars($plat['categorie'] . ' - ' . $plat['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="form-label">Nombre minimum</label>
                                        <input type="number" name="nb_personnes_min" class="form-control" min="1" value="<?php echo (int)$menu['nb_personnes_min']; ?>" required>
                                        <label class="form-label">Prix par personne</label>
                                        <input type="number" step="0.01" name="prix_min" class="form-control" min="0" value="<?php echo htmlspecialchars($menu['prix_min']); ?>" required>
                                        <label class="form-label">Stock</label>
                                        <input type="number" name="stock" class="form-control" min="0" value="<?php echo (int)($menu['stock'] ?? 0); ?>" required>
                                        <label class="form-label">Conditions</label>
                                        <textarea name="conditions" class="form-control"><?php echo htmlspecialchars($menu['conditions'] ?? ''); ?></textarea>
                                        <button type="submit" class="btn-action-small btn-primary border-0">Modifier ce menu</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2">Gestion des Plats</h4>
            <form method="POST" action="" class="mb-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_plat_save" value="1">
                <label class="form-label">Nom du plat</label>
                <input type="text" name="nom" class="form-control" required>
                <label class="form-label">Catégorie</label>
                <select name="categorie" class="form-control" required>
                    <option value="entrée">Entrée</option>
                    <option value="plat">Plat</option>
                    <option value="dessert">Dessert</option>
                </select>
                <label class="form-label">Allergènes</label>
                <select name="allergenes_plat[]" class="form-control" multiple size="5">
                    <?php foreach($allergenes as $allergene): ?>
                        <option value="<?php echo (int)$allergene['id_allergene']; ?>"><?php echo htmlspecialchars($allergene['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary border-0">Ajouter</button>
            </form>

            <?php foreach($plats as $plat): ?>
                <?php $selectedAllergenes = $platAllergeneMap[(int)$plat['id_plat']] ?? []; ?>
                <form method="POST" action="" class="mb-3 pb-3 border-bottom border-secondary">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id_plat" value="<?php echo (int)$plat['id_plat']; ?>">
                    <input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars($plat['nom']); ?>" required>
                    <select name="categorie" class="form-control" required>
                        <?php foreach(['entrée', 'plat', 'dessert'] as $categorie): ?>
                            <option value="<?php echo htmlspecialchars($categorie); ?>" <?php echo $plat['categorie'] === $categorie ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($categorie)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="allergenes_plat[]" class="form-control" multiple size="5">
                        <?php foreach($allergenes as $allergene): ?>
                            <option value="<?php echo (int)$allergene['id_allergene']; ?>" <?php echo option_selected((int)$allergene['id_allergene'], $selectedAllergenes); ?>>
                                <?php echo htmlspecialchars($allergene['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="action_plat_save" value="1" class="btn-action-small btn-primary border-0">Modifier</button>
                    <button type="submit" name="action_plat_delete" value="1" class="btn-action-small btn-outline text-danger border-danger" data-confirm-click="Supprimer ce plat ?">Supprimer</button>
                </form>
            <?php endforeach; ?>
        </div>

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2">Allergènes</h4>
            <form method="POST" action="" class="mb-4">
                <?php echo csrf_field(); ?>
                <label class="form-label">Nouvel allergène</label>
                <input type="text" name="nom_allergene" class="form-control" required>
                <button type="submit" name="action_allergene_save" value="1" class="btn-primary border-0">Ajouter</button>
            </form>
            <?php foreach($allergenes as $allergene): ?>
                <form method="POST" action="" class="allergen-row-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id_allergene" value="<?php echo (int)$allergene['id_allergene']; ?>">
                    <span><?php echo htmlspecialchars($allergene['nom']); ?></span>
                    <button type="submit" name="action_allergene_delete" value="1" class="btn-action-small btn-outline text-danger border-danger">Supprimer</button>
                </form>
            <?php endforeach; ?>
        </div>

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-solid fa-comments"></i> Modération des Avis</h4>

            <?php if(empty($avisEnAttente)): ?>
                <p class="text-muted fst-italic">Aucun avis en attente de modération.</p>
            <?php else: ?>
                <?php foreach($avisEnAttente as $avis): ?>
                    <div class="mb-4 pb-3 border-bottom border-secondary">
                        <strong class="text-gold"><?php echo htmlspecialchars($avis['nom'] . ' ' . $avis['prenom']); ?></strong>
                        <p class="small text-muted mb-2">Menu : <?php echo htmlspecialchars($avis['menu_titre']); ?></p>
                        <p class="fst-italic mb-3">"<?php echo htmlspecialchars($avis['commentaire']); ?>"</p>
                        <form method="POST" action="" class="inline-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id_avis" value="<?php echo (int)$avis['id_avis']; ?>">
                            <button type="submit" name="action_avis" value="valider" class="btn-action-small btn-outline text-success border-success">Valider</button>
                            <button type="submit" name="action_avis" value="refuser" class="btn-action-small btn-outline text-danger border-danger">Refuser</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-regular fa-clock"></i> Horaires</h4>

            <?php foreach($horaires as $h): ?>
                <form method="POST" action="" class="mb-3 pb-3 border-bottom border-secondary">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action_modifier_horaire" value="1">
                    <input type="hidden" name="id_horaire" value="<?php echo (int)$h['id_horaire']; ?>">
                    <label class="form-label">Jour</label>
                    <input type="text" name="jour" class="form-control" value="<?php echo htmlspecialchars($h['jour']); ?>" required>
                    <label class="form-label">Horaires</label>
                    <input type="text" name="heures" class="form-control" value="<?php echo htmlspecialchars($h['heures']); ?>" required>
                    <button type="submit" class="btn-action-small btn-primary border-0 mt-2">Mettre à jour</button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
