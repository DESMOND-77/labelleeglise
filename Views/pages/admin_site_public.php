<?php /* Administration du site public. Variables : $settings,
   $centres, $equipe, $evenements, $faq, $ministries, $csrf,
   $errors, $saved. Rien n'est publié tant que l'administrateur
   n'a pas fourni et validé chaque contenu. */ ?>
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

<form class="form-card" method="post" action="index.php" enctype="multipart/form-data">
  <input type="hidden" name="action" value="save_site_settings">
  <?= $csrf ?>
  <h3>Identité et accueil</h3>
  <label>Nom d'affichage<input type="text" name="nom_affichage" maxlength="150" value="<?= h($settings['nom_affichage'] ?? '') ?>"></label>
  <label>Slogan<input type="text" name="slogan" maxlength="200" value="<?= h($settings['slogan'] ?? '') ?>"></label>
  <label>Texte d'accueil (hero)<textarea name="accueil_texte" rows="3" maxlength="500"><?= h($settings['accueil_texte'] ?? '') ?></textarea></label>
  <label>Adresse (siège)<input type="text" name="adresse" maxlength="255" value="<?= h($settings['adresse'] ?? '') ?>"></label>
  <label>Image du hero (jpg/png/webp, < 4 Mo)
    <input type="file" name="hero_image" accept="image/*">
  </label>
  <?php if (!empty($settings['hero_image'])): ?>
    <p class="sub">Image actuelle : <img src="<?= h($settings['hero_image']) ?>" alt="" style="max-width:200px"></p>
  <?php endif; ?>
  <input type="hidden" name="hero_image_current" value="<?= h($settings['hero_image'] ?? '') ?>">
  <label>Texte alternatif de l'image du hero<input type="text" name="hero_image_alt" maxlength="200" value="<?= h($settings['hero_image_alt'] ?? '') ?>"></label>

  <h3>Contacts et réseaux</h3>
  <label>Téléphone<input type="tel" name="telephone" maxlength="30" value="<?= h($settings['telephone'] ?? '') ?>"></label>
  <label>WhatsApp (avec indicatif international)<input type="tel" name="whatsapp" maxlength="30" value="<?= h($settings['whatsapp'] ?? '') ?>"></label>
  <label>E-mail<input type="email" name="email" maxlength="150" value="<?= h($settings['email'] ?? '') ?>"></label>
  <label>Facebook (https)<input type="url" name="facebook" value="<?= h($settings['facebook'] ?? '') ?>"></label>
  <label>YouTube (https)<input type="url" name="youtube" value="<?= h($settings['youtube'] ?? '') ?>"></label>
  <label>Instagram (https)<input type="url" name="instagram" value="<?= h($settings['instagram'] ?? '') ?>"></label>

  <h3>Médias</h3>
  <label>Lien du culte en direct (https)<input type="url" name="lien_direct" value="<?= h($settings['lien_direct'] ?? '') ?>"></label>
  <label>Titre de la dernière prédication<input type="text" name="predication_titre" maxlength="200" value="<?= h($settings['predication_titre'] ?? '') ?>"></label>
  <label>Date de la prédication (AAAA-MM-JJ)<input type="text" name="predication_date" maxlength="10" placeholder="2026-10-11" value="<?= h($settings['predication_date'] ?? '') ?>"></label>
  <label>Lien de la prédication (https)<input type="url" name="predication_url" value="<?= h($settings['predication_url'] ?? '') ?>"></label>

  <h3>Engagement</h3>
  <label>Lien de don sécurisé (https, prestataire officiel)<input type="url" name="lien_don" value="<?= h($settings['lien_don'] ?? '') ?>"></label>
  <label>Usage des dons (explication validée)<textarea name="don_usage" rows="2" maxlength="500"><?= h($settings['don_usage'] ?? '') ?></textarea></label>

  <h3>Pages légales</h3>
  <label>Mentions légales<textarea name="mentions_legales" rows="4"><?= h($settings['mentions_legales'] ?? '') ?></textarea></label>
  <label>Politique de confidentialité<textarea name="confidentialite" rows="4"><?= h($settings['confidentialite'] ?? '') ?></textarea></label>
  <p class="sub">La politique de confidentialité doit exister avant tout formulaire ou outil de mesure.</p>
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
    <label>Responsable affiché (texte public, seulement si destiné au public)<input type="text" name="responsable_public" maxlength="150" value="<?= h($c['responsable_public'] ?? '') ?>"></label>
    <label>Lien d'itinéraire (https)<input type="url" name="itineraire_url" value="<?= h($c['itineraire_url'] ?? '') ?>"></label>
    <label>Téléphone<input type="tel" name="telephone" maxlength="30" value="<?= h($c['telephone'] ?? '') ?>"></label>
    <label>WhatsApp<input type="tel" name="whatsapp" maxlength="30" value="<?= h($c['whatsapp'] ?? '') ?>"></label>
    <label>E-mail<input type="email" name="email" maxlength="150" value="<?= h($c['email'] ?? '') ?>"></label>
    <label>Horaires de culte (une ligne : Libellé | Jours | HH:MM | HH:MM facultatif)
      <textarea name="horaires" rows="3"><?= h($c['horaires_text'] ?? '') ?></textarea>
    </label>
    <p class="sub">Publication : adresse, au moins un horaire valide et un moyen de contact (centre ou général) sont requis.</p>
    <button class="btn btn-primary" type="submit">Enregistrer ce centre</button>
  </form>
<?php endforeach; ?>

<form class="form-card" method="post" action="index.php">
  <input type="hidden" name="action" value="save_equipe_public">
  <?= $csrf ?>
  <h3>Équipe pastorale affichée</h3>
  <p class="sub">Cochez uniquement les personnes ayant autorisé la publication de leur nom, rôle, photo et présentation.</p>
  <?php if (!$equipe): ?>
    <p>Aucun membre d'équipe enregistré (voir « Gérer histoire et équipe »).</p>
  <?php endif; ?>
  <?php foreach ($equipe as $m): ?>
    <label class="check-label"><input type="checkbox" name="publie[]" value="<?= (int) $m['id'] ?>"<?= !empty($m['publie']) ? ' checked' : '' ?>> <?= h($m['nom_affichage']) ?> - <?= h($m['role_affichage']) ?></label>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Enregistrer l'équipe</button>
</form>

<form class="form-card" method="post" action="index.php" enctype="multipart/form-data">
  <input type="hidden" name="action" value="save_events_public">
  <?= $csrf ?>
  <h3>Événements à venir affichés</h3>
  <?php if (!$evenements): ?>
    <p>Aucun événement à venir (créez-en dans le calendrier).</p>
  <?php endif; ?>
  <?php foreach ($evenements as $e): ?>
    <fieldset class="form-card-inner">
      <input type="hidden" name="ids[]" value="<?= (int) $e['id'] ?>">
      <label class="check-label"><input type="checkbox" name="publie[]" value="<?= (int) $e['id'] ?>"<?= !empty($e['publie']) ? ' checked' : '' ?>> <?= h($e['nom']) ?> - <?= h(substr((string) $e['date_debut'], 0, 16)) ?><?= $e['lieu'] ? ' - ' . h($e['lieu']) : '' ?></label>
      <label>Description publique<textarea name="description[<?= (int) $e['id'] ?>]" rows="2" maxlength="1000"><?= h($e['description'] ?? '') ?></textarea></label>
      <label>Affiche (jpg/png/webp, < 4 Mo)<input type="file" name="affiche[<?= (int) $e['id'] ?>]" accept="image/*"></label>
      <?php if (!empty($e['affiche'])): ?>
        <p class="sub">Affiche actuelle : <img src="<?= h($e['affiche']) ?>" alt="" style="max-width:200px"></p>
      <?php endif; ?>
      <label>Lien d'inscription (https)<input type="url" name="lien_inscription[<?= (int) $e['id'] ?>]" value="<?= h($e['lien_inscription'] ?? '') ?>"></label>
    </fieldset>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Enregistrer les événements</button>
</form>

<form class="form-card" method="post" action="index.php">
  <input type="hidden" name="action" value="save_faq_public">
  <?= $csrf ?>
  <h3>Première visite — FAQ</h3>
  <p class="sub">tenue, durée du culte, accueil des enfants, stationnement, accessibilité, langues, déroulement.</p>
  <?php foreach ($faq as $f): ?>
    <fieldset class="form-card-inner">
      <label class="check-label"><input type="checkbox" name="publie[]" value="<?= (int) $f['id'] ?>"<?= !empty($f['publie']) ? ' checked' : '' ?>> Publier</label>
      <label>Question<input type="text" name="question[<?= (int) $f['id'] ?>]" maxlength="255" value="<?= h($f['question']) ?>"></label>
      <label>Réponse<textarea name="reponse[<?= (int) $f['id'] ?>]" rows="3" maxlength="5000"><?= h($f['reponse']) ?></textarea></label>
      <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cette question ?" href="<?= h(url('index.php', ['action' => 'delete_faq', 'id' => (int) $f['id']])) ?>"><i class="fa-solid fa-trash"></i></a>
    </fieldset>
  <?php endforeach; ?>
  <?php foreach ([1, 2, 3] as $new): ?>
    <fieldset class="form-card-inner">
      <label>Question nouvelle <?= $new ?><input type="text" name="question[new<?= $new ?>]" maxlength="255"></label>
      <label>Réponse<textarea name="reponse[new<?= $new ?>]" rows="3" maxlength="5000"></textarea></label>
      <label class="check-label"><input type="checkbox" name="publie[]" value="new<?= $new ?>"> Publier</label>
    </fieldset>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Enregistrer la FAQ</button>
</form>

<form class="form-card" method="post" action="index.php">
  <input type="hidden" name="action" value="save_ministries_public">
  <?= $csrf ?>
  <h3>Ministères et activités (Vie de l'église)</h3>
  <p class="sub">Une <strong>Bacenta</strong> est un rassemblement de prière ; une <strong>Basonta</strong> est un groupe ou un service. Ne les confondez pas. Aucune liste de membres n'est jamais affichée.</p>
  <?php foreach (['bacentas' => 'Bacentas — rassemblements de prière', 'basontas' => 'Basontas — groupes et services'] as $type => $title): ?>
    <h4><?= h($title) ?></h4>
    <?php if (!$ministries[$type]): ?>
      <p>Aucun élément enregistré (voir la section correspondante de l'application).</p>
    <?php endif; ?>
    <?php foreach ($ministries[$type] as $m): ?>
      <fieldset class="form-card-inner">
        <label class="check-label"><input type="checkbox" name="<?= h($type) ?>_publie[]" value="<?= (int) $m['id'] ?>"<?= !empty($m['publie']) ? ' checked' : '' ?>> <?= h($m['nom']) ?></label>
        <label>Description publique<textarea name="<?= h($type) ?>_description[<?= (int) $m['id'] ?>]" rows="2" maxlength="2000" placeholder="Public concerné, horaire, contact…"><?= h($m['description_publique'] ?? '') ?></textarea></label>
      </fieldset>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Enregistrer les ministères</button>
</form>
