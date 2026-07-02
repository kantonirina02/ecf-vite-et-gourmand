<?php
require_once __DIR__ . '/security.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Vite & Gourmand - Traiteur d'Exception</title>

  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <div id="app">
    <a class="skip-link" href="#main-content">Aller au contenu principal</a>
    <nav class="navbar" aria-label="Navigation principale">
      <div class="nav-container">
        <div class="logo">
          <a href="index">
            <span class="logo-vite">Vite</span><span class="logo-amp">&</span><span class="logo-gourmand">Gourmand</span>
          </a>
        </div>

        <ul class="nav-links" id="navigation-links">
          <li><a href="index"><i class="fa-solid fa-house"></i> Accueil</a></li>
          <li><a href="menus"><i class="fa-solid fa-utensils"></i> Nos Menus</a></li>
          <li><a href="contact"><i class="fa-solid fa-envelope"></i> Contact</a></li>
        </ul>

        <div class="nav-actions">
          <?php if(isset($_SESSION['user_id'])): ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="espace_admin" class="btn-login btn-login-highlight">
                    <i class="fa-solid fa-crown"></i> Admin (<?php echo htmlspecialchars($_SESSION['prenom']); ?>)
                </a>

            <?php elseif ($_SESSION['role'] === 'employe'): ?>
                <a href="espace_employe" class="btn-login btn-login-highlight">
                    <i class="fa-solid fa-user-tie"></i> Espace Pro (<?php echo htmlspecialchars($_SESSION['prenom']); ?>)
                </a>

            <?php else: ?>
                <a href="espace_utilisateur" class="btn-login">
                    <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($_SESSION['prenom']); ?>
                </a>
            <?php endif; ?>

            <a href="logout" class="text-danger logout-link" title="Se déconnecter">
                <i class="fa-solid fa-power-off"></i>
            </a>

        <?php else: ?>

            <a href="login" class="btn-login">
                <i class="fa-regular fa-user"></i> Connexion
            </a>

        <?php endif; ?>
                </div>

        <button class="mobile-menu-btn" type="button" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="navigation-links">
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
      </div>
    </nav>
    <main id="main-content">
