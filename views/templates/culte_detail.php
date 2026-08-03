<?php /* Détail d'un culte : pointage de présence + liste des présents.
           Variables : $culte, $presents, $isAdmin, $csrf, $defaultDate. */
$date = $culte['date_culte'] ? date('d/m/Y', strtotime($culte['date_culte'])) : 'Date à définir';
$allUsers = qall("SELECT id, prenom, nom FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY prenom, nom");
$presentIds = array_map(fn($p) => (int) $p['id'], $presents);
?>
<div class="dash-section-title"><h2>🙌 Pointer la présence</h2><span><?= h($culte['nom']) ?> — <?= h($date) ?></span></div>
<form method="post" action="index.php" class="suivi-block">
  <input type="hidden" name="action" value="point_culte">
  <?= $csrf ?>
  <input type="hidden" name="culte" value="<?= h($culte['id']) ?>">
  <div class="form-group" style="max-width:280px;">
    <label>Date de la présence</label>
    <input type="date" name="date_presence" value="<?= h($defaultDate) ?>">
  </div>
  <div class="presence-check-grid">
    <?php foreach ($allUsers as $u): ?>
      <label class="check-label">
        <input type="checkbox" name="present[<?= h($u['id']) ?>]" value="1" <?= in_array((int) $u['id'], $presentIds, true) ? 'checked' : '' ?>>
        <?= h(full_name($u)) ?>
      </label>
    <?php endforeach; ?>
  </div>
  <div class="modal-actions">
    <button type="submit" class="btn btn-primary">Enregistrer les présences</button>
  </div>
</form>

<div class="dash-section-title"><h2>👥 Présents (<?= count($presents) ?>)</h2></div>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Nom</th><th>Téléphone</th><th>Quartier</th><th>Présence</th></tr></thead>
    <tbody>
      <?php if (!$presents): ?>
        <tr><td colspan="4"><?= empty_state('📭', 'Aucune présence enregistrée pour ce culte.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($presents as $p): ?>
          <tr>
            <td><?= h(full_name($p)) ?></td>
            <td><?= h($p['telephone'] ?? '') ?></td>
            <td><?= h($p['quartier'] ?? '') ?></td>
            <td><span class="badge present">Présent</span></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
