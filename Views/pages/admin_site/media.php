<?php /* Médias et prédications - liens vers les plateformes officielles uniquement.
   Variables : $settings, $csrf. */ ?>
<div class="page-header">
  <h2>Médias et prédications</h2>
  <p class="sub">Liens vers les plateformes officielles (YouTube, etc.). Aucun streaming n'est hébergé par le site.</p>
</div>

<form class="form-card" method="post" action="index.php" data-track-dirty>
  <input type="hidden" name="action" value="save_site_settings">
  <input type="hidden" name="return_tab" value="media">
  <?= $csrf ?>

  <fieldset class="form-group">
    <legend>Culte en direct</legend>
    <label>Lien du culte en direct (https)
      <input type="url" name="lien_direct" value="<?= h($settings['lien_direct'] ?? '') ?>" placeholder="https://…">
    </label>
    <p class="sub">Le bouton « Regarder le culte en direct » n'apparaît que si ce lien est renseigné.</p>
  </fieldset>

  <fieldset class="form-group">
    <legend>Dernière prédication</legend>
    <div class="field-grid">
      <label>Titre<input type="text" name="predication_titre" maxlength="200" value="<?= h($settings['predication_titre'] ?? '') ?>"></label>
      <label>Date de la prédication<input type="date" name="predication_date" value="<?= h($settings['predication_date'] ?? '') ?>"></label>
    </div>
    <label>Lien de la prédication (https - plateforme officielle)
      <input type="url" name="predication_url" value="<?= h($settings['predication_url'] ?? '') ?>" placeholder="https://www.youtube.com/…">
    </label>
    <p class="sub">La carte « Dernière prédication » n'apparaît que si le lien est valide.</p>
  </fieldset>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Enregistrer les modifications</button>
    <?php if (!empty($settings['lien_direct']) || !empty($settings['predication_url'])): ?>
      <a class="btn" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>#medias" target="_blank" rel="noopener noreferrer">Prévisualiser la section</a>
    <?php endif; ?>
  </div>
</form>
