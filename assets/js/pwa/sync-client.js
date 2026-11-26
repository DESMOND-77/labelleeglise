/**
 * PWA - Client de synchronisation (plan §7, §8).
 * ================================================================
 * Déclencheurs : ouverture, retour en ligne, reprise de visibilité,
 * Background Sync (si disponible), action manuelle. Repli systématique
 * via `online` / visibilité / bouton (plan §11).
 *
 * Avant chaque envoi : bootstrap serveur (session + jeton CSRF FRAIS +
 * capacités). Jamais de jeton mis en file, jamais d'envoi sans session
 * pour une opération privée. Une session expirée BLOQUE l'envoi sans
 * jamais supprimer les brouillons (plan §7.5).
 *
 * Chaque opération reçoit un accusé distinct : un lot partiellement
 * traité est rejouable sans doublon (idempotence serveur par UUID).
 */
(function (global) {
  'use strict';

  var ns = global.LBEGF = global.LBEGF || {};

  var SYNC_TAG = 'lbe-sync';
  var listeners = [];
  // Mots de passe fournis à l'envoi (inscription publique) : mémoire vive
  // uniquement, jamais écrits dans IndexedDB (plan §6.1).
  var pendingCredentials = {};
  var registration = null;
  var retryTimer = null;
  var running = false;

  var state = {
    status: 'boot',          // boot|online|offline|syncing|pending|conflict|auth_required|error|disabled
    pending: 0,
    drafts: 0,
    syncing: 0,
    conflicts: 0,
    rejected: 0,
    lastSyncAt: null,
    lastMessage: '',
    lastError: ''
  };

  function boot() {
    return global.LBEGF_PWA || {};
  }

  function outbox() { return ns.outbox; }

  function on(event, callback) {
    if (event === 'state') {
      listeners.push(callback);
    }
  }

  function setState(patch) {
    Object.keys(patch || {}).forEach(function (key) { state[key] = patch[key]; });
    listeners.forEach(function (callback) {
      try { callback(state); } catch (ignored) { /* un auditeur cassé ne casse pas la synchro */ }
    });
    if (typeof document !== 'undefined' && document.dispatchEvent) {
      try {
        document.dispatchEvent(new CustomEvent('lbegf:pwa-state', { detail: state }));
      } catch (ignored) { /* navigateurs très anciens */ }
    }
  }

  function getState() {
    return state;
  }

  /* ---------- HTTP ---------- */

  function endpoint(name) {
    var endpoints = boot().endpoints || {};
    return endpoints[name] || '';
  }

  function getJson(name) {
    return fetch(endpoint(name), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        return { status: response.status, body: body };
      });
    });
  }

  function postJson(name, body) {
    return fetch(endpoint(name), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(body)
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (json) {
        return { status: response.status, body: json };
      });
    });
  }

  /* ---------- Amorçage ---------- */

  var bootstrapCache = null;

  function bootstrap(force) {
    if (bootstrapCache && !force && (Date.now() - bootstrapCache.at) < 30000) {
      return Promise.resolve(bootstrapCache.body);
    }
    return getJson('bootstrap').then(function (response) {
      if (response.status >= 200 && response.status < 300) {
        bootstrapCache = { at: Date.now(), body: response.body };
        return response.body;
      }
      throw new Error('bootstrap_failed_' + response.status);
    });
  }

  function refreshCounts() {
    if (!ns.outbox) {
      return Promise.resolve(state);
    }
    return outbox().counts().then(function (counts) {
      var patch = {
        pending: counts.pending,
        drafts: counts.drafts,
        syncing: counts.syncing,
        conflicts: counts.conflict,
        rejected: counts.rejected + counts.failed
      };
      if (!global.navigator || global.navigator.onLine === false) {
        patch.status = counts.pending ? 'pending' : 'offline';
      } else if (counts.conflict) {
        patch.status = 'conflict';
      } else if (counts.pending) {
        patch.status = 'pending';
      } else if (state.status !== 'syncing') {
        patch.status = 'online';
      }
      setState(patch);
      scheduleRetry();
      return state;
    }).catch(function () {
      return state;
    });
  }

  /* ---------- Envoi ---------- */

  function backoffMs(attempts) {
    var limits = boot().sync || {};
    var base = limits.retryBaseMs || 4000;
    var max = limits.retryMaxMs || 900000;
    var delay = Math.min(max, base * Math.pow(2, Math.max(0, attempts - 1)));
    return delay + Math.floor(Math.random() * Math.min(2000, delay / 2));
  }

  function wireOperation(op) {
    return {
      operation_id: op.operation_id,
      type: op.type,
      action: op.action,
      schema_version: op.schema_version,
      entity_id: op.entity_id || null,
      entity_key: op.entity_key || null,
      base_fingerprint: op.base_fingerprint || '',
      base_snapshot: op.base_snapshot || null,
      payload: op.payload,
      resolution: op.resolution || '',
      created_at_client: op.created_at
    };
  }

  function syncNow(reason) {
    if (!boot().offlineCapture || !ns.outbox) {
      return Promise.resolve({ ok: false, disabled: true });
    }
    if (!global.navigator || global.navigator.onLine === false) {
      setState({ status: 'offline', lastMessage: 'Hors connexion : envoi impossible pour le moment.' });
      return Promise.resolve({ ok: false, offline: true });
    }
    if (running) {
      return Promise.resolve({ ok: false, busy: true });
    }
    running = true;
    setState({ status: 'syncing', lastMessage: 'Synchronisation en cours…' });

    return recoverStuckSyncing()
      .then(function () { return outbox().adoptRecovery(); })
      .then(function () { return bootstrap(true); })
      .then(function (info) {
        if (boot().user && !info.authenticated) {
          // Session expirée ou compte désactivé : on bloque l'envoi, on ne
          // supprime RIEN (plan §7.5).
          setState({
            status: 'auth_required',
            lastMessage: 'Connexion requise pour envoyer. Vos brouillons sont conservés sur cet appareil.'
          });
          return { ok: false, auth_required: true };
        }

        var scope = info.user ? info.user.account_scope : 'public';
        return outbox().listReady(scope).then(function (ready) {
          var privateOps = ready.filter(function (op) { return op.type !== 'public_register'; });
          var publicOps = ready.filter(function (op) { return op.type === 'public_register'; });

          if (!privateOps.length && !publicOps.length) {
            setState({ status: 'online', lastSyncAt: new Date().toISOString(), lastMessage: '' });
            return { ok: true, sent: 0 };
          }

          return markSyncing(privateOps.concat(publicOps))
            .then(function () { return sendPrivate(privateOps, info); })
            .then(function (results) { return sendPublic(publicOps, info).then(function (more) { return results.concat(more); }); })
            .then(function (results) { return applyResults(results, scope); })
            .then(function (summary) {
              setState({
                status: summary.conflicts ? 'conflict' : (summary.pending ? 'pending' : 'online'),
                lastSyncAt: new Date().toISOString(),
                lastMessage: summary.conflicts
                  ? 'Conflit à résoudre : ouvrez la file hors ligne.'
                  : (summary.pending ? 'En attente de synchronisation (' + summary.pending + ')' : 'Synchronisé.')
              });
              return { ok: true, sent: summary.sent, results: summary.results };
            });
        });
      })
      .catch(function (error) {
        var offline = !global.navigator || global.navigator.onLine === false;
        var message = String(error && error.message ? error.message : error);
        if (message.indexOf('session_required') !== -1) {
          running = false;
          setState({
            status: 'auth_required',
            lastMessage: 'Connexion requise pour envoyer. Vos brouillons sont conservés sur cet appareil.'
          });
          refreshCounts();
          return { ok: false, auth_required: true };
        }
        setState({
          status: offline ? 'offline' : 'error',
          lastError: message,
          lastMessage: offline ? 'Hors connexion.' : 'Synchronisation interrompue : nouvelle tentative programmée.'
        });
        scheduleRetry();
        return { ok: false, error: String(error && error.message ? error.message : error) };
      })
      .then(function (outcome) {
        running = false;
        refreshCounts();
        return outcome;
      });
  }

  /**
   * Filet de sécurité : une opération restée `syncing` (onglet fermé pendant
   * l'envoi, panne) redevient `queued` après deux minutes. L'idempotence
   * serveur garantit qu'un éventuel double envoi ne double pas l'écriture.
   */
  function recoverStuckSyncing() {
    if (!ns.outbox) {
      return Promise.resolve(0);
    }
    var cutoff = new Date(Date.now() - 120000).toISOString();
    return outbox().listOperations(null, ['syncing']).then(function (stuck) {
      var chain = Promise.resolve(0);
      stuck.forEach(function (op) {
        if (String(op.updated_at) >= cutoff) {
          return;
        }
        chain = chain.then(function (count) {
          return outbox().updateOperation(op.operation_id, { state: 'queued' })
            .then(function () { return count + 1; });
        });
      });
      return chain;
    });
  }

  function markSyncing(ops) {
    var chain = Promise.resolve();
    ops.forEach(function (op) {
      chain = chain.then(function () {
        return outbox().updateOperation(op.operation_id, { state: 'syncing' });
      });
    });
    return chain;
  }

  function sendPrivate(ops, info) {
    if (!ops.length) {
      return Promise.resolve([]);
    }
    var limits = boot().sync || {};
    var size = limits.maxBatch || 20;
    var results = [];
    var chain = Promise.resolve();
    for (var start = 0; start < ops.length; start += size) {
      (function (chunk) {
        chain = chain.then(function () {
          return postJson('sync', {
            protocol_version: 1,
            csrf: info.csrf_token,
            operations: chunk.map(wireOperation)
          }).then(function (response) {
            if (response.status === 401) {
              var authError = new Error('session_required');
              throw authError;
            }
            var batch = (response.body && response.body.results) || [];
            batch.forEach(function (result) { results.push(result); });
            // Opérations sans accusé (lot refusé en bloc) : retryable.
            chunk.forEach(function (op) {
              var found = batch.some(function (result) { return result.operation_id === op.operation_id; });
              if (!found) {
                results.push({
                  operation_id: op.operation_id,
                  status: 'retryable_error',
                  code: (response.body && response.body.error) || 'batch_refused',
                  message: (response.body && response.body.message) || 'Lot refusé par le serveur.'
                });
              }
            });
            return null;
          });
        });
      })(ops.slice(start, start + size));
    }
    return chain.then(function () { return results; });
  }

  function setPendingCredentials(operationId, credentials) {
    pendingCredentials[operationId] = credentials || null;
  }

  function sendPublic(ops, info) {
    var results = [];
    var chain = Promise.resolve();
    ops.forEach(function (op) {
      chain = chain.then(function () {
        var wire = wireOperation(op);
        if (pendingCredentials[op.operation_id]) {
          var merged = {};
          Object.keys(wire.payload || {}).forEach(function (key) { merged[key] = wire.payload[key]; });
          Object.keys(pendingCredentials[op.operation_id]).forEach(function (key) {
            merged[key] = pendingCredentials[op.operation_id][key];
          });
          wire.payload = merged;
        }
        return postJson('publicRegister', {
          protocol_version: 1,
          csrf: info.csrf_token,
          operation: wire
        }).then(function (response) {
          var batch = (response.body && response.body.results) || [];
          batch.forEach(function (result) { results.push(result); });
          if (!batch.length) {
            results.push({
              operation_id: op.operation_id,
              status: response.status === 429 ? 'rejected' : 'retryable_error',
              code: (response.body && response.body.error) || 'public_refused',
              message: (response.body && response.body.message) || 'Demande refusée par le serveur.'
            });
          }
          return null;
        });
      });
    });
    return chain.then(function () { return results; });
  }

  function applyResults(results, scope) {
    var summary = { sent: 0, pending: 0, conflicts: 0, results: results };
    var chain = Promise.resolve();

    results.forEach(function (result) {
      chain = chain.then(function () {
        var operationId = result.operation_id;
        if (!operationId) {
          return null;
        }
        return outbox().getOperation(operationId).then(function (record) {
          if (!record) {
            return null;
          }
          delete pendingCredentials[operationId];
          var status = String(result.status || '');

          if (status === 'accepted' || status === 'duplicate') {
            summary.sent += 1;
            return outbox().updateMeta(operationId, function (meta) {
              meta.server = {
                entity_id: result.entity_id || null,
                entity_key: result.entity_key || null,
                message: result.message || '',
                code: result.code || '',
                synced_at: result.server_time || new Date().toISOString()
              };
              return meta;
            }).then(function () {
              return outbox().updateOperation(operationId, {
                state: 'synced',
                last_status: status,
                last_code: result.code || '',
                last_error: ''
              });
            });
          }

          if (status === 'conflict') {
            summary.conflicts += 1;
            return outbox().updateMeta(operationId, function (meta) {
              meta.server = {
                state: result.server_state || null,
                fingerprint: result.server_fingerprint || '',
                resolutions: result.resolutions || ['server', 'client', 'merge'],
                message: result.message || ''
              };
              return meta;
            }).then(function () {
              return outbox().updateOperation(operationId, {
                state: 'conflict',
                last_status: status,
                last_code: result.code || '',
                last_error: result.message || 'Conflit de version.'
              });
            });
          }

          if (status === 'validation_error' || status === 'unauthorized' || status === 'rejected') {
            var rejectedPatch = record.type === 'public_register' ? { requires_input: true } : {};
            return outbox().updateOperation(operationId, rejectedPatch).then(function () {
              return null;
            }).then(function () {
              return outbox().updateMeta(operationId, function (meta) {
              meta.errors = {
                fields: result.errors || null,
                message: result.message || 'Saisie refusée par le serveur.'
              };
              return meta;
            }).then(function () {
                return outbox().updateOperation(operationId, Object.assign({
                  state: 'rejected',
                  last_status: status,
                  last_code: result.code || '',
                  last_error: result.message || 'Saisie refusée par le serveur.'
                }, record.type === 'public_register' ? { requires_input: true } : {}));
              });
            });
          }

          // retryable_error : backoff exponentiel avec jitter, plafond, arrêt
          // définitif après max_attempts (plan §8).
          var attempts = (record.attempts || 0) + 1;
          var limits = boot().sync || {};
          var maxAttempts = limits.maxAttempts || 8;
          if (attempts >= maxAttempts) {
            return outbox().updateOperation(operationId, {
              state: 'failed',
              attempts: attempts,
              last_status: status,
              last_code: result.code || '',
              last_error: result.message || 'Envoi impossible après plusieurs tentatives.'
            });
          }
          summary.pending += 1;
          return outbox().updateOperation(operationId, {
            state: 'queued',
            attempts: attempts,
            last_status: status,
            last_code: result.code || '',
            last_error: result.message || 'Nouvelle tentative programmée.',
            next_attempt_at: Date.now() + backoffMs(attempts)
          });
        });
      });
    });

    return chain.then(function () {
      return outbox().counts(scope).then(function (counts) {
        summary.pending = counts.pending;
        summary.conflicts = summary.conflicts || counts.conflict;
        return summary;
      });
    });
  }

  /* ---------- Résolution de conflit ---------- */

  function resolveConflict(operationId, resolution) {
    if (['server', 'client', 'merge'].indexOf(resolution) === -1) {
      return Promise.reject(new Error('resolution_invalide'));
    }
    return outbox().updateOperation(operationId, {
      state: 'queued',
      resolution: resolution,
      next_attempt_at: 0,
      last_error: ''
    }).then(function () {
      return syncNow('conflict-resolution');
    });
  }

  /* ---------- Déclencheurs ---------- */

  function scheduleRetry() {
    if (retryTimer) {
      clearTimeout(retryTimer);
      retryTimer = null;
    }
    if (!ns.outbox) {
      return;
    }
    outbox().listOperations(null, ['queued']).then(function (queued) {
      var now = Date.now();
      var future = queued
        .filter(function (op) { return op.next_attempt_at > now; })
        .map(function (op) { return op.next_attempt_at; })
        .sort(function (a, b) { return a - b; });
      if (!future.length) {
        return null;
      }
      var delay = Math.min(300000, Math.max(2000, future[0] - now));
      retryTimer = setTimeout(function () { syncNow('retry-timer'); }, delay);
      return null;
    }).catch(function () { return null; });
  }

  function registerBackgroundSync() {
    if (!registration || !registration.sync || typeof registration.sync.register !== 'function') {
      return Promise.resolve(false);
    }
    return registration.sync.register(SYNC_TAG).then(function () { return true; }).catch(function () { return false; });
  }

  function setRegistration(reg) {
    registration = reg;
  }

  function init() {
    if (!boot().enabled || !ns.outbox) {
      setState({ status: 'disabled' });
      return Promise.resolve(state);
    }

    if (typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
          refreshCounts().then(function () { syncNow('visibility'); });
        }
      });
    }
    global.addEventListener('online', function () {
      refreshCounts().then(function () { syncNow('online'); });
    });
    global.addEventListener('offline', function () {
      setState({ status: 'offline', lastMessage: 'Hors connexion.' });
    });

    if (global.navigator && global.navigator.serviceWorker) {
      global.navigator.serviceWorker.addEventListener('message', function (event) {
        var data = event.data || {};
        if (data.type === 'pwa:sync') {
          syncNow(data.reason || 'service-worker');
        }
      });
    }

    return outbox().publishScopeHint()
      .then(function () { return outbox().expireStale(outbox().scopeOf()); })
      .then(function () { return refreshCounts(); })
      .then(function () {
        if (state.pending > 0) {
          registerBackgroundSync();
          syncNow('boot');
        }
        return state;
      })
      .catch(function () {
        setState({ status: 'error', lastMessage: 'Stockage local indisponible.' });
        return state;
      });
  }

  ns.sync = {
    init: init,
    on: on,
    getState: getState,
    syncNow: syncNow,
    bootstrap: bootstrap,
    refreshCounts: refreshCounts,
    resolveConflict: resolveConflict,
    setPendingCredentials: setPendingCredentials,
    registerBackgroundSync: registerBackgroundSync,
    setRegistration: setRegistration,
    backoffMs: backoffMs
  };
})(typeof window !== 'undefined' ? window : self);
