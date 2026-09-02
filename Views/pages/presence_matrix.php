<?php /* Matrice annuelle des présences d'une unité (in-app, lecture seule).
   Variables : $unit, $pageKey, $unitType, $year, $matrix, $statuts, $printUrl, $occUrl. */
$cls = ['present' => 'presence-cell-present', 'absent' => 'presence-cell-absent', 'excuse' => 'presence-cell-excuse'];
?>
<div class="section-toolbar">
  <div>
    <h2><?= h($unit['nom']) ?></h2>
    <div class="sub">Présences <?= (int) $year ?> — matrice annuelle</div>
  </div>
  <div class="toolbar-actions">
    <form method="get" action="index.php" class="inline-form">
      <input type="hidden" name="page" value="<?= h($pageKey) ?>">
      <input type="hidden" name="id" value="<?= h($unit['id']) ?>">
      <input type="hidden" name="tab" value="presences_annuel">
      <label>Année <input type="number" name="year" value="<?= (int) $year ?>" min="2000" max="2100" onchange="this.form.submit()"></label>
    </form>
    <a class="btn btn-outline" href="<?= h($occUrl) ?>"><i class="fa-solid fa-clipboard-check"></i> Pointer une date</a>
    <a class="btn btn-primary" href="<?= h($printUrl) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Imprimer</a>
  </div>
</div>

<?php if (!$matrix['dates']): ?>
  <?= empty_state('fa-table-cells', "Aucune présence enregistrée pour cette unité en {$year}.") ?>
<?php else: ?>
<div class="table-wrap table-scroll">
  <table class="presence-matrix">
    <thead>
      <tr><th>Membre</th>
        <?php foreach ($matrix['dates'] as $d): ?><th><?= h(date('d/m', strtotime($d))) ?></th><?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($matrix['rows'] as $row): ?>
        <tr>
          <td><?= h(full_name($row['user'])) ?></td>
          <?php foreach ($matrix['dates'] as $d): $s = $row['cells'][$d] ?? ''; ?>
            <td class="<?= h($cls[$s] ?? '') ?>"><?= $s ? h(mb_substr($statuts[$s], 0, 1)) : '—' ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="presence-hint">P = Présent · A = Absent · E = Excusé</p>
</div>
<?php endif; ?>
