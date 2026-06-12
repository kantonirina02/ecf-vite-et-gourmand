class MobileNavigation {
  constructor(buttonSelector, linksSelector, actionsSelector) {
    this.button = document.querySelector(buttonSelector);
    this.links = document.querySelector(linksSelector);
    this.actions = document.querySelector(actionsSelector);
  }

  init() {
    if (!this.button || !this.links) {
      return;
    }

    this.button.addEventListener('click', () => {
      this.toggle();
    });
  }

  toggle() {
    const isOpen = this.links.classList.toggle('active');

    if (this.actions) {
      this.actions.classList.toggle('active', isOpen);
    }

    this.button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }
}

// Outils front communs utilises par les composants qui injectent du HTML.
class HtmlUtils {
  static escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    }[char]));
  }

  static safeFileName(value) {
    return String(value ?? '').split(/[\\/]/).pop();
  }
}

// Charge les menus depuis api/menus.php avec fetch, puis applique les filtres cote navigateur.
class MenuFilter {
  constructor(containerSelector) {
    this.container = document.querySelector(containerSelector);
    this.menus = [];
    this.controls = {
      minPrice: document.getElementById('filter-price-min'),
      maxPrice: document.getElementById('filter-price-max'),
      priceValue: document.getElementById('price-val'),
      theme: document.getElementById('filter-theme'),
      regime: document.getElementById('filter-regime'),
      minPeople: document.getElementById('filter-min-people'),
    };
  }

  async init() {
    if (!this.container) {
      return;
    }

    this.bindEvents();
    await this.loadMenus();
  }

  bindEvents() {
    this.controls.minPrice?.addEventListener('input', () => this.render());
    this.controls.theme?.addEventListener('change', () => this.render());
    this.controls.regime?.addEventListener('change', () => this.render());
    this.controls.minPeople?.addEventListener('input', () => this.render());

    this.controls.maxPrice?.addEventListener('input', () => {
      if (this.controls.priceValue) {
        this.controls.priceValue.textContent = this.controls.maxPrice.value;
      }

      this.render();
    });
  }

  async loadMenus() {
    try {
      this.container.innerHTML = this.loadingTemplate();

      // Appel vers le back-end PHP : api/menus.php renvoie les menus en JSON depuis MySQL.
      const response = await fetch(this.container.dataset.apiUrl || 'api/menus.php', {
        headers: {
          Accept: 'application/json',
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error('Erreur HTTP');
      }

      const payload = await response.json();

      if (!payload.success || !Array.isArray(payload.data)) {
        throw new Error(payload.message || 'Reponse API invalide');
      }

      this.menus = payload.data;
      this.render();
    } catch (error) {
      this.container.innerHTML = this.errorTemplate();
    }
  }

  getFilters() {
    return {
      minPrice: parseInt(this.controls.minPrice?.value, 10) || 0,
      maxPrice: parseInt(this.controls.maxPrice?.value, 10) || Infinity,
      theme: String(this.controls.theme?.value ?? '').toLowerCase(),
      regime: String(this.controls.regime?.value ?? '').toLowerCase(),
      minPeople: parseInt(this.controls.minPeople?.value, 10) || 0,
    };
  }

  getFilteredMenus() {
    const filters = this.getFilters();

    return this.menus.filter((menu) => {
      const price = parseFloat(menu.prix_min) || 0;
      const people = parseInt(menu.nb_personnes_min, 10) || 0;
      const theme = String(menu.theme ?? '').toLowerCase();
      const regime = String(menu.regime ?? '').toLowerCase();

      return price >= filters.minPrice
        && price <= filters.maxPrice
        && (filters.theme === '' || theme === filters.theme)
        && (filters.regime === '' || regime === filters.regime)
        && (filters.minPeople === 0 || people >= filters.minPeople);
    });
  }

  render() {
    const filteredMenus = this.getFilteredMenus();

    if (filteredMenus.length === 0) {
      this.container.innerHTML = this.emptyTemplate();
      return;
    }

    this.container.innerHTML = filteredMenus.map((menu) => this.menuTemplate(menu)).join('');
  }

  menuTemplate(menu) {
    const image = encodeURIComponent(HtmlUtils.safeFileName(menu.image));
    const title = HtmlUtils.escape(menu.titre);
    const description = HtmlUtils.escape(String(menu.description ?? '').substring(0, 100));
    const theme = HtmlUtils.escape(menu.theme);
    const minPeople = HtmlUtils.escape(menu.nb_personnes_min);
    const price = Number(menu.prix_min || 0).toFixed(2);
    const detailUrl = `menu_detail?id=${encodeURIComponent(menu.id_menu)}`;

    return `
      <div class="menu-card">
        <img src="assets/images/${image}" alt="${title}" class="menu-img">
        <div class="menu-content">
          <span class="menu-tag">${theme}</span>
          <h3 class="menu-title">${title}</h3>
          <p class="menu-desc">${description}...</p>
          <div class="menu-meta">
            <span><i class="fa-solid fa-users"></i> Min. ${minPeople}</span>
            <span class="menu-price">${price} EUR / pers.</span>
          </div>
          <a href="${detailUrl}" class="btn-outline" style="text-align:center; display:block;">Découvrir le Menu</a>
        </div>
      </div>
    `;
  }

  loadingTemplate() {
    return '<div style="grid-column: 1/-1; text-align:center; padding: 4rem; background: var(--card-bg); border-radius:16px;">Chargement des menus...</div>';
  }

  emptyTemplate() {
    return '<div style="grid-column: 1/-1; text-align:center; padding: 4rem; background: var(--card-bg); border-radius:16px;">Aucun menu ne correspond à vos critères.</div>';
  }

  errorTemplate() {
    return '<div style="grid-column: 1/-1; text-align:center; padding: 4rem; background: var(--card-bg); border-radius:16px;">Impossible de charger les menus.</div>';
  }
}

// Charge les statistiques depuis api/admin_stats.php, puis construit le graphique Chart.js.
class AdminStatsChart {
  constructor(canvasSelector) {
    this.canvas = document.querySelector(canvasSelector);
    this.chart = null;
  }

  async init() {
    if (!this.canvas || typeof Chart === 'undefined') {
      return;
    }

    await this.loadStats();
  }

  async loadStats() {
    try {
      // Appel admin protege : api/admin_stats.php verifie la session admin avant de renvoyer le JSON.
      const response = await fetch(this.canvas.dataset.apiUrl || 'api/admin_stats.php', {
        headers: {
          Accept: 'application/json',
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error('Erreur HTTP');
      }

      const payload = await response.json();

      if (!payload.success || !payload.data) {
        throw new Error(payload.message || 'Reponse API invalide');
      }

      this.render(payload.data);
    } catch (error) {
      this.renderError();
    }
  }

  render(data) {
    const ctx = this.canvas.getContext('2d');

    this.chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.labels || [],
        datasets: [{
          label: 'Nombre de commandes par menu',
          data: data.commandes || [],
          backgroundColor: 'rgba(245, 158, 11, 0.8)',
          borderColor: 'rgb(245, 158, 11)',
          borderWidth: 1,
          borderRadius: 4,
        }],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
          },
        },
      },
    });
  }

  renderError() {
    const wrapper = this.canvas.parentElement;

    if (wrapper) {
      wrapper.innerHTML = '<div class="text-center text-danger p-4">Impossible de charger le graphique.</div>';
    }
  }
}

class App {
  init() {
    new MobileNavigation('.mobile-menu-btn', '.nav-links', '.nav-actions').init();
    new MenuFilter('#menus-container').init();
    new AdminStatsChart('#graphiqueCommandes').init();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  new App().init();
});
