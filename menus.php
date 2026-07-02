<?php
require_once 'includes/db.php';
include 'includes/header.php';
?>

<div class="menus-hero">
  <h1 class="section-title section-title-compact">Notre Carte</h1>
  <p class="menus-hero-text">
    Découvrez l'ensemble de nos créations culinaires. Utilisez les filtres pour trouver le menu parfaitement adapté à vos envies et à vos convives.
  </p>
</div>

<div class="menus-layout">
  <aside class="filters-panel glass-panel">
    <h3 class="filters-title">
      <i class="fa-solid fa-filter"></i> Filtres
    </h3>

    <div class="filter-group">
      <label class="filter-highlight-label">Fourchette de prix</label>

      <div class="filter-inline-row filter-inline-row-center">
        <span class="text-secondary small">Prix Min.</span>
        <input type="number" id="filter-price-min" class="filter-price-input" min="0" value="0">
      </div>

      <div class="filter-inline-row">
        <span class="text-secondary small">Prix Max. (<span id="price-val">200</span>€)</span>
      </div>
      <input type="range" id="filter-price-max" min="0" max="300" value="200" step="5">
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
