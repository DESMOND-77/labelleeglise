<?php /* À propos : phrase d'accroche, histoire et image de la section « À propos ».
   Variables : $settings, $presentation, $csrf. */ ?>
<div class="page-header">
  <h2>À propos</h2>
  <p class="sub">Texte et image de la section « À propos » de la vitrine.
    <a class="btn btn-sm" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>#a-propos" target="_blank" rel="noopener noreferrer">Prévisualiser la section</a>
  </p>
</div>

<form class="form-card" method="post" action="index.php" enctype="multipart/form-data" data-track-dirty>
  <input type="hidden" name="action" value="save_about_public">
  <?= $csrf ?>

  <fieldset class="form-group">
    <legend>Présentation de l'église</legend>
    <label>Phrase d'accroche
      <input type="text" name="accroche" maxlength="300" value="<?= h($presentation['accroche'] ?? '') ?>">
    </label>
    <label>Histoire de l'église (séparez les paragraphes par une ligne vide)
      <textarea name="histoire" rows="10" maxlength="8000"><?= h($presentation['histoire'] ?? '') ?></textarea>
    </label>
    <p class="form-hint">La section n'apparaît sur la vitrine que si l'accroche ou l'histoire est renseignée. Son titre devient « Notre histoire » dès qu'une histoire est saisie.</p>
  </fieldset>

  <fieldset class="form-group">
    <legend>Image</legend>
    <div class="field-row">
      <label>Image de la section - jpg, png, webp ou gif, 4 Mo max
        <input type="file" name="about_image" accept="image/*">
      </label>
      <div class="field-note">
        <?php if (!empty($settings['about_image'])): ?>
          <img class="preview-thumb" src="<?= h($settings['about_image']) ?>" alt="Image actuelle de la section À propos">
          <p>Image actuelle (conservée si aucun nouveau fichier n'est choisi).</p>
        <?php else: ?>
          <p>Aucune image dédiée : la vitrine utilise l'image d'accueil, sinon la photo d'un centre publié.</p>
        <?php endif; ?>
        <img class="preview-thumb" id="about-preview" alt="" hidden>
      </div>
    </div>
    <input type="hidden" name="about_image_current" value="<?= h($settings['about_image'] ?? '') ?>">
    <label>Texte alternatif de l'image (description pour les lecteurs d'écran)
      <input type="text" name="about_image_alt" maxlength="200" value="<?= h($settings['about_image_alt'] ?? '') ?>">
    </label>
  </fieldset>

  <fieldset class="form-group">
    <legend>Affiché automatiquement</legend>
    <ul class="form-hint">
      <li>Les chiffres (centres, années de ministère, pasteurs et bergers, groupes de prière) sont calculés à partir des centres et des ministères publiés.</li>
      <li>L'équipe pastorale se règle dans l'onglet <a href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'team'])) ?>">Équipe pastorale</a>.</li>
    </ul>
  </fieldset>

  <button class="btn btn-primary" type="submit">Enregistrer les modifications</button>
</form>
