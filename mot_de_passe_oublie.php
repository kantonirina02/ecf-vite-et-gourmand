<?php
session_start();
require_once 'includes/db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // verification de l'email si ca existe dans la base de données
    $verif = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
    $verif->execute([$email]);

    if ($verif->rowCount() > 0) {
        // si l'email existe, on simule l'envoi d'un mail avec un faux "token" de sécurité
        $token_simule = base64_encode($email);
        $lien_reset = "reinitialisation_mdp.php?token=" . $token_simule;

        // on affiche le lien directement à l'écran
        $message = "<div class='alert-success mb-4'>
                        Un email de réinitialisation vous a été envoyé. <br>
                        <hr class='my-2 border-success'>
                        <em></em><a href='$lien_reset' class='fw-bold text-success'>Cliquez ici pour réinitialiser le mot de passe</a>
                    </div>";
    } else {
        $message = "<div class='alert-success mb-4'>Si cet email existe dans notre système, un lien de réinitialisation vient de vous être envoyé.</div>";
    }
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="glass-panel p-5">
                <div class="text-center mb-4">
                    <h3 class="logo-font text-gold">Mot de passe oublié</h3>
                    <p class="text-white small">Entrez votre adresse email pour recevoir un lien de réinitialisation.</p>
                </div>

                <?php if(!empty($message)) echo $message; ?>

                <form method="POST" action="">
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
