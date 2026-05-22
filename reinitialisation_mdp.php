<?php
require_once 'includes/security.php';
require_once 'includes/db.php';

$message = "";

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_reset (
            id_reset INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_password_reset_token (email, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
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
    $req = $pdo->prepare("
        SELECT id_reset, token_hash, expires_at, used_at
        FROM password_reset
        WHERE email = ?
        ORDER BY id_reset DESC
        LIMIT 1
    ");
    $req->execute([$email]);
    $reset = $req->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log($e->getMessage());
    $reset = false;
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

        $update = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE email = ?");
        if ($update->execute([$hash, $email])) {
            $pdo->prepare("UPDATE password_reset SET used_at = NOW() WHERE id_reset = ?")->execute([$reset['id_reset']]);
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
