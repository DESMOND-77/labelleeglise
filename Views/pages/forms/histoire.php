<?php /* Formulaire histoire. Variables : $accroche, $histoire, $cancelUrl, $csrf. */ ?>
<?= section_toolbar('Modifier la présentation') ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card">
    <input type="hidden" name="action" value="save_histoire">
    <?= $csrf ?>
    <div class="form-group">
      <label>Phrase d'accroche</label>
      <input type="text" name="accroche" value="<?= h($accroche) ?>">
    </div>
    <div class="form-group">
      <label>Histoire de l'église</label>
      <textarea name="histoire" rows="8"><?= h($histoire) ?></textarea>
    </div>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
