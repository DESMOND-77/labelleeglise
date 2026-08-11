<?php /* Formulaire bacenta. Variables : $bacenta, $centreOpts, $cancelUrl, $csrf, $respUrl. */
$bacenta = $bacenta ?? null;
?>
<?= section_toolbar(h($bacenta ? 'Modifier le bacenta' : 'Ajouter un bacenta')) ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card">
    <input type="hidden" name="action" value="save_bacenta">
    <?= $csrf ?>
    <?php if ($bacenta): ?><input type="hidden" name="id" value="<?= h($bacenta['id']) ?>"><?php endif; ?>
    <div class="form-group"><label>Nom du bacenta</label><input type="text" name="nom" value="<?= h($bacenta['nom'] ?? '') ?>" required autofocus></div>
    <div class="form-group"><label>Centre (structure)</label><select name="centre_id"><?= $centreOpts ?></select></div>
    <?php if (!empty($respUrl)): ?>
    <div class="form-group"><label>Responsable(s)</label><p class="form-hint">Le rôle est distinct de la responsabilité : gérez les responsables de ce bacenta depuis <a href="<?= h($respUrl) ?>">Paramètres → Accès &amp; Responsables</a>.</p></div>
    <?php endif; ?>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
