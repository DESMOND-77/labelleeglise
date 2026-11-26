/**
 * PWA - Coffre de chiffrement local (Web Crypto, plan §6.2).
 * ================================================================
 * AES-GCM 256 bits, IV unique par chiffrement, données additionnelles
 * (AAD) liées à l'identifiant de l'enregistrement : une copie déplacée
 * vers une autre clé ou un autre enregistrement ne se déchiffre pas.
 *
 * Deux modes (décision produit §17.1, configurable serveur) :
 *  - `simple` : clé appareil non extractible conservée dans IndexedDB.
 *    Protège contre la lecture brute du stockage ; PAS contre un XSS ni
 *    contre un appareil compromis. Utilisable aussi par le Service Worker.
 *  - `pin`    : clé appareil enveloppée (wrapped) par une clé dérivée du
 *    code PIN de l'utilisateur (PBKDF2-SHA256, sel aléatoire). Le Service
 *    Worker ne peut pas la déverrouiller : la saisie reste sous contrôle
 *    humain.
 *
 * La clé n'est JAMAIS envoyée au serveur. Les conversions simple ↔ PIN
 * font tourner une nouvelle clé appareil et ré-chiffrent tous les
 * enregistrements du compte (pas de donnée laissée sous l'ancienne clé).
 */
(function (global) {
  'use strict';

  var ns = global.LBEGF = global.LBEGF || {};
  var subtle = global.crypto && global.crypto.subtle ? global.crypto.subtle : null;

  var unlocked = {}; // scope -> CryptoKey (mémoire uniquement, jamais persisté tel quel en mode PIN)

  function boot() {
    return global.LBEGF_PWA || {};
  }

  function bootVault() {
    return boot().vault || {};
  }

  function bootMode() {
    return bootVault().mode === 'pin' ? 'pin' : 'simple';
  }

  function iterations() {
    var value = parseInt(bootVault().iterations, 10);
    return value >= 100000 ? value : 210000;
  }

  function supported() {
    return !!subtle && !!ns.db;
  }

  function metaKey(scope) {
    return 'vault:' + scope;
  }

  function utf8(str) {
    return new TextEncoder().encode(str);
  }

  function fromUtf8(bytes) {
    return new TextDecoder().decode(bytes);
  }

  function b64(bytes) {
    var binary = '';
    var view = new Uint8Array(bytes);
    for (var i = 0; i < view.length; i += 1) {
      binary += String.fromCharCode(view[i]);
    }
    return global.btoa(binary);
  }

  function unb64(str) {
    var binary = global.atob(str);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i += 1) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
  }

  function randomBytes(length) {
    var bytes = new Uint8Array(length);
    global.crypto.getRandomValues(bytes);
    return bytes;
  }

  function randomHex(length) {
    var hex = '';
    var bytes = randomBytes(Math.ceil(length / 2));
    for (var i = 0; i < bytes.length; i += 1) {
      hex += (bytes[i] + 0x100).toString(16).slice(1);
    }
    return hex.slice(0, length);
  }

  function getMeta(scope) {
    return ns.db.get(ns.db.STORES.CRYPTO_META, metaKey(scope));
  }

  function setMeta(record) {
    return ns.db.put(ns.db.STORES.CRYPTO_META, record);
  }

  function generateDeviceKey(extractable) {
    return subtle.generateKey({ name: 'AES-GCM', length: 256 }, extractable, ['encrypt', 'decrypt']);
  }

  function importDeviceKey(rawBytes) {
    return subtle.importKey('raw', rawBytes, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
  }

  function derivePinKey(pin, salt, iter) {
    return subtle.importKey('raw', utf8(pin), 'PBKDF2', false, ['deriveKey']).then(function (baseKey) {
      return subtle.deriveKey(
        { name: 'PBKDF2', salt: salt, iterations: iter, hash: 'SHA-256' },
        baseKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt']
      );
    });
  }

  function createSimpleVault(scope) {
    return generateDeviceKey(false).then(function (key) {
      unlocked[scope] = key;
      return setMeta({
        key: metaKey(scope),
        scope: scope,
        mode: 'simple',
        key_id: randomHex(12),
        created_at: new Date().toISOString(),
        crypto_key: key
      }).then(function () { return key; });
    });
  }

  /**
   * Clé utilisable sans interaction : mode `simple` uniquement.
   * Retourne null si le coffre est en mode PIN et verrouillé.
   */
  function autoKey(scope) {
    if (!supported()) {
      return Promise.reject(new Error('crypto_unavailable'));
    }
    if (unlocked[scope]) {
      return Promise.resolve(unlocked[scope]);
    }
    return getMeta(scope).then(function (meta) {
      if (!meta) {
        if (bootMode() === 'pin') {
          return null; // création du coffre PIN : passe par createPin()
        }
        return createSimpleVault(scope);
      }
      if (meta.mode === 'simple' && meta.crypto_key) {
        unlocked[scope] = meta.crypto_key;
        return meta.crypto_key;
      }
      return null;
    });
  }

  function status(scope) {
    if (!supported()) {
      return Promise.resolve({ supported: false, mode: bootMode(), locked: true, has_vault: false });
    }
    return getMeta(scope).then(function (meta) {
      if (!meta) {
        return { supported: true, mode: bootMode(), locked: bootMode() === 'pin', has_vault: false };
      }
      return {
        supported: true,
        mode: meta.mode,
        locked: meta.mode === 'pin' && !unlocked[scope],
        has_vault: true,
        key_id: meta.key_id || '',
        created_at: meta.created_at || ''
      };
    });
  }

  /* ---------- Mode PIN ---------- */

  function createPin(scope, pin) {
    if (!supported()) {
      return Promise.reject(new Error('crypto_unavailable'));
    }
    return rotateDeviceKey(scope, pin, 'pin');
  }

  function removePin(scope, pin) {
    if (!supported()) {
      return Promise.reject(new Error('crypto_unavailable'));
    }
    return unlock(scope, pin).then(function (ok) {
      if (!ok) {
        throw new Error('bad_pin');
      }
      return rotateDeviceKey(scope, null, 'simple');
    });
  }

  /**
   * Fait tourner une nouvelle clé appareil et ré-chiffre tout le compte.
   * `toMode` = 'pin' (enveloppe la clé avec le PIN) ou 'simple'.
   */
  function rotateDeviceKey(scope, pin, toMode) {
    var oldKey = unlocked[scope] || null;
    var previous = null;

    return getMeta(scope)
      .then(function (meta) {
        previous = meta || null;
        if (!oldKey && previous && previous.mode === 'simple' && previous.crypto_key) {
          oldKey = previous.crypto_key;
          unlocked[scope] = oldKey;
        }
        if (!oldKey && !previous) {
          return createSimpleVault(scope).then(function (key) { oldKey = key; });
        }
        if (!oldKey) {
          throw new Error('vault_locked');
        }
        return null;
      })
      .then(function () {
        return generateDeviceKey(true);
      })
      .then(function (freshExtractable) {
        return subtle.exportKey('raw', freshExtractable).then(function (raw) {
          return { raw: new Uint8Array(raw) };
        });
      })
      .then(function (material) {
        if (toMode !== 'pin') {
          return importDeviceKey(material.raw).then(function (newKey) {
            return applyRotation(scope, oldKey, newKey, {
              key: metaKey(scope),
              scope: scope,
              mode: 'simple',
              key_id: randomHex(12),
              created_at: new Date().toISOString(),
              crypto_key: newKey
            });
          });
        }
        var salt = randomBytes(16);
        var iter = iterations();
        return derivePinKey(pin, salt, iter).then(function (wrapKey) {
          var iv = randomBytes(12);
          return subtle.encrypt({ name: 'AES-GCM', iv: iv }, wrapKey, material.raw).then(function (wrapped) {
            return importDeviceKey(material.raw).then(function (newKey) {
              return applyRotation(scope, oldKey, newKey, {
                key: metaKey(scope),
                scope: scope,
                mode: 'pin',
                key_id: randomHex(12),
                created_at: new Date().toISOString(),
                salt: b64(salt),
                iterations: iter,
                wrapped: { iv: b64(iv), data: b64(wrapped) }
              });
            });
          });
        });
      });
  }

  function applyRotation(scope, oldKey, newKey, meta) {
    var reencrypt = ns.outbox && ns.outbox.reencryptAll
      ? ns.outbox.reencryptAll(scope, oldKey, newKey)
      : Promise.resolve(0);
    return reencrypt.then(function () {
      return setMeta(meta);
    }).then(function () {
      unlocked[scope] = newKey;
      return true;
    });
  }

  function unlock(scope, pin) {
    if (!supported()) {
      return Promise.resolve(false);
    }
    return getMeta(scope).then(function (meta) {
      if (!meta || meta.mode !== 'pin' || !meta.wrapped) {
        return false;
      }
      return derivePinKey(pin, unb64(meta.salt), meta.iterations || iterations())
        .then(function (wrapKey) {
          return subtle.decrypt(
            { name: 'AES-GCM', iv: unb64(meta.wrapped.iv), additionalData: utf8(metaKey(scope)) },
            wrapKey,
            unb64(meta.wrapped.data)
          );
        })
        .then(function (raw) {
          return importDeviceKey(new Uint8Array(raw));
        })
        .then(function (key) {
          unlocked[scope] = key;
          return true;
        })
        .catch(function () {
          return false;
        });
    });
  }

  function lock(scope) {
    delete unlocked[scope];
    return Promise.resolve(true);
  }

  /* ---------- Chiffrement des enregistrements ---------- */

  function encrypt(scope, value, aad) {
    return autoKey(scope).then(function (key) {
      if (!key) {
        throw new Error('vault_locked');
      }
      var iv = randomBytes(12);
      var plain = utf8(JSON.stringify(value === undefined ? null : value));
      var params = { name: 'AES-GCM', iv: iv };
      if (aad) {
        params.additionalData = utf8(aad);
      }
      return subtle.encrypt(params, key, plain).then(function (data) {
        return { v: 1, iv: b64(iv), data: b64(data), aad: aad || null };
      });
    });
  }

  function decrypt(scope, envelope, keyOverride) {
    if (!envelope) {
      return Promise.resolve(null);
    }
    var keyPromise = keyOverride
      ? Promise.resolve(keyOverride)
      : autoKey(scope).then(function (key) {
        if (!key) {
          throw new Error('vault_locked');
        }
        return key;
      });
    return keyPromise.then(function (key) {
      var params = { name: 'AES-GCM', iv: unb64(envelope.iv) };
      if (envelope.aad) {
        params.additionalData = utf8(envelope.aad);
      }
      return subtle.decrypt(params, key, unb64(envelope.data));
    }).then(function (plain) {
      return JSON.parse(fromUtf8(plain));
    });
  }

  /** Supprime clé et verrou d'un compte (purge locale, plan §5.3). */
  function wipe(scope) {
    delete unlocked[scope];
    return ns.db.remove(ns.db.STORES.CRYPTO_META, metaKey(scope));
  }

  ns.vault = {
    supported: supported,
    bootMode: bootMode,
    status: status,
    autoKey: autoKey,
    createPin: createPin,
    removePin: removePin,
    unlock: unlock,
    lock: lock,
    encrypt: encrypt,
    decrypt: decrypt,
    wipe: wipe
  };
})(typeof window !== 'undefined' ? window : self);
