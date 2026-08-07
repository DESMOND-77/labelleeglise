<?php /* Dîmes. Variables : $member, $year, $yearOptions, $dimes, $monthsShort, $total. */ ?>
<form method="get" action="index.php" class="suivi-toolbar">
  <input type="hidden" name="page" value="bergerFiche">
  <input type="hidden" name="membre" value="<?= h($member['id']) ?>">
  <input type="hidden" name="tab" value="dimes">
  <label>Année</label>
  <select name="annee" onchange="this.form.submit()"><?= $yearOptions ?></select>
</form>

<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_dimes">
  <?= csrf_field() ?>
  <input type="hidden" name="membre" value="<?= h($member['id']) ?>">
  <input type="hidden" name="annee" value="<?= h($year) ?>">
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Mois</th><th>Montant (FCFA)</th></tr></thead>
      <tbody>
        <?php foreach ($dimes as $i => $montant): ?>
          <tr>
            <td><?= h($monthsShort[$i]) ?></td>
            <td><input type="number" min="0" step="0.01" name="dimes[<?= $i ?>]" value="<?= $montant ? h($montant) : '' ?>" placeholder="0"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="totals-row">
    <?= total_chip('Total ' . $year, format_fcfa($total)) ?>
  </div>
  <div class="modal-actions">
    <button type="submit" class="btn btn-primary">Enregistrer les dîmes</button>
  </div>
</form>
