<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    <nav class="navbar">
      <div class="nav-container">
        <div class="logo">
          <a href="index.php">
            <span class="logo-vite">Vite</span><span class="logo-amp">&</span><span class="logo-gourmand">Gourmand</span>
          </a>
        </div>

        <ul class="nav-links">
          <li><a href="index.php"><i class="fa-solid fa-house"></i> Accueil</a></li>
          <li><a href="menus.php"><i class="fa-solid fa-utensils"></i> Nos Menus</a></li>
          <li><a href="contact.php"><i class="fa-solid fa-envelope"></i> Contact</a></li>
        </ul>

        <div class="nav-actions" style="display: flex; align-items: center; gap: 1rem;">
          <?php if(isset($_SESSION['user_id'])): ?>

              <a href="espace_utilisateur.php" class="btn-login" style="text-decoration: none;">
                  <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($_SESSION['prenom']); ?>
              </a>
              <a href="logout.php" class="text-danger" title="Se déconnecter" style="font-size: 1.3rem; text-decoration: none;">
                  <i class="fa-solid fa-power-off"></i>
              </a>

          <?php else: ?>

              <a href="login.php" class="btn-login" style="text-decoration: none;">
                  <i class="fa-regular fa-user"></i> Connexion
              </a>

          <?php endif; ?>
        </div>

        <button class="mobile-menu-btn"><i class="fa-solid fa-bars"></i></button>
      </div>
    </nav>
    <main id="main-content">
