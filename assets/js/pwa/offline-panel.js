/**
 * PWA - Panneau « Données hors ligne » (file, brouillons, coffre, conflits).
 * ================================================================
 * Même interface en ligne (tiroir) et hors ligne (le tiroir ne dépend que
 * d'IndexedDB + Web Crypto). L'écran serveur `?page=pwa_offline` réutilise
 * exactement ce rendu pour la partie locale.
 *
 * Aucune donnée locale n'y est présentée comme « enregistrée » : chaque
 * ligne rappelle son état (brouillon / en attente / synchronisé le …).
 */
(function (global) {
  'use strict';

  var ns = global.LBEGF = global.LBEGF || {};
  var drawer = null;
  var body = null;

  function boot() {
    return global.LBEGF_PWA || {};
  }

  function outbox() { return ns.outbox; }
  function vault() { return ns.vault; }
  function sync() { return ns.sync; }
  function toast(message, kind, action) {
    if (ns.forms && ns.forms.toast) {
      return ns.forms.toast(message, kind, action);
    }
    return null;
  }

  var STATE_LABELS = {
    draft: ['Brouillon local', 'neutral'],
    queued: ['En attente de synchronisation', 'warn'],
    syncing: ['Synchronisation en cours', 'info'],
    synced: ['Synchronisé', 'present'],
    conflict: ['Conflit à résoudre', 'absent'],
    rejected: ['Refusé par le serveur', 'absent'],
    failed: ['Échec définitif', 'absent']
  };

  var FIELD_LABELS = {
    nb_presents: 'Nombre de présents',
    nb_adultes: 'Adultes',
    nb_enfants: 'Enfants',
    nb_anciens: 'Anciens',
    nb_nouveaux: 'Nouveaux',
    nb_nes_de_nouveau: 'Nés de nouveau',
    offrande: 'Offrande',
    assistants: 'Assistants',
    livre_enseigne: 'Livre enseigné',
    chapitre_enseigne: 'Chapitre enseigné',
    date_rapport: 'Date du rapport',
    centre_id: 'Centre',
    bacenta_id: 'Bacenta',
    scope_type: 'Portée',
    membre: 'Fiche',
    semaine: 'Semaine'
  };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (text !== undefined && text !== null) {
      node.textContent = text;
    }
    return node;
  }

  function formatDate(value) {
    if (!value) {
      return '';
    }
    var date = new Date(value);
    if (isNaN(date.getTime())) {
      return String(value);
    }
    return date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }

  function badge(state) {
    var info = STATE_LABELS[state] || [state, 'neutral'];
    return el('span', 'badge ' + info[1], info[0]);
  }

  /* ---------- Structure ---------- */

  function ensureDrawer() {
    if (drawer) {
      return drawer;
    }
    drawer = el('div', 'pwa-drawer');
    drawer.id = 'pwaDrawer';
    drawer.hidden = true;

    var backdrop = el('div', 'pwa-drawer-backdrop');
    backdrop.addEventListener('click', close);
    drawer.appendChild(backdrop);

    var panel = el('aside', 'pwa-drawer-panel');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-label', 'Données hors ligne');

    var header = el('header', 'pwa-drawer-head');
    header.appendChild(el('h2', null, 'Données hors ligne'));
    var closeBtn = el('button', 'pwa-drawer-close');
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Fermer');
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', close);
    header.appendChild(closeBtn);
    panel.appendChild(header);

    body = el('div', 'pwa-drawer-body');
    panel.appendChild(body);
    drawer.appendChild(panel);
    document.body.appendChild(drawer);
    return drawer;
  }

  function open() {
    if (!boot().enabled || !outbox()) {
      if (boot().panelUrl) {
        global.location.href = boot().panelUrl;
      }
      return;
    }
    ensureDrawer();
    drawer.hidden = false;
    document.body.classList.add('pwa-drawer-open');
    renderInto(body);
  }

  function close() {
    if (drawer) {
      drawer.hidden = true;
      document.body.classList.remove('pwa-drawer-open');
    }
  }

  /* ---------- Rendu ---------- */

  function renderInto(target) {
    target.innerHTML = '';
    target.appendChild(el('p', 'pwa-loading', 'Lecture du stockage local…'));

    Promise.all([
      outbox().counts(),
      vault().status(outbox().scopeOf()),
      outbox().listOperations(null),
      outbox().listDrafts()
    ]).then(function (results) {
      target.innerHTML = '';
      renderSummary(target, results[0]);
      renderVault(target, results[1]);
      renderOperations(target, results[2]);
      renderDrafts(target, results[3]);
      renderDanger(target);
    }).catch(function (error) {
      target.innerHTML = '';
      target.appendChild(el('p', 'alert alert-warning',
        'Stockage local indisponible : ' + String(error && error.message ? error.message : error)));
    });
  }

  function renderSummary(target, counts) {
    var section = el('section', 'pwa-section');
    section.appendChild(el('h3', null, 'État de la synchronisation'));

    var state = sync() ? sync().getState() : { status: 'boot' };
    var line = el('p', 'pwa-hint');
    var online = !global.navigator || global.navigator.onLine !== false;
    line.textContent = (online ? 'En ligne.' : 'Hors connexion.')
      + ' En attente : ' + counts.pending
      + ' · Conflits : ' + counts.conflict
      + ' · Brouillons : ' + counts.drafts
      + (state.lastSyncAt ? ' · Synchronisé le ' + formatDate(state.lastSyncAt) : '');
    section.appendChild(line);

    var actions = el('div', 'pwa-actions');
    var syncBtn = el('button', 'btn btn-primary btn-sm');
    syncBtn.type = 'button';
    syncBtn.textContent = 'Synchroniser maintenant';
    syncBtn.addEventListener('click', function () {
      syncBtn.disabled = true;
      sync().syncNow('manual').then(function () {
        syncBtn.disabled = false;
        renderInto(target);
      });
    });
    actions.appendChild(syncBtn);

    if (boot().panelUrl && global.location.search.indexOf('page=pwa_offline') === -1) {
      var link = el('a', 'btn btn-outline btn-sm', 'Tout gérer (page dédiée)');
      link.href = boot().panelUrl;
      actions.appendChild(link);
    }
    section.appendChild(actions);
    target.appendChild(section);
  }

  function renderVault(target, status) {
    var section = el('section', 'pwa-section');
    section.appendChild(el('h3', null, 'Coffre local'));
    var scope = outbox().scopeOf();

    var info = el('p', 'pwa-hint');
    if (!status.supported) {
      info.textContent = 'Chiffrement indisponible sur ce navigateur : la saisie hors ligne est désactivée.';
      section.appendChild(info);
      target.appendChild(section);
      return;
    }
    info.textContent = status.mode === 'pin'
      ? 'Coffre protégé par code PIN ' + (status.locked ? '(verrouillé).' : '(déverrouillé).')
      : 'Protection simplifiée (clé appareil). Sur un appareil partagé, protégez le coffre par un code PIN.';
    section.appendChild(info);

    var actions = el('div', 'pwa-actions');

    if (status.mode === 'pin' && status.locked) {
      var pinInput = el('input', 'pwa-pin-input');
      pinInput.type = 'password';
      pinInput.placeholder = 'Code PIN / phrase secrète';
      pinInput.autocomplete = 'current-password';
      var unlockBtn = el('button', 'btn btn-primary btn-sm', 'Déverrouiller');
      unlockBtn.type = 'button';
      unlockBtn.addEventListener('click', function () {
        vault().unlock(scope, pinInput.value).then(function (ok) {
          if (!ok) {
            toast('Code incorrect.', 'warn');
            return;
          }
          toast('Coffre déverrouillé.', 'ok');
          renderInto(target);
        });
      });
      actions.appendChild(pinInput);
      actions.appendChild(unlockBtn);
    } else if (status.mode === 'pin') {
      var lockBtn = el('button', 'btn btn-outline btn-sm', 'Verrouiller');
      lockBtn.type = 'button';
      lockBtn.addEventListener('click', function () {
        vault().lock(scope).then(function () { renderInto(target); });
      });
      actions.appendChild(lockBtn);
      var removePin = el('button', 'btn btn-outline btn-sm', 'Retirer le PIN');
      removePin.type = 'button';
      removePin.addEventListener('click', function () {
        var pin = global.prompt('Code PIN actuel :');
        if (pin === null) {
          return;
        }
        vault().removePin(scope, pin).then(function () {
          toast('Coffre repassé en protection simplifiée.', 'ok');
          renderInto(target);
        }).catch(function () {
          toast('Code incorrect.', 'warn');
        });
      });
      actions.appendChild(removePin);
    } else {
      var setPin = el('button', 'btn btn-outline btn-sm', 'Protéger par un code PIN');
      setPin.type = 'button';
      setPin.addEventListener('click', function () {
        var pin = global.prompt('Choisissez un code PIN / une phrase secrète pour ce coffre (6 caractères minimum) :');
        if (pin === null || pin.length < 6) {
          if (pin !== null) {
            toast('Code trop court (6 caractères minimum).', 'warn');
          }
          return;
        }
        var confirmPin = global.prompt('Confirmez le code PIN :');
        if (confirmPin !== pin) {
          toast('Les deux codes diffèrent.', 'warn');
          return;
        }
        vault().createPin(scope, pin).then(function () {
          toast('Coffre protégé par code PIN.', 'ok');
          renderInto(target);
        }).catch(function () {
          toast('Impossible de protéger le coffre.', 'warn');
        });
      });
      actions.appendChild(setPin);
    }

    section.appendChild(actions);
    target.appendChild(section);
  }

  function renderOperations(target, operations) {
    var section = el('section', 'pwa-section');
    section.appendChild(el('h3', null, 'File d’attente (' + operations.length + ')'));

    if (!operations.length) {
      section.appendChild(el('p', 'pwa-hint', 'Aucune opération en attente sur cet appareil pour ce compte.'));
      target.appendChild(section);
      return;
    }

    operations.slice().reverse().forEach(function (op) {
      var card = el('div', 'pwa-item');
      var head = el('div', 'pwa-item-head');
      head.appendChild(el('strong', null, op.unreadable ? 'Saisie illisible (coffre verrouillé ?)' : op.label));
      head.appendChild(badge(op.state));
      card.appendChild(head);

      var meta = el('p', 'pwa-hint');
      meta.textContent = 'Créée le ' + formatDate(op.created_at)
        + ' · expire le ' + formatDate(op.expires_at)
        + (op.attempts ? ' · tentatives : ' + op.attempts : '');
      card.appendChild(meta);

      if (op.last_error) {
        card.appendChild(el('p', 'pwa-error', op.last_error));
      }

      var actions = el('div', 'pwa-actions');

      if (op.state === 'conflict') {
        var resolveBtn = el('button', 'btn btn-primary btn-sm', 'Résoudre le conflit');
        resolveBtn.type = 'button';
        resolveBtn.addEventListener('click', function () { openConflict(target, op); });
        actions.appendChild(resolveBtn);
      }

      if (op.requires_input && op.type === 'public_register') {
        var complete = el('button', 'btn btn-primary btn-sm', 'Compléter et envoyer');
        complete.type = 'button';
        complete.addEventListener('click', function () { askPublicCredentials(target, op); });
        actions.appendChild(complete);
      }

      if (op.state === 'queued' || op.state === 'failed') {
        var send = el('button', 'btn btn-outline btn-sm', 'Envoyer maintenant');
        send.type = 'button';
        send.addEventListener('click', function () {
          sync().syncNow('manual').then(function () { renderInto(target); });
        });
        actions.appendChild(send);
      }

      if (op.form_url && op.state !== 'synced') {
        var reopen = el('a', 'btn btn-outline btn-sm', 'Reprendre la saisie');
        reopen.href = op.form_url;
        actions.appendChild(reopen);
      }

      var remove = el('button', 'btn btn-outline btn-sm', op.state === 'synced' ? 'Retirer de la liste' : 'Supprimer');
      remove.type = 'button';
      remove.addEventListener('click', function () {
        if (!global.confirm('Supprimer définitivement cette saisie de cet appareil ? Elle ne sera pas envoyée au serveur.')) {
          return;
        }
        outbox().removeOperation(op.operation_id).then(function () {
          sync().refreshCounts();
          renderInto(target);
        });
      });
      actions.appendChild(remove);

      card.appendChild(actions);
      section.appendChild(card);
    });

    target.appendChild(section);
  }

  function renderDrafts(target, drafts) {
    if (!drafts.length) {
      return;
    }
    var section = el('section', 'pwa-section');
    section.appendChild(el('h3', null, 'Brouillons non soumis (' + drafts.length + ')'));
    drafts.slice().reverse().forEach(function (draft) {
      var card = el('div', 'pwa-item');
      var head = el('div', 'pwa-item-head');
      head.appendChild(el('strong', null, draft.unreadable ? 'Brouillon illisible (coffre verrouillé ?)' : draft.label));
      head.appendChild(badge('draft'));
      card.appendChild(head);
      card.appendChild(el('p', 'pwa-hint', 'Modifié le ' + formatDate(draft.updated_at) + ' · expire le ' + formatDate(draft.expires_at)));

      var actions = el('div', 'pwa-actions');
      if (draft.form_url) {
        var reopen = el('a', 'btn btn-outline btn-sm', 'Reprendre');
        reopen.href = draft.form_url;
        actions.appendChild(reopen);
      }
      var remove = el('button', 'btn btn-outline btn-sm', 'Supprimer');
      remove.type = 'button';
      remove.addEventListener('click', function () {
        outbox().removeDraft(draft.draft_id).then(function () {
          sync().refreshCounts();
          renderInto(target);
        });
      });
      actions.appendChild(remove);
      card.appendChild(actions);
      section.appendChild(card);
    });
    target.appendChild(section);
  }

  function renderDanger(target) {
    var section = el('section', 'pwa-section pwa-section-danger');
    section.appendChild(el('h3', null, 'Appareil partagé ?'));
    section.appendChild(el('p', 'pwa-hint',
      'Supprime immédiatement brouillons, file d’attente et clé locale de ce compte sur cet appareil. Les saisies non envoyées seront perdues.'));
    var wipe = el('button', 'btn btn-danger btn-sm', 'Effacer les données hors ligne de ce compte');
    wipe.type = 'button';
    wipe.addEventListener('click', function () {
      if (!global.confirm('Effacer toutes les données hors ligne de ce compte sur cet appareil ?')) {
        return;
      }
      outbox().purgeAccount(outbox().scopeOf()).then(function () {
        toast('Données hors ligne effacées de cet appareil.', 'ok');
        sync().refreshCounts();
        renderInto(target);
      });
    });
    section.appendChild(wipe);

    var retention = (boot().vault || {}).retentionDays || 7;
    section.appendChild(el('p', 'pwa-hint',
      'Conservation automatique : ' + retention + ' jour(s). Au-delà, les brouillons locaux sont supprimés de cet appareil avec un avertissement.'));
    target.appendChild(section);
  }

  /* ---------- Conflits ---------- */

  function conflictRows(op) {
    var rows = [];
    var local = op.payload || {};
    var server = (op.server && op.server.state) || {};
    var keys = {};
    Object.keys(local).forEach(function (key) { keys[key] = true; });
    Object.keys(server).forEach(function (key) { keys[key] = true; });
    Object.keys(keys).forEach(function (key) {
      if (key === 'csrf' || key === 'action' || key === 'retour') {
        return;
      }
      var mine = local[key];
      var theirs = server[key];
      var mineText = typeof mine === 'object' ? JSON.stringify(mine) : String(mine === null || mine === undefined ? '' : mine);
      var theirsText = typeof theirs === 'object' ? JSON.stringify(theirs) : String(theirs === null || theirs === undefined ? '' : theirs);
      if (mineText === theirsText) {
        return;
      }
      rows.push({
        label: FIELD_LABELS[key] || key,
        mine: mineText,
        theirs: theirsText
      });
    });
    return rows;
  }

  function openConflict(target, op) {
    var overlay = el('div', 'pwa-modal');
    var box = el('div', 'pwa-modal-box');
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');

    box.appendChild(el('h3', null, 'Conflit : ' + op.label));
    box.appendChild(el('p', 'pwa-hint',
      (op.server && op.server.message) || 'Le serveur détient une version différente de cette saisie. Choisissez explicitement comment résoudre : rien n’est écrasé en silence.'));

    var rows = conflictRows(op);
    if (rows.length) {
      var table = el('table', 'pwa-conflict-table');
      var head = el('tr');
      head.appendChild(el('th', null, 'Champ'));
      head.appendChild(el('th', null, 'Votre saisie'));
      head.appendChild(el('th', null, 'Serveur'));
      table.appendChild(head);
      rows.forEach(function (row) {
        var tr = el('tr');
        tr.appendChild(el('td', null, row.label));
        tr.appendChild(el('td', null, row.mine || '—'));
        tr.appendChild(el('td', null, row.theirs || '—'));
        table.appendChild(tr);
      });
      box.appendChild(table);
    } else {
      box.appendChild(el('p', 'pwa-hint', 'Aucune différence de champ détectée (conflit de création ou de structure).'));
    }

    var actions = el('div', 'pwa-actions');
    [['server', 'Garder la version serveur', 'btn btn-outline btn-sm'],
      ['client', 'Reprendre ma saisie', 'btn btn-primary btn-sm'],
      ['merge', 'Fusion champ par champ', 'btn btn-outline btn-sm']].forEach(function (choice) {
      var button = el('button', choice[2], choice[1]);
      button.type = 'button';
      button.addEventListener('click', function () {
        overlay.remove();
        sync().resolveConflict(op.operation_id, choice[0]).then(function () {
          toast('Résolution envoyée au serveur.', 'ok');
          renderInto(target);
        });
      });
      actions.appendChild(button);
    });
    var cancel = el('button', 'btn btn-outline btn-sm', 'Plus tard');
    cancel.type = 'button';
    cancel.addEventListener('click', function () { overlay.remove(); });
    actions.appendChild(cancel);
    box.appendChild(actions);

    overlay.appendChild(box);
    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        overlay.remove();
      }
    });
    document.body.appendChild(overlay);
  }

  /* ---------- Inscription publique : mot de passe demandé à l'envoi ---------- */

  function askPublicCredentials(target, op) {
    var overlay = el('div', 'pwa-modal');
    var box = el('div', 'pwa-modal-box');
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.appendChild(el('h3', null, 'Envoyer la demande d’inscription'));
    box.appendChild(el('p', 'pwa-hint',
      'Le mot de passe n’a pas été conservé sur cet appareil (règle de sécurité). Saisissez-le maintenant : il sera transmis une seule fois, chiffré par HTTPS, puis oublié.'));

    var pass = el('input', 'pwa-pin-input');
    pass.type = 'password';
    pass.placeholder = 'Mot de passe';
    pass.autocomplete = 'new-password';
    var confirm = el('input', 'pwa-pin-input');
    confirm.type = 'password';
    confirm.placeholder = 'Confirmer le mot de passe';
    confirm.autocomplete = 'new-password';
    box.appendChild(pass);
    box.appendChild(confirm);

    var actions = el('div', 'pwa-actions');
    var send = el('button', 'btn btn-primary btn-sm', 'Envoyer maintenant');
    send.type = 'button';
    send.addEventListener('click', function () {
      if (pass.value.length < 8 || pass.value !== confirm.value) {
        toast('Mot de passe invalide ou confirmation différente.', 'warn');
        return;
      }
      overlay.remove();
      sync().setPendingCredentials(op.operation_id, {
        password: pass.value,
        password_confirm: confirm.value
      });
      pass.value = '';
      confirm.value = '';
      outbox().updateOperation(op.operation_id, { requires_input: false, state: 'queued', next_attempt_at: 0 })
        .then(function () { return sync().syncNow('manual-public'); })
        .then(function () { renderInto(target); });
    });
    actions.appendChild(send);
    var cancel = el('button', 'btn btn-outline btn-sm', 'Annuler');
    cancel.type = 'button';
    cancel.addEventListener('click', function () {
      pass.value = '';
      confirm.value = '';
      overlay.remove();
    });
    actions.appendChild(cancel);
    box.appendChild(actions);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
  }

  /* ---------- Garde de déconnexion (plan §5.3, §17.2) ---------- */

  function guardLogout() {
    document.addEventListener('click', function (event) {
      var link = event.target && event.target.closest ? event.target.closest('a[href*="action=logout"]') : null;
      if (!link || !outbox()) {
        return;
      }
      var policy = (boot().vault || {}).logoutPolicy || 'ask';
      outbox().counts().then(function (counts) {
        var hasLocal = counts.pending > 0 || counts.drafts > 0 || counts.conflict > 0;
        if (!hasLocal) {
          return;
        }
        event.preventDefault();

        if (policy === 'purge') {
          if (global.confirm('Des saisies locales non envoyées existent. Elles seront supprimées de cet appareil. Continuer la déconnexion ?')) {
            outbox().purgeAccount(outbox().scopeOf()).then(function () {
              global.location.href = link.href;
            });
          }
          return;
        }
        if (policy === 'keep') {
          if (global.confirm('Des saisies locales restent en attente sur cet appareil (coffre conservé). Continuer la déconnexion ?')) {
            global.location.href = link.href;
          }
          return;
        }

        var overlay = el('div', 'pwa-modal');
        var box = el('div', 'pwa-modal-box');
        box.appendChild(el('h3', null, 'Saisies en attente sur cet appareil'));
        box.appendChild(el('p', 'pwa-hint',
          counts.pending + ' opération(s) en attente et ' + counts.drafts + ' brouillon(s) ne sont pas encore enregistrés sur le serveur. Que faire avant de vous déconnecter ?'));

        var actions = el('div', 'pwa-actions');
        var syncFirst = el('button', 'btn btn-primary btn-sm', 'Synchroniser puis quitter');
        syncFirst.type = 'button';
        syncFirst.addEventListener('click', function () {
          overlay.remove();
          sync().syncNow('logout').then(function (outcome) {
            if (outcome && outcome.ok && !(outcome.sent === 0 && false)) {
              outbox().counts().then(function (after) {
                if (after.pending === 0) {
                  global.location.href = link.href;
                  return;
                }
                toast('Certaines saisies n’ont pas pu être envoyées.', 'warn');
              });
            }
          });
        });
        actions.appendChild(syncFirst);

        var keep = el('button', 'btn btn-outline btn-sm', 'Quitter et conserver (verrouillé)');
        keep.type = 'button';
        keep.addEventListener('click', function () {
          overlay.remove();
          vault().lock(outbox().scopeOf()).then(function () {
            global.location.href = link.href;
          });
        });
        actions.appendChild(keep);

        var drop = el('button', 'btn btn-danger btn-sm', 'Quitter et supprimer mes brouillons');
        drop.type = 'button';
        drop.addEventListener('click', function () {
          overlay.remove();
          outbox().purgeAccount(outbox().scopeOf()).then(function () {
            global.location.href = link.href;
          });
        });
        actions.appendChild(drop);

        var cancel = el('button', 'btn btn-outline btn-sm', 'Rester connecté');
        cancel.type = 'button';
        cancel.addEventListener('click', function () { overlay.remove(); });
        actions.appendChild(cancel);

        box.appendChild(actions);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
      });
    }, true);
  }

  /* ---------- Amorçage ---------- */

  function init() {
    if (typeof document === 'undefined' || !boot().enabled || !outbox()) {
      return;
    }
    guardLogout();

    // Écran serveur « Données hors ligne » : même rendu que le tiroir.
    var host = document.getElementById('pwaPanelRoot');
    if (host) {
      var scopeLabel = document.getElementById('pwaPanelScope');
      if (scopeLabel) {
        scopeLabel.textContent = 'Compte cloisonné : ' + outbox().scopeOf();
      }
      renderInto(host);
      return;
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        close();
      }
    });
  }

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  }

  ns.panel = {
    open: open,
    close: close,
    render: renderInto
  };
})(typeof window !== 'undefined' ? window : self);
