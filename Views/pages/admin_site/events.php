<?php /* Événements à venir - publication par événement.
   Variables : $evenements, $csrf. */ ?>
<div class="page-header">
  <h2>Événements à venir</h2>
  <p class="sub">Seuls les événements publiés et à venir apparaissent sur le site public.</p>
</div>

<?php if (!$evenements): ?>
  <div class="empty-state">
    <div class="emoji"><i class="fa-solid fa-calendar-days"></i></div>
    <p>Aucun événement à venir enregistré.</p>
    <p><a class="btn btn-primary" href="<?= h(url('index.php', ['page' => 'agenda'])) ?>">Créer un événement</a></p>
  </div>
<?php else: ?>
  <form class="form-card" method="post" action="index.php" enctype="multipart/form-data" data-track-dirty>
    <input type="hidden" name="action" value="save_events_public">
    <?= $csrf ?>

    <?php foreach ($evenements as $e): ?>
      <fieldset class="form-group form-card-inner">
        <legend>
          <?= h($e['nom']) ?>
          <span class="muted"> - <?= h(substr((string) $e['date_debut'], 0, 16)) ?><?= $e['lieu'] ? ' · ' . h($e['lieu']) : '' ?></span>
        </legend>
        <input type="hidden" name="ids[]" value="<?= (int) $e['id'] ?>">
        <label class="check-label">
          <input type="checkbox" name="publie[]" value="<?= (int) $e['id'] ?>"<?= !empty($e['publie']) ? ' checked' : '' ?>>
          Publier sur le site public
        </label>
        <label>Description publique
          <textarea name="description[<?= (int) $e['id'] ?>]" rows="2" maxlength="1000" placeholder="Une à deux phrases pour les visiteurs"><?= h($e['description'] ?? '') ?></textarea>
        </label>
        <div class="field-grid">
          <label>Affiche (jpg, png, webp ou gif - 4 Mo max)
            <input type="file" name="affiche[<?= (int) $e['id'] ?>]" accept="image/*">
          </label>
          <label>Lien d'inscription (https, si un mécanisme existe)
            <input type="url" name="lien_inscription[<?= (int) $e['id'] ?>]" value="<?= h($e['lien_inscription'] ?? '') ?>">
          </label>
        </div>
        <?php if (!empty($e['affiche'])): ?>
          <p class="field-note">Affiche actuelle : <img class="preview-thumb" src="<?= h($e['affiche']) ?>" alt=""></p>
        <?php endif; ?>
      </fieldset>
    <?php endforeach; ?>

    <button class="btn btn-primary" type="submit">Enregistrer les événements</button>
  </form>
<?php endif; ?>
