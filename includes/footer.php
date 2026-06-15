<?php
require_once __DIR__ . '/classes/ScheduleRepository.php';

$horaires_footer = [];

if (isset($pdo)) {
    try {
        $scheduleRepositoryFooter = new ScheduleRepository($pdo);
        $horaires_footer = $scheduleRepositoryFooter->findAll();
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}

$horaires_footer_gauche = array_slice($horaires_footer, 0, 3);
$horaires_footer_droite = array_slice($horaires_footer, 3);
?>
</main>
    <footer class="footer mt-auto">
      <div class="footer-content">
        <div class="footer-section brand">
          <h3>Vite & Gourmand</h3>
          <p>L'excellence culinaire à votre service à Bordeaux depuis 25 ans. Sublimez vos événements avec notre savoir-faire.</p>
        </div>
        <div class="footer-section hours">
          <h3><i class="fa-regular fa-clock"></i> Nos Horaires</h3>
          <div class="footer-hours-grid">
            <?php if (!empty($horaires_footer)): ?>
                <ul>
                <?php foreach($horaires_footer_gauche as $h): ?>
                        <li>
                          <span><?php echo htmlspecialchars($h['jour']); ?>:</span>
                          <?php echo htmlspecialchars($h['heures']); ?>
                        </li>
                <?php endforeach; ?>
                </ul>
                <ul>
                <?php foreach($horaires_footer_droite as $h): ?>
                        <li>
                          <span><?php echo htmlspecialchars($h['jour']); ?>:</span>
                          <?php echo htmlspecialchars($h['heures']); ?>
                        </li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <ul>
                  <li><span>Lundi:</span> 08:00 - 19:00</li>
                  <li><span>Mardi:</span> 08:00 - 19:00</li>
                  <li><span>Mercredi:</span> 08:00 - 19:00</li>
                </ul>
                <ul>
                  <li><span>Jeudi:</span> 08:00 - 19:00</li>
                  <li><span>Vendredi:</span> 08:00 - 19:00</li>
                  <li><span>Samedi:</span> 09:00 - 18:00</li>
                  <li><span>Dimanche:</span> 09:00 - 14:00</li>
                </ul>
            <?php endif; ?>
          </div>
        </div>
        <div class="footer-section links">
          <h3><i class="fa-solid fa-link"></i> Liens Utiles</h3>
          <ul>
            <li><a href="mentions">Mentions Légales</a></li>
            <li><a href="cgv">Conditions Générales de Vente</a></li>
            <li><a href="confidentialite">Politique de confidentialité</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> Vite & Gourmand. Tous droits réservés.</p>
      </div>
    </footer>
  </div>

  <script src="assets/js/app.js" defer></script>
</body>
</html>
