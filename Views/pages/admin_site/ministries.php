<?php /* Ministères et activités - bacentas (prière) / basontas (groupes et services).
   Variables : $ministries, $csrf. */ ?>
<div class="page-header">
  <h2>Ministères et activités</h2>
  <p class="sub">Aucune liste de membres n'est jamais affichée publiquement.</p>
</div>

<form class="form-card" method="post" action="index.php" data-track-dirty>
  <input type="hidden" name="action" value="save_ministries_public">
  <?= $csrf ?>

  <fieldset class="form-group">
    <legend>Bacentas - rassemblements de prière</legend>
    <?php if (!$ministries['bacentas']): ?>
      <p class="sub">Aucun bacenta enregistré (section « Bacentas » de l'application).</p>
    <?php endif; ?>
    <?php foreach ($ministries['bacentas'] as $m): ?>
      <div class="ministry-row">
        <label class="check-label">
          <input type="checkbox" name="bacentas_publie[]" value="<?= (int) $m['id'] ?>"<?= !empty($m['publie']) ? ' checked' : '' ?>>
          <span class="team-row-name"><?= h($m['nom']) ?></span>
        </label>
        <label class="ministry-desc">Description publique (public concerné, horaire, contact…)
          <textarea name="bacentas_description[<?= (int) $m['id'] ?>]" rows="2" maxlength="2000"><?= h($m['description_publique'] ?? '') ?></textarea>
        </label>
      </div>
    <?php endforeach; ?>
  </fieldset>

  <fieldset class="form-group">
    <legend>Basontas - groupes et services (chorale, pianistes, ushers…)</legend>
    <?php if (!$ministries['basontas']): ?>
      <p class="sub">Aucun basonta enregistré (section « Basontas » de l'application).</p>
    <?php endif; ?>
    <?php foreach ($ministries['basontas'] as $m): ?>
      <div class="ministry-row">
        <label class="check-label">
          <input type="checkbox" name="basontas_publie[]" value="<?= (int) $m['id'] ?>"<?= !empty($m['publie']) ? ' checked' : '' ?>>
          <span class="team-row-name"><?= h($m['nom']) ?></span>
        </label>
        <label class="ministry-desc">Description publique (public concerné, horaire, contact…)
          <textarea name="basontas_description[<?= (int) $m['id'] ?>]" rows="2" maxlength="2000"><?= h($m['description_publique'] ?? '') ?></textarea>
        </label>
      </div>
    <?php endforeach; ?>
  </fieldset>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Enregistrer les ministères</button>
    <?php if ($ministries['bacentas'] || $ministries['basontas']): ?>
      <a class="btn" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>#vie-eglise" target="_blank" rel="noopener noreferrer">Prévisualiser la section</a>
    <?php endif; ?>
  </div>
</form>
