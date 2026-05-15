<?php
require_once 'includes/db.php';
include 'includes/header.php'; ?>

<section class="hero">
  <div class="hero-content">
    <h1>Vite & <span class="highlight">Gourmand</span></h1>
    <p>L'art de la gastronomie pour vos événements. Sublimez vos moments précieux avec nos créations culinaires d'exception depuis plus de 25 ans.</p>
    <a href="menus.php" class="btn-primary" style="text-decoration:none;">Découvrir la Carte</a>
  </div>
</section>

<section class="section">
  <h2 class="section-title">Notre Savoir-Faire</h2>
  <div class="about-grid">
    <div class="feature-card">
      <i class="fa-solid fa-award feature-icon"></i>
      <h3>Excellence</h3>
      <p>Des ingrédients rigoureusement sélectionnés auprès de producteurs locaux pour une qualité irréprochable.</p>
    </div>
    <div class="feature-card">
      <i class="fa-solid fa-leaf feature-icon"></i>
      <h3>Créativité</h3>
      <p>Des menus innovants qui évoluent avec les saisons, alliant tradition française et modernité.</p>
    </div>
    <div class="feature-card">
      <i class="fa-solid fa-handshake feature-icon"></i>
      <h3>Sur-Mesure</h3>
      <p>Un accompagnement personnalisé pour que chaque événement soit unique et à votre image.</p>
    </div>
  </div>
</section>

<section class="section" style="background: rgba(255,255,255,0.02)">
  <h2 class="section-title">Ils Nous Font Confiance</h2>
  <div class="reviews-container">
    <div class="review-card">
      <div class="review-stars">
        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
      </div>
      <p class="review-text">"Une prestation incroyable pour notre mariage. Le menu Prestige était divin, le service impeccable."</p>
      <div class="review-author">
        <div class="review-avatar">M</div>
        <div>
          <strong>Marie D.</strong>
          <div style="font-size:0.8rem;color:var(--text-muted)">Événement Privé</div>
        </div>
      </div>
    </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
