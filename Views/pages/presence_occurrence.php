<?php /* Pointage de présence d'une occurrence.
   Variables : $unitType, $unit, $pageKey, $date, $grid, $statuts, $joursHint, $csrf, $matrixUrl. */ ?>
<div class="section-toolbar">
  <div>
    <h2><?= h($unit['nom']) ?></h2>
    <div class="sub">Pointage des présences — une date</div>
  </div>
  <a class="btn btn-outline" href="<?= h($matrixUrl) ?>"><i class="fa-solid fa-table-cells"></i> Matrice annuelle</a>
</div>

<form method="get" action="index.php" class="presence-datebar">
  <input type="hidden" name="page" value="<?= h($pageKey) ?>">
  <input type="hidden" name="id" value="<?= h($unit['id']) ?>">
  <input type="hidden" name="tab" value="presences">
  <label>Date du rassemblement</label>
  <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
  <?php if ($joursHint !== ''): ?><span class="presence-hint">Jours habituels : <?= h($joursHint) ?></span><?php endif; ?>
</form>

<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_presence_occurrence">
  <?= $csrf ?>
  <input type="hidden" name="unit_type" value="<?= h($unitType) ?>">
  <input type="hidden" name="unit_id" value="<?= h($unit['id']) ?>">
  <input type="hidden" name="date" value="<?= h($date) ?>">

  <div class="table-wrap">
    <table class="data-table presence-table">
      <thead><tr><th>Membre</th><th>Statut</th></tr></thead>
      <tbody>
        <?php if (!$grid): ?>
          <tr><td colspan="2"><?= empty_state('fa-users', 'Aucun membre à pointer pour cette unité.') ?></td></tr>
        <?php else: ?>
          <?php foreach ($grid as $line): $u = $line['user']; ?>
            <tr>
              <td><?= h(full_name($u)) ?></td>
              <td>
                <select name="statut[<?= (int) $u['id'] ?>]">
                  <option value="">—</option>
                  <?php foreach ($statuts as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $line['statut'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="modal-actions">
    <button type="submit" class="btn btn-primary" <?= $grid ? '' : 'disabled' ?>>Enregistrer les présences</button>
  </div>
</form>
