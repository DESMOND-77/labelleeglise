/**
 * PWA - Brouillons, file d'attente et états (plan §6, §9.3).
 * ================================================================
 * Chaque enregistrement est cloisonné par compte (`account_scope`) :
 * ces étiquettes ne prouvent jamais une autorisation (le serveur
 * recontrôle tout au commit), elles empêchent seulement qu'un compte
 * lise ou envoie la file d'un autre sur un appareil partagé.
 *
 * États (jamais présentés comme « enregistré » côté serveur) :
 *   draft    : saisie locale non soumise ;
 *   queued   : prête à partir au retour du réseau ;
 *   syncing  : envoi en cours ;
 *   synced   : accusé de réception serveur vérifiable reçu ;
 *   conflict : version serveur divergente, résolution explicite requise ;
 *   rejected : refus serveur définitif (validation, droits, anomalie) ;
 *   failed   : tentatives épuisées, action manuelle requise.
 */
(function (global) {
  'use strict';

  var ns = global.LBEGF = global.LBEGF || {};

  function db() { return ns.db; }
  function vault() { return ns.vault; }
  function boot() { return global.LBEGF_PWA || {}; }

  function uuid() {
    if (global.crypto && typeof global.crypto.randomUUID === 'function') {
      return global.crypto.randomUUID();
    }
    var bytes = new Uint8Array(16);
    global.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    var hex = '';
    for (var i = 0; i < 16; i += 1) {
      hex += (bytes[i] + 0x100).toString(16).slice(1);
    }
    return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
  }

  function iso(ms) {
    return new Date(ms || Date.now()).toISOString();
  }

  function scopeOf() {
    var user = boot().user;
    return user && user.accountScope ? user.accountScope : 'public';
  }

  function retentionMs() {
    var days = parseInt((boot().vault || {}).retentionDays, 10);
    if (!days || days < 1) {
      days = 7;
    }
    return days * 86400000;
  }

  function expiryFrom(nowMs) {
    return iso(nowMs + retentionMs());
  }

  /* ---------- Brouillons ---------- */

  function saveDraft(params) {
    var scope = scopeOf();
    var draftId = scope + ':' + params.formKey;
    var now = Date.now();
    return vault().encrypt(scope, params.payload, 'draft:' + draftId).then(function (payloadCipher) {
      return vault().encrypt(scope, {
        label: params.label || '',
        snapshot: params.baseSnapshot || null
      }, 'draft:' + draftId).then(function (metaCipher) {
        return db().get(db().STORES.DRAFTS, draftId).then(function (existing) {
          var record = {
            draft_id: draftId,
            account_scope: scope,
            type: params.type,
            action: params.action,
            schema_version: params.schemaVersion || 1,
            entity_key: params.entityKey || '',
            base_fingerprint: params.baseFingerprint || '',
            form_url: params.formUrl || '',
            payload: payloadCipher,
            meta: metaCipher,
            created_at: (existing && existing.created_at) || iso(now),
            updated_at: iso(now),
            expires_at: expiryFrom(now)
          };
          return db().put(db().STORES.DRAFTS, record).then(function () { return draftId; });
        });
      });
    });
  }

  function listDrafts(scope) {
    var target = scope || scopeOf();
    return db().byIndex(db().STORES.DRAFTS, 'by_account', target).then(function (rows) {
      return hydrateAll(target, rows, 'draft');
    });
  }

  function removeDraft(draftId, scope) {
    return db().get(db().STORES.DRAFTS, draftId).then(function (record) {
      if (!record || record.account_scope !== (scope || scopeOf())) {
        return false;
      }
      return db().remove(db().STORES.DRAFTS, draftId).then(function () { return true; });
    });
  }

  /* ---------- File d'attente ---------- */

  /**
   * Met une opération en attente de synchronisation. Le payload est chiffré
   * avant écriture ; l'empreinte de base vient du serveur (jamais calculée
   * côté client).
   */
  function queueOperation(params) {
    var scope = params.accountScope || scopeOf();
    var operationId = params.operationId || uuid();
    var now = Date.now();
    var aad = 'op:' + operationId;

    return vault().encrypt(scope, params.payload, aad).then(function (payloadCipher) {
      return vault().encrypt(scope, {
        label: params.label || '',
        snapshot: params.baseSnapshot || null,
        server: null,
        errors: null
      }, aad).then(function (metaCipher) {
        var record = {
          operation_id: operationId,
          account_scope: scope,
          user_id: params.userId || null,
          type: params.type,
          action: params.action,
          schema_version: params.schemaVersion || 1,
          entity_id: params.entityId || null,
          entity_key: params.entityKey || '',
          base_fingerprint: params.baseFingerprint || '',
          payload: payloadCipher,
          meta: metaCipher,
          state: 'queued',
          attempts: 0,
          last_status: '',
          last_error: '',
          last_code: '',
          resolution: '',
          requires_input: !!params.requiresInput,
          next_attempt_at: 0,
          created_at: iso(now),
          updated_at: iso(now),
          expires_at: expiryFrom(now),
          form_url: params.formUrl || ''
        };
        return db().put(db().STORES.OUTBOX, record).then(function () { return operationId; });
      });
    });
  }

  function getOperation(operationId) {
    return db().get(db().STORES.OUTBOX, operationId);
  }

  function updateOperation(operationId, patch) {
    return db().get(db().STORES.OUTBOX, operationId).then(function (record) {
      if (!record) {
        return null;
      }
      var merged = {};
      Object.keys(record).forEach(function (key) { merged[key] = record[key]; });
      Object.keys(patch || {}).forEach(function (key) { merged[key] = patch[key]; });
      merged.updated_at = iso();
      return db().put(db().STORES.OUTBOX, merged).then(function () { return merged; });
    });
  }

  /** Met à jour la partie chiffrée « meta » (label/snapshot/server/errors). */
  function updateMeta(operationId, mutate) {
    return db().get(db().STORES.OUTBOX, operationId).then(function (record) {
      if (!record) {
        return null;
      }
      return vault().decrypt(record.account_scope, record.meta).then(function (meta) {
        var next = mutate(meta || { label: '', snapshot: null, server: null, errors: null });
        return vault().encrypt(record.account_scope, next, 'op:' + operationId).then(function (cipher) {
          return updateOperation(operationId, { meta: cipher });
        });
      });
    });
  }

  function listOperations(scope, states) {
    var target = scope || scopeOf();
    return db().byIndex(db().STORES.OUTBOX, 'by_account', target).then(function (rows) {
      var filtered = states && states.length
        ? rows.filter(function (row) { return states.indexOf(row.state) !== -1; })
        : rows;
      return hydrateAll(target, filtered, 'operation');
    });
  }

  function listReady(scope) {
    var now = Date.now();
    return listOperations(scope, ['queued', 'failed']).then(function (ops) {
      return ops
        .filter(function (op) { return !op.requires_input; })
        .filter(function (op) { return !op.next_attempt_at || op.next_attempt_at <= now; })
        .sort(function (a, b) { return String(a.created_at) < String(b.created_at) ? -1 : 1; });
    });
  }

  function removeOperation(operationId, scope) {
    return db().get(db().STORES.OUTBOX, operationId).then(function (record) {
      if (!record || record.account_scope !== (scope || scopeOf())) {
        return false;
      }
      return db().remove(db().STORES.OUTBOX, operationId).then(function () { return true; });
    });
  }

  function counts(scope) {
    var target = scope || scopeOf();
    return Promise.all([
      db().byIndex(db().STORES.OUTBOX, 'by_account', target),
      db().byIndex(db().STORES.DRAFTS, 'by_account', target)
    ]).then(function (result) {
      var out = {
        drafts: result[1].length,
        queued: 0,
        syncing: 0,
        synced: 0,
        conflict: 0,
        rejected: 0,
        failed: 0,
        pending: 0
      };
      result[0].forEach(function (row) {
        if (out[row.state] !== undefined) {
          out[row.state] += 1;
        }
        if (row.state === 'queued' || row.state === 'syncing' || row.state === 'failed') {
          out.pending += 1;
        }
      });
      return out;
    });
  }

  /* ---------- Déchiffrement pour affichage / envoi ---------- */

  function hydrateAll(scope, rows, kind) {
    var chain = Promise.resolve([]);
    var out = [];
    rows.forEach(function (row) {
      chain = chain.then(function () {
        return vault().decrypt(scope, row.payload).then(function (payload) {
          return vault().decrypt(scope, row.meta).then(function (meta) {
            var hydrated = {};
            Object.keys(row).forEach(function (key) { hydrated[key] = row[key]; });
            hydrated.payload = payload;
            hydrated.label = (meta && meta.label) || defaultLabel(row);
            hydrated.base_snapshot = meta ? meta.snapshot : null;
            hydrated.server = meta ? meta.server : null;
            hydrated.errors = meta ? meta.errors : null;
            hydrated.kind = kind;
            out.push(hydrated);
          });
        }).catch(function () {
          // Enregistrement illisible (verrouillé, corrompu, autre appareil) :
          // on le signale sans jamais exposer de contenu brut.
          var locked = {};
          Object.keys(row).forEach(function (key) { locked[key] = row[key]; });
          locked.payload = null;
          locked.label = defaultLabel(row);
          locked.unreadable = true;
          locked.kind = kind;
          out.push(locked);
        });
      });
    });
    return chain.then(function () {
      return out.sort(function (a, b) { return String(a.created_at) < String(b.created_at) ? -1 : 1; });
    });
  }

  function defaultLabel(row) {
    var labels = {
      rapport_jour: 'Rapport du jour',
      suivi_hebdo: 'Suivi hebdomadaire',
      public_register: 'Demande d’inscription'
    };
    return (labels[row.type] || row.type || 'Saisie') + ' — ' + String(row.created_at || '').slice(0, 10);
  }

  /* ---------- Expiration, purge, ré-chiffrement, recovery ---------- */

  function expireStale(scope) {
    var now = iso();
    var target = scope || null; // null = tous les comptes (chaque compte purge le sien au boot)
    var removed = 0;
    var stores = [db().STORES.DRAFTS, db().STORES.OUTBOX, db().STORES.RECOVERY];
    var keyPaths = { drafts: 'draft_id', outbox: 'operation_id', recovery: 'recovery_id' };
    var chain = Promise.resolve(0);
    stores.forEach(function (store) {
      chain = chain.then(function () {
        return db().all(store).then(function (rows) {
          var jobs = Promise.resolve();
          rows.forEach(function (row) {
            if (target && row.account_scope !== target) {
              return;
            }
            if (row.expires_at && row.expires_at < now) {
              jobs = jobs.then(function () {
                return db().remove(store, row[keyPaths[store]]).then(function () { removed += 1; });
              });
            }
          });
          return jobs;
        });
      });
    });
    return chain.then(function () { return removed; });
  }

  /** Purge complète d'un compte : brouillons, file, recovery et clé locale. */
  function purgeAccount(scope) {
    var target = scope || scopeOf();
    var stores = [db().STORES.DRAFTS, db().STORES.OUTBOX, db().STORES.RECOVERY];
    var keyPaths = { drafts: 'draft_id', outbox: 'operation_id', recovery: 'recovery_id' };
    var chain = Promise.resolve();
    stores.forEach(function (store) {
      chain = chain.then(function () {
        return db().byIndex(store, 'by_account', target).then(function (rows) {
          var jobs = Promise.resolve();
          rows.forEach(function (row) {
            jobs = jobs.then(function () { return db().remove(store, row[keyPaths[store]]); });
          });
          return jobs;
        });
      });
    });
    return chain.then(function () {
      return vault().wipe(target);
    }).then(function () { return true; });
  }

  /** Ré-chiffre tout le compte lors d'une rotation de clé (simple ↔ PIN). */
  function reencryptAll(scope, oldKey, newKey) {
    var stores = [db().STORES.DRAFTS, db().STORES.OUTBOX, db().STORES.RECOVERY];
    var keyPaths = { drafts: 'draft_id', outbox: 'operation_id', recovery: 'recovery_id' };
    var total = 0;
    var chain = Promise.resolve();
    stores.forEach(function (store) {
      chain = chain.then(function () {
        return db().byIndex(store, 'by_account', scope).then(function (rows) {
          var jobs = Promise.resolve();
          rows.forEach(function (row) {
            jobs = jobs.then(function () {
              var fields = ['payload', 'meta', 'fields'];
              var job = Promise.resolve();
              fields.forEach(function (field) {
                if (!row[field]) {
                  return;
                }
                job = job.then(function () {
                  return vault().decrypt(scope, row[field], oldKey).then(function (plain) {
                    return encryptWith(newKey, plain, row[field].aad).then(function (cipher) {
                      row[field] = cipher;
                    });
                  });
                });
              });
              return job.then(function () {
                return db().put(store, row).then(function () { total += 1; });
              });
            });
          });
          return jobs;
        });
      });
    });
    return chain.then(function () { return total; });
  }

  function encryptWith(key, value, aad) {
    var iv = new Uint8Array(12);
    global.crypto.getRandomValues(iv);
    var params = { name: 'AES-GCM', iv: iv };
    if (aad) {
      params.additionalData = new TextEncoder().encode(aad);
    }
    var subtle = global.crypto.subtle;
    return subtle.encrypt(params, key, new TextEncoder().encode(JSON.stringify(value === undefined ? null : value)))
      .then(function (data) {
        function b64(bytes) {
          var binary = '';
          var view = new Uint8Array(bytes);
          for (var i = 0; i < view.length; i += 1) { binary += String.fromCharCode(view[i]); }
          return global.btoa(binary);
        }
        return { v: 1, iv: b64(iv), data: b64(data), aad: aad || null };
      });
  }

  /* ---------- Saisies capturées par le Service Worker ---------- */

  function saveRecovery(params) {
    var scope = params.account_scope || 'public';
    var recoveryId = uuid();
    var now = Date.now();
    var aad = 'recovery:' + recoveryId;
    return vault().encrypt(scope, params.fields, aad).then(function (cipher) {
      return db().put(db().STORES.RECOVERY, {
        recovery_id: recoveryId,
        account_scope: scope,
        action: params.action,
        fields: cipher,
        url: params.url || '',
        created_at: iso(now),
        updated_at: iso(now),
        expires_at: expiryFrom(now)
      }).then(function () { return recoveryId; });
    });
  }

  /**
   * Second filet contre la persistance d'un secret : même si une saisie
   * interrompue en contenait un (capture par une ancienne version du Service
   * Worker), il n'entre jamais dans la file d'attente.
   */
  function isSecretField(key) {
    return /pass(word|phrase)?|pwd|secret|code_pin|otp|token/i.test(String(key));
  }

  var RECOVERY_MAP = {
    save_rapport_jour: { type: 'rapport_jour', action: 'save' },
    save_suivi: { type: 'suivi_hebdo', action: 'save' },
    register: { type: 'public_register', action: 'submit', requiresInput: true }
  };

  /**
   * Transforme les saisies capturées par le Service Worker en opérations en
   * attente, puis les supprime du store de recovery. Seules les lignes du
   * compte courant sont adoptées : la file d'un autre compte n'apparaît
   * jamais (plan §5.3).
   */
  function adoptRecovery(scope) {
    var target = scope || scopeOf();
    return db().byIndex(db().STORES.RECOVERY, 'by_account', target).then(function (rows) {
      var jobs = Promise.resolve(0);
      rows.forEach(function (row) {
        jobs = jobs.then(function (count) {
          var mapping = RECOVERY_MAP[row.action];
          if (!mapping) {
            return db().remove(db().STORES.RECOVERY, row.recovery_id).then(function () { return count; });
          }
          return vault().decrypt(target, row.fields).then(function (fields) {
            var payload = {};
            Object.keys(fields || {}).forEach(function (key) {
              if (key === 'csrf' || key === 'action' || isSecretField(key)) {
                return; // jeton et secrets ne quittent jamais l'appareil (plan §6.1)
              }
              payload[key] = fields[key];
            });
            return queueOperation({
              accountScope: target,
              type: mapping.type,
              action: mapping.action,
              schemaVersion: 1,
              payload: payload,
              baseFingerprint: '',
              baseSnapshot: null,
              formUrl: row.url || '',
              label: defaultLabel({ type: mapping.type, created_at: row.created_at }) + ' (envoi interrompu)',
              requiresInput: !!mapping.requiresInput
            }).then(function () {
              return db().remove(db().STORES.RECOVERY, row.recovery_id);
            }).then(function () { return count + 1; });
          }).catch(function () {
            return count;
          });
        });
      });
      return jobs;
    });
  }

  /* ---------- Métadonnées de synchronisation ---------- */

  function setMetaValue(key, value) {
    return db().put(db().STORES.SYNC_META, { key: key, value: value, updated_at: iso() });
  }

  function getMetaValue(key) {
    return db().get(db().STORES.SYNC_META, key).then(function (row) {
      return row ? row.value : null;
    });
  }

  /** Indique au Service Worker quel compte est actif sur cet onglet. */
  function publishScopeHint() {
    return setMetaValue('scope_hint', {
      account_scope: scopeOf(),
      published_at: iso()
    });
  }

  ns.outbox = {
    uuid: uuid,
    scopeOf: scopeOf,
    retentionMs: retentionMs,
    saveDraft: saveDraft,
    listDrafts: listDrafts,
    removeDraft: removeDraft,
    queueOperation: queueOperation,
    getOperation: getOperation,
    updateOperation: updateOperation,
    updateMeta: updateMeta,
    listOperations: listOperations,
    listReady: listReady,
    removeOperation: removeOperation,
    counts: counts,
    expireStale: expireStale,
    purgeAccount: purgeAccount,
    reencryptAll: reencryptAll,
    saveRecovery: saveRecovery,
    adoptRecovery: adoptRecovery,
    setMetaValue: setMetaValue,
    getMetaValue: getMetaValue,
    publishScopeHint: publishScopeHint
  };
})(typeof window !== 'undefined' ? window : self);
