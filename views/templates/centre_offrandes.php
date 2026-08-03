<?php /* Offrandes d'un centre (4 mercredis par mois).
           Variables : $centre, $monthKey, $monthOptions, $offrandes, $monthTotal, $yearTotal, $year, $csrf. */ ?>
<div class="section-toolbar">
  <div>
    <h2><?= h($centre['nom']) ?> — Offrandes</h2>
    <div class="sub">Offrandes des mercredis</div>
  </div>
  <form method="get" action="index.php" class="suivi-toolbar">
    <input type="hidden" name="page" value="centres">
    <input type="hidden" name="id" value="<?= h($centre['id']) ?>">
    <input type="hidden" name="tab" value="suivi">
    <label>Mois</label>
    <select name="semaine" onchange="this.form.submit()"><?= $monthOptions ?></select>
  </form>
</div>

<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_offrandes_centre">
  <?= $csrf ?>
  <input type="hidden" name="centre" value="<?= h($centre['id']) ?>">
  <input type="hidden" name="mois" value="<?= h($monthKey) ?>">
  <div class="suivi-block">
    <h3>💰 Offrandes — <?= h($monthLabel ?? month_label($monthKey)) ?></h3>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Semaine</th><th>Montant (FCFA)</th></tr></thead>
        <tbody>
          <?php foreach ($offrandes as $i => $montant): ?>
            <tr>
              <td>Mercredi <?= $i + 1 ?></td>
              <td><input type="number" min="0" step="0.01" name="offrandes[<?= $i ?>]" value="<?= $montant ? h($montant) : '' ?>" placeholder="0"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="totals-row">
      <?= total_chip('Total du mois', format_fcfa($monthTotal)) ?>
      <?= total_chip('Total de l\'année ' . $year, format_fcfa($yearTotal)) ?>
    </div>
  </div>
  <div class="modal-actions">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
  </div>
</form>
