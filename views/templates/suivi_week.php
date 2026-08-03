<?php /* Tableau de suivi hebdomadaire.
           Variables : $backBtn, $member, $weekKey, $weekRange, $mondayDate, $week,
                       $fields, $days, $pct, $adminStats, $csrf, $isAdmin, $semaineNav. */ ?>
<?= $backBtn ?>
<div class="section-toolbar">
  <div>
    <h2><?= h(trim($member['prenom'] . ' ' . $member['nom'])) ?></h2>
    <div class="sub">Tableau de suivi hebdomadaire</div>
  </div>
  <form method="get" action="index.php" class="suivi-toolbar">
    <input type="hidden" name="page" value="suiviBergers">
    <input type="hidden" name="membre" value="<?= h($member['id']) ?>">
    <label>Date (année / mois / jour)</label>
    <input type="date" name="semaine" value="<?= h($mondayDate) ?>" onchange="this.form.submit()">
  </form>
</div>
<div class="week-range-label"><?= h($weekRange) ?></div>

<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_suivi">
  <?= $csrf ?>
  <input type="hidden" name="membre" value="<?= h($member['id']) ?>">
  <input type="hidden" name="semaine" value="<?= h($weekKey) ?>">

  <div class="table-wrap table-scroll">
    <table class="data-table suivi-table">
      <thead>
        <tr>
          <th>Jour</th>
          <?php foreach ($fields as $f): ?>
            <th><?= h($f['label']) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($days as $dayIndex => $day): ?>
          <?php $isSunday = $day === 'Dimanche'; $data = $week[$day] ?? []; ?>
          <tr>
            <td class="day-cell"><?= h($day) ?><span class="day-date"><?= format_date_short(date_for_day_in_week($weekKey, $dayIndex)) ?></span></td>
            <?php foreach ($fields as $f): ?>
              <?php if (!empty($f['sundayOnly']) && !$isSunday): ?>
                <td class="cell-na">—</td>
                <?php continue; ?>
              <?php endif; ?>
              <?php $val = $data[$f['key']] ?? ''; ?>
              <td>
                <?php if (($f['type'] ?? 'text') === 'select'): ?>
                  <select name="suivi[<?= h($day) ?>][<?= h($f['key']) ?>]">
                    <option value="">—</option>
                    <option value="Oui" <?= $val === 'Oui' ? 'selected' : '' ?>>Oui</option>
                    <option value="Non" <?= $val === 'Non' ? 'selected' : '' ?>>Non</option>
                  </select>
                <?php else: ?>
                  <input type="<?= ($f['type'] ?? 'text') === 'number' ? 'number' : 'text' ?>"
                         <?= ($f['type'] ?? 'text') === 'number' ? 'min="0"' : '' ?>
                         name="suivi[<?= h($day) ?>][<?= h($f['key']) ?>]" value="<?= h($val) ?>">
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="modal-actions">
    <span class="completion-chip">Réalisation : <b><?= (int) $pct ?> %</b></span>
    <button type="submit" class="btn btn-primary">Enregistrer la semaine</button>
  </div>
</form>

<?= $adminStats ?>
