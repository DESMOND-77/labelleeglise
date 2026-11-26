<?php /* Écran « Données hors ligne » (PWA).
   Variables : $user, $isAdmin, $pwaEnabled, $offlineCapture, $serviceWorker,
               $vaultMode, $retentionDays, $logoutPolicy, $allowedOps,
               $excludedOps, $serverSummary, $purged, $purgeError, $purgeUrl,
               $capabilities, $csrf.

   Tout ce qui concerne le CONTENU local (brouillons, file d'attente, conflits,
   coffre) est rendu par le client : ces données vivent dans IndexedDB sur cet
   appareil et ne transitent jamais par le serveur avant d'être envoyées. */
?>
<?= section_toolbar(
    'Données hors ligne',
    'Ce que cet appareil conserve, comment c’est protégé, et ce qui reste strictement en ligne'
) ?>

<div class="pwa-alerts">
  <?php if ($purged !== null): ?>
    <div class="alert alert-success" role="status"><i class="fa-solid fa-check"></i> Journal serveur purgé : <?= (int) $purged ?> ligne(s) supprimée(s).</div>
  <?php endif; ?>
  <?php if ($purgeError !== null): ?>
    <div class="alert alert-danger" role="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= h($purgeError) ?></div>
  <?php endif; ?>
  <?php if (!$pwaEnabled): ?>
    <div class="alert alert-warning" role="alert"><i class="fa-solid fa-plug-circle-xmark"></i> La couche PWA est désactivée par configuration (<code>Config/pwa.php</code>).</div>
  <?php elseif (!$offlineCapture): ?>
    <div class="alert alert-warning" role="alert"><i class="fa-solid fa-plug-circle-xmark"></i> La saisie hors ligne est désactivée : les formulaires fonctionnent uniquement en ligne.</div>
  <?php endif; ?>
</div>

<!-- Contenu local : file d'attente, brouillons, coffre, conflits (rendu client). -->
<div class="dash-section-title"><h2><i class="fa-solid fa-mobile-screen"></i> Sur cet appareil</h2><span id="pwaPanelScope">Chargement…</span></div>
<div id="pwaPanelRoot" class="pwa-panel"
     data-retention-days="<?= (int) $retentionDays ?>"
     data-vault-mode="<?= h($vaultMode) ?>"
     data-logout-policy="<?= h($logoutPolicy) ?>">
  <?php if (!$offlineCapture): ?>
    <div class="empty-state"><div class="emoji"><i class="fa-solid fa-plug-circle-xmark"></i></div>
      <p>Aucune donnée hors ligne n’est conservée : la saisie hors ligne est désactivée sur cette installation.</p></div>
  <?php else: ?>
    <div class="empty-state"><div class="emoji"><i class="fa-solid fa-hourglass-half"></i></div>
      <p>Lecture du stockage local… Si ce message persiste, votre navigateur ne fournit pas IndexedDB ou Web Crypto (mode privé ancien, navigateur non supporté).</p></div>
  <?php endif; ?>
</div>

<div class="dash-section-title"><h2><i class="fa-solid fa-id-card"></i> Compte et politique</h2><span>Décidée par le serveur</span></div>
<div class="form-page">
  <div class="form-card">
    <?= info_rows_html([
        ['Compte', trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))],
        ['Rôle', ROLE_LABELS[$user['role']] ?? $user['role']],
        ['Périmètre local (cloisonnement)', 'u' . (int) $user['id']],
        ['Coffre local', $vaultMode === 'pin' ? 'Protégé par code PIN / phrase secrète' : 'Clé appareil (protection simplifiée)'],
        ['Conservation des brouillons', $retentionDays . ' jour(s) puis suppression automatique'],
        ['Déconnexion avec saisies en attente', ['ask' => 'Demande explicite (synchroniser, conserver ou supprimer)', 'keep' => 'Conserve le coffre verrouillé', 'purge' => 'Supprime les brouillons locaux'][$logoutPolicy] ?? $logoutPolicy],
        ['Service Worker / cache statique', $serviceWorker ? 'Activé' : 'Désactivé'],
    ]) ?>
    <?php if ($vaultMode !== 'pin'): ?>
      <p class="form-hint"><i class="fa-solid fa-circle-info"></i> Protection simplifiée : la clé est conservée par le navigateur, non extractible.
        Elle protège contre la lecture brute du stockage, <strong>pas</strong> contre une faille XSS ni contre un appareil compromis.
        Sur un appareil partagé par plusieurs responsables, demandez l’activation du mode PIN (<code>PWA_VAULT_MODE=pin</code>).</p>
    <?php endif; ?>
  </div>

  <div class="form-card">
    <h3>Journal serveur de vos opérations</h3>
    <?php if (is_array($serverSummary)): ?>
      <?= info_rows_html([
          ['Opérations reçues', (string) (int) $serverSummary['total']],
          ['Acceptées (appliquées)', (string) (int) $serverSummary['accepted']],
          ['Conflits à résoudre', (string) (int) $serverSummary['conflict']],
          ['Refusées / échouées', (string) (int) $serverSummary['failed']],
          ['Dernière synchronisation', $serverSummary['last_synced_at'] ? date('d/m/Y H:i', strtotime((string) $serverSummary['last_synced_at'])) : 'Jamais'],
      ]) ?>
      <p class="form-hint">Le journal ne contient <strong>aucune donnée de formulaire</strong> : uniquement l’identifiant d’opération,
        son empreinte, l’état final et l’entité produite.</p>
    <?php else: ?>
      <p class="form-hint">Journal inaccessible pour le moment (base indisponible ou table non créée).</p>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
      <div class="modal-actions">
        <a class="btn btn-outline btn-sm" data-confirm="Purger le journal des opérations de plus de 30 jours ?" href="<?= h($purgeUrl) ?>">
          <i class="fa-solid fa-broom"></i> Purger le journal (&gt; 30 jours)
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="dash-section-title"><h2><i class="fa-solid fa-list-check"></i> Périmètre hors connexion</h2><span>Formulaire par formulaire</span></div>
<div class="form-page">
  <div class="form-card">
    <h3>Autorisé hors connexion</h3>
    <?php if ($allowedOps === []): ?>
      <p class="form-hint">Aucune opération n’est activée pour le moment.</p>
    <?php else: ?>
      <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Type</th><th>Action</th><th>Accès</th><th>Disponible pour vous</th></tr></thead>
        <tbody>
        <?php foreach ($allowedOps as $op): ?>
          <?php
          $capability = null;
          foreach ((array) $capabilities as $candidate) {
              if (($candidate['type'] ?? '') === $op['type']) {
                  $capability = $candidate;
                  break;
              }
          }
          $available = $capability === null ? false : !empty($capability['available']);
          $labels = [
              'rapport_jour'    => 'Rapport du Jour (brouillon puis soumission revalidée)',
              'suivi_hebdo'     => 'Suivi hebdomadaire personnel',
              'public_register' => 'Demande d’inscription publique (envoi différé)',
          ];
          ?>
          <tr>
            <td><?= h($labels[$op['type']] ?? $op['type']) ?></td>
            <td><code><?= h($op['actions']) ?></code></td>
            <td><?= $op['public'] ? 'Public (sans connexion)' : 'Session requise' ?></td>
            <td>
              <?php if ($available): ?>
                <span class="badge present"><i class="fa-solid fa-check"></i> Oui</span>
              <?php else: ?>
                <span class="badge neutral"><i class="fa-solid fa-lock"></i> Non (<?= h($capability['reason'] ?? 'droits insuffisants') ?>)</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <p class="form-hint">Même autorisé hors connexion, chaque envoi est ré-authentifié, ré-autorisé (rôle, responsabilité, périmètre relus en base),
        revalidé puis commité par le serveur. Rien n’est « enregistré » avant cet accusé de réception.</p>
    <?php endif; ?>
  </div>

  <div class="form-card">
    <h3>Jamais hors connexion</h3>
    <p class="form-hint">Ces actions restent utilisables en ligne uniquement. Elles affichent un message clair si vous tentez de les réaliser hors connexion.</p>
    <div class="pwa-chips">
      <?php foreach ($excludedOps as $excluded): ?><span class="badge neutral"><?= h((string) $excluded) ?></span><?php endforeach; ?>
    </div>
    <p class="form-hint">Motifs : risque de doublon financier, opérations de masse sans modèle de conflit, suppressions,
      gestion des rôles et permissions, téléversements, effets externes non idempotents (emails, relances, notifications).</p>
  </div>
</div>

<div class="form-page">
  <div class="form-card">
    <h3>Ce que le mode hors ligne ne garantit pas</h3>
    <ul class="pwa-notes">
      <li>Une saisie en attente n’est <strong>ni validée ni enregistrée</strong> tant que le serveur ne l’a pas acceptée.</li>
      <li>La synchronisation n’est pas garantie si le navigateur ferme l’application ou suspend l’arrière-plan : relancez l’application pour reprendre l’envoi.</li>
      <li>Les pages de gestion ne sont <strong>jamais</strong> conservées dans un cache ; hors connexion, seule la vitrine publique déjà consultée reste lisible.</li>
      <li>Les images du dossier <code>uploads/</code> ne sont pas mises en cache (dossier mixte public/privé).</li>
      <li>Le chiffrement local ne remplace ni la prévention XSS, ni la sécurité du terminal : sur un appareil partagé, verrouillez le coffre et purgez les données en fin d’utilisation.</li>
    </ul>
  </div>
</div>
