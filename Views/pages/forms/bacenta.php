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
    <div class="form-group">
      <label>Jour(s) de rassemblement (récurrence hebdomadaire)</label>
      <div class="checkbox-row">
        <?php $bJours = explode(',', (string) ($bacenta['jours_semaine'] ?? '')); ?>
        <?php foreach (WEEK_DAYS as $d): ?>
          <label class="check-label"><input type="checkbox" name="jours_semaine[]" value="<?= h($d) ?>" <?= in_array($d, $bJours, true) ? 'checked' : '' ?>> <?= h($d) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Heure début</label><input type="time" name="heure_debut" value="<?= h($bacenta['heure_debut'] ?? '') ?>"></div>
      <div class="form-group"><label>Heure fin</label><input type="time" name="heure_fin" value="<?= h($bacenta['heure_fin'] ?? '') ?>"></div>
    </div>
    <?php if (!empty($respUrl)): ?>
    <div class="form-group"><label>Responsable(s)</label><p class="form-hint">Le rôle est distinct de la responsabilité : gérez les responsables de ce bacenta depuis <a href="<?= h($respUrl) ?>">Paramètres → Accès &amp; Responsables</a>.</p></div>
    <?php endif; ?>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
