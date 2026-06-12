<?php
require_once 'includes/db.php';
include 'includes/header.php';
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

  <div class="menus-grid" id="menus-container" data-api-url="api/menus.php"></div>
</div>

<?php include 'includes/footer.php'; ?>
