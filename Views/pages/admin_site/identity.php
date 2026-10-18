<?php /* Identité et accueil (nom, slogan, accueil, adresse, image, contacts, réseaux).
   Variables : $settings, $csrf. */ ?>
<div class="page-header">
  <h2>Identité et accueil</h2>
  <p class="sub">Nom, slogan, texte d'accueil, adresse générale, image d'accueil et coordonnées affichés sur la vitrine publique.</p>
</div>

<form class="form-card" method="post" action="index.php" enctype="multipart/form-data" data-track-dirty>
  <input type="hidden" name="action" value="save_site_settings">
  <input type="hidden" name="return_tab" value="identity">
  <?= $csrf ?>

  <fieldset class="form-group">
    <legend>Identité visuelle et accueil</legend>
    <label>Nom d'affichage <span class="req" title="Requis">*</span>
      <input type="text" name="nom_affichage" maxlength="150" required value="<?= h($settings['nom_affichage'] ?? '') ?>">
    </label>
    <label>Slogan (une phrase courte)
      <input type="text" name="slogan" maxlength="200" value="<?= h($settings['slogan'] ?? '') ?>">
    </label>
    <label>Texte d'accueil (accueil chaleureux des visiteurs)
      <textarea name="accueil_texte" rows="3" maxlength="500"><?= h($settings['accueil_texte'] ?? '') ?></textarea>
    </label>
    <label>Adresse (siège)
      <input type="text" name="adresse" maxlength="255" value="<?= h($settings['adresse'] ?? '') ?>">
    </label>
    <div class="field-row">
      <label>Image d'accueil (hero) — jpg, png, webp ou gif, 4 Mo max
        <input type="file" name="hero_image" accept="image/*">
      </label>
      <div class="field-note">
        <?php if (!empty($settings['hero_image'])): ?>
          <img class="preview-thumb" src="<?= h($settings['hero_image']) ?>" alt="Image d'accueil actuelle">
          <p>Image actuelle (conservée si aucun nouveau fichier n'est choisi).</p>
        <?php else: ?>
          <p>Aucune image — le dégradé de marque est utilisé.</p>
        <?php endif; ?>
        <img class="preview-thumb" id="hero-preview" alt="" hidden>
      </div>
    </div>
    <input type="hidden" name="hero_image_current" value="<?= h($settings['hero_image'] ?? '') ?>">
    <label>Texte alternatif de l'image d'accueil (description pour les lecteurs d'écran)
      <input type="text" name="hero_image_alt" maxlength="200" value="<?= h($settings['hero_image_alt'] ?? '') ?>">
    </label>
  </fieldset>

  <fieldset class="form-group">
    <legend>Coordonnées et réseaux officiels</legend>
    <div class="field-grid">
      <label>Téléphone<input type="tel" name="telephone" maxlength="30" value="<?= h($settings['telephone'] ?? '') ?>"></label>
      <label>WhatsApp (indicatif international)<input type="tel" name="whatsapp" maxlength="30" value="<?= h($settings['whatsapp'] ?? '') ?>"></label>
      <label>E-mail<input type="email" name="email" maxlength="150" value="<?= h($settings['email'] ?? '') ?>"></label>
      <label>Facebook (https)<input type="url" name="facebook" value="<?= h($settings['facebook'] ?? '') ?>"></label>
      <label>YouTube (https)<input type="url" name="youtube" value="<?= h($settings['youtube'] ?? '') ?>"></label>
      <label>Instagram (https)<input type="url" name="instagram" value="<?= h($settings['instagram'] ?? '') ?>"></label>
    </div>
  </fieldset>

  <button class="btn btn-primary" type="submit">Enregistrer les modifications</button>
</form>
