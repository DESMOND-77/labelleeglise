<?php /* Pointage de présence d'une occurrence (bacenta / basonta / culte).
   Variables : $unitType, $unit, $pageKey, $date, $grid, $summary, $statuts, $joursHint, $csrf, $matrixUrl. */ ?>
<div class="section-toolbar">
  <div>
    <h2><?= h($unit['nom']) ?></h2>
    <div class="sub">Pointage des présences — une date</div>
  </div>
  <a class="btn btn-outline" href="<?= h($matrixUrl) ?>"><i class="fa-solid fa-table-cells"></i> Matrice annuelle</a>
</div>

<?= view('pages/partials/attendance_pointage', [
    'unitType'   => $unitType,
    'unitId'     => (int) $unit['id'],
    'unitLabel'  => $unit['nom'],
    'date'       => $date,
    'dateBarUrl' => ['page' => $pageKey, 'id' => (int) $unit['id'], 'tab' => 'presences'],
    'grid'       => $grid,
    'summary'    => $summary,
    'statuts'    => $statuts,
    'joursHint'  => $joursHint,
    'csrf'       => $csrf,
    'canPointe'  => true,
]) ?>
