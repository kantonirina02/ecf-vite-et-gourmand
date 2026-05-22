</main>
    <footer class="footer mt-auto">
      <div class="footer-content">
        <div class="footer-section brand">
          <h3>Vite & Gourmand</h3>
          <p>L'excellence culinaire à votre service à Bordeaux depuis 25 ans. Sublimez vos événements avec notre savoir-faire.</p>
        </div>
        <div class="footer-section hours">
          <h3><i class="fa-regular fa-clock"></i> Nos Horaires</h3>
          <ul>
            <?php
            // verification de la connexion à la BDD
            if (isset($pdo)) {
                $req_horaires_footer = $pdo->query("SELECT * FROM horaire");
                if ($req_horaires_footer) {
                    $horaires_footer = $req_horaires_footer->fetchAll(PDO::FETCH_ASSOC);

                    foreach($horaires_footer as $h):
            ?>
                        <li>
                          <span><?php echo htmlspecialchars($h['jour']); ?>:</span>
                          <?php echo htmlspecialchars($h['heures']); ?>
                        </li>
            <?php
                    endforeach;
                }
            } else {
                // Secours au cas où $pdo n'est pas chargé sur une page
                echo '<li><span>Lundi - Vendredi:</span> 08:00 - 19:00</li>';
                echo '<li><span>Samedi:</span> 09:00 - 18:00</li>';
                echo '<li><span>Dimanche:</span> 09:00 - 14:00</li>';
            }
            ?>
          </ul>
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

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const btnBurger = document.querySelector('.mobile-menu-btn');
      const navLinks = document.querySelector('.nav-links');
      const navActions = document.querySelector('.nav-actions');

      if(btnBurger && navLinks) {
          btnBurger.addEventListener('click', () => {
              navLinks.classList.toggle('active');
              if(navActions) navActions.classList.toggle('active');
              btnBurger.setAttribute('aria-expanded', navLinks.classList.contains('active') ? 'true' : 'false');
          });
      }
    });
  </script>
</body>
</html>
