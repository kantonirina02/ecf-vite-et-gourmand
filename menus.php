<?php
require_once 'includes/db.php';
include 'includes/header.php';

// On récupère tous les menus
$requete = $pdo->query("SELECT * FROM menu ORDER BY id_menu ASC");
$menusPHP = $requete->fetchAll(PDO::FETCH_ASSOC);

// Conversion pour le JavaScript
$menusJS = json_encode($menusPHP);
?>

<div style="background: var(--bg-darker); padding: 4rem 2rem 2rem; text-align:center;">
  <h1 class="section-title" style="margin-bottom:0">Notre Carte</h1>
  <p style="color:var(--text-muted); max-width:600px; margin:2rem auto 0;">
    Découvrez l'ensemble de nos créations culinaires. Utilisez les filtres pour trouver le menu parfaitement adapté à vos envies et à vos convives.
  </p>
</div>

<div class="menus-layout">
  <aside class="filters-panel glass-panel">
    <h3 style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem;">
      <i class="fa-solid fa-filter"></i> Filtres
    </h3>

    <div class="filter-group">
      <label style="color: var(--gold-primary); margin-bottom: 0.5rem; display:block; font-weight: 500;">Fourchette de prix</label>

      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <span class="text-secondary small">Prix Min.</span>
        <input type="number" id="filter-price-min" min="0" value="0" style="width: 80px; padding: 0.4rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; border-radius: 4px;">
      </div>

      <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
        <span class="text-secondary small">Prix Max. (<span id="price-val">200</span>€)</span>
      </div>
      <input type="range" id="filter-price-max" min="0" max="300" value="200" step="5" style="width: 100%;">
    </div>

    <div class="filter-group">
      <label>Thème</label>
      <select id="filter-theme">
        <option value="">Tous les thèmes</option>
        <option value="Noel">Noël</option>
        <option value="Pâques">Pâques</option>
        <option value="Classique">Classique</option>
      </select>
    </div>

    <div class="filter-group">
      <label>Régime Alimentaire</label>
      <select id="filter-regime">
        <option value="">Tous les régimes</option>
        <option value="classique">Classique</option>
        <option value="vegetarien">Végétarien</option>
        <option value="vegan">Vegan</option>
      </select>
    </div>

    <div class="filter-group">
      <label>Personnes Min.</label>
      <input type="number" id="filter-min-people" min="1" placeholder="Ex: 5">
    </div>
  </aside>

  <div class="menus-grid" id="menus-container"></div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
const menus = <?php echo $menusJS; ?>;
const container = document.getElementById('menus-container');

function updateMenus() {
  const minPrice = parseInt(document.getElementById('filter-price-min').value) || 0;
  const maxPrice = parseInt(document.getElementById('filter-price-max').value) || Infinity;
  const theme = document.getElementById('filter-theme').value.toLowerCase();
  const regime = document.getElementById('filter-regime').value.toLowerCase();
  const minPeople = parseInt(document.getElementById('filter-min-people').value) || 0;

  const filtered = menus.filter(m => {
    const matchPrice = parseFloat(m.prix_min) >= minPrice && parseFloat(m.prix_min) <= maxPrice;
    const matchTheme = theme === '' || m.theme.toLowerCase() === theme;
    const matchRegime = regime === '' || m.regime.toLowerCase() === regime;

    const matchPeople = minPeople === 0 || parseInt(m.nb_personnes_min) >= minPeople;

    return matchPrice && matchTheme && matchRegime && matchPeople;
  });

  if (filtered.length === 0) {
    container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding: 4rem; background: var(--card-bg); border-radius:16px;">Aucun menu ne correspond à vos critères.</div>';
    return;
  }

  container.innerHTML = filtered.map(m => `
    <div class="menu-card">
      <img src="assets/images/${m.image}" alt="${m.titre}" class="menu-img">
      <div class="menu-content">
        <span class="menu-tag">${m.theme}</span>
        <h3 class="menu-title">${m.titre}</h3>
        <p class="menu-desc">${m.description.substring(0, 100)}...</p>
        <div class="menu-meta">
          <span><i class="fa-solid fa-users"></i> Min. ${m.nb_personnes_min}</span>
          <span class="menu-price">${m.prix_min}€ / pers.</span>
        </div>
        <a href="menu_detail.php?id=${m.id_menu}" class="btn-outline" style="text-align:center; display:block;">Découvrir le Menu</a>
      </div>
    </div>
  `).join('');
}

document.getElementById('filter-price-min').addEventListener('input', updateMenus);
document.getElementById('filter-price-max').addEventListener('input', (e) => {
  document.getElementById('price-val').textContent = e.target.value;
  updateMenus();
});
document.getElementById('filter-theme').addEventListener('change', updateMenus);
document.getElementById('filter-regime').addEventListener('change', updateMenus);
document.getElementById('filter-min-people').addEventListener('input', updateMenus);

updateMenus();
</script>
