<?php /* Paramètres du module « Suivi des Rapports du Jour » (admin).
   Variables : $settings, $rules, $weekDays, $centres, $bacentas, $configProblems,
               $auditRecent, $failedReminders, $cronToken, $lastRun, $editRule,
               $saved, $error, $csrf. */ ?>
<?php
$s = static function (string $key, string $default = '') use ($settings) {
    return (string) ($settings[$key] ?? $default);
};
$bool = static function (string $key, bool $default = true) use ($s) {
    $v = $s($key, $default ? '1' : '0');
    return in_array(strtolower($v), ['1', 'true', 'on', 'yes'], true);
};
$heure = $s('heure_limite_defaut');
?>
<?= view('pages/partials/rapport_tabs', ['active' => 'parametres', 'retour' => 'rapport_suivi']) ?>
<?= section_toolbar(
    SECTION_LABELS['rapport_parametres'],
    'Calendriers, échéances, rappels, bilans et barème - réservé à l’administrateur',
    '<a class="btn btn-outline btn-sm" href="' . h(url('index.php', ['page' => 'rapport_suivi'])) . '"><i class="fa-solid fa-clipboard-check"></i> Voir la checklist</a>'
) ?>

<div class="suivi-alerts">
  <?php if ($saved): ?><div class="alert alert-success" role="status"><?= h($saved) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
  <?php foreach ($configProblems as $problem): ?>
    <div class="alert alert-danger" role="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= h((string) $problem) ?></div>
  <?php endforeach; ?>
</div>

<?php if (is_array($lastRun)): ?>
  <div class="chart-card" style="margin-bottom:var(--space-4)">
    <h3 class="suivi-chart-title"><?= !empty($lastRun['dry_run']) ? 'Simulation (aucun envoi)' : 'Traitement manuel' ?> - <?= h((string) ($lastRun['finished_at'] ?? '')) ?></h3>
    <p class="suivi-cell-sub">
      Rappels : <?= (int) ($lastRun['reminders']['checked'] ?? 0) ?> examiné(s) · <?= (int) ($lastRun['reminders']['sent'] ?? 0) ?> envoyé(s) · <?= (int) ($lastRun['reminders']['failed'] ?? 0) ?> en échec · <?= (int) ($lastRun['reminders']['skipped'] ?? 0) ?> ignoré(s) · <?= (int) ($lastRun['reminders']['cancelled_closed'] ?? 0) ?> clôturé(s) automatiquement
    </p>
    <?php if (isset($lastRun['locked']) && $lastRun['locked'] === false): ?>
      <p class="suivi-cell-sub" style="color:var(--warning-text)"><i class="fa-solid fa-lock"></i> Un autre traitement était déjà en cours - rien n'a été envoyé.</p>
    <?php endif; ?>
    <?php $bilans = $lastRun['bilans'] ?? null; ?>
    <?php if (is_array($bilans) && ($bilans !== [] || isset($bilans['sent']))): ?>
      <p class="suivi-cell-sub">Bilans : <?= (int) ($bilans['sent'] ?? 0) ?> envoyé(s)
        <?php foreach ((array) ($bilans['periods'] ?? []) as $type => $info): ?>
          - <?= $type === 'month' ? 'mensuel' : 'hebdo' ?> <?= h((string) ($info['from'] ?? '')) ?> → <?= h((string) ($info['to'] ?? '')) ?>
          (<?= isset($info['recipients']) ? (int) $info['recipients'] . ' destinataire(s)' : (int) ($info['sent'] ?? 0) . ' envoyé(s)' ?>)
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
    <?php if (!empty($lastRun['details'])): ?>
      <details>
        <summary class="suivi-cell-sub" style="cursor:pointer">Détail (<?= count((array) $lastRun['details']) ?> ligne(s))</summary>
        <ul class="suivi-cell-sub">
          <?php foreach (array_slice((array) $lastRun['details'], 0, 40) as $line): ?>
            <li><?= h((string) $line) ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="suivi-settings-grid">

  <div class="form-card suivi-settings-span">
    <h3><i class="fa-solid fa-clock"></i> Exécution automatique (cron)</h3>
    <p class="suivi-cell-sub">Planifiez l’appel de la tâche - toutes les 15 minutes conseillé. Sans exécution régulière, aucun rappel ni bilan ne partira.</p>
    <label class="suivi-cell-sub" for="cron-cli">Commande CLI</label>
    <input id="cron-cli" type="text" readonly value="php <?= h(BASE_PATH . '/cron-rapport-rappels.php') ?>" onclick="this.select()" style="width:100%;font-family:monospace">
    <?php if ($cronToken !== ''): ?>
      <label class="suivi-cell-sub" for="cron-web" style="margin-top:var(--space-3)">Alternative webcron (jeton secret)</label>
      <input id="cron-web" type="text" readonly value="<?= h(url('index.php', ['action' => 'rapport_cron', 'token' => $cronToken])) ?>" onclick="this.select()" style="width:100%;font-family:monospace">
    <?php else: ?>
      <p class="suivi-cell-sub" style="color:var(--warning-text)">Jeton cron non configuré - seule la voie CLI est disponible.</p>
    <?php endif; ?>
    <div class="modal-actions" style="justify-content:flex-start;margin-top:var(--space-3)">
      <form method="post" action="index.php" style="display:inline">
        <input type="hidden" name="action" value="rapport_cron_run">
        <input type="hidden" name="dry_run" value="1">
        <?= $csrf ?>
        <button type="submit" class="btn btn-outline"><i class="fa-solid fa-flask"></i> Simuler (dry-run, aucun envoi)</button>
      </form>
      <form method="post" action="index.php" style="display:inline" onsubmit="return confirm('Envoyer réellement les rappels et bilans dus maintenant ?');">
        <input type="hidden" name="action" value="rapport_cron_run">
        <input type="hidden" name="dry_run" value="0">
        <?= $csrf ?>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Envoyer maintenant</button>
      </form>
    </div>
  </div>

  <form method="post" action="index.php" class="form-card suivi-settings-span">
    <input type="hidden" name="action" value="rapport_settings_save">
    <?= $csrf ?>
    <h3><i class="fa-solid fa-sliders"></i> Paramètres généraux</h3>

    <div class="form-grid">
      <label>Heure limite par défaut (globale)
        <input type="time" name="heure_limite_defaut" value="<?= h($heure) ?>" <?= $heure === '' ? 'aria-describedby="heure-limite-help"' : '' ?>>
        <small id="heure-limite-help" class="suivi-cell-sub"><?= $heure === '' ? 'Vide = erreur de configuration signalée (aucune heure inventée).' : 'Appliquée aux calendriers sans heure limite propre.' ?></small>
      </label>
      <label>Délai de grâce après l’heure limite (minutes)
        <input type="number" name="grace_minutes" min="0" max="10080" required value="<?= h($s('grace_minutes', '720')) ?>">
        <small class="suivi-cell-sub">Après ce délai, l’échéance bascule « En retard ».</small>
      </label>
      <label>Date de départ du cumul par défaut
        <input type="date" name="cumul_start_default" value="<?= h($s('cumul_start_default')) ?>">
        <small class="suivi-cell-sub">Utilisée uniquement si aucune affectation n’est trouvée ; vide = « Non évalué ».</small>
      </label>
    </div>

    <h4 style="margin:var(--space-4) 0 var(--space-2)">Rappels automatiques <span class="suivi-cell-sub">(Africa/Libreville)</span></h4>
    <div class="form-grid">
      <label class="suivi-days-checkboxes"><span><input type="checkbox" name="rappel_preventif_enabled" value="1" <?= $bool('rappel_preventif_enabled') ? 'checked' : '' ?>> Veille (préventif)</span></label>
      <label>Heure du rappel préventif (la veille)
        <input type="time" name="rappel_preventif_heure" value="<?= h($s('rappel_preventif_heure', '18:00')) ?>">
      </label>
      <label class="suivi-days-checkboxes"><span><input type="checkbox" name="rappel_echeance_enabled" value="1" <?= $bool('rappel_echeance_enabled') ? 'checked' : '' ?>> Jour J (après l’heure limite)</span></label>
      <label class="suivi-days-checkboxes"><span><input type="checkbox" name="rappel_relance_enabled" value="1" <?= $bool('rappel_relance_enabled') ? 'checked' : '' ?>> Lendemain (relance)</span></label>
      <label>Heure de la relance (le lendemain)
        <input type="time" name="rappel_relance_heure" value="<?= h($s('rappel_relance_heure', '09:00')) ?>">
      </label>
      <label>Tentatives max par canal
        <input type="number" name="reminder_max_attempts" min="1" max="10" required value="<?= h($s('reminder_max_attempts', '3')) ?>">
      </label>
      <label>Délai entre tentatives (minutes)
        <input type="number" name="reminder_retry_minutes" min="1" max="720" required value="<?= h($s('reminder_retry_minutes', '30')) ?>">
      </label>
    </div>

    <h4 style="margin:var(--space-4) 0 var(--space-2)">Canaux</h4>
    <div class="form-grid">
      <label class="suivi-days-checkboxes"><span><input type="checkbox" name="channel_inapp_enabled" value="1" <?= $bool('channel_inapp_enabled') ? 'checked' : '' ?>> Notifications in-app</span></label>
      <label class="suivi-days-checkboxes"><span><input type="checkbox" name="channel_email_enabled" value="1" <?= $bool('channel_email_enabled') ? 'checked' : '' ?>> E-mail</span></label>
    </div>

    <h4 style="margin:var(--space-4) 0 var(--space-2)">Félicitations &amp; bilans</h4>
    <div class="form-grid">
      <label class="suivi-days-checkboxes"><span><input type="checkbox" name="felicitations_enabled" value="1" <?= $bool('felicitations_enabled') ? 'checked' : '' ?>> Félicitations au nouveau niveau de barème</span></label>
      <span></span>
      <label class="suivi-days-checkboxes"><span><input type="checkbox" name="bilan_hebdo_enabled" value="1" <?= $bool('bilan_hebdo_enabled') ? 'checked' : '' ?>> Bilan hebdomadaire (semaine ISO, le lundi)</span></label>
      <label>Heure d’envoi du bilan hebdo
        <input type="time" name="bilan_hebdo_heure" value="<?= h($s('bilan_hebdo_heure', '08:00')) ?>">
      </label>
      <label class="suivi-days-checkboxes"><span><input type="checkbox" name="bilan_mensuel_enabled" value="1" <?= $bool('bilan_mensuel_enabled') ? 'checked' : '' ?>> Bilan mensuel (mois civil, le 1<sup>er</sup>)</span></label>
      <label>Heure d’envoi du bilan mensuel
        <input type="time" name="bilan_mensuel_heure" value="<?= h($s('bilan_mensuel_heure', '08:00')) ?>">
      </label>
    </div>

    <div class="modal-actions" style="justify-content:flex-start;margin-top:var(--space-3)">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer les paramètres</button>
    </div>
  </form>

</div>

<h2 class="dash-section-title" style="margin-top:var(--space-6)"><i class="fa-solid fa-calendar-days"></i> Calendriers &amp; heures limites</h2>
<p class="suivi-cell-sub" style="margin-bottom:var(--space-3)">Une réunion planifiée = une échéance. Modifier un calendrier n’affecte jamais les occurrences déjà générées ; les rappels et bilans suivront la nouvelle cadence à partir de la prochaine synchronisation.</p>

<?php foreach ([['centres', $centres, 'centre', 'Centres'], ['bacentas', $bacentas, 'bacenta', 'Bacentas']] as [$group, $items, $type, $title]): ?>
  <details class="chart-card" <?= $type === 'centre' ? 'open' : '' ?> style="margin-bottom:var(--space-4)">
    <summary class="suivi-chart-title" style="cursor:pointer"><?= h($title) ?> (<?= count($items) ?>)</summary>
    <?php if (!$items): ?>
      <p class="suivi-cell-sub">Aucun élément.</p>
    <?php else: ?>
      <?php foreach ($items as $item):
        $joursActifs = array_filter(array_map('trim', explode(',', (string) ($item['jours_semaine'] ?? ''))));
      ?>
        <form method="post" action="index.php" class="suivi-matrix chart-card" style="margin-top:var(--space-3);background:var(--bg)">
          <input type="hidden" name="action" value="rapport_calendar_save">
          <input type="hidden" name="calendar_type" value="<?= h($type) ?>">
          <input type="hidden" name="calendar_id" value="<?= (int) $item['id'] ?>">
          <?= $csrf ?>
          <div class="form-grid" style="align-items:end">
            <div>
              <strong><?= h((string) $item['nom']) ?></strong>
              <?php if ($type === 'bacenta' && !empty($item['centre_nom'])): ?><div class="suivi-cell-sub"><?= h((string) $item['centre_nom']) ?></div><?php endif; ?>
            </div>
            <fieldset style="border:0;padding:0;margin:0">
              <legend class="suivi-cell-sub" style="margin-bottom:4px">Jours de réunion</legend>
              <span class="suivi-days-checkboxes">
                <?php foreach ($weekDays as $day): ?>
                  <label><input type="checkbox" name="jours_semaine[]" value="<?= h($day) ?>" <?= in_array($day, $joursActifs, true) ? 'checked' : '' ?>> <?= h($day) ?></label>
                <?php endforeach; ?>
              </span>
            </fieldset>
            <label>Début
              <input type="time" name="heure_debut" value="<?= h((string) ($item['heure_debut'] ?? '')) ?>">
            </label>
            <label>Fin
              <input type="time" name="heure_fin" value="<?= h((string) ($item['heure_fin'] ?? '')) ?>">
            </label>
            <label>Heure limite
              <input type="time" name="heure_limite" value="<?= h((string) ($item['heure_limite'] ?? '')) ?>">
              <small class="suivi-cell-sub">Vide = défaut global</small>
            </label>
            <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
          </div>
        </form>
      <?php endforeach; ?>
    <?php endif; ?>
  </details>
<?php endforeach; ?>

<h2 class="dash-section-title" style="margin-top:var(--space-6)"><i class="fa-solid fa-ranking-star"></i> Barème de réalisation</h2>
<p class="suivi-cell-sub" style="margin-bottom:var(--space-3)">Échelons non chevauchants couvrant [0, 100 %] (bornés par un point parfait à 100 inclus). Toute modification crée une nouvelle version appliquée aux évaluations futures - l’historique des bilans conserve le barème qui était en vigueur.</p>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Ordre</th><th>Libellé</th><th>Intervalle</th><th>Message</th><th>État</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rules): ?>
        <tr><td colspan="6"><?= empty_state('fa-ranking-star', 'Aucun échelon - le barème par défaut devrait être présent (migration).') ?></td></tr>
      <?php else: ?>
        <?php foreach ($rules as $rule): ?>
          <tr>
            <td><?= (int) $rule['sort_order'] ?></td>
            <td><strong><?= h((string) $rule['label']) ?></strong><div class="suivi-cell-sub"><?= h((string) $rule['level_key']) ?></div></td>
            <td>[<?= rtrim(rtrim(number_format((float) $rule['min_rate'], 2, ',', ' '), '0'), ',') ?> % ; <?= ((float) $rule['max_rate'] >= 100 ? '100 (inclus)' : rtrim(rtrim(number_format((float) $rule['max_rate'], 2, ',', ' '), '0'), ',') . ' %') ?>[</td>
            <td class="suivi-audit-details"><?= h((string) $rule['message_template']) ?></td>
            <td><?= (int) $rule['is_active'] === 1 ? '<span class="badge success">Actif</span>' : '<span class="badge neutral">Inactif</span>' ?></td>
            <td class="row-actions">
              <a class="btn btn-outline btn-sm" href="<?= h(url('index.php', ['page' => 'rapport_parametres', 'edit_rule' => (int) $rule['id']])) ?>#bareme-form"><i class="fa-solid fa-pen"></i> Modifier</a>
              <form method="post" action="index.php" style="display:inline" onsubmit="return confirm('Supprimer cet échelon ? La couverture de [0, 100 %] sera vérifiée avant suppression.');">
                <input type="hidden" name="action" value="rapport_rule_delete">
                <input type="hidden" name="rule_id" value="<?= (int) $rule['id'] ?>">
                <?= $csrf ?>
                <button type="submit" class="btn btn-outline btn-sm" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<form method="post" action="index.php" class="form-card" id="bareme-form" style="margin-top:var(--space-4)">
  <input type="hidden" name="action" value="rapport_rules_save">
  <input type="hidden" name="rule_id" value="<?= $editRule ? (int) $editRule['id'] : 0 ?>">
  <?= $csrf ?>
  <h3><i class="fa-solid fa-<?= $editRule ? 'pen' : 'plus' ?>"></i> <?= $editRule ? 'Modifier l’échelon « ' . h((string) $editRule['label']) . ' »' : 'Nouvel échelon' ?></h3>
  <div class="form-grid">
    <label>Libellé
      <input type="text" name="label" required maxlength="80" value="<?= h($editRule ? (string) $editRule['label'] : '') ?>" placeholder="Ex. Exemplaire">
    </label>
    <label>Clé (optionnel - dérivée du libellé sinon)
      <input type="text" name="level_key" maxlength="30" value="<?= h($editRule ? (string) $editRule['level_key'] : '') ?>" placeholder="ex. exemplaire" pattern="[a-z0-9_\-]+" title="minuscules, chiffres, tirets et underscores">
    </label>
    <label>Taux minimal (%)
      <input type="number" name="min_rate" min="0" max="100" step="0.01" required value="<?= h($editRule ? (string) $editRule['min_rate'] : '') ?>">
    </label>
    <label>Taux maximal (%)
      <input type="number" name="max_rate" min="0" max="100" step="0.01" required value="<?= h($editRule ? (string) $editRule['max_rate'] : '') ?>">
    </label>
    <label>Ordre d’affichage
      <input type="number" name="sort_order" min="1" step="1" value="<?= h($editRule ? (string) $editRule['sort_order'] : '') ?>" placeholder="auto">
    </label>
    <label class="suivi-days-checkboxes" style="align-self:end"><span><input type="checkbox" name="is_active" value="1" <?= (!$editRule || (int) $editRule['is_active'] === 1) ? 'checked' : '' ?>> Échelon actif</span></label>
    <label style="grid-column:1 / -1">Message associé (affiché dans les bilans et félicitations)
      <textarea name="message_template" required rows="2" maxlength="500" placeholder="Ex. Bravo pour votre régularité…"><?= h($editRule ? (string) $editRule['message_template'] : '') ?></textarea>
    </label>
  </div>
  <div class="modal-actions" style="justify-content:flex-start;margin-top:var(--space-3)">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= $editRule ? 'Enregistrer l’échelon' : 'Ajouter l’échelon' ?></button>
    <?php if ($editRule): ?><a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'rapport_parametres'])) ?>">Annuler la modification</a><?php endif; ?>
  </div>
</form>

<h2 class="dash-section-title" style="margin-top:var(--space-6)"><i class="fa-solid fa-bell-slash"></i> Rappels en échec</h2>
<?php if (!$failedReminders): ?>
  <p class="suivi-cell-sub">Aucun rappel en échec - les envois fonctionnent ou aucun rappel n’est encore planifié.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Date</th><th>Occurrence</th><th>Type</th><th>Canal</th><th>Tentatives</th><th>Dernière erreur</th></tr></thead>
      <tbody>
        <?php foreach ($failedReminders as $fail): ?>
          <tr>
            <td><?= h(date('d/m/Y H:i', strtotime((string) $fail['created_at']))) ?></td>
            <td><?= h((string) $fail['scope_type']) ?> #<?= (int) $fail['scope_id'] ?> - <?= h(date('d/m/Y', strtotime((string) $fail['report_date']))) ?></td>
            <td><?= h(rapport_reminder_type_labels()[$fail['reminder_type']] ?? (string) $fail['reminder_type']) ?></td>
            <td><?= $fail['channel'] === 'inapp' ? 'In-app' : 'E-mail' ?></td>
            <td><?= (int) $fail['attempt_count'] ?></td>
            <td class="suivi-audit-details"><?= h((string) ($fail['last_error'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<h2 class="dash-section-title" style="margin-top:var(--space-6)"><i class="fa-solid fa-clock-rotate-left"></i> Journal d’audit</h2>
<?php if (!$auditRecent): ?>
  <p class="suivi-cell-sub">Aucune entrée pour le moment. Les modifications de barème, de calendrier, les annulations et les relances manuelles y sont journalisées.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Date</th><th>Action</th><th>Cible</th><th>Détails</th><th>Par</th></tr></thead>
      <tbody>
        <?php foreach ($auditRecent as $entry): ?>
          <tr>
            <td><?= h(date('d/m/Y H:i', strtotime((string) $entry['created_at']))) ?></td>
            <td><span class="badge neutral"><?= h((string) $entry['action']) ?></span></td>
            <td><?= h((string) ($entry['entity_type'] ?? '-')) ?><?= !empty($entry['entity_id']) ? ' #' . (int) $entry['entity_id'] : '' ?></td>
            <td class="suivi-audit-details"><?= h((string) ($entry['details'] ?? '')) ?></td>
            <td><?= h(trim((string) ($entry['prenom'] ?? '') . ' ' . (string) ($entry['nom'] ?? ''))) ?: '<span class="suivi-cell-sub">système</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
