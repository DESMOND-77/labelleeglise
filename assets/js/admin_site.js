/* Back-office « Site public » : améliorations progressives.
   Tout fonctionne sans JavaScript (recherche/filtrage, ajout de
   FAQ, aperçu des images sont des bonus). */
(function () {
  'use strict';

  /* ---- Recherche et filtre de la liste des centres ---- */
  var search = document.getElementById('centres-search');
  var filter = document.getElementById('centres-filter');
  var count = document.getElementById('centres-count');
  var empty = document.getElementById('centres-empty');

  function applyFilters() {
    var q = (search && search.value || '').trim().toLowerCase();
    var mode = filter ? filter.value : 'all';
    var rows = document.querySelectorAll('[data-centres-table] tbody tr, [data-centres-cards] .mini-card');
    var visible = 0;
    rows.forEach(function (row) {
      var name = (row.getAttribute('data-name') || '').toLowerCase();
      var publie = row.getAttribute('data-publie') === '1';
      var complete = row.getAttribute('data-complete') === '1';
      var okQ = q === '' || name.indexOf(q) !== -1;
      var okMode = mode === 'all'
        || (mode === 'publie' && publie)
        || (mode === 'brouillon' && !publie)
        || (mode === 'incomplet' && !complete);
      var show = okQ && okMode;
      row.style.display = show ? '' : 'none';
      if (show) { visible++; }
    });
    if (count) {
      count.textContent = visible === rows.length ? '' : visible + ' / ' + rows.length + ' affiché' + (visible > 1 ? 's' : '');
    }
    if (empty) { empty.hidden = visible !== 0; }
  }

  if (search) { search.addEventListener('input', applyFilters); }
  if (filter) { filter.addEventListener('change', applyFilters); }

  /* ---- FAQ : ajout dynamique de questions ---- */
  var faqAdd = document.getElementById('faq-add');
  var faqList = document.getElementById('faq-list');
  var faqTemplate = document.getElementById('faq-row-template');
  var faqCounter = 0;
  if (faqAdd && faqList && faqTemplate) {
    faqAdd.addEventListener('click', function () {
      faqCounter++;
      var clone = faqTemplate.content.firstElementChild;
      if (!clone) { return; }
      var idx = document.querySelectorAll('.faq-item-admin').length + 1;
      clone.querySelector('legend').innerHTML = 'Nouvelle question <span class="faq-index">(' + idx + ')</span>';
      // Nom unique pour le checkbox de publication (sinon « nouvelle »).
      var box = clone.querySelector('input[type="checkbox"]');
      if (box) { box.value = 'new' + faqCounter; }
      var inputs = clone.querySelectorAll('input[type="text"], textarea');
      inputs.forEach(function (el) { el.name = el.name.replace('[]', '[new' + faqCounter + ']'); });
      faqList.appendChild(clone);
      var first = clone.querySelector('input[type="text"]');
      if (first) { first.focus(); }
    });
  }

  /* ---- Aperçu de l'image d'accueil ---- */
  var heroInput = document.querySelector('input[name="hero_image"]');
  var heroPreview = document.getElementById('hero-preview');
  if (heroInput && heroPreview) {
    heroInput.addEventListener('change', function () {
      var file = heroInput.files && heroInput.files[0];
      if (!file) { heroPreview.hidden = true; return; }
      var reader = new FileReader();
      reader.onload = function () {
        heroPreview.src = reader.result;
        heroPreview.hidden = false;
      };
      reader.readAsDataURL(file);
    });
  }

  /* ---- Avertissement de saisie non enregistrée ---- */
  document.querySelectorAll('form[data-track-dirty]').forEach(function (form) {
    form.addEventListener('input', function () {
      form.dataset.dirty = '1';
    });
    form.addEventListener('submit', function () {
      delete form.dataset.dirty;
    });
  });
  window.addEventListener('beforeunload', function (e) {
    var dirty = document.querySelector('form[data-track-dirty][data-dirty="1"]');
    if (dirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  /* ---- Retour du focus après sauvegarde (accessibilité) ---- */
  var status = document.getElementById('save-status');
  if (status) {
    status.setAttribute('tabindex', '-1');
    status.focus({ preventScroll: true });
  }
})();
