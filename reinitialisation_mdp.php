<?php
require_once 'includes/security.php';
require_once 'includes/db.php';
require_once 'includes/classes/PasswordResetRepository.php';
require_once 'includes/classes/UserRepository.php';

$message = "";
$passwordResetRepository = new PasswordResetRepository($pdo);
$userRepository = new UserRepository($pdo);

try {
    $passwordResetRepository->ensureTable();
} catch (Throwable $e) {
    error_log($e->getMessage());
}

if (!isset($_GET['token'], $_GET['email'])) {
    header('Location: index');
    exit;
}

$token = $_GET['token'];
$email = trim($_GET['email']);

try {
    $reset = $passwordResetRepository->findLatestByEmail($email);
} catch (Throwable $e) {
    error_log($e->getMessage());
    $reset = null;
}

if (
    !$reset
    || !empty($reset['used_at'])
    || strtotime($reset['expires_at']) < time()
    || !password_verify($token, $reset['token_hash'])
) {
    http_response_code(403);
    exit("Lien de reinitialisation invalide ou expire.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nouveau_mdp = $_POST['nouveau_mdp'];
    $regex_mdp = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/';

    if (!preg_match($regex_mdp, $nouveau_mdp)) {
        $message = "<div class='alert-error mb-4'>Le mot de passe doit contenir au moins 10 caracteres, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractere special.</div>";
    } else {
        $hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);

        if ($userRepository->updatePasswordByEmail($email, $hash)) {
            $passwordResetRepository->markUsed((int) $reset['id_reset']);
            $message = "<div class='alert-success mb-4'>Votre mot de passe a ete mis a jour avec succes ! <br><a href='login' class='fw-bold text-success'>Cliquez ici pour vous connecter</a></div>";
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="glass-panel p-5">
                <div class="text-center mb-4">
                    <h3 class="logo-font text-gold">Nouveau Mot de Passe</h3>
                    <p class="text-white small">Choisissez un nouveau mot de passe pour : <br><strong class="text-warning"><?php echo htmlspecialchars($email); ?></strong></p>
                </div>

                <?php if(!empty($message)) echo $message; ?>

                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <div class="mb-4">
                        <label class="form-label text-warning">Nouveau mot de passe</label>
                        <input type="password" name="nouveau_mdp" class="form-control" required>
                    </div>
                    <button type="submit" class="btn-primary w-100 border-0">Valider</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
