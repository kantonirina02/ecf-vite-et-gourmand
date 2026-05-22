<?php
require_once 'includes/security.php';
require_once 'includes/db.php';
include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="glass-panel p-5">
        <h1 class="logo-font text-gold mb-5 text-center">Politique de confidentialité</h1>

        <div class="text-white">
            <h4 class="text-warning mt-4">Données collectées</h4>
            <p>Vite & Gourmand collecte les informations nécessaires à la création de compte, à la commande et au suivi client : nom, prénom, email, téléphone, adresse postale, commandes, avis et messages de contact.</p>

            <h4 class="text-warning mt-4">Finalités</h4>
            <p>Ces données servent uniquement à gérer les comptes, traiter les commandes, envoyer les confirmations, assurer le suivi des prestations, modérer les avis et répondre aux demandes de contact.</p>

            <h4 class="text-warning mt-4">Base légale</h4>
            <p>Le traitement repose sur l'exécution du service demandé par le client, le respect des obligations légales de l'entreprise et le consentement pour les messages envoyés volontairement.</p>

            <h4 class="text-warning mt-4">Durée de conservation</h4>
            <p>Les comptes et commandes sont conservés pendant la durée nécessaire au suivi commercial et comptable. Les demandes de contact et journaux techniques sont conservés pour une durée limitée au besoin de traitement.</p>

            <h4 class="text-warning mt-4">Sécurité</h4>
            <p>Les mots de passe sont hachés, les formulaires sensibles utilisent une protection CSRF, les accès sont limités par rôle, et les statistiques non relationnelles ne contiennent que des données de commande agrégées par menu.</p>

            <h4 class="text-warning mt-4">Vos droits</h4>
            <p>Vous pouvez demander l'accès, la rectification ou la suppression de vos données en contactant l'entreprise via la page contact. Une vérification d'identité pourra être demandée avant toute suppression.</p>

            <h4 class="text-warning mt-4">Responsable du traitement</h4>
            <p>Vite & Gourmand, traiteur événementiel situé à Bordeaux. Contact : <a href="contact" class="text-gold">formulaire de contact</a>.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
