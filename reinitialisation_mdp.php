<?php
session_start();
require_once 'includes/db.php';

$message = "";

// récuperation et décode l'email caché dans le lien
if (!isset($_GET['token'])) {
    header('Location: /');
    exit;
}

$email_decode = base64_decode($_GET['token']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nouveau_mdp = $_POST['nouveau_mdp'];

    $regex_mdp = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/';

    if (!preg_match($regex_mdp, $nouveau_mdp)) {
        $message = "<div class='alert-error mb-4'>Le mot de passe doit contenir au moins 10 caractères, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.</div>";
    } else {
        // hachage du nouveau mot de passe
        $hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);

        // mise à jour dans la base de données
        $update = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE email = ?");
        if ($update->execute([$hash, $email_decode])) {
            $message = "<div class='alert-success mb-4'>Votre mot de passe a été mis à jour avec succès ! <br><a href='login' class='fw-bold text-success'>Cliquez ici pour vous connecter</a></div>";
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
                    <p class="text-white small">Choisissez un nouveau mot de passe : <br><strong class="text-warning"><?php echo htmlspecialchars($email_decode); ?></strong></p>
                </div>

                <?php if(!empty($message)) echo $message; ?>

                <form method="POST" action="">
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
