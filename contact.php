<?php
require_once 'includes/security.php';
require_once 'includes/db.php';
require_once 'includes/mailer.php';

$message_alerte = "";

// traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $titre = clean_text_input($_POST['titre'] ?? '', 150);
    $email = trim($_POST['email']);
    $description = trim($_POST['description'] ?? '');

    // vérification basique
    if (!empty($titre) && !empty($email) && !empty($description) && mb_strlen($description, 'UTF-8') <= 3000 && filter_var($email, FILTER_VALIDATE_EMAIL)) {

        // Préparation de l'email pour l'entreprise
        $destinataire = "contact@viteetgourmand.fr";
        $sujet = "Nouveau message de contact : " . $titre;
        $contenu = "Vous avez reçu un nouveau message de : " . $email . "\n\n" . "Message :\n" . $description;
        $envoi_reussi = send_app_email($destinataire, $sujet, $contenu, $email);

        if ($envoi_reussi) {
            $message_alerte = "<div class='alert-success mb-4'>Votre message a bien été envoyé à notre équipe. Nous vous répondrons dans les plus brefs délais !</div>";
        } else {
            $message_alerte = "<div class='alert-success mb-4'>Merci, votre message a bien été pris en compte. Notre équipe vous répondra dans les plus brefs délais.</div>";
        }
    } else {
        $message_alerte = "<div class='alert-error mb-4'>Veuillez remplir tous les champs correctement avec une adresse email valide.</div>";
    }
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="glass-panel p-5">
                <div class="text-center mb-4">
                    <h2 class="logo-font text-gold"><i class="fa-solid fa-paper-plane"></i> Nous Contacter</h2>
                    <p class="text-white mt-2">Une question sur nos menus ou une demande de devis sur-mesure ? Julie et José sont à votre écoute.</p>
                </div>

                <?php if(!empty($message_alerte)) echo $message_alerte; ?>

                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <div class="mb-4">
                        <label class="form-label text-warning">Votre adresse Email</label>
                        <input type="email" name="email" class="form-control" placeholder="exemple@domaine.com" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-warning">Titre de votre demande</label>
                        <input type="text" name="titre" class="form-control" placeholder="Ex: Devis pour un mariage de 50 personnes" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-warning">Description (Votre message)</label>
                        <textarea name="description" class="form-control textarea-resizable" rows="6" placeholder="Détaillez votre demande ici..." required></textarea>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn-primary border-0 w-100 btn-submit-large">
                            Envoyer le message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
