<?php
require_once 'includes/security.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';
require_once 'includes/nosql_stats.php';
require_once 'includes/classes/UserRepository.php';

require_role(['admin']);

$userRepository = new UserRepository($pdo);
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_creer_employe'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];
    $regex_mdp = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert-error'>Adresse email invalide.</div>";
    } elseif (!preg_match($regex_mdp, $mot_de_passe)) {
        $message = "<div class='alert-error'>Le mot de passe doit contenir 10 caracteres, une majuscule, une minuscule, un chiffre et un caractere special.</div>";
    } else {
        if ($userRepository->emailExists($email)) {
            $message = "<div class='alert-error'>Cet email est deja utilise.</div>";
        } else {
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

            if ($userRepository->createEmployee($nom, $prenom, $email, $hash)) {
                $body = "Bonjour $prenom,\n\nUn compte employe Vite & Gourmand a ete cree pour vous.\n";
                $body .= "Le mot de passe ne figure pas dans cet email. Rapprochez-vous de l'administrateur pour l'obtenir.\n\n";
                $body .= "L'equipe Vite & Gourmand.";
                send_app_email($email, "Creation de votre compte employe", $body);
                $message = "<div class='alert-success'>Compte employe cree avec succes.</div>";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_statut'], $_POST['id_employe'])) {
    $id_employe = (int)$_POST['id_employe'];
    $nouveau_statut = $_POST['action_statut'] === 'desactiver' ? 'inactif' : 'actif';

    if ($userRepository->updateEmployeeStatus($id_employe, $nouveau_statut)) {
        $message = "<div class='alert-success'>Le statut de l'employe a ete mis a jour.</div>";
    }
}

$employes = $userRepository->findEmployees();

nosql_sync_stats_from_sql($pdo);
$stats = nosql_read_stats();
$filtre_menu = $_GET['stats_menu'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

$stats_filtrees = [];
foreach ($stats as $entry) {
    if ($filtre_menu !== '' && (string)$entry['id_menu'] !== (string)$filtre_menu) {
        continue;
    }

    $commandes = $entry['commandes'] ?? [];
    $count = 0;
    $ca = 0;

    foreach ($commandes as $commande) {
        $date = $commande['date'] ?? '';
        if ($date_debut !== '' && $date < $date_debut) {
            continue;
        }
        if ($date_fin !== '' && $date > $date_fin) {
            continue;
        }
        $count++;
        $ca += (float)($commande['montant'] ?? 0);
    }

    $stats_filtrees[] = [
        'id_menu' => $entry['id_menu'],
        'nom_menu' => $entry['nom_menu'],
        'nombre_commandes' => $count,
        'chiffre_affaires' => $ca,
    ];
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="d-flex justify-content-between align-items-center mb-5 border-bottom border-warning pb-3">
        <h2 class="logo-font text-gold m-0">Espace Administrateur</h2>
        <a href="espace_employe" class="btn-outline border-warning text-warning admin-panel-link">
            <i class="fa-solid fa-arrow-right"></i> Aller au panel Employe
        </a>
    </div>

    <?php if(!empty($message)) echo $message; ?>

    <div class="dashboard-grid">
        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-solid fa-user-plus"></i> Nouvel Employe</h4>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_creer_employe" value="1">
                <input type="text" name="nom" class="form-control" placeholder="Nom" required>
                <input type="text" name="prenom" class="form-control" placeholder="Prenom" required>
                <input type="email" name="email" class="form-control" placeholder="Email professionnel" required>
                <input type="text" name="mot_de_passe" class="form-control" placeholder="Mot de passe provisoire" required>
                <small class="text-muted">10 caracteres minimum, 1 majuscule, 1 minuscule, 1 chiffre, 1 caractere special.</small>
                <button type="submit" class="btn-primary w-100 border-0 mt-3">Creer le compte</button>
            </form>
        </div>

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-solid fa-users-gear"></i> Equipe actuelle</h4>

            <?php if(empty($employes)): ?>
                <p class="text-muted fst-italic">Aucun employe cree pour le moment</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Nom / Prenom</th>
                                <th>Email</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($employes as $emp): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($emp['nom'] . ' ' . $emp['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                    <td>
                                        <?php if(($emp['statut_compte'] ?? 'actif') === 'actif'): ?>
                                            <span class="badge-status badge-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-waiting border-danger text-danger bg-transparent">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(($emp['statut_compte'] ?? 'actif') === 'actif'): ?>
                                            <form method="POST" action="" class="inline-form" data-confirm="Rendre ce compte inutilisable ?">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="id_employe" value="<?php echo (int)$emp['id_utilisateur']; ?>">
                                                <button type="submit" name="action_statut" value="desactiver" class="btn-action-small btn-outline text-danger border-danger">Desactiver</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="" class="inline-form">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="id_employe" value="<?php echo (int)$emp['id_utilisateur']; ?>">
                                                <button type="submit" name="action_statut" value="activer" class="btn-action-small btn-outline text-success border-success">Reactiver</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="mt-5 pt-4 border-top border-secondary">
                <h4 class="text-gold mb-4"><i class="fa-solid fa-chart-pie"></i> Statistiques des Commandes</h4>

                <form method="GET" action="" class="mb-4 stats-filter-form">
                    <select name="stats_menu" class="form-control stats-menu-select">
                        <option value="">Tous les menus</option>
                        <?php foreach($stats as $entry): ?>
                            <option value="<?php echo htmlspecialchars($entry['id_menu']); ?>" <?php echo ((string)$filtre_menu === (string)$entry['id_menu']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($entry['nom_menu']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date_debut" class="form-control stats-date-input" value="<?php echo htmlspecialchars($date_debut); ?>">
                    <input type="date" name="date_fin" class="form-control stats-date-input" value="<?php echo htmlspecialchars($date_fin); ?>">
                    <button type="submit" class="btn-action-small btn-primary border-0">Filtrer</button>
                </form>

                <?php if(empty($stats_filtrees)): ?>
                    <div class="alert-waiting text-center p-3 border border-warning text-warning bg-transparent rounded">Aucune donnee statistique trouvee. Les statistiques se rempliront apres les nouvelles commandes.</div>
                <?php else: ?>
                    <?php
                    $stats_api_query = http_build_query(array_filter([
                        'stats_menu' => $filtre_menu,
                        'date_debut' => $date_debut,
                        'date_fin' => $date_fin,
                    ], fn($value) => $value !== ''));
                    $stats_api_url = 'api/admin_stats.php' . ($stats_api_query !== '' ? '?' . $stats_api_query : '');
                    ?>
                    <div class="p-3 bg-white rounded shadow-sm">
                        <canvas id="graphiqueCommandes" height="100" data-api-url="<?php echo htmlspecialchars($stats_api_url, ENT_QUOTES, 'UTF-8'); ?>"></canvas>
                    </div>
                    <div class="table-responsive mt-4">
                        <table class="custom-table">
                            <thead><tr><th>Menu</th><th>Commandes</th><th>Chiffre d'affaires</th></tr></thead>
                            <tbody>
                                <?php foreach($stats_filtrees as $entry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($entry['nom_menu']); ?></td>
                                        <td><?php echo (int)$entry['nombre_commandes']; ?></td>
                                        <td><?php echo number_format((float)$entry['chiffre_affaires'], 2, ',', ' '); ?> EUR</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
