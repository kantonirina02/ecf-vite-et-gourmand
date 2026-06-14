<?php
require_once 'includes/security.php';
require_once 'includes/db.php';
require_once 'includes/classes/UserRepository.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index');
    exit;
}

$message = "";
$userRepository = new UserRepository($pdo);

if (isset($_GET['inscription']) && $_GET['inscription'] === 'success') {
    $message = "<div class='alert-success mb-4'>Inscription réussie. Vous pouvez maintenant vous connecter.</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password_clair = $_POST['mot_de_passe'] ?? '';

    if (login_is_blocked($pdo, $email)) {
        $message = "<div class='alert-error'>Trop de tentatives. Réessayez dans quelques minutes.</div>";
    } else {
        $user = $userRepository->findByEmail($email);

        if (
            $user
            && password_verify($password_clair, $user['mot_de_passe'])
            && ($user['role'] !== 'employe' || ($user['statut_compte'] ?? 'actif') === 'actif')
        ) {
            session_regenerate_id(true);
            clear_login_attempts($pdo, $email);
            $_SESSION['user_id'] = $user['id_utilisateur'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['prenom'] = $user['prenom'];

            header('Location: index');
            exit;
        }

        record_login_failure($pdo, $email);
        $message = "<div class='alert-error'>Identifiants incorrects.</div>";
    }
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="glass-panel p-4 p-md-5 form-container">
        <h2 class="logo-font text-center mb-4 text-white auth-title">Connexion</h2>

        <?php if(!empty($message)) echo $message; ?>

        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <label class="form-label" for="email">Adresse Email</label>
            <input id="email" type="email" name="email" class="form-control" autocomplete="email" required>

            <label class="form-label" for="mot_de_passe">Mot de passe</label>
            <input id="mot_de_passe" type="password" name="mot_de_passe" class="form-control" autocomplete="current-password" required>

            <button type="submit" class="btn-primary w-100 border-0 mt-2">Se connecter</button>
        </form>

        <div class="text-center mt-4 pt-3 border-top border-secondary">
            <p class="text-muted mb-2">Mot de passe oublié ? <a href="mot_de_passe_oublie" class="text-gold text-decoration-none">Réinitialiser</a></p>
            <p class="text-muted">Pas encore de compte ? <a href="register" class="text-gold text-decoration-none">S'inscrire</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
