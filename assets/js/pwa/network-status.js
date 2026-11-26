/**
 * PWA - Indicateur d'état réseau et de synchronisation (plan §9.3).
 * ================================================================
 * Pastille discrète, présente sur la vitrine comme sur l'espace de gestion.
 * Elle affiche uniformément : en ligne / hors connexion / brouillon local /
 * en attente (N) / synchronisation en cours / synchronisé le … / conflit /
 * connexion requise. Jamais une donnée locale n'y est présentée comme une
 * donnée officiellement enregistrée.
 */
(function (global) {
  'use strict';

  var ns = global.LBEGF = global.LBEGF || {};
  var pill = null;
  var lastState = null;

  function boot() {
    return global.LBEGF_PWA || {};
  }

  function describe(state) {
    var online = !global.navigator || global.navigator.onLine !== false;
    if (!boot().enabled) {
      return null;
    }
    if (state.status === 'syncing') {
      return { kind: 'syncing', icon: 'fa-arrows-rotate fa-spin', text: 'Synchronisation en cours…' };
    }
    if (state.status === 'auth_required') {
      return { kind: 'auth', icon: 'fa-right-to-bracket', text: 'Connexion requise pour envoyer' };
    }
    if (!online) {
      if (state.pending > 0) {
        return { kind: 'offline', icon: 'fa-plane-slash', text: 'Hors connexion — en attente (' + state.pending + ')' };
      }
      return { kind: 'offline', icon: 'fa-plane-slash', text: 'Hors connexion' };
    }
    if (state.conflicts > 0) {
      return { kind: 'conflict', icon: 'fa-code-compare', text: 'Conflit à résoudre (' + state.conflicts + ')' };
    }
    if (state.pending > 0) {
      return { kind: 'pending', icon: 'fa-clock-rotate-left', text: 'En attente de synchronisation (' + state.pending + ')' };
    }
    if (state.rejected > 0) {
      return { kind: 'warn', icon: 'fa-triangle-exclamation', text: 'Saisie refusée : à corriger (' + state.rejected + ')' };
    }
    if (state.status === 'error') {
      return { kind: 'warn', icon: 'fa-triangle-exclamation', text: 'Synchronisation interrompue' };
    }
    if (state.lastSyncAt) {
      var date = new Date(state.lastSyncAt);
      return {
        kind: 'ok',
        icon: 'fa-cloud-check',
        text: 'Synchronisé le ' + date.toLocaleDateString('fr-FR') + ' à '
          + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
      };
    }
    if (state.drafts > 0) {
      return { kind: 'draft', icon: 'fa-file-pen', text: 'Brouillon enregistré sur cet appareil (' + state.drafts + ')' };
    }
    return { kind: 'ok', icon: 'fa-wifi', text: 'En ligne' };
  }

  function render(state) {
    lastState = state;
    if (!boot().enabled) {
      return;
    }
    if (!pill) {
      pill = document.createElement('button');
      pill.type = 'button';
      pill.id = 'pwaStatusPill';
      pill.className = 'pwa-pill';
      pill.setAttribute('aria-live', 'polite');
      pill.addEventListener('click', function () {
        if (ns.panel && ns.panel.open) {
          ns.panel.open();
        } else if (boot().panelUrl) {
          global.location.href = boot().panelUrl;
        }
      });
      document.body.appendChild(pill);
    }
    var info = describe(state);
    if (!info) {
      pill.hidden = true;
      return;
    }
    pill.hidden = false;
    pill.className = 'pwa-pill pwa-pill-' + info.kind;
    pill.title = state.lastMessage || info.text;
    pill.innerHTML = '<i class="fa-solid ' + info.icon + '" aria-hidden="true"></i><span>' + info.text + '</span>';
  }

  function init() {
    if (typeof document === 'undefined' || !boot().enabled) {
      return;
    }
    if (ns.sync && ns.sync.on) {
      ns.sync.on('state', render);
    }
    global.addEventListener('online', function () {
      if (lastState) {
        render(Object.assign({}, lastState, { status: lastState.pending ? 'pending' : 'online' }));
      }
    });
    global.addEventListener('offline', function () {
      if (lastState) {
        render(Object.assign({}, lastState, { status: 'offline' }));
      }
    });
    if (ns.sync && ns.sync.refreshCounts) {
      ns.sync.refreshCounts().then(render);
    }
  }

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  }

  ns.net = { render: render, describe: describe };
})(typeof window !== 'undefined' ? window : self);
