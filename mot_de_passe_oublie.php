<?php
require_once 'includes/security.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';
require_once 'includes/classes/PasswordResetRepository.php';
require_once 'includes/classes/UserRepository.php';

$message = "";
$passwordResetRepository = new PasswordResetRepository($pdo);
$userRepository = new UserRepository($pdo);

function current_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');

    return $scheme . '://' . $host . ($path === '' ? '' : $path);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email']);
    $message = "<div class='alert-success mb-4'>Si cet email existe dans notre systeme, un lien de reinitialisation vient de vous etre envoye.</div>";
    $lastResetRequest = (int) ($_SESSION['last_reset_request'] ?? 0);

    if ($lastResetRequest > time() - 60) {
        $message = "<div class='alert-success mb-4'>Si cet email existe dans notre systeme, un lien de reinitialisation vient de vous etre envoye.</div>";
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['last_reset_request'] = time();
        $user = $userRepository->findByEmail($email);

        if ($user) {
            try {
                $passwordResetRepository->ensureTable();

                $token = bin2hex(random_bytes(32));
                $tokenHash = password_hash($token, PASSWORD_DEFAULT);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $passwordResetRepository->markOpenRequestsAsUsed($email);
                $passwordResetRepository->create($email, $tokenHash, $expiresAt);

                $resetLink = current_base_url() . '/reinitialisation_mdp?token=' . urlencode($token) . '&email=' . urlencode($email);
                $body = "Bonjour " . ($user['prenom'] ?: '') . ",\n\n";
                $body .= "Cliquez sur ce lien pour reinitialiser votre mot de passe :\n$resetLink\n\n";
                $body .= "Ce lien expire dans 1 heure.\n\nL'equipe Vite & Gourmand.";

                send_app_email($email, "Reinitialisation de votre mot de passe", $body);
            } catch (Throwable $e) {
                error_log($e->getMessage());
            }
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
                    <h3 class="logo-font text-gold">Mot de passe oublie</h3>
                    <p class="text-white small">Entrez votre adresse email pour recevoir un lien de reinitialisation.</p>
                </div>

                <?php if(!empty($message)) echo $message; ?>

                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <div class="mb-4">
                        <label class="form-label text-warning">Votre Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn-primary w-100 border-0">Recevoir le lien</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
