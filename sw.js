/**
 * La Belle Église Internationale Franceville - Service Worker racine.
 * ================================================================
 * Plan : `docs/plans/pwa-offline-sync-implementation-plan.md` (§5, §10, §11).
 *
 * Règles de sécurité appliquées ici, sans exception :
 *
 * - Jamais de cache global : seules les ressources statiques versionnées et
 *   les pages publiques EXPLICITEMENT autorisées (en-tête serveur
 *   `X-LBE-Cacheable: 1`, réponse anonyme) peuvent être conservées.
 * - Aucune page de gestion, aucun JSON privé, aucune réponse d'API, aucun
 *   cookie, aucun upload n'entre dans Cache Storage (réseau seul).
 * - Une navigation privée ou inconnue hors connexion renvoie vers la page de
 *   secours générique : jamais une copie privée, jamais une page expirée
 *   présentée comme actuelle.
 * - Les brouillons IndexedDB ne sont JAMAIS supprimés par une mise à jour de
 *   ce Service Worker (plan §11) : seuls les caches obsolètes sont purgés.
 * - Une mise à jour n'interrompt pas un formulaire en cours : le nouveau
 *   worker attend une action explicite « Actualiser » (message skip-waiting).
 *
 * Ce fichier est servi à la racine web pour obtenir le scope de la vitrine
 * ET de l'espace de gestion. Il ne fait aucune requête vers une autre origine.
 */

'use strict';

var CACHE_PREFIX = 'lbe-';
var SYNC_TAG = 'lbe-sync';
var CONFIG_KEY_URL = '/__lbe_pwa_config__';
var DEFAULT_OFFLINE_PAGE = 'assets/offline.html';
var NAVIGATION_TIMEOUT_MS = 9000;
var NAVIGATION_TIMEOUT_PRIVATE_MS = 12000;

/** Modules partagés avec la page (IndexedDB, coffre, file) - même code, même schéma. */
try {
  importScripts(
    'assets/js/pwa/offline-db.js',
    'assets/js/pwa/crypto-vault.js',
    'assets/js/pwa/outbox.js'
  );
} catch (importError) {
  // Mode dégradé : sans ces modules, pas de capture d'envoi interrompu.
  // Les caches et la page de secours restent fonctionnels.
}

var memoryConfig = null;
var canCapturePosts = typeof globalThis !== 'undefined'
  && globalThis.LBEGF && globalThis.LBEGF.outbox && globalThis.LBEGF.vault;

/* ================================================================
 * CONFIGURATION (chargée depuis l'endpoint serveur `pwa/v1/assets`)
 * ================================================================ */

function configKeyRequest() {
  return new Request(new URL(CONFIG_KEY_URL, self.location.href).href);
}

function staticCacheName(version) {
  return CACHE_PREFIX + 'static-' + version;
}

function publicCacheName(version) {
  return CACHE_PREFIX + 'public-' + version;
}

/** Lit la configuration : mémoire, puis cache, puis réseau. */
function loadConfig() {
  if (memoryConfig) {
    return Promise.resolve(memoryConfig);
  }
  return caches.match(configKeyRequest())
    .then(function (cached) {
      if (cached) {
        return cached.json();
      }
      return fetchConfigFromNetwork();
    })
    .catch(function () {
      return fetchConfigFromNetwork();
    })
    .then(function (config) {
      if (config && config.version) {
        memoryConfig = config;
      }
      return config || null;
    })
    .catch(function () {
      return null;
    });
}

function fetchConfigFromNetwork() {
  var endpoint = new URL('index.php?api=pwa/v1/assets', self.location.href);
  return fetch(endpoint.href, { credentials: 'omit' })
    .then(function (response) {
      if (!response.ok) {
        throw new Error('config_unavailable');
      }
      return response.json();
    })
    .then(function (config) {
      if (!config || !config.version) {
        throw new Error('config_invalid');
      }
      return caches.open(staticCacheName(config.version))
        .then(function (cache) {
          return cache.put(
            configKeyRequest(),
            new Response(JSON.stringify(config), { headers: { 'Content-Type': 'application/json' } })
          );
        })
        .then(function () { return config; });
    });
}

/* ================================================================
 * INSTALLATION / MISE À JOUR
 * ================================================================ */

self.addEventListener('install', function (event) {
  event.waitUntil(
    loadConfig()
      .then(function (config) {
        if (!config || config.enabled === false) {
          return null;
        }
        return caches.open(staticCacheName(config.version)).then(function (cache) {
          var urls = (config.precache || []).slice(0, 200);
          // Précache tolérant : une ressource manquante ne casse pas l'install.
          return Promise.all(urls.map(function (url) {
            var request = new Request(new URL(url, self.location.href).href, { cache: 'no-cache' });
            return cache.add(request).catch(function () { return null; });
          }));
        });
      })
      .catch(function () { return null; })
      // Pas de skipWaiting() automatique : une mise à jour ne doit pas
      // interrompre un formulaire en cours de saisie (plan §11).
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    loadConfig()
      .then(function (config) {
        var keep = [];
        if (config) {
          keep.push(staticCacheName(config.version));
          keep.push(publicCacheName(config.version));
        }
        return caches.keys().then(function (keys) {
          return Promise.all(keys
            .filter(function (key) {
              return key.indexOf(CACHE_PREFIX) === 0 && keep.indexOf(key) === -1;
            })
            .map(function (key) { return caches.delete(key); }));
        });
      })
      .catch(function () { return null; })
      .then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('message', function (event) {
  var data = event.data || {};
  if (data.type === 'skip-waiting') {
    self.skipWaiting();
    return;
  }
  if (data.type === 'sync-now') {
    notifyClients({ type: 'pwa:sync', reason: 'manual' });
    return;
  }
  if (data.type === 'config-stale') {
    memoryConfig = null;
    loadConfig();
  }
});

/** Background Sync (si disponible) : réveille les onglets ouverts pour qu'ils
 *  synchronisent eux-mêmes (le coffre peut exiger un déverrouillage). */
self.addEventListener('sync', function (event) {
  if (event.tag === SYNC_TAG) {
    event.waitUntil(notifyClients({ type: 'pwa:sync', reason: 'background-sync' }));
  }
});

function notifyClients(message) {
  return self.clients.matchAll({ type: 'window', includeUncontrolled: false })
    .then(function (clients) {
      clients.forEach(function (client) {
        client.postMessage(message);
      });
      return clients.length;
    });
}

/* ================================================================
 * INTERCEPTION
 * ================================================================ */

self.addEventListener('fetch', function (event) {
  var request = event.request;
  var url = new URL(request.url);

  // Jamais d'interception cross-origin (CDN, cartes, vidéos, intégrations) :
  // ces contenus ne sont pas garantis hors ligne (plan §10).
  if (url.origin !== self.location.origin) {
    return;
  }

  // Écritures (POST) : réseau seul. Seul un envoi de formulaire interrompu
  // par la perte du réseau peut être capturé - et uniquement pour les
  // opérations allowlistées, en mode coffre « simple » (le Service Worker ne
  // peut pas déverrouiller un coffre protégé par PIN).
  if (request.method !== 'GET') {
    if (request.mode === 'navigate' && canCapturePosts) {
      event.respondWith(handlePostNavigation(request));
    }
    return;
  }

  // API de synchronisation : réseau seul, jamais de cache, jamais de copie
  // hors ligne (plan §11).
  if (url.searchParams.has('api')) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(handleNavigation(request, url));
    return;
  }

  if (isStaticAsset(url)) {
    event.respondWith(handleStatic(request));
    return;
  }

  // Tout le reste (uploads/, exports, fichiers privés…) : réseau seul.
});

function isStaticAsset(url) {
  var path = url.pathname;
  return path.indexOf('/assets/') !== -1
    && path.indexOf('/assets/offline.html') === -1
    && !/\.(php|html)$/i.test(path);
}

function isRootNavigation(url) {
  var path = url.pathname.replace(/index\.php$/i, '');
  return path === '/' || path === '' || /\/index\.php$/i.test(url.pathname);
}

function publicPageFor(url, config) {
  var page = url.searchParams.get('page');
  if (!page && isRootNavigation(url)) {
    // `/` sans paramètre : vitrine publique pour un visiteur anonyme
    // (même règle que le front controller).
    page = 'eglise';
  }
  if (!page) {
    return null;
  }
  var allowlist = (config && config.public_pages) || [];
  return allowlist.indexOf(page) !== -1 ? page : null;
}

/* ---------- Ressources statiques : Cache First ---------- */

function handleStatic(request) {
  return caches.match(request).then(function (cached) {
    if (cached) {
      return cached;
    }
    return fetch(request).then(function (response) {
      if (response.ok) {
        var copy = response.clone();
        caches.open(staticCacheName((memoryConfig && memoryConfig.version) || 'v1'))
          .then(function (cache) { return cache.put(request, copy); })
          .catch(function () { /* quota : on ignore */ });
      }
      return response;
    }).catch(function () {
      return new Response('', { status: 504, statusText: 'Hors connexion' });
    });
  });
}

/* ---------- Navigations : public = Network First, privé = réseau seul ---------- */

function handleNavigation(request, url) {
  return loadConfig().then(function (config) {
    var publicPage = publicPageFor(url, config);
    var networkPromise = fetch(request, { credentials: 'same-origin' });

    if (!publicPage) {
      // Page de gestion, connexion, page inconnue : jamais depuis un cache.
      return withTimeout(networkPromise, NAVIGATION_TIMEOUT_PRIVATE_MS)
        .catch(function () {
          return offlinePage(config, request.url);
        });
    }

    return withTimeout(networkPromise, NAVIGATION_TIMEOUT_MS)
      .then(function (response) {
        if (response.ok && response.headers.get('X-LBE-Cacheable') === '1') {
          var copy = response.clone();
          caches.open(publicCacheName(config.version))
            .then(function (cache) {
              return cache.put(request, copy).then(function () {
                return cache.put(
                  new Request(new URL(CONFIG_KEY_URL + '?refreshed', self.location.href).href),
                  new Response(JSON.stringify({ refreshed_at: new Date().toISOString(), url: request.url }), {
                    headers: { 'Content-Type': 'application/json' },
                  })
                );
              });
            })
            .catch(function () { /* quota : on ignore */ });
        }
        return response;
      })
      .catch(function () {
        return caches.match(request)
          .then(function (cached) {
            if (cached) {
              return cached;
            }
            return offlinePage(config, request.url);
          });
      });
  });
}

function withTimeout(promise, ms) {
  var timer = null;
  var guarded = promise.catch(function () { throw new Error('network'); });
  return Promise.race([
    guarded,
    new Promise(function (resolve, reject) {
      timer = setTimeout(function () { reject(new Error('timeout')); }, ms);
    }),
  ]).then(function (value) {
    clearTimeout(timer);
    return value;
  }, function (error) {
    clearTimeout(timer);
    throw error;
  });
}

/* ---------- Page de secours hors ligne ---------- */

function offlinePage(config, originalUrl) {
  var pagePath = (config && config.offline_page) || DEFAULT_OFFLINE_PAGE;
  var pageUrl = new URL(pagePath, self.location.href);
  return caches.match(new Request(pageUrl.href))
    .then(function (cached) {
      if (cached) {
        var copy = cached.clone();
        return new Response(copy.body, {
          status: 200,
          headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'Cache-Control': 'no-store',
            'X-LBE-Offline-Fallback': '1',
            'X-LBE-Offline-For': originalUrl || '',
          },
        });
      }
      return new Response(minimalOfflineHtml(), {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
      });
    })
    .catch(function () {
      return new Response(minimalOfflineHtml(), {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
      });
    });
}

function minimalOfflineHtml() {
  return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
    + '<meta name="viewport" content="width=device-width, initial-scale=1">'
    + '<title>Hors connexion</title></head><body style="font-family:sans-serif;padding:2rem;color:#1c2333">'
    + '<h1>Vous êtes hors connexion</h1>'
    + '<p>Cette page n’est pas disponible sans réseau et aucune copie locale n’est autorisée.</p>'
    + '<p><button onclick="location.reload()">Réessayer</button></p></body></html>';
}

/* ---------- Capture d'un envoi de formulaire interrompu ---------- */

var CAPTURABLE_POST_ACTIONS = ['save_rapport_jour', 'save_suivi', 'register'];

function handlePostNavigation(request) {
  var copy = request.clone();
  return fetch(request, { credentials: 'same-origin' })
    .catch(function () {
      return captureInterruptedPost(copy).then(function (captured) {
        if (!captured) {
          return offlinePage(memoryConfig, request.url);
        }
        return interruptedSubmitResponse(captured);
      });
    });
}

function captureInterruptedPost(request) {
  return loadConfig()
    .then(function (config) {
      if (!config || config.capture_failed_posts !== true || !canCapturePosts) {
        return null;
      }
      return request.formData();
    })
    .then(function (form) {
      if (!form) {
        return null;
      }
      var action = String(form.get('action') || '');
      if (CAPTURABLE_POST_ACTIONS.indexOf(action) === -1) {
        return null;
      }
      var fields = {};
      form.forEach(function (value, key) {
        if (key === 'csrf') {
          return; // jamais de jeton en file (plan §6.1)
        }
        if (isSecretField(key)) {
          return; // jamais de secret écrit sur l'appareil (plan §6.1) :
                  // il sera redemandé au moment de l'envoi.
        }
        if (typeof value === 'string') {
          fields[key] = value;
        }
      });
      return globalThis.LBEGF.outbox.saveRecovery({
        action: action,
        fields: fields,
        url: request.url,
        account_scope: String(form.get('__pwa_scope') || '') || 'public',
      }).then(function (recoveryId) {
        return { action: action, recoveryId: recoveryId };
      });
    })
    .catch(function () {
      return null;
    });
}

/**
 * Champ sensible à ne JAMAIS conserver sur l'appareil, même chiffré :
 * mots de passe et champs explicitement marqués `type="password"`.
 * Le Service Worker n'a pas accès au DOM du formulaire, il filtre donc sur
 * le nom du champ (le client fait de même sur `data-pwa-no-store`).
 */
function isSecretField(key) {
  return /pass(word|phrase)?|pwd|secret|code_pin|otp|token/i.test(String(key));
}

function interruptedSubmitResponse(captured) {
  var labels = {
    save_rapport_jour: 'votre rapport du jour',
    save_suivi: 'votre suivi hebdomadaire',
    register: 'votre demande d’inscription',
  };
  var html = '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
    + '<meta name="viewport" content="width=device-width, initial-scale=1">'
    + '<meta name="theme-color" content="#0B1020"><title>Hors connexion</title></head>'
    + '<body style="font-family:sans-serif;padding:2rem;color:#1c2333;max-width:36rem;margin:auto">'
    + '<h1>Saisie conservée sur cet appareil</h1>'
    + '<p>Le réseau s’est interrompu pendant l’envoi de ' + (labels[captured.action] || 'votre saisie') + '.</p>'
    + '<p><strong>Rien n’a été enregistré sur le serveur.</strong> Votre saisie a été conservée '
    + 'comme brouillon chiffré sur cet appareil, en attente de synchronisation.</p>'
    + '<p><a href="index.php">Revenir à l’application</a></p></body></html>';
  return new Response(html, {
    status: 200,
    headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
  });
}
