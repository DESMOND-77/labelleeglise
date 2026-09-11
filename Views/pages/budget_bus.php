<?php /* Budget Bus du dimanche par centre (M2).
   Variables : $table, $centres, $year, $filterCentre, $edit, $errors, $old, $csrf.
   $table = ['centres' => [cid => ['nom','rows','months','total']], 'grandTotal', 'count']. */
$val = function (string $k, $default = '') use ($old, $edit) {
    if (array_key_exists($k, $old)) {
        return $old[$k];
    }
    return $edit[$k] ?? $default;
};
$editId   = $edit ? (int) $edit['id'] : (int) ($old['id'] ?? 0);
$fcfa     = static fn($n) => number_format((float) $n, 0, ',', ' ');
$yearsOpt = range((int) date('Y') + 1, (int) date('Y') - 5);
?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['budgetBus']) ?></h2><div class="sub">Sommes retirées / collectées par centre pour le bus du dimanche</div></div>
</div>

<form method="get" action="index.php" class="bus-filters">
  <input type="hidden" name="page" value="budgetBus">
  <label>Année
    <select name="annee" onchange="this.form.submit()">
      <?php foreach ($yearsOpt as $y): ?>
        <option value="<?= (int) $y ?>" <?= (int) $year === (int) $y ? 'selected' : '' ?>><?= (int) $y ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Centre
    <select name="centre" onchange="this.form.submit()">
      <option value="">Tous</option>
      <?php foreach ($centres as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) $filterCentre === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if ($filterCentre): ?><a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'budgetBus', 'annee' => $year])) ?>">Effacer</a><?php endif; ?>
</form>

<?php if (!empty($errors['_form'])): ?><div class="alert alert-danger"><?= h($errors['_form']) ?></div><?php endif; ?>

<form method="post" action="index.php" class="form-card bus-form">
  <input type="hidden" name="action" value="save_bus_budget">
  <?= $csrf ?>
  <input type="hidden" name="annee" value="<?= (int) $year ?>">
  <?php if ($editId): ?><input type="hidden" name="id" value="<?= (int) $editId ?>"><?php endif; ?>

  <h3 class="form-section-title"><?= $editId ? 'Modifier un mouvement' : 'Nouveau mouvement' ?></h3>
  <div class="form-grid">
    <div class="form-group">
      <label>Centre</label>
      <select name="centre_id">
        <option value="">— Choisir —</option>
        <?php foreach ($centres as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $val('centre_id') === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!empty($errors['centre_id'])): ?><span class="form-error"><?= h($errors['centre_id']) ?></span><?php endif; ?>
    </div>
    <div class="form-group">
      <label>Date</label>
      <input type="date" name="date_retrait" value="<?= h((string) $val('date_retrait', date('Y-m-d'))) ?>">
      <?php if (!empty($errors['date_retrait'])): ?><span class="form-error"><?= h($errors['date_retrait']) ?></span><?php endif; ?>
    </div>
    <div class="form-group">
      <label>Montant (FCFA)</label>
      <input type="text" inputmode="decimal" name="montant" value="<?= h((string) $val('montant', '0')) ?>">
      <?php if (!empty($errors['montant'])): ?><span class="form-error"><?= h($errors['montant']) ?></span><?php endif; ?>
    </div>
  </div>
  <div class="form-grid">
    <div class="form-group form-group-wide">
      <label>Observations</label>
      <textarea name="observations" rows="2"><?= h((string) $val('observations')) ?></textarea>
    </div>
  </div>
  <div class="modal-actions">
    <button type="submit" class="btn btn-primary"><?= $editId ? 'Enregistrer les modifications' : 'Ajouter le mouvement' ?></button>
    <?php if ($editId): ?><a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'budgetBus', 'annee' => $year])) ?>">Annuler</a><?php endif; ?>
  </div>
</form>

<?php if (empty($table['centres'])): ?>
  <?= empty_state('fa-bus', 'Aucun mouvement enregistré pour ' . (int) $year . '.') ?>
<?php else: ?>
  <?php foreach ($table['centres'] as $cid => $centre): ?>
    <div class="bus-centre">
      <div class="bus-centre-head">
        <h3><?= h($centre['nom']) ?></h3>
        <?= total_chip('Total ' . (int) $year, $fcfa($centre['total']) . ' FCFA') ?>
      </div>
      <div class="table-wrap">
        <table class="data-table" data-no-paginate>
          <thead><tr><th>Date</th><th class="text-right">Montant</th><th>Observations</th><th>Auteur</th><th></th></tr></thead>
          <tbody>
            <?php $curMonth = null; $monthSum = 0.0; ?>
            <?php foreach ($centre['rows'] as $r): ?>
              <?php $m = substr((string) $r['date_retrait'], 0, 7); ?>
              <?php if ($curMonth !== null && $m !== $curMonth): ?>
                <tr class="bus-subtotal"><td><?= h(month_label($curMonth)) ?></td><td class="text-right"><?= $fcfa($monthSum) ?></td><td colspan="3">Sous-total du mois</td></tr>
                <?php $monthSum = 0.0; ?>
              <?php endif; ?>
              <?php $curMonth = $m; $monthSum += (float) $r['montant']; ?>
              <tr>
                <td><?= h(date('d/m/Y', strtotime((string) $r['date_retrait']))) ?></td>
                <td class="text-right"><?= $fcfa($r['montant']) ?></td>
                <td><?= h((string) ($r['observations'] ?? '')) ?: '—' ?></td>
                <td><?= h(trim(($r['auteur_prenom'] ?? '') . ' ' . ($r['auteur_nom'] ?? ''))) ?: '—' ?></td>
                <td class="row-actions">
                  <a class="icon-btn" title="Modifier" href="<?= h(url('index.php', ['page' => 'budgetBus', 'annee' => $year, 'edit' => $r['id']])) ?>"><i class="fa-solid fa-pen"></i></a>
                  <a class="icon-btn danger" title="Supprimer" href="<?= h(url('index.php', ['action' => 'delete_bus_budget', 'id' => $r['id']])) ?>" onclick="return confirm('Supprimer ce mouvement ?')"><i class="fa-solid fa-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if ($curMonth !== null): ?>
              <tr class="bus-subtotal"><td><?= h(month_label($curMonth)) ?></td><td class="text-right"><?= $fcfa($monthSum) ?></td><td colspan="3">Sous-total du mois</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="bus-grand-total">
    <?= total_chip('Total général ' . (int) $year . ($filterCentre ? '' : ' — tous centres'), $fcfa($table['grandTotal']) . ' FCFA') ?>
  </div>
<?php endif; ?>
