<?php /* Fiche de suivi d'un responsable.
   Variables : $target, $sheet, $responsabilites, $isSelf, $isAdmin, $restricted. */ ?>
<?php
$fmtRate = static function ($rate) {
    return $rate === null ? 'Non évalué' : number_format((float) $rate, 1, ',', ' ') . ' %';
};
$pillClass = static function ($rate) {
    if ($rate === null) return 'na';
    $v = (float) $rate;
    if ($v >= 80) return 'good';
    if ($v >= 50) return 'watch';
    return 'bad';
};
$blocs = [
    'week' => ['label' => 'Cette semaine', 'from' => $sheet['week']['from'] ?? null, 'to' => $sheet['week']['to'] ?? null],
    'month' => ['label' => 'Ce mois-ci', 'from' => $sheet['month']['from'] ?? null, 'to' => $sheet['month']['to'] ?? null],
    'cumul' => ['label' => 'Cumul depuis la prise de responsabilité', 'from' => $sheet['cumul_start'], 'to' => date('Y-m-d')],
];
$targetName = trim((string) ($target['prenom'] ?? '') . ' ' . (string) ($target['nom'] ?? ''));
$cumulSourceLabel = [
    'affectations' => 'calculé depuis l’historique des affectations',
    'parametre_admin' => 'date de départ définie par l’administration',
    'absent' => 'non évalué - aucune affectation trouvée et aucune date de départ configurée',
];
?>
<?= section_toolbar(
    'Suivi de ' . ($isSelf ? 'vous-même' : h($targetName)),
    $isSelf
        ? 'Vos indicateurs de réalisation - visibles uniquement par vous et les administrateurs'
        : 'Indicateurs de réalisation sur la période et depuis la prise de responsabilité'
) ?>

<?php if ($restricted): ?>
<div class="suivi-restricted-note" role="note">
  <i class="fa-solid fa-lock"></i>
  <span>Fiche consultée dans le cadre de votre périmètre : les statistiques sont limitées aux échéances des centres et bacentas que vous gérez, et l’historique des bilans n’est pas exposé.</span>
</div>
<?php endif; ?>

<div class="suivi-blocs">
  <?php foreach ($blocs as $key => $meta): $bloc = $sheet[$key] ?? null; ?>
    <div class="chart-card">
      <div class="suivi-bloc-head">
        <h3><?= h($meta['label']) ?></h3>
        <?php if ($bloc && !empty($bloc['from'])): ?>
          <span class="suivi-cell-sub"><?= h(date('d/m/Y', strtotime((string) $bloc['from']))) ?> → <?= h(date('d/m/Y', strtotime((string) $bloc['to']))) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($bloc === null): ?>
        <div class="empty-state"><div class="emoji"><i class="fa-solid fa-circle-question"></i></div>
          <p>Non évalué<?= $key === 'cumul' ? ' - ' . h($cumulSourceLabel[$sheet['cumul_source']] ?? '') : '' ?></p>
        </div>
      <?php else: ?>
        <div class="suivi-bloc-figures">
          <div><b><?= (int) $bloc['expected'] ?></b><span>Attendus</span></div>
          <div><b><?= (int) $bloc['credited'] ?></b><span>Crédités</span></div>
          <div><b><?= (int) $bloc['missing'] ?></b><span>Manqués</span></div>
        </div>
        <div class="suivi-bloc-rates">
          <span class="suivi-rate">Réalisation <b><?= $fmtRate($bloc['completion_rate']) ?></b> (<?= (int) $bloc['credited'] ?>/<?= (int) $bloc['denominator'] ?>)</span>
          <span class="suivi-rate">Ponctualité <b><?= $fmtRate($bloc['punctuality_rate']) ?></b> (<?= (int) $bloc['on_time'] ?>/<?= (int) $bloc['denominator'] ?>)</span>
          <span class="suivi-rate">Rapports par d’autres <b><?= (int) $bloc['filled_by_other'] ?></b></span>
        </div>
        <span class="suivi-level-pill <?= h($pillClass($bloc['completion_rate'])) ?>"><i class="fa-solid fa-ranking-star"></i> <?= h((string) ($bloc['level_label'] ?? 'Non évalué')) ?></span>
        <?php if (!empty($bloc['level_message'])): ?>
          <p class="suivi-cell-sub" style="margin-top:var(--space-2)"><?= h((string) $bloc['level_message']) ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="chart-card" style="margin-bottom:var(--space-4)">
  <h3 class="suivi-chart-title">Prises de responsabilité actuelles</h3>
  <?php if (!$responsabilites): ?>
    <p class="suivi-cell-sub">Aucune responsabilité de gestion de centre ou bacenta enregistrée à ce jour.</p>
  <?php else: ?>
    <ul style="margin:0;padding-left:var(--space-5)">
      <?php foreach ($responsabilites as $resp): ?>
        <li><?= $resp['target_type'] === 'center' ? 'Centre' : 'Bacenta' ?> - <strong><?= h((string) ($resp['cible_nom'] ?? ('#' . (int) $resp['target_id']))) ?></strong> - depuis le <?= h(date('d/m/Y', strtotime((string) $resp['created_at']))) ?></li>
      <?php endforeach; ?>
    </ul>
    <p class="suivi-cell-sub" style="margin-top:var(--space-2)">Le cumul débute à la plus ancienne prise de responsabilité (<?= h($cumulSourceLabel[$sheet['cumul_source']] ?? '') ?>).</p>
  <?php endif; ?>
</div>

<div class="chart-card">
  <h3 class="suivi-chart-title">Historique des bilans</h3>
  <?php if ($restricted): ?>
    <p class="suivi-cell-sub">L’historique des bilans n’est pas exposé hors du périmètre du spectateur.</p>
  <?php elseif (!$sheet['history']): ?>
    <p class="suivi-cell-sub">Aucun bilan historisé pour le moment. Les bilans hebdomadaires et mensuels apparaîtront ici après leur premier envoi.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table suivi-history-table">
        <thead>
          <tr><th>Période</th><th>Attendus</th><th>Soumis</th><th>À temps</th><th>En retard</th><th>Réalisation</th><th>Ponctualité</th><th>Niveau</th></tr>
        </thead>
        <tbody>
          <?php foreach ($sheet['history'] as $hrow): ?>
            <tr>
              <td><?= (string) $hrow['period_type'] === 'week' ? 'Semaine ' : ((string) $hrow['period_type'] === 'month' ? 'Mois ' : 'Cumul ') ?><?= h(date('d/m/Y', strtotime((string) $hrow['period_start']))) ?> → <?= h(date('d/m/Y', strtotime((string) $hrow['period_end']))) ?></td>
              <td><?= (int) $hrow['expected_count'] ?></td>
              <td><?= (int) $hrow['submitted_count'] ?></td>
              <td><?= (int) $hrow['on_time_count'] ?></td>
              <td><?= (int) $hrow['late_count'] ?></td>
              <td><?= $fmtRate($hrow['completion_rate'] !== null ? (float) $hrow['completion_rate'] : null) ?></td>
              <td><?= $fmtRate($hrow['punctuality_rate'] !== null ? (float) $hrow['punctuality_rate'] : null) ?></td>
              <td><?= h((string) ($hrow['level_key'] ?? 'Non évalué')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
