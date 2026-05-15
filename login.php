<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = "";

// Affichage d'un message de succès après inscription
if (isset($_GET['inscription']) && $_GET['inscription'] === 'success') {
    $message = "<div class='alert-success mb-4'>Inscription réussie ! Vous pouvez maintenant vous connecter.</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password_clair = $_POST['mot_de_passe'];

    $requete = $pdo->prepare("SELECT * FROM utilisateur WHERE email = ?");
    $requete->execute([$email]);
    $user = $requete->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password_clair, $user['mot_de_passe'])) {
        $_SESSION['user_id'] = $user['id_utilisateur'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['prenom'] = $user['prenom'];

        header('Location: index.php');
        exit;
    } else {
        $message = "<div class='alert-error'>Identifiants incorrects.</div>";
    }
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="glass-panel p-4 p-md-5 form-container">
        <h2 class="logo-font text-center mb-4 text-white" style="font-size: 2.5rem;">Connexion</h2>

        <?php if(!empty($message)) echo $message; ?>

        <form method="POST" action="">
            <label class="form-label">Adresse Email</label>
            <input type="email" name="email" class="form-control" required>

            <label class="form-label">Mot de passe</label>
            <input type="password" name="mot_de_passe" class="form-control" required>

            <button type="submit" class="btn-primary w-100 border-0 mt-2">Se connecter</button>
        </form>

        <div class="text-center mt-4 pt-3 border-top border-secondary">
            <p class="text-muted mb-2">Mot de passe oublié ? <a href="#" class="text-gold text-decoration-none">Réinitialiser</a></p>
            <p class="text-muted">Pas encore de compte ? <a href="register.php" class="text-gold text-decoration-none">S'inscrire</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
