/**
 * PWA - Adaptateurs EXPLICITES des formulaires compatibles (plan §1, §9).
 * ================================================================
 * Aucun formulaire n'est mis en file par défaut : seuls les formulaires
 * portant les attributs `data-pwa-type` / `data-pwa-action` (posés côté
 * serveur après audit) sont pris en charge.
 *
 * Comportement :
 *  - en ligne  : le flux HTML historique est inchangé ; le brouillon local
 *    éventuel est retiré (le Service Worker capture la saisie si le réseau
 *    tombe pendant l'envoi) ;
 *  - hors ligne : la soumission est interceptée, la saisie est chiffrée puis
 *    mise en attente ; l'interface confirme « Brouillon enregistré sur cet
 *    appareil — en attente de synchronisation », jamais « enregistré » ;
 *  - en continu : sauvegarde automatique d'un brouillon local (reprise après
 *    fermeture du navigateur), sans jamais stocker les champs listés dans
 *    `data-pwa-no-store` (mots de passe).
 */
(function (global) {
  'use strict';

  var ns = global.LBEGF = global.LBEGF || {};
  var attached = false;

  function boot() {
    return global.LBEGF_PWA || {};
  }

  function outbox() { return ns.outbox; }
  function vault() { return ns.vault; }

  /* ---------- Utilitaires de formulaire ---------- */

  function parsePath(name) {
    var match = /^([^\[]+)((?:\[[^\]]*\])*)$/.exec(name);
    if (!match) {
      return [name];
    }
    var segments = [match[1]];
    var rest = match[2] || '';
    var parts = rest.match(/\[([^\]]*)\]/g) || [];
    parts.forEach(function (part) {
      segments.push(part.slice(1, -1));
    });
    return segments;
  }

  /** FormData -> objet (clés imbriquées `a[b][c]`, tableaux `a[]`). */
  function collect(form, skipNames) {
    var skip = {};
    (skipNames || []).forEach(function (name) { skip[name] = true; });
    var data = {};
    var formData = new FormData(form);
    formData.forEach(function (value, key) {
      if (skip[key] || typeof value !== 'string') {
        return;
      }
      var segments = parsePath(key);
      var cursor = data;
      for (var i = 0; i < segments.length - 1; i += 1) {
        var segment = segments[i];
        if (segment === '') {
          return; // tableau non indexé : géré ci-dessous au dernier niveau
        }
        if (typeof cursor[segment] !== 'object' || cursor[segment] === null) {
          cursor[segment] = {};
        }
        cursor = cursor[segment];
      }
      var last = segments[segments.length - 1];
      if (last === '') {
        var parentKey = segments[segments.length - 2];
        if (!Array.isArray(data[parentKey])) {
          data[parentKey] = [];
        }
        data[parentKey].push(value);
      } else {
        cursor[last] = value;
      }
    });
    return data;
  }

  function skipListFor(form) {
    var skip = ['csrf', 'action', 'retour', '__pwa_scope'];
    var noStore = String(form.getAttribute('data-pwa-no-store') || '');
    noStore.split(',').forEach(function (name) {
      var clean = name.trim();
      if (clean) {
        skip.push(clean);
      }
    });
    return skip;
  }

  function formKeyFor(form, meta) {
    var entityKey = form.getAttribute('data-pwa-entity-key')
      || form.getAttribute('data-pwa-entity-id')
      || form.getAttribute('data-pwa-form-url')
      || 'new';
    return meta.type + ':' + entityKey;
  }

  function labelFor(meta, payload) {
    if (meta.type === 'rapport_jour') {
      var date = String(payload.date_rapport || '').slice(0, 10);
      var scope = payload.scope_type === 'centre' ? 'centre' : 'bacenta';
      return 'Rapport du jour — ' + (date || 'date inconnue') + ' (' + scope + ')';
    }
    if (meta.type === 'suivi_hebdo') {
      return 'Suivi hebdomadaire — semaine ' + (payload.semaine || '?');
    }
    if (meta.type === 'public_register') {
      return 'Demande d’inscription — ' + (payload.prenom || '') + ' ' + (payload.nom || '');
    }
    return meta.type + ' / ' + meta.action;
  }

  function metaFor(form) {
    return {
      type: form.getAttribute('data-pwa-type') || '',
      action: form.getAttribute('data-pwa-action') || '',
      schemaVersion: parseInt(form.getAttribute('data-pwa-schema-version') || '1', 10),
      baseFingerprint: form.getAttribute('data-pwa-base-fingerprint') || '',
      formUrl: form.getAttribute('data-pwa-form-url') || '',
      entityId: form.getAttribute('data-pwa-entity-id') || ''
    };
  }

  function baseSnapshotFor(form) {
    var raw = form.getAttribute('data-pwa-base-snapshot') || '';
    if (!raw) {
      return null;
    }
    try {
      return JSON.parse(raw);
    } catch (ignored) {
      return null;
    }
  }

  /* ---------- Toast (partagé avec le panneau) ---------- */

  function toast(message, kind, action) {
    var host = document.getElementById('pwaToasts');
    if (!host) {
      host = document.createElement('div');
      host.id = 'pwaToasts';
      host.className = 'pwa-toasts';
      document.body.appendChild(host);
    }
    var item = document.createElement('div');
    item.className = 'pwa-toast pwa-toast-' + (kind || 'info');
    item.setAttribute('role', 'status');

    var text = document.createElement('span');
    text.textContent = message;
    item.appendChild(text);

    if (action && action.label) {
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = action.label;
      button.addEventListener('click', function () {
        item.remove();
        action.onClick();
      });
      item.appendChild(button);
    }

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'pwa-toast-close';
    close.setAttribute('aria-label', 'Fermer');
    close.textContent = '×';
    close.addEventListener('click', function () { item.remove(); });
    item.appendChild(close);

    host.appendChild(item);
    setTimeout(function () {
      if (item.parentNode) {
        item.remove();
      }
    }, 9000);
    return item;
  }

  /* ---------- Brouillons ---------- */

  function saveDraftFor(form, meta, formKey) {
    if (!boot().offlineCapture || !outbox()) {
      return Promise.resolve(null);
    }
    var payload = collect(form, skipListFor(form));
    return outbox().saveDraft({
      type: meta.type,
      action: meta.action,
      schemaVersion: meta.schemaVersion,
      formKey: formKey,
      entityKey: form.getAttribute('data-pwa-entity-key') || '',
      payload: payload,
      baseFingerprint: meta.baseFingerprint,
      baseSnapshot: baseSnapshotFor(form),
      formUrl: meta.formUrl,
      label: labelFor(meta, payload)
    }).catch(function () {
      return null; // coffre verrouillé ou stockage plein : silencieux côté saisie
    });
  }

  function fillForm(form, payload) {
    Object.keys(payload || {}).forEach(function (key) {
      var value = payload[key];
      if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
        fillNested(form, key, value);
        return;
      }
      if (Array.isArray(value)) {
        value.forEach(function (item) {
          setFieldValue(form, key + '[]', item);
        });
        return;
      }
      setFieldValue(form, key, value === null ? '' : String(value));
    });
  }

  function fillNested(form, prefix, object) {
    Object.keys(object || {}).forEach(function (key) {
      var value = object[key];
      if (value !== null && typeof value === 'object') {
        fillNested(form, prefix + '[' + key + ']', value);
        return;
      }
      setFieldValue(form, prefix + '[' + key + ']', value === null ? '' : String(value));
    });
  }

  function setFieldValue(form, name, value) {
    var nodes = form.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]');
    nodes.forEach(function (node) {
      if (node.type === 'checkbox') {
        node.checked = node.value === value || value === 'on' || value === '1';
      } else if (node.type === 'radio') {
        node.checked = node.value === value;
      } else {
        node.value = value;
      }
    });
  }

  function offerDraftRestore(form, meta, formKey) {
    return outbox().listDrafts().then(function (drafts) {
      var draft = null;
      drafts.forEach(function (item) {
        if (item.draft_id === outbox().scopeOf() + ':' + formKey) {
          draft = item;
        }
      });
      if (!draft || !draft.payload) {
        return null;
      }
      var banner = document.createElement('div');
      banner.className = 'pwa-restore alert alert-info';
      banner.setAttribute('role', 'status');
      var text = document.createElement('span');
      text.innerHTML = '<i class="fa-solid fa-rotate-left"></i> ';
      text.appendChild(document.createTextNode(
        'Un brouillon local non envoyé a été retrouvé pour ce formulaire (' + draft.label + '). Rien n’a été enregistré sur le serveur.'
      ));
      banner.appendChild(text);

      var restore = document.createElement('button');
      restore.type = 'button';
      restore.className = 'btn btn-outline btn-sm';
      restore.textContent = 'Reprendre le brouillon';
      restore.addEventListener('click', function () {
        fillForm(form, draft.payload);
        banner.remove();
      });
      banner.appendChild(restore);

      var discard = document.createElement('button');
      discard.type = 'button';
      discard.className = 'btn btn-outline btn-sm';
      discard.textContent = 'Supprimer le brouillon';
      discard.addEventListener('click', function () {
        outbox().removeDraft(draft.draft_id).then(function () { banner.remove(); });
      });
      banner.appendChild(discard);

      form.parentNode.insertBefore(banner, form);
      return draft;
    }).catch(function () { return null; });
  }

  /* ---------- Mise en file hors ligne ---------- */

  function queueFromForm(form, meta, formKey) {
    var payload = collect(form, skipListFor(form));
    var requiresInput = meta.type === 'public_register';
    return outbox().queueOperation({
      type: meta.type,
      action: meta.action,
      schemaVersion: meta.schemaVersion,
      entityId: meta.entityId ? parseInt(meta.entityId, 10) : null,
      entityKey: form.getAttribute('data-pwa-entity-key') || '',
      payload: payload,
      baseFingerprint: meta.baseFingerprint,
      baseSnapshot: baseSnapshotFor(form),
      formUrl: meta.formUrl,
      label: labelFor(meta, payload),
      requiresInput: requiresInput
    }).then(function (operationId) {
      return outbox().removeDraft(outbox().scopeOf() + ':' + formKey)
        .then(function () { return operationId; });
    });
  }

  /** Pose (ou met à jour) le champ caché `__pwa_scope` d'un formulaire. */
  function stampScope(form, scope) {
    if (!scope || typeof document === 'undefined') {
      return;
    }
    var field = form.querySelector('input[name="__pwa_scope"]');
    if (!field) {
      field = document.createElement('input');
      field.type = 'hidden';
      field.name = '__pwa_scope';
      form.appendChild(field);
    }
    field.value = String(scope);
  }

  /* ---------- Attache ---------- */

  function attach() {
    if (attached || typeof document === 'undefined') {
      return;
    }
    attached = true;
    if (!boot().enabled) {
      return;
    }

    var forms = document.querySelectorAll('form[data-pwa-type]');
    forms.forEach(function (form) {
      var meta = metaFor(form);
      if (!meta.type || !meta.action) {
        return;
      }
      var formKey = formKeyFor(form, meta);

      if (boot().offlineCapture && outbox()) {
        var timer = null;
        var onChange = function () {
          if (timer) {
            clearTimeout(timer);
          }
          timer = setTimeout(function () { saveDraftFor(form, meta, formKey); }, 900);
        };
        form.addEventListener('input', onChange);
        form.addEventListener('change', onChange);
        offerDraftRestore(form, meta, formKey);
      }

      form.addEventListener('submit', function (event) {
        if (global.navigator && global.navigator.onLine !== false) {
          // Flux en ligne inchangé (plan §3.3). Le brouillon local devient
          // inutile ; le Service Worker capture la saisie si le réseau tombe.
          if (outbox()) {
            outbox().removeDraft(outbox().scopeOf() + ':' + formKey).catch(function () { return null; });
            // Marqueur de compte pour le Service Worker : si le réseau tombe
            // pendant cet envoi, la saisie capturée sera rattachée au bon
            // compte (jamais à la file d'un autre utilisateur - plan §5.3).
            // Champ ignoré par le serveur, jamais conservé dans un brouillon.
            stampScope(form, outbox().scopeOf());
          }
          return;
        }
        event.preventDefault();
        if (!boot().offlineCapture || !outbox()) {
          toast('La saisie hors ligne est désactivée sur cette installation.', 'warn');
          return;
        }
        queueFromForm(form, meta, formKey).then(function () {
          if (ns.sync && ns.sync.refreshCounts) {
            ns.sync.refreshCounts();
            ns.sync.registerBackgroundSync();
          }
          var message = meta.type === 'public_register'
            ? 'Demande enregistrée sur cet appareil — en attente de synchronisation. Le mot de passe sera demandé à l’envoi.'
            : 'Brouillon enregistré sur cet appareil — en attente de synchronisation. Rien n’est enregistré sur le serveur pour le moment.';
          toast(message, 'pending', ns.panel ? {
            label: 'Voir la file',
            onClick: function () { ns.panel.open(); }
          } : null);
        }).catch(function (error) {
          var locked = String(error && error.message) === 'vault_locked';
          toast(
            locked
              ? 'Coffre verrouillé : déverrouillez-le pour enregistrer cette saisie hors ligne.'
              : 'Impossible d’enregistrer la saisie sur cet appareil.',
            'warn',
            locked && ns.panel ? { label: 'Déverrouiller', onClick: function () { ns.panel.open(); } } : null
          );
        });
      });
    });
  }

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', attach);
    } else {
      attach();
    }
  }

  ns.forms = {
    collect: collect,
    attach: attach,
    toast: toast,
    labelFor: labelFor
  };
})(typeof window !== 'undefined' ? window : self);
