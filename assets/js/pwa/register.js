/**
 * PWA - Enregistrement du Service Worker et cycle de mise à jour.
 * ================================================================
 * Plan §4.3, §11 : détection de support, erreurs silencieuses côté UX
 * (l'application classique continue de fonctionner sans PWA), mise à jour
 * jamais imposée pendant une saisie : l'utilisateur clique « Actualiser ».
 */
(function (global) {
  'use strict';

  var ns = global.LBEGF = global.LBEGF || {};

  function boot() {
    return global.LBEGF_PWA || {};
  }

  function register() {
    var config = boot();
    if (!config.enabled || !config.serviceWorker || !config.swUrl) {
      return Promise.resolve(null);
    }
    if (!global.navigator || !('serviceWorker' in global.navigator)) {
      return Promise.resolve(null); // API absente : aucun message d'erreur
    }
    // HTTPS (ou localhost) uniquement : un SW sur HTTP simple serait refusé
    // par le navigateur ; on n'insiste pas.
    var isSecure = global.isSecureContext === true
      || global.location.hostname === 'localhost'
      || global.location.hostname === '127.0.0.1';
    if (!isSecure) {
      return Promise.resolve(null);
    }

    return global.navigator.serviceWorker.register(config.swUrl, { scope: config.basePath || '/' })
      .then(function (registration) {
        if (ns.sync && ns.sync.setRegistration) {
          ns.sync.setRegistration(registration);
        }

        registration.addEventListener('updatefound', function () {
          var worker = registration.installing;
          if (!worker) {
            return;
          }
          worker.addEventListener('statechange', function () {
            if (worker.state === 'installed' && global.navigator.serviceWorker.controller) {
              offerRefresh(registration);
            }
          });
        });

        if (registration.waiting && global.navigator.serviceWorker.controller) {
          offerRefresh(registration);
        }
        return registration;
      })
      .catch(function () {
        // Échec d'enregistrement (hébergeur, scope, quota) : silencieux.
        return null;
      });
  }

  function offerRefresh(registration) {
    if (document.getElementById('pwaUpdateBanner')) {
      return;
    }
    var banner = document.createElement('div');
    banner.id = 'pwaUpdateBanner';
    banner.className = 'pwa-update-banner';
    banner.setAttribute('role', 'status');

    var text = document.createElement('span');
    text.textContent = 'Mise à jour de l’application disponible.';
    banner.appendChild(text);

    var refresh = document.createElement('button');
    refresh.type = 'button';
    refresh.className = 'btn btn-primary btn-sm';
    refresh.textContent = 'Actualiser';
    refresh.addEventListener('click', function () {
      var worker = registration.waiting;
      if (!worker) {
        global.location.reload();
        return;
      }
      var reloaded = false;
      global.navigator.serviceWorker.addEventListener('controllerchange', function () {
        if (!reloaded) {
          reloaded = true;
          global.location.reload();
        }
      });
      worker.postMessage({ type: 'skip-waiting' });
    });
    banner.appendChild(refresh);

    var later = document.createElement('button');
    later.type = 'button';
    later.className = 'btn btn-outline btn-sm';
    later.textContent = 'Plus tard';
    later.addEventListener('click', function () {
      banner.remove();
    });
    banner.appendChild(later);

    document.body.appendChild(banner);
  }

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        register().then(function () {
          if (ns.sync && ns.sync.init) {
            return ns.sync.init();
          }
          return null;
        });
      });
    } else {
      register().then(function () {
        if (ns.sync && ns.sync.init) {
          return ns.sync.init();
        }
        return null;
      });
    }
  }
})(typeof window !== 'undefined' ? window : self);
