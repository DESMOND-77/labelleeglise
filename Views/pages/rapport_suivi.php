<?php /* Checklist des échéances de rapport.
   Variables : $rows, $total, $page, $pages, $perPage, $filters, $filterQuery,
               $centres, $responsables, $isAdmin, $canSettings, $currentUserId,
               $saved, $error, $csrf. */ ?>
<?php
$statusLabels = rapport_status_labels();
$filters = $filters ?? [];
$filterQuery = $filterQuery ?? [];
$vue = $filters['vue'] ?? 'all';
$exportQuery = array_merge(['page' => 'rapport_suivi_export'], $filterQuery);
$statusOptions = [
    '' => 'Tous les statuts',
    'pending' => 'À venir',
    'due' => 'À remplir',
    'overdue' => 'En retard',
    'filled_on_time' => 'Rempli à temps',
    'filled_late' => 'Rempli en retard',
    'cancelled' => 'Annulés',
];
?>
<?= section_toolbar(
    SECTION_LABELS['rapport_suivi'],
    $total . ' échéance(s) sur la période — taux affichés avec numérateur et dénominateur',
    '<a class="btn btn-outline btn-sm" href="' . h(url('index.php', $exportQuery)) . '"><i class="fa-solid fa-file-csv"></i> Exporter CSV</a>'
) ?>

<div class="suivi-alerts">
  <?php if ($saved): ?><div class="alert alert-success" role="status"><?= h($saved) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
</div>

<div class="suivi-restricted-note" role="note">
  <i class="fa-solid fa-shield-halved"></i>
  <span>Un rapport rempli par un autre responsable ne vaut pas crédit : il clôt l'échéance pour tout le monde sans pénalité individuelle, sauf mention contraire dans le barème.</span>
</div>

<form method="get" action="index.php" class="suivi-filters">
  <input type="hidden" name="page" value="rapport_suivi">
  <input type="hidden" name="vue" value="<?= h((string) $vue) ?>">
  <label>Période
    <select name="periode" onchange="this.form.submit()">
      <option value="semaine" <?= $filters['periode'] === 'semaine' ? 'selected' : '' ?>>Semaine ISO (en cours)</option>
      <option value="mois" <?= $filters['periode'] === 'mois' ? 'selected' : '' ?>>Mois civil (en cours)</option>
      <option value="custom" <?= $filters['periode'] === 'custom' ? 'selected' : '' ?>>Personnalisée</option>
    </select>
  </label>
  <label>Du
    <input type="date" name="from" value="<?= h((string) ($filters['from_input'] ?? '')) ?>">
  </label>
  <label>Au
    <input type="date" name="to" value="<?= h((string) ($filters['to_input'] ?? '')) ?>">
  </label>
  <label>Type
    <select name="type" onchange="this.form.submit()">
      <option value="">Centre &amp; bacenta</option>
      <option value="centre" <?= $filters['scope_type'] === 'centre' ? 'selected' : '' ?>>Centre uniquement</option>
      <option value="bacenta" <?= $filters['scope_type'] === 'bacenta' ? 'selected' : '' ?>>Bacenta uniquement</option>
    </select>
  </label>
  <?php if ($isAdmin || $centres): ?>
  <label>Centre
    <select name="centre" onchange="this.form.submit()">
      <option value="">Tous</option>
      <?php foreach ($centres as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) ($filters['centre_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <label>Responsable
    <select name="responsable" onchange="this.form.submit()">
      <option value="">Tous</option>
      <?php foreach ($responsables as $r): ?>
        <option value="<?= (int) $r['id'] ?>" <?= (int) ($filters['user_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= h($r['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Statut
    <select name="statut" onchange="this.form.submit()">
      <?php foreach ($statusOptions as $value => $label): ?>
        <option value="<?= h((string) $value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Recherche
    <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Nom du centre / bacenta" maxlength="80">
  </label>
  <div class="filter-actions">
    <button type="submit" class="btn btn-outline"><i class="fa-solid fa-filter"></i> Filtrer</button>
    <?php
      $vueParams = array_merge(['page' => 'rapport_suivi', 'vue' => $vue === 'mes' ? 'all' : 'mes'], array_filter($filterQuery, static fn($k) => $k !== 'vue', ARRAY_FILTER_USE_KEY));
    ?>
    <a class="btn btn-outline" href="<?= h(url('index.php', $vueParams)) ?>"><?= $vue === 'mes' ? 'Voir mon périmètre' : 'Voir mes échéances' ?></a>
  </div>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Date &amp; échéance</th>
        <th>Structure</th>
        <th>Type</th>
        <th>Responsables</th>
        <th>Statut</th>
        <th>Rapport</th>
        <th>Dernière relance</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8"><?= empty_state('fa-clipboard-check', 'Aucune échéance sur la période sélectionnée.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $status = (string) $r['status'];
          $isCancelled = $status === 'cancelled';
          $isFilled = in_array($status, ['filled_on_time', 'filled_late'], true);
          $due = $r['due_at'] ? date('d/m/Y à H:i', strtotime((string) $r['due_at'])) : null;
          $reportDate = date('d/m/Y', strtotime((string) $r['report_date']));
          $formParams = ['page' => 'rapport', 'date' => $r['report_date'], 'centre' => (int) $r['centre_id'], 'type' => $r['scope_type']];
          if ($r['scope_type'] === 'bacenta') {
              $formParams['bacenta'] = (int) $r['scope_id'];
          }
        ?>
          <tr>
            <td>
              <div class="suivi-cell-main">
                <strong><?= h($reportDate) ?></strong>
                <span class="suivi-cell-sub suivi-due <?= $due ? '' : 'missing' ?>"><?= $due ? h('échéance ' . $due) : 'heure limite non configurée' ?></span>
              </div>
            </td>
            <td>
              <div class="suivi-cell-main">
                <strong><?= h((string) ($r['cible_nom'] ?? '-')) ?></strong>
                <?php if (!empty($r['centre_nom']) && ($r['scope_type'] ?? '') === 'bacenta'): ?>
                  <span class="suivi-cell-sub"><?= h((string) $r['centre_nom']) ?></span>
                <?php endif; ?>
              </div>
            </td>
            <td><?= $r['scope_type'] === 'centre' ? 'Centre' : 'Bacenta' ?></td>
            <td><?= h((string) ($r['responsables_noms'] ?? '')) ?: '<span class="suivi-cell-sub">aucun responsable — à signaler à l’administrateur</span>' ?></td>
            <td><span class="badge suivi-status-<?= h($status) ?>"><?= h($statusLabels[$status] ?? $status) ?></span></td>
            <td>
              <?php if ($isFilled && !empty($r['submitted_report_id'])): ?>
                <a class="btn btn-outline btn-sm" href="<?= h(url('index.php', ['page' => 'rapport', 'id' => (int) $r['submitted_report_id']])) ?>"><i class="fa-solid fa-eye"></i> Voir</a>
                <?php if (!empty($r['auteur_nom'])): ?><div class="suivi-cell-sub">par <?= h((string) $r['auteur_nom']) ?></div><?php endif; ?>
              <?php elseif ($isCancelled): ?>
                <span class="suivi-cell-sub">—</span>
              <?php else: ?>
                <a class="btn btn-primary btn-sm" href="<?= h(url('index.php', $formParams)) ?>"><i class="fa-solid fa-pen"></i> Remplir</a>
              <?php endif; ?>
            </td>
            <td><?= $r['dernier_rappel'] ? h((string) $r['dernier_rappel']) : '<span class="suivi-cell-sub">aucun</span>' ?></td>
            <td class="row-actions">
              <?php if (!$isFilled && !$isCancelled): ?>
                <form method="post" action="index.php" style="display:inline" onsubmit="return confirm('Relancer tous les responsables affectés ?');">
                  <input type="hidden" name="action" value="rapport_manual_relance">
                  <input type="hidden" name="occurrence_id" value="<?= (int) $r['id'] ?>">
                  <?= $csrf ?>
                  <button type="submit" class="btn btn-outline btn-sm" title="Relance manuelle"><i class="fa-solid fa-bell"></i> Relancer</button>
                </form>
              <?php endif; ?>
              <?php if ($isAdmin && !$isCancelled && !$isFilled): ?>
                <form method="post" action="index.php" style="display:inline-flex;gap:6px;align-items:center" onsubmit="return confirm('Annuler cette échéance ? Les responsables ne recevront plus de rappels.');">
                  <input type="hidden" name="action" value="rapport_occurrence_cancel">
                  <input type="hidden" name="occurrence_id" value="<?= (int) $r['id'] ?>">
                  <?= $csrf ?>
                  <input type="text" name="motif" required maxlength="250" placeholder="Motif d’annulation" aria-label="Motif d’annulation" style="min-height:32px;font-size:12px;max-width:170px">
                  <button type="submit" class="btn btn-outline btn-sm" title="Annuler l’échéance"><i class="fa-solid fa-ban"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
  <div class="suivi-pager">
    <span class="pager-info">Page <?= (int) $page ?> / <?= (int) $pages ?> — <?= (int) $total ?> échéance(s)</span>
    <div class="pager-links">
      <?php if ($page > 1): ?>
        <a class="btn btn-outline btn-sm" href="<?= h(url('index.php', array_merge(['page' => 'rapport_suivi'], $filterQuery, ['p' => $page - 1]))) ?>"><i class="fa-solid fa-chevron-left"></i> Précédent</a>
      <?php endif; ?>
      <?php if ($page < $pages): ?>
        <a class="btn btn-outline btn-sm" href="<?= h(url('index.php', array_merge(['page' => 'rapport_suivi'], $filterQuery, ['p' => $page + 1]))) ?>">Suivant <i class="fa-solid fa-chevron-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
