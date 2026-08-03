<?php /* Formulaire culte. Variables : $culte, $respOpts, $cancelUrl, $csrf. */
$culte = $culte ?? null;
?>
<?= section_toolbar(h($culte ? 'Modifier le culte' : 'Ajouter un culte')) ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card">
    <input type="hidden" name="action" value="save_culte">
    <?= $csrf ?>
    <?php if ($culte): ?><input type="hidden" name="id" value="<?= h($culte['id']) ?>"><?php endif; ?>
    <div class="form-group"><label>Nom du culte</label><input type="text" name="nom" value="<?= h($culte['nom'] ?? '') ?>" required autofocus></div>
    <div class="form-grid">
      <div class="form-group"><label>Date</label><input type="date" name="date_culte" value="<?= h($culte['date_culte'] ?? '') ?>"></div>
      <div class="form-group"><label>Responsable</label><select name="responsable_id"><?= $respOpts ?></select></div>
      <div class="form-group"><label>Heure début</label><input type="time" name="heure_debut" value="<?= h($culte['heure_debut'] ?? '') ?>"></div>
      <div class="form-group"><label>Heure fin</label><input type="time" name="heure_fin" value="<?= h($culte['heure_fin'] ?? '') ?>"></div>
    </div>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
