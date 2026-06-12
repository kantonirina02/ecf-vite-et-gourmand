<?php
$requestPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$route = basename($requestPath);

if ($route !== '' && $route !== 'index' && !str_contains($route, '.') && is_file(__DIR__ . '/' . $route . '.php')) {
    require __DIR__ . '/' . $route . '.php';
    exit;
}

require_once 'includes/db.php';
require_once 'includes/classes/ReviewRepository.php';

$reviewRepository = new ReviewRepository($pdo);
$avis_valides = $reviewRepository->findLatestApprovedForHome(3);

include 'includes/header.php'; ?>

<section class="hero">
  <div class="hero-content">
    <h1>Vite & <span class="highlight">Gourmand</span></h1>
    <p>L'art de la gastronomie pour vos événements. Sublimez vos moments précieux avec nos créations culinaires d'exception depuis plus de 25 ans.</p>
    <a href="menus" class="btn-primary">Découvrir la Carte</a>
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

<section class="reviews-section py-5">
    <div class="container">
        <h2 class="logo-font text-gold text-center mb-5">Ils Nous Font Confiance</h2>

        <div class="row justify-content-center home-reviews-grid">
            <?php if (count($avis_valides) > 0): ?>
                <?php foreach($avis_valides as $avis): ?>
                    <div class="glass-panel p-4 home-review-card">
                        <div class="text-warning mb-3">
                            <?php echo str_repeat('⭐', (int)$avis['note']); ?>
                        </div>
                        <p class="fst-italic text-white mb-4">"<?php echo htmlspecialchars($avis['commentaire']); ?>"</p>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-circle bg-warning text-dark fw-bold rounded-circle d-flex justify-content-center align-items-center home-review-avatar">
                                <?php echo strtoupper(substr($avis['prenom'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong class="text-gold"><?php echo htmlspecialchars($avis['prenom'] . ' ' . $avis['initiale_nom'] . '.'); ?></strong>
                                <br><small class="text-muted">Client(e) vérifié(e)</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted fst-italic w-100">Les avis de nos clients apparaîtront ici prochainement.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
