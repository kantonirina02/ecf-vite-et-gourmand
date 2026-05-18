<?php
session_start();
require_once 'includes/db.php';
include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="glass-panel p-5">
        <h1 class="logo-font text-gold mb-5 text-center">Conditions Générales de Vente (CGV)</h1>

        <div class="text-white">
            <h4 class="text-warning mt-4">Article 1 : Commandes</h4>
            <p>Toute commande doit respecter le nombre minimum de personnes indiqué sur le menu choisi. Une réduction de 10% est appliquée automatiquement pour toute commande dépassant de 5 personnes le minimum requis.</p>

            <h4 class="text-warning mt-4">Article 2 : Livraison</h4>
            <p>Les livraisons s'effectuent à l'adresse indiquée par le client. Si la livraison a lieu en dehors de la ville de Bordeaux, un forfait de 5,00 € sera appliqué, majoré de 0,59 € par kilomètre parcouru.</p>

            <h4 class="text-warning mt-4">Article 3 : Prêt de matériel et Pénalités</h4>
            <p>Dans le cas où du matériel vous est prêté lors de la prestation, celui-ci doit être restitué en bon état. <strong>Si, sous 10 jours ouvrés après la livraison, le matériel n'est pas restitué, le client devra s'acquitter d'une pénalité forfaitaire de 600 euros.</strong></p>

            <h4 class="text-warning mt-4">Article 4 : Annulation</h4>
            <p>Le client peut annuler sa commande depuis son Espace Personnel tant que celle-ci n'a pas le statut "Accepté" par notre équipe. Au-delà, l'annulation nécessite une prise de contact directe avec l'entreprise.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
