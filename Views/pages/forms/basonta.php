<?php /* Formulaire basonta. Variables : $basonta, $cancelUrl, $csrf. */
$basonta = $basonta ?? null;
$jours = explode(',', (string) ($basonta['jours_semaine'] ?? ''));
?>
<?= section_toolbar(h($basonta ? 'Modifier le basonta' : 'Ajouter un basonta')) ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card">
    <input type="hidden" name="action" value="save_basonta">
    <?= $csrf ?>
    <?php if ($basonta): ?><input type="hidden" name="id" value="<?= h($basonta['id']) ?>"><?php endif; ?>
    <div class="form-group"><label>Nom du basonta</label><input type="text" name="nom" value="<?= h($basonta['nom'] ?? '') ?>" required autofocus></div>
    <div class="form-group">
      <label>Jour(s) de rassemblement (récurrence hebdomadaire)</label>
      <div class="checkbox-row">
        <?php foreach (WEEK_DAYS as $d): ?>
          <label class="check-label"><input type="checkbox" name="jours_semaine[]" value="<?= h($d) ?>" <?= in_array($d, $jours, true) ? 'checked' : '' ?>> <?= h($d) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Heure début</label><input type="time" name="heure_debut" value="<?= h($basonta['heure_debut'] ?? '') ?>"></div>
      <div class="form-group"><label>Heure fin</label><input type="time" name="heure_fin" value="<?= h($basonta['heure_fin'] ?? '') ?>"></div>
    </div>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
