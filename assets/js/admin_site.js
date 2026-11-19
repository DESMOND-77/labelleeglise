/* Back-office « Site public » : améliorations progressives.
   Tout fonctionne sans JavaScript (recherche/filtrage, ajout de
   FAQ, aperçu des images sont des bonus). */
(function () {
  'use strict';

  /* ---- Recherche et filtre de la liste des centres ---- */
  const search = document.getElementById('centres-search');
  const filter = document.getElementById('centres-filter');
  const count = document.getElementById('centres-count');
  const empty = document.getElementById('centres-empty');

  function applyFilters() {
    const q = (search && search.value || '').trim().toLowerCase();
    const mode = filter ? filter.value : 'all';
    const rows = document.querySelectorAll('[data-centres-table] tbody tr, [data-centres-cards] .mini-card');
    let visible = 0;
    rows.forEach(function (row) {
      const name = (row.getAttribute('data-name') || '').toLowerCase();
      const publie = row.getAttribute('data-publie') === '1';
      const complete = row.getAttribute('data-complete') === '1';
      const okQ = q === '' || name.indexOf(q) !== -1;
      const okMode = mode === 'all'
        || (mode === 'publie' && publie)
        || (mode === 'brouillon' && !publie)
        || (mode === 'incomplet' && !complete);
      const show = okQ && okMode;
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
  const faqAdd = document.getElementById('faq-add');
  const faqList = document.getElementById('faq-list');
  const faqTemplate = document.getElementById('faq-row-template');
  let faqCounter = 0;
  if (faqAdd && faqList && faqTemplate && faqTemplate.content) {
    faqAdd.addEventListener('click', function () {
      faqCounter++;
      const templateEl = faqTemplate.content.firstElementChild;
      if (!templateEl) { return; }
      const clone = templateEl.cloneNode(true);
      const idx = document.querySelectorAll('.faq-item-admin').length + 1;
      const legend = clone.querySelector('legend');
      if (legend) {
        legend.textContent = '';
        legend.appendChild(document.createTextNode('Nouvelle question '));
        const span = document.createElement('span');
        span.className = 'faq-index';
        span.textContent = '(' + idx + ')';
        legend.appendChild(span);
      }
      // Nom unique pour le checkbox de publication (sinon « nouvelle »).
      const box = clone.querySelector('input[type="checkbox"]');
      if (box) { box.value = 'new' + faqCounter; }
      const inputs = clone.querySelectorAll('input[type="text"], textarea');
      inputs.forEach(function (el) {
        if (el.name) {
          el.name = el.name.replace('[]', '[new' + faqCounter + ']');
        }
      });
      faqList.appendChild(clone);
      const first = clone.querySelector('input[type="text"]');
      if (first) { first.focus(); }
    });
  }

  /* ---- Aperçu des images (accueil, à propos) ---- */
  [['hero_image', 'hero-preview'], ['about_image', 'about-preview']].forEach(function (pair) {
    const input = document.querySelector('input[name="' + pair[0] + '"]');
    const preview = document.getElementById(pair[1]);
    if (!input || !preview) { return; }
    input.addEventListener('change', function () {
      const file = input.files && input.files[0];
      if (!file) { preview.hidden = true; return; }
      const reader = new FileReader();
      reader.onload = function () {
        preview.src = reader.result;
        preview.hidden = false;
      };
      reader.readAsDataURL(file);
    });
  });

  /* ---- Aperçu des photos importées (album) ---- */
  const importInput = document.getElementById('album-photo-import');
  const importPreview = document.getElementById('photo-import-preview');
  if (importInput && importPreview) {
    let pendingFiles = [];

    function renderPreviews() {
      importPreview.innerHTML = '';
      if (pendingFiles.length === 0) {
        importPreview.innerHTML =
          '<div class="photo-import-empty">Aucune photo sélectionnée.</div>';
        return;
      }
      pendingFiles.forEach(function (file, idx) {
        const item = document.createElement('div');
        item.className = 'photo-import-item';
        const reader = new FileReader();
        reader.onload = function () {
          const img = document.createElement('img');
          img.src = reader.result;
          img.alt = file.name;
          item.appendChild(img);
        };
        reader.readAsDataURL(file);
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'photo-import-remove';
        remove.title = 'Retirer';
        remove.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        remove.addEventListener('click', function (e) {
          e.preventDefault();
          pendingFiles.splice(idx, 1);
          // Rebuild the FileList for the input
          const dt = new DataTransfer();
          pendingFiles.forEach(function (f) { dt.items.add(f); });
          importInput.files = dt.files;
          renderPreviews();
        });
        item.appendChild(remove);
        const name = document.createElement('div');
        name.className = 'photo-import-name';
        name.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' Ko)';
        item.appendChild(name);
        importPreview.appendChild(item);
      });
    }

    importInput.addEventListener('change', function () {
      const newFiles = Array.prototype.slice.call(importInput.files || []);
      pendingFiles = pendingFiles.concat(newFiles);
      const dt = new DataTransfer();
      pendingFiles.forEach(function (f) { dt.items.add(f); });
      importInput.files = dt.files;
      renderPreviews();
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
    const dirty = document.querySelector('form[data-track-dirty][data-dirty="1"]');
    if (dirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  /* ---- Retour du focus après sauvegarde (accessibilité) ---- */
  const status = document.getElementById('save-status');
  if (status) {
    status.setAttribute('tabindex', '-1');
    try {
      status.focus({ preventScroll: true });
    } catch (_err) {
      status.focus();
    }
  }
})();
