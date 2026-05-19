<?php
require_once 'includes/db.php';
include 'includes/header.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $gsm = $_POST['gsm'];
    $adresse_postale = $_POST['adresse_postale'];
    $password_clair = $_POST['mot_de_passe'];

    // verification du domaine de l'email
    $domaine_email = substr(strrchr($email, "@"), 1);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !checkdnsrr($domaine_email, "MX")) {
        $message = "<div class='alert-error'>L'adresse email fournie semble invalide ou appartient à un domaine inexistant (ex: @test.com).</div>";

    // On vérifie les critères si l'email est bon
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{10,}$/', $password_clair)) {
        $message = "<div class='alert-error'>Le mot de passe ne respecte pas les critères de sécurité (10 caractères min, 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial).</div>";

    } else {
        // Hachage du mot de passe
        $hash = password_hash($password_clair, PASSWORD_DEFAULT);

        try {
            // Utilisation BDD
            $insert = $pdo->prepare("INSERT INTO utilisateur (nom, prenom, email, gsm, adresse_postale, mot_de_passe, role) VALUES (?, ?, ?, ?, ?, ?, 'utilisateur')");
            $insert->execute([$nom, $prenom, $email, $gsm, $adresse_postale, $hash]);

            // Redirection vers la page de connexion avec un message de succès
            header('Location: login?inscription=success');
            exit;

        } catch (PDOException $e) {
            $message = "<div class='alert-error'>Erreur : Cet email est déjà utilisé pour un autre compte.</div>";
        }
    }
}
?>

<div class="container py-5 mt-5">
    <div class="glass-panel p-4 p-md-5 form-container-large">
        <h2 class="logo-font text-center mb-4 text-white" style="font-size: 2.5rem;">Créer un compte</h2>

        <?php if(!empty($message)) echo $message; ?>

        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Nom</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prénom</label>
                    <input type="text" name="prenom" class="form-control" required>
                </div>
            </div>

            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>

            <label class="form-label">Numéro</label>
            <input type="text" name="gsm" class="form-control" required>

            <label class="form-label">Adresse postale</label>
            <textarea name="adresse_postale" class="form-control" required></textarea>

            <label class="form-label">Mot de passe</label>
            <input type="password" name="mot_de_passe" class="form-control" required>
            <p class="text-muted small mb-4">Min. 10 caractères, 1 Majuscule, 1 Chiffre, 1 Caractère spécial (@$!%*?&).</p>

            <button type="submit" class="btn-primary w-100 border-0">S'inscrire</button>
        </form>

        <div class="text-center mt-4 pt-3 border-top border-secondary">
            <p class="text-muted">Déjà un compte ? <a href="login" class="text-gold text-decoration-none">Connectez-vous</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
