<?php
session_start();
require_once 'includes/db.php';

// Sécurité: uniquement les admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$message = "";

// Action de l'admin
// creation d'un comte employé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_creer_employe'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];

    // Vérification du mot de passe
    $regex_mdp = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/';

    if (!preg_match($regex_mdp, $mot_de_passe)) {
        $message = "<div class='alert-error'>Le mot de passe doit contenir 10 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.</div>";
    } else {
        // Vérifier si l'email existe déjà
        $check_email = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
        $check_email->execute([$email]);

        if ($check_email->rowCount() > 0) {
            $message = "<div class='alert-error'>Cet email est déjà utilisé.</div>";
        } else {
            // Hachage et Insertion role employé
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, statut_compte) VALUES (?, ?, ?, ?, 'employe', 'actif')");

            if ($insert->execute([$nom, $prenom, $email, $hash])) {
                // Simulation de l'envoi d'email
                $message = "<div class='alert-success'>Compte employé créé avec succès ! Un mail de notification a été envoyé à $email.</div>";
            }
        }
    }
}

// activer ou désactiver un compte employé
if (isset($_GET['action_statut']) && isset($_GET['id_employe'])) {
    $id_employe = (int)$_GET['id_employe'];
    $nouveau_statut = $_GET['action_statut'] === 'desactiver' ? 'inactif' : 'actif';

    $update = $pdo->prepare("UPDATE utilisateur SET statut_compte = ? WHERE id_utilisateur = ? AND role = 'employe'");
    if ($update->execute([$nouveau_statut, $id_employe])) {
        $message = "<div class='alert-success'>Le statut de l'employé a été mis à jour.</div>";
    }
}

// récuoération des données
// Récupérer les employés pour la gestion
$req_employes = $pdo->query("SELECT * FROM utilisateur WHERE role = 'employe' ORDER BY nom ASC");
if ($req_employes) {
    $employes = $req_employes->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employes = [];
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="d-flex justify-content-between align-items-center mb-5 border-bottom border-warning pb-3">
        <h2 class="logo-font text-gold m-0">Espace Administrateur</h2>
        <a href="espace_employe.php" class="btn-outline border-warning text-warning" style="padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none;">
            <i class="fa-solid fa-arrow-right"></i> Aller au panel Employé
        </a>
    </div>

    <?php if(!empty($message)) echo $message; ?>

    <div class="dashboard-grid">

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-solid fa-user-plus"></i> Nouvel Employé</h4>
            <form method="POST" action="">
                <input type="hidden" name="action_creer_employe" value="1">

                <div class="mb-3">
                    <label class="form-label">Nom</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Prénom</label>
                    <input type="text" name="prenom" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email professionnel</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mot de passe provisoire</label>
                    <input type="text" name="mot_de_passe" class="form-control" required>
                    <small class="text-muted">10 car. min, 1 maj, 1 min, 1 chiffre, 1 car. spécial.</small>
                </div>

                <button type="submit" class="btn-primary w-100 border-0 mt-3">Créer le compte</button>
            </form>
        </div>

        <div class="glass-panel p-4">
            <h4 class="text-white mb-4 border-bottom border-secondary pb-2"><i class="fa-solid fa-users-gear"></i> Équipe Actuelle</h4>

            <?php if(empty($employes)): ?>
                <p class="text-muted fst-italic">Aucun employé créé pour le moment</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Nom / Prénom</th>
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
                                        <?php if($emp['statut_compte'] === 'actif'): ?>
                                            <span class="badge-status badge-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-waiting border-danger text-danger bg-transparent">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($emp['statut_compte'] === 'actif'): ?>
                                            <a href="?action_statut=desactiver&id_employe=<?php echo $emp['id_utilisateur']; ?>"
                                               class="btn-action-small btn-outline text-danger border-danger"
                                               onclick="return confirm('Rendre ce compte inutilisable ?');">Désactiver</a>
                                        <?php else: ?>
                                            <a href="?action_statut=activer&id_employe=<?php echo $emp['id_utilisateur']; ?>"
                                               class="btn-action-small btn-outline text-success border-success">Réactiver</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="mt-5 pt-4 border-top border-secondary">
                <h4 class="text-gold mb-4"><i class="fa-solid fa-chart-pie"></i> Statistiques</h4>
                <div class="alert-waiting text-center p-4 border border-warning rounded" style="background: rgba(245, 158, 11, 0.1);">
                    <p class="text-warning mb-0">La connexion à la base de données est en cours d'installation...</p>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
