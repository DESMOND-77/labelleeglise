<?php /* Pages légales et confidentialité. Variables : $settings, $csrf. */ ?>
<div class="page-header">
  <h2>Pages légales et confidentialité</h2>
  <p class="sub">Textes fournis et validés par l'église - jamais rédigés automatiquement.</p>
</div>

<div class="alert alert-warning" role="note">
  La politique de confidentialité doit être publiée <strong>avant</strong> tout formulaire public ou outil de mesure d'audience.
  Aucun outil de mesure n'est actuellement intégré.
</div>

<form class="form-card" method="post" action="index.php" data-track-dirty>
  <input type="hidden" name="action" value="save_site_settings">
  <input type="hidden" name="return_tab" value="legal">
  <?= $csrf ?>

  <fieldset class="form-group">
    <legend>Mentions légales</legend>
    <label>Texte des mentions légales
      <textarea name="mentions_legales" rows="8" placeholder="Éditeur, direction de publication, hébergeur, droits…"><?= h($settings['mentions_legales'] ?? '') ?></textarea>
    </label>
    <?php if (!empty($settings['mentions_legales'])): ?>
      <p class="sub"><a href="<?= h(url('index.php', ['page' => 'mentions_legales'])) ?>" target="_blank" rel="noopener noreferrer">Prévisualiser la page</a></p>
    <?php endif; ?>
  </fieldset>

  <fieldset class="form-group">
    <legend>Politique de confidentialité</legend>
    <label>Texte de la politique de confidentialité
      <textarea name="confidentialite" rows="8" placeholder="Données collectées, finalités, durée, droits…"><?= h($settings['confidentialite'] ?? '') ?></textarea>
    </label>
    <?php if (!empty($settings['confidentialite'])): ?>
      <p class="sub"><a href="<?= h(url('index.php', ['page' => 'confidentialite'])) ?>" target="_blank" rel="noopener noreferrer">Prévisualiser la page</a></p>
    <?php endif; ?>
  </fieldset>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Enregistrer les modifications</button>
  </div>
</form>
