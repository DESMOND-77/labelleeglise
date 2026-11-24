<?php /* Tableau de bord des Rapports du Jour.
   Variables : $data, $filters, $filterQuery, $isAdmin, $configProblems, $failedReminders. */ ?>
<?php
$period = $data['period'];
$current = $data['current'];
$previous = $data['previous'];
$filterQuery = $filterQuery ?? [];
$fmtRate = static function ($rate) {
    return $rate === null ? 'Non évalué' : number_format((float) $rate, 1, ',', ' ') . ' %';
};
$delta = static function ($value) {
    if ($value === null) {
        return ['-', 'flat'];
    }
    $v = (float) $value;
    if (abs($v) < 0.05) {
        return ['± 0,0 pt', 'flat'];
    }
    return [($v > 0 ? '+' : '') . number_format($v, 1, ',', ' ') . ' pt', $v > 0 ? 'up' : 'down'];
};
[$deltaCompletion, $deltaCompletionClass] = $delta($data['delta_completion']);
[$deltaPunctuality, $deltaPunctualityClass] = $delta($data['delta_punctuality']);
?>
<?= section_toolbar(
    SECTION_LABELS['rapport_dashboard'],
    h((string) $period['label']) . ' - périmètre ' . ($filters['vue'] === 'mes' ? 'personnel' : 'géré') . ' · KPI en % avec numérateur et dénominateur'
) ?>

<div class="suivi-alerts">
  <?php foreach ($configProblems as $problem): ?>
    <div class="alert alert-danger" role="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= h((string) $problem) ?></div>
  <?php endforeach; ?>
  <?php if ($isAdmin && $failedReminders > 0): ?>
    <div class="alert alert-warning" role="alert"><i class="fa-solid fa-bell"></i> <?= (int) $failedReminders ?> rappel(s) en échec d’envoi - voir <a href="<?= h(url('index.php', ['page' => 'rapport_parametres'])) ?>">Paramètres &gt; journal des échecs</a>.</div>
  <?php endif; ?>
  <?php if ($isAdmin && (int) $data['sans_responsable'] > 0): ?>
    <div class="alert alert-warning" role="alert"><i class="fa-solid fa-user-group"></i> <?= (int) $data['sans_responsable'] ?> échéance(s) sans responsable affecté - personne ne sera relancée. Affecter un responsable dans la structure concernée.</div>
  <?php endif; ?>
</div>

<form method="get" action="index.php" class="suivi-filters">
  <input type="hidden" name="page" value="rapport_dashboard">
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
  <div class="filter-actions"><button type="submit" class="btn btn-outline"><i class="fa-solid fa-filter"></i> Appliquer</button></div>
</form>

<div class="stats-grid suivi-kpi-grid">
  <div class="stat-card">
    <div class="stat-top"><span class="stat-label">Taux de réalisation</span></div>
    <div class="stat-value"><?= $fmtRate($current['completion_rate']) ?></div>
    <div class="suivi-kpi-delta <?= h($deltaCompletionClass) ?>"><?= h($deltaCompletion) ?> vs <?= h((string) $period['prev_label']) ?></div>
    <div class="stat-label"><?= (int) $current['submitted'] ?> / <?= (int) $current['expected'] ?> échéance(s)</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span class="stat-label">Taux de ponctualité</span></div>
    <div class="stat-value"><?= $fmtRate($current['punctuality_rate']) ?></div>
    <div class="suivi-kpi-delta <?= h($deltaPunctualityClass) ?>"><?= h($deltaPunctuality) ?> vs <?= h((string) $period['prev_label']) ?></div>
    <div class="stat-label"><?= (int) $current['on_time'] ?> à temps / <?= (int) $current['expected'] ?> attendu(s)</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span class="stat-label">Rapports soumis</span></div>
    <div class="stat-value"><?= (int) $current['submitted'] ?></div>
    <div class="stat-label">sur <?= (int) $current['expected'] ?> échéance(s) attendue(s)</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span class="stat-label">Manquants</span></div>
    <div class="stat-value"><?= (int) $current['missing'] ?></div>
    <div class="stat-label">échéance(s) sans rapport à ce stade</div>
  </div>
</div>

<div class="suivi-chart-grid">
  <div class="chart-card suivi-chart-wide">
    <h3 class="suivi-chart-title">Évolution quotidienne - échéances attendues vs rapports soumis</h3>
    <canvas id="rapportEvolutionChart" aria-label="Évolution des échéances et rapports soumis par jour"></canvas>
  </div>
  <div class="chart-card">
    <h3 class="suivi-chart-title">Par structure (top 12)</h3>
    <canvas id="rapportStructureChart" aria-label="Rapports par centre et bacenta"></canvas>
  </div>
  <div class="chart-card">
    <h3 class="suivi-chart-title">Répartition par statut</h3>
    <canvas id="rapportStatusChart" aria-label="Répartition des échéances par statut"></canvas>
  </div>
</div>

<div class="chart-card" style="margin-top:var(--space-4)">
  <h3 class="suivi-chart-title">Responsables les plus réguliers sur la période <a class="btn btn-outline btn-sm" style="float:right" href="<?= h(url('index.php', ['page' => 'rapport_classement'])) ?>">Classement complet</a></h3>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Responsable</th><th>Attendus</th><th>Crédités</th><th>Taux</th><th>Ponctualité</th><th></th></tr></thead>
      <tbody>
        <?php $top = array_slice($data['responsables'], 0, 15); ?>
        <?php if (!$top): ?>
          <tr><td colspan="6"><?= empty_state('fa-user-group', 'Aucun responsable avec échéance sur la période.') ?></td></tr>
        <?php else: ?>
          <?php foreach ($top as $r): ?>
            <tr>
              <td><?= h(trim((string) ($r['prenom'] ?? '') . ' ' . (string) ($r['nom'] ?? ''))) ?></td>
              <td><?= (int) $r['expected'] ?></td>
              <td><?= (int) $r['credited'] ?></td>
              <td><?= $fmtRate($r['completion_rate']) ?></td>
              <td><?= $fmtRate($r['punctuality_rate']) ?></td>
              <td class="row-actions"><a class="btn btn-outline btn-sm" href="<?= h(url('index.php', ['page' => 'rapport_responsable', 'id' => (int) $r['user_id']])) ?>"><i class="fa-solid fa-user-check"></i> Fiche</a></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
