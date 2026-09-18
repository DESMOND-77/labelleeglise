/* Agenda unifié - sélection d'un jour sans rechargement + restauration du scroll.
   Aucune dépendance. Chargé uniquement sur ?page=agenda (voir layout.php). */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var dataEl = document.getElementById('agenda-data');
    var grid = document.querySelector('.agenda-grid');
    var panel = document.getElementById('agenda-day');
    var title = document.getElementById('agenda-day-title');
    if (!dataEl || !grid || !panel) {
      return;
    }

    var data = {};
    try {
      data = JSON.parse(dataEl.textContent || '{}') || {};
    } catch (e) {
      data = {};
    }

    /* ---- Restauration du scroll autour du rechargement mensuel ---- */
    try {
      var saved = sessionStorage.getItem('agenda:scroll');
      if (saved !== null) {
        window.scrollTo(0, parseInt(saved, 10) || 0);
        sessionStorage.removeItem('agenda:scroll');
      }
    } catch (e) { /* sessionStorage indisponible : sans effet */ }
    window.addEventListener('beforeunload', function () {
      try {
        sessionStorage.setItem('agenda:scroll', String(window.scrollY));
      } catch (e) { /* sans effet */ }
    });

    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    var MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
      'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    var DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    function prettyDate(iso) {
      var p = iso.split('-');
      var dt = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
      var label = DAYS[dt.getUTCDay()] + ' ' + (+p[2]) + ' ' + MONTHS[+p[1] - 1] + ' ' + p[0];
      return label.charAt(0).toUpperCase() + label.slice(1);
    }

    function eventLine(e) {
      var href = 'index.php?page=calendrier&evt=' + encodeURIComponent(e.id);
      var time = e.heure_debut
        ? '<span class="agenda-time">' + esc(e.heure_debut) + (e.heure_fin ? '–' + esc(e.heure_fin) : '') + '</span> '
        : '';
      var place = e.lieu ? '<span class="agenda-place">' + esc(e.lieu) + '</span>' : '';
      var badge = e.is_multi_day ? '<span class="agenda-badge">plusieurs jours</span>' : '';
      return '<li><a href="' + esc(href) + '">' + time + esc(e.nom) + '</a>' + place + badge + '</li>';
    }

    function birthdayLine(b) {
      var age = (b.age !== null && b.age !== undefined) ? ' - ' + (b.age | 0) + ' ans' : '';
      return '<li>🎂 ' + esc(b.nom) + age + '</li>';
    }

    function renderDay(iso) {
      var slot = data[iso] || { events: [], birthdays: [] };
      var events = slot.events || [];
      var birthdays = slot.birthdays || [];
      var html = '';

      html += '<section class="agenda-detail-block"><h4 class="agenda-detail-title">Événements</h4>';
      html += events.length
        ? '<ul class="agenda-detail-list">' + events.map(eventLine).join('') + '</ul>'
        : '<p class="agenda-empty">Aucun événement.</p>';
      html += '</section>';

      html += '<section class="agenda-detail-block"><h4 class="agenda-detail-title">Anniversaires</h4>';
      html += birthdays.length
        ? '<ul class="agenda-detail-list">' + birthdays.map(birthdayLine).join('') + '</ul>'
        : '<p class="agenda-empty">Aucun anniversaire.</p>';
      html += '</section>';

      if (!events.length && !birthdays.length) {
        html += '<p class="agenda-empty agenda-empty-global">Aucune activité programmée pour cette journée.</p>';
      }

      panel.innerHTML = html;
      if (title) {
        title.textContent = prettyDate(iso);
      }
    }

    function selectDay(iso) {
      var current = grid.querySelector('.agenda-day.is-selected');
      if (current) {
        current.classList.remove('is-selected');
        current.removeAttribute('aria-current');
      }
      var next = grid.querySelector('.agenda-day[data-date="' + iso + '"]');
      if (next) {
        next.classList.add('is-selected');
        next.setAttribute('aria-current', 'date');
      }
      renderDay(iso);
      try {
        history.replaceState(null, '', 'index.php?page=agenda&ym=' + iso.slice(0, 7) + '&date=' + iso);
      } catch (e) { /* sans effet */ }
    }

    grid.addEventListener('click', function (ev) {
      var cell = ev.target.closest('.agenda-day[data-date]');
      if (!cell || !grid.contains(cell)) {
        return;
      }
      ev.preventDefault();
      selectDay(cell.getAttribute('data-date'));
    });
  });
})();
