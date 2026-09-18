/* Composant de pointage unifié - recherche, filtres, compteurs live,
   « Tout présent » / « Réinitialiser ». Aucune dépendance, auto-gardé, defer.
   Le partial fonctionne sans ce script (radios + submit). */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('.attendance-pointage');
    if (!root) {
      return;
    }

    root.querySelectorAll('.js-only').forEach(function (el) {
      el.classList.remove('is-hidden');
    });

    var rows = Array.prototype.slice.call(root.querySelectorAll('.attendance-row'));
    var searchInput = root.querySelector('.attendance-search');
    var filterBtns = Array.prototype.slice.call(root.querySelectorAll('.attendance-filter'));
    var activeFilter = 'all';

    function norm(s) {
      return (s || '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
    }

    function statusOf(row) {
      var checked = row.querySelector('input[type=radio]:checked');
      if (checked) {
        return checked.value || 'non_renseigne';
      }
      // lecture seule (pas de radios) : garder le data-status rendu par le serveur
      return row.dataset.status || 'non_renseigne';
    }

    function recount() {
      var c = { present: 0, absent: 0, excuse: 0, non_renseigne: 0 };
      rows.forEach(function (r) {
        var s = statusOf(r);
        r.dataset.status = s;
        if (c[s] !== undefined) {
          c[s]++;
        }
      });
      root.querySelectorAll('[data-count]').forEach(function (el) {
        var k = el.dataset.count;
        el.textContent = k === 'total' ? rows.length : c[k];
      });
    }

    function applyFilters() {
      var q = norm(searchInput ? searchInput.value : '');
      rows.forEach(function (r) {
        var okStatus = activeFilter === 'all' || r.dataset.status === activeFilter;
        var okSearch = norm(r.dataset.name).indexOf(q) !== -1;
        r.hidden = !(okStatus && okSearch);
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', applyFilters);
    }

    filterBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        activeFilter = btn.dataset.filter || 'all';
        filterBtns.forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        applyFilters();
      });
    });

    var allPresent = root.querySelector('.attendance-all-present');
    if (allPresent) {
      allPresent.addEventListener('click', function () {
        rows.forEach(function (r) {
          var radio = r.querySelector('input[type=radio][value="present"]');
          if (radio) {
            radio.checked = true;
          }
        });
        recount();
        applyFilters();
      });
    }

    var reset = root.querySelector('.attendance-reset');
    if (reset) {
      reset.addEventListener('click', function () {
        rows.forEach(function (r) {
          var radio = r.querySelector('input[type=radio][value=""]');
          if (radio) {
            radio.checked = true;
          }
        });
        recount();
        applyFilters();
      });
    }

    root.addEventListener('change', function (e) {
      if (e.target && e.target.matches('input[type=radio]')) {
        recount();
        applyFilters();
      }
    });

    recount();
  });
})();
