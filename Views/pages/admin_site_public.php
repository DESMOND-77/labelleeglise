<?php /* Administration du site public. Variables : $settings, $centres, $csrf, $errors, $saved. */ ?>
<?= section_toolbar('Site public', 'Contenus affichés sur la page d\'accueil publique. Rien n\'est publié tant que vous ne l\'avez pas renseigné.') ?>

<?php if ($saved): ?>
  <div class="alert alert-success" role="status">Modifications enregistrées.</div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger" role="alert"><?= h($e) ?></div>
<?php endforeach; ?>

<p>
  <a class="btn" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>">Voir la page publique</a>
  <a class="btn" href="<?= h(url('index.php', ['page' => 'apropos'])) ?>">Gérer histoire et équipe</a>
  <a class="btn" href="<?= h(url('index.php', ['page' => 'centresPresentation'])) ?>">Gérer les articles des centres</a>
</p>

<form class="form-card" method="post" action="index.php">
  <input type="hidden" name="action" value="save_site_settings">
  <?= $csrf ?>
  <h3>Identité et contacts généraux</h3>
  <label>Nom d'affichage<input type="text" name="nom_affichage" maxlength="150" value="<?= h($settings['nom_affichage'] ?? '') ?>"></label>
  <label>Téléphone<input type="tel" name="telephone" maxlength="30" value="<?= h($settings['telephone'] ?? '') ?>"></label>
  <label>WhatsApp (avec indicatif international)<input type="tel" name="whatsapp" maxlength="30" value="<?= h($settings['whatsapp'] ?? '') ?>"></label>
  <label>E-mail<input type="email" name="email" maxlength="150" value="<?= h($settings['email'] ?? '') ?>"></label>
  <label>Facebook (https)<input type="url" name="facebook" value="<?= h($settings['facebook'] ?? '') ?>"></label>
  <label>YouTube (https)<input type="url" name="youtube" value="<?= h($settings['youtube'] ?? '') ?>"></label>
  <label>Instagram (https)<input type="url" name="instagram" value="<?= h($settings['instagram'] ?? '') ?>"></label>
  <label>Lien du culte en direct (https)<input type="url" name="lien_direct" value="<?= h($settings['lien_direct'] ?? '') ?>"></label>
  <button class="btn btn-primary" type="submit">Enregistrer</button>
</form>

<?php if (!$centres): ?>
  <?= empty_state('fa-school', 'Aucun centre. Créez d\'abord un centre dans la section Centres.') ?>
<?php endif; ?>

<?php foreach ($centres as $c): ?>
  <form class="form-card" method="post" action="index.php">
    <input type="hidden" name="action" value="save_centre_public">
    <input type="hidden" name="centre_id" value="<?= (int) $c['id'] ?>">
    <?= $csrf ?>
    <h3><?= h($c['nom']) ?></h3>
    <label class="check-label"><input type="checkbox" name="publie" value="1"<?= !empty($c['publie']) ? ' checked' : '' ?>> Publier ce centre sur le site public</label>
    <label>Adresse<input type="text" name="adresse" maxlength="255" value="<?= h($c['adresse'] ?? '') ?>"></label>
    <label>Quartier<input type="text" name="quartier" maxlength="100" value="<?= h($c['quartier'] ?? '') ?>"></label>
    <label>Ville<input type="text" name="ville" maxlength="100" value="<?= h($c['ville'] ?? '') ?>"></label>
    <label>Lien d'itinéraire (https)<input type="url" name="itineraire_url" value="<?= h($c['itineraire_url'] ?? '') ?>"></label>
    <label>Téléphone<input type="tel" name="telephone" maxlength="30" value="<?= h($c['telephone'] ?? '') ?>"></label>
    <label>WhatsApp<input type="tel" name="whatsapp" maxlength="30" value="<?= h($c['whatsapp'] ?? '') ?>"></label>
    <label>E-mail<input type="email" name="email" maxlength="150" value="<?= h($c['email'] ?? '') ?>"></label>
    <label>Horaires de culte (une ligne : Libellé | Jours | HH:MM | HH:MM facultatif)
      <textarea name="horaires" rows="3"><?= h($c['horaires_text'] ?? '') ?></textarea>
    </label>
    <p class="sub">Publication : adresse, au moins un horaire et un moyen de contact (centre ou général) sont requis.</p>
    <button class="btn btn-primary" type="submit">Enregistrer ce centre</button>
  </form>
<?php endforeach; ?>
