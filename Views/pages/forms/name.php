<?php /* Formulaire de nom (quartier / groupe / item). Variables : $title, $action, $name, $extra, $cancelUrl. */ ?>
<?= section_toolbar(h($title)) ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card">
    <input type="hidden" name="action" value="<?= h($action) ?>">
    <?= $extra ?>
    <div class="form-group">
      <label>Nom</label>
      <input type="text" name="name" value="<?= h($name) ?>" required autofocus>
    </div>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
