/**
 * PWA - IndexedDB versionné (plan §6.1).
 * ================================================================
 * Base `lbe_pwa`, stores distincts :
 *  - `drafts`      : brouillons de formulaires non soumis ;
 *  - `outbox`      : opérations prêtes à être synchronisées (et leurs états) ;
 *  - `sync_meta`   : curseurs, versions de protocole, dernier état ;
 *  - `crypto_meta` : paramètres cryptographiques (jamais de secret en clair) ;
 *  - `recovery`    : saisies capturées par le Service Worker après une coupure.
 *
 * Aucun mot de passe, cookie de session, jeton CSRF ou token n'y est stocké.
 * Le même fichier est importé par la page ET par le Service Worker
 * (`importScripts`) : un seul schéma, une seule version.
 */
(function (global) {
  'use strict';

  var DB_NAME = 'lbe_pwa';
  var DB_VERSION = 1;

  var STORES = {
    DRAFTS: 'drafts',
    OUTBOX: 'outbox',
    SYNC_META: 'sync_meta',
    CRYPTO_META: 'crypto_meta',
    RECOVERY: 'recovery'
  };

  var dbPromise = null;

  function supported() {
    return typeof global.indexedDB !== 'undefined' && !!global.indexedDB;
  }

  function open() {
    if (dbPromise) {
      return dbPromise;
    }
    dbPromise = new Promise(function (resolve, reject) {
      if (!supported()) {
        reject(new Error('indexeddb_unavailable'));
        return;
      }
      var request = global.indexedDB.open(DB_NAME, DB_VERSION);

      request.onupgradeneeded = function () {
        var db = request.result;
        if (!db.objectStoreNames.contains(STORES.DRAFTS)) {
          var drafts = db.createObjectStore(STORES.DRAFTS, { keyPath: 'draft_id' });
          drafts.createIndex('by_account', 'account_scope', { unique: false });
          drafts.createIndex('by_updated', 'updated_at', { unique: false });
        }
        if (!db.objectStoreNames.contains(STORES.OUTBOX)) {
          var outbox = db.createObjectStore(STORES.OUTBOX, { keyPath: 'operation_id' });
          outbox.createIndex('by_account', 'account_scope', { unique: false });
          outbox.createIndex('by_state', 'state', { unique: false });
          outbox.createIndex('by_created', 'created_at', { unique: false });
        }
        if (!db.objectStoreNames.contains(STORES.SYNC_META)) {
          db.createObjectStore(STORES.SYNC_META, { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains(STORES.CRYPTO_META)) {
          db.createObjectStore(STORES.CRYPTO_META, { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains(STORES.RECOVERY)) {
          var recovery = db.createObjectStore(STORES.RECOVERY, { keyPath: 'recovery_id' });
          recovery.createIndex('by_account', 'account_scope', { unique: false });
        }
      };

      request.onsuccess = function () { resolve(request.result); };
      request.onerror = function () { reject(request.error || new Error('indexeddb_open_failed')); };
      request.onblocked = function () { reject(new Error('indexeddb_blocked')); };
    });

    // Un échec ne doit pas condamner la session : on autorise une nouvelle tentative.
    dbPromise.catch(function () { dbPromise = null; });
    return dbPromise;
  }

  /**
   * Exécute $fn(store) dans une transaction. $fn peut renvoyer une
   * IDBRequest (sa `result` est résolue) ou rien.
   */
  function tx(storeName, mode, fn) {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var transaction = db.transaction([storeName], mode);
        var store = transaction.objectStore(storeName);
        var request = null;
        try {
          request = fn(store) || null;
        } catch (error) {
          try { transaction.abort(); } catch (ignored) { /* déjà morte */ }
          reject(error);
          return;
        }
        transaction.oncomplete = function () {
          resolve(request ? request.result : undefined);
        };
        transaction.onerror = function () { reject(transaction.error || new Error('transaction_error')); };
        transaction.onabort = function () { reject(transaction.error || new Error('transaction_aborted')); };
      });
    });
  }

  function put(storeName, value) {
    return tx(storeName, 'readwrite', function (store) { return store.put(value); });
  }

  function get(storeName, key) {
    return tx(storeName, 'readonly', function (store) { return store.get(key); });
  }

  function remove(storeName, key) {
    return tx(storeName, 'readwrite', function (store) { return store.delete(key); });
  }

  function all(storeName) {
    return tx(storeName, 'readonly', function (store) { return store.getAll(); });
  }

  function byIndex(storeName, indexName, value) {
    return tx(storeName, 'readonly', function (store) {
      return store.index(indexName).getAll(value);
    });
  }

  function clear(storeName) {
    return tx(storeName, 'readwrite', function (store) { return store.clear(); });
  }

  /** Estimation du quota restant (plan §16 : indicateur d'espace). */
  function estimate() {
    if (!global.navigator || !global.navigator.storage || !global.navigator.storage.estimate) {
      return Promise.resolve(null);
    }
    return global.navigator.storage.estimate().catch(function () { return null; });
  }

  global.LBEGF = global.LBEGF || {};
  global.LBEGF.db = {
    DB_NAME: DB_NAME,
    DB_VERSION: DB_VERSION,
    STORES: STORES,
    supported: supported,
    open: open,
    put: put,
    get: get,
    remove: remove,
    all: all,
    byIndex: byIndex,
    clear: clear,
    estimate: estimate
  };
})(typeof window !== 'undefined' ? window : self);
