/* Pagination client des tableaux — auto sur chaque table.data-table dépassant
   le seuil, sauf [data-no-paginate]. Aucune dépendance, defer, auto-gardé.
   Sans JS : le tableau complet reste affiché (dégradation propre). */
(function () {
  'use strict';

  var PAGE_SIZE = 15;

  function isEmptyStateRow(tr) {
    // Ligne unique d'état vide : une seule cellule avec colspan.
    return tr.cells.length === 1 && tr.cells[0].hasAttribute('colspan');
  }

  function enhance(table) {
    if (table.dataset.paginated === '1' || !table.tBodies.length) {
      return;
    }
    var tbody = table.tBodies[0];
    var rows = Array.prototype.slice.call(tbody.rows).filter(function (r) {
      return !isEmptyStateRow(r);
    });
    if (rows.length <= PAGE_SIZE) {
      return;
    }
    table.dataset.paginated = '1';

    var pages = Math.ceil(rows.length / PAGE_SIZE);
    var current = 1;

    var pager = document.createElement('div');
    pager.className = 'table-pager no-print';

    var prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'btn btn-outline btn-sm';
    prev.innerHTML = '‹ Précédent';

    var next = document.createElement('button');
    next.type = 'button';
    next.className = 'btn btn-outline btn-sm';
    next.innerHTML = 'Suivant ›';

    var status = document.createElement('span');
    status.className = 'table-pager-status';
    status.setAttribute('aria-live', 'polite');

    pager.appendChild(prev);
    pager.appendChild(status);
    pager.appendChild(next);

    function render(scroll) {
      var start = (current - 1) * PAGE_SIZE;
      var end = start + PAGE_SIZE;
      rows.forEach(function (r, i) {
        r.hidden = i < start || i >= end;
      });
      status.textContent = 'Page ' + current + ' / ' + pages
        + '  ·  ' + (start + 1) + '–' + Math.min(end, rows.length) + ' sur ' + rows.length;
      prev.disabled = current === 1;
      next.disabled = current === pages;
      if (scroll) {
        table.scrollIntoView({ block: 'nearest' });
      }
    }

    prev.addEventListener('click', function () {
      if (current > 1) {
        current--;
        render(true);
      }
    });
    next.addEventListener('click', function () {
      if (current < pages) {
        current++;
        render(true);
      }
    });

    var anchor = table.closest('.table-wrap') || table;
    anchor.parentNode.insertBefore(pager, anchor.nextSibling);

    render(false);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('table.data-table:not([data-no-paginate])').forEach(enhance);
  });
})();
