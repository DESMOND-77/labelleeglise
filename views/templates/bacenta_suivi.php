<?php /* Suivi mensuel d'un bacenta : visites + offrandes.
           Variables : $bacenta, $monthKey, $monthLabel, $monthOptions, $visites, $offrandes,
                       $monthTotal, $yearTotal, $year, $csrf. */ ?>
<div class="section-toolbar">
  <div>
    <h2><?= h($bacenta['nom']) ?></h2>
    <div class="sub">Suivi mensuel — Visites & Offrandes</div>
  </div>
  <form method="get" action="index.php" class="suivi-toolbar">
    <input type="hidden" name="page" value="bacentas">
    <input type="hidden" name="id" value="<?= h($bacenta['id']) ?>">
    <input type="hidden" name="tab" value="suivi">
    <label>Mois</label>
    <select name="semaine" onchange="this.form.submit()"><?= $monthOptions ?></select>
  </form>
</div>

<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_visites_offrandes">
  <?= $csrf ?>
  <input type="hidden" name="bacenta" value="<?= h($bacenta['id']) ?>">
  <input type="hidden" name="mois" value="<?= h($monthKey) ?>">

  <div class="suivi-block">
    <h3>🧑‍🤝‍🧑 Fiche Visites — <?= h($monthLabel) ?></h3>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Semaine</th><th>Nom du visiteur</th><th>Date de visite</th><th>Observation</th></tr></thead>
        <tbody>
          <?php foreach ($visites as $i => $v): ?>
            <tr>
              <td><?= h(DIMANCHES_LABELS[$i]) ?></td>
              <td><input type="text" name="visites[<?= $i ?>][nom_visite]" value="<?= h($v['nom_visite']) ?>" placeholder="Nom du visiteur"></td>
              <td><input type="date" name="visites[<?= $i ?>][date_visite]" value="<?= h($v['date_visite']) ?>"></td>
              <td><input type="text" name="visites[<?= $i ?>][observations]" value="<?= h($v['observations']) ?>" placeholder="Observation"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="suivi-block">
    <h3>💰 Offrandes des vendredis — <?= h($monthLabel) ?></h3>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Semaine</th><th>Montant (FCFA)</th></tr></thead>
        <tbody>
          <?php foreach ($offrandes as $i => $montant): ?>
            <tr>
              <td>Vendredi <?= $i + 1 ?></td>
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
