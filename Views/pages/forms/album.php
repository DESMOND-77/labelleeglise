<?php
/* Formulaire album (création ou modification).
   Variables : $album (null si création), $isNew, $csrf, $evenements, $photos, $cancelUrl. */
$album = $album ?? null;
$isNew = $album === null;
$titre   = $album ? $album['titre'] : '';
$desc    = $album ? $album['description'] : '';
$categorie = $album ? ($album['categorie'] ?? '') : '';
$annee   = $album ? ($album['annee'] ?? '') : '';
$evenementId = $album ? ($album['evenement_id'] ?? '') : '';
$publie  = $isNew ? 1 : (int) ($album['publie'] ?? 0);
$archive = $isNew ? 0 : (int) ($album['archive'] ?? 0);
$photos  = $photos ?? [];
?>
<section class="card" aria-labelledby="form-album-titre">
  <h2 id="form-album-titre"><?= h($isNew ? 'Nouvel album' : 'Modifier l’album') ?></h2>

  <form method="post" action="index.php" class="form-card" enctype="multipart/form-data" data-track-dirty>
    <input type="hidden" name="action" value="<?= $isNew ? 'save_album_create' : 'save_album_update' ?>">
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int) $album['id'] ?>"><?php endif; ?>
    <?php if (!$isNew): ?><input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>"><?php endif; ?>
    <input type="hidden" name="return_tab" value="albums">
    <?= $csrf ?>

    <div class="form-group">
      <label>Titre de l’album <span class="required">*</span></label>
      <input type="text" name="titre" maxlength="150" value="<?= h($titre) ?>" required>
    </div>

    <div class="form-group">
      <label>Description</label>
      <textarea name="description" rows="3" maxlength="1000" placeholder="Une présentation courte de l’album…"><?= h($desc) ?></textarea>
    </div>

    <div class="field-grid">
      <div class="form-group">
        <label>Catégorie</label>
        <input type="text" name="categorie" maxlength="60" value="<?= h($categorie) ?>" placeholder="ex : Culte, Concert, Jeunesse">
      </div>
      <div class="form-group">
        <label>Année</label>
        <input type="number" name="annee" min="1900" max="2100" value="<?= h($annee) ?>" placeholder="2024">
      </div>
      <div class="form-group">
        <label>Lien à un événement (optionnel)</label>
        <select name="evenement_id">
          <option value="">Aucun</option>
          <?php foreach ($evenements ?? [] as $e): ?>
            <option value="<?= (int) $e['id'] ?>" <?= $evenementId == (string) $e['id'] ? 'selected' : '' ?>>
              <?= h($e['nom']) ?> (<?= date('d/m/Y', strtotime($e['date_debut'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Couverture de l’album (jpg, png, webp ou gif - 4 Mo max)</label>
      <input type="file" name="couverture" accept="image/*">
      <?php if (!$isNew && !empty($album['couverture'])): ?>
        <p class="field-note">
          Couverture actuelle : <img class="preview-thumb" src="<?= h($album['couverture']) ?>" alt="">
          <input type="hidden" name="couverture_current" value="<?= h($album['couverture']) ?>">
        </p>
      <?php endif; ?>
    </div>

    <div class="form-group form-group--inline">
      <label class="check-label">
        <input type="checkbox" name="publie" value="1"<?= $publie === 1 ? ' checked' : '' ?>>
        Publier sur le site public
      </label>
      <?php if (!$isNew): ?>
        <label class="check-label">
          <input type="checkbox" name="archive" value="1"<?= $archive === 1 ? ' checked' : '' ?>>
          Archiver (masquer de la liste publique)
        </label>
      <?php endif; ?>
    </div>

    <?php if (!$isNew): ?>
      <fieldset class="form-group form-card-inner">
        <legend>Photos de l’album</legend>

        <div class="form-actions" style="margin-bottom:var(--lp-4)">
          <label class="btn btn-primary" style="display:inline-flex;align-items:center;gap:var(--lp-2);cursor:pointer">
            <i class="fa-solid fa-cloud-arrow-up"></i> Importer des photos
            <input type="file" id="album-photo-import" name="photos[]" accept="image/*" multiple style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%">
          </label>
        </div>

        <div id="photo-import-preview" class="photo-import-preview"></div>

        <?php if ($photos === []): ?>
          <div class="empty-state">
            <div class="emoji"><i class="fa-regular fa-image"></i></div>
            <p>Aucune photo dans cet album.</p>
          </div>
        <?php else: ?>
          <div class="album-photos-admin" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:var(--lp-4);margin-top:var(--lp-4)">
            <?php foreach ($photos as $p): ?>
              <div class="album-photo-admin-card" style="position:relative;display:flex;flex-direction:column;gap:var(--lp-2);background:var(--lp-surface);border:var(--lp-hairline) solid var(--lp-line);border-radius:var(--lp-radius);padding:var(--lp-2)">
                <div class="album-photo-admin-thumb" style="aspect-ratio:1/1;overflow:hidden;border-radius:var(--lp-radius);background:var(--lp-cream-2);position:relative">
                  <img src="<?= h($p['fichier']) ?>" alt="<?= h($p['legende'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;display:block">
                  <?php if ((int) $p['publie'] !== 1): ?>
                    <span class="badge badge absent" style="position:absolute;top:var(--lp-1);left:var(--lp-1);font-size:0.65rem">Non publié</span>
                  <?php endif; ?>
                </div>
                <input type="hidden" name="photo_ids[]" value="<?= (int) $p['id'] ?>">
                <label>Légende
                  <input type="text" name="photo_legendes[<?= (int) $p['id'] ?>]" value="<?= h($p['legende'] ?? '') ?>" style="width:100%;font-size:var(--lp-fs-sm)">
                </label>
                <div class="field-row" style="gap:var(--lp-2)">
                  <label class="check-label" style="font-size:var(--lp-fs-sm)">
                    <input type="checkbox" name="photo_publie[<?= (int) $p['id'] ?>]" value="1"<?= (int) $p['publie'] === 1 ? ' checked' : '' ?>>
                    Publier
                  </label>
                  <label style="font-size:var(--lp-fs-sm);margin-left:auto">Position
                    <input type="number" name="photo_positions[<?= (int) $p['id'] ?>]" value="<?= (int) $p['position'] ?>" min="0" style="width:50px;font-size:var(--lp-fs-sm)">
                  </label>
                  <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cette photo de l\'album ?" href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'albums', 'action' => 'delete_album_photo', 'pid' => (int) $p['id'], 'form' => 'album', 'id' => (int) $p['album_id']])) ?>"><i class="fa-solid fa-trash"></i></a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="form-actions" style="margin-top:var(--lp-4)">
            <label class="check-label" style="font-size:var(--lp-fs-sm)">
              <input type="checkbox" name="reorder_photos" value="1">
              J’ai réorganisé l’ordre des photos (enregistrer les positions)
            </label>
          </div>
        <?php endif; ?>
      </fieldset>
    <?php endif; ?>

    <div class="form-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl ?? url('index.php', ['page' => 'site_public', 'tab' => 'albums'])) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary"><?= $isNew ? 'Créer l’album' : 'Enregistrer l’album' ?></button>
    </div>
  </form>
</section>

<style>
  .album-photo-admin-card .field-row {
    display: flex;
    align-items: center;
    gap: var(--lp-2);
  }
  .album-photo-admin-card .btn-sm {
    margin-left: auto;
  }
</style>
