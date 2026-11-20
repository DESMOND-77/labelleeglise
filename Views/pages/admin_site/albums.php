<?php /* Gestion des albums - création, modification, publication, archivage.
   Variables : $albums (de $service->adminData()), $csrf. */ ?>
<div class="page-header">
  <h2>Albums / Galerie multimédia</h2>
  <p class="sub">Créez, modifiez et publiez des galeries photos sur le site public.</p>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<div class="admin-cols">
  <!-- Liste des albums -->
  <section class="card" aria-labelledby="albums-list">
    <h3 id="albums-list">Albums</h3>

    <div class="form-actions" style="margin-bottom:var(--lp-4)">
      <a class="btn btn-primary" href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'albums', 'form' => 'album'])) ?>"><i class="fa-solid fa-plus"></i> Nouvel album</a>
    </div>

    <?php if ($albums === []): ?>
      <div class="empty-state">
        <div class="emoji"><i class="fa-regular fa-image"></i></div>
        <p>Aucun album enregistré.</p>
      </div>
    <?php else: ?>
      <form method="post" action="index.php" class="form-card" data-track-dirty>
        <input type="hidden" name="action" value="save_albums_publie">
        <?= $csrf ?>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th></th>
                <th>Couverture</th>
                <th>Titre</th>
                <th>Catégorie</th>
                <th>Année</th>
                <th>Photos</th>
                <th>Événement</th>
                <th>Statut</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($albums as $a): ?>
                <tr>
                  <td>
                    <label class="check-label">
                      <input type="checkbox" name="publie[]" value="<?= (int) $a['id'] ?>"<?= (int) $a['publie'] === 1 ? ' checked' : '' ?>>
                      <span class="sr-only">Publier <?= h($a['titre']) ?></span>
                    </label>
                  </td>
                  <td class="album-admin-thumb">
                    <?php if (!empty($a['couverture'])): ?>
                      <img src="<?= h($a['couverture']) ?>" alt="" loading="lazy" >
                    <?php else: ?>
                      <span class="thumb-empty">—</span>
                    <?php endif; ?>
                  </td>
                  <td><strong><?= h($a['titre']) ?></strong></td>
                  <td><?= h($a['categorie'] ?? '-') ?></td>
                  <td><?= $a['annee'] !== null ? (int) $a['annee'] : '-' ?></td>
                  <td><?= (int) ($a['nb_photos'] ?? 0) ?></td>
                  <td><?= h($a['evenement_nom'] ?? '-') ?></td>
                  <td>
                    <span class="badge <?= (int) $a['publie'] === 1 ? 'badge present' : 'badge absent' ?>">
                      <?= (int) $a['publie'] === 1 ? 'Publié' : 'Brouillon' ?>
                    </span>
                    <?php if ((int) $a['archive'] === 1): ?>
                      <br><span class="badge badge neutral" style="font-size:0.7rem">Archivé</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="btn-group">
                      <a class="btn btn-sm btn-outline" href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'albums', 'form' => 'album', 'id' => (int) $a['id']])) ?>"><i class="fa-solid fa-pen"></i></a>
                      <a class="btn btn-sm btn-outline" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>#albums" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-eye"></i></a>
                      <a class="btn btn-sm btn-outline" href="<?= h(url('index.php', ['page' => 'album', 'id' => (int) $a['id']])) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-external-link-alt"></i></a>
                      <a class="btn btn-sm btn-danger" href="index.php?action=delete_album&amp;id=<?= (int) $a['id'] ?>" onclick="return confirm('Supprimer cet album et toutes ses photos ?')" title="Supprimer"><i class="fa-solid fa-trash"></i></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Appliquer les publications</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <!-- Archivés -->
  <section class="card" aria-labelledby="albums-archives">
    <h3 id="albums-archives">Archivés</h3>
    <?php
    $archives = array_filter($albums, static fn($a) => (int) $a['archive'] === 1);
    if ($archives === []): ?>
      <p class="sub">Aucun album archivé.</p>
    <?php else: ?>
      <form method="post" action="index.php" class="form-card">
        <input type="hidden" name="action" value="save_albums_unarchive">
        <?= $csrf ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width:40px"></th>
                <th>Titre</th>
                <th style="width:90px">Année</th>
                <th style="width:150px">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($archives as $a): ?>
                <tr>
                  <td>
                    <label class="check-label">
                      <input type="checkbox" name="ids[]" value="<?= (int) $a['id'] ?>">
                      <span class="sr-only">Désarchiver <?= h($a['titre']) ?></span>
                    </label>
                  </td>
                  <td><strong><?= h($a['titre']) ?></strong></td>
                  <td><?= $a['annee'] !== null ? (int) $a['annee'] : '-' ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline" href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'albums', 'form' => 'album', 'id' => (int) $a['id']])) ?>"><i class="fa-solid fa-pen"></i></a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Désarchiver les sélectionnés</button>
        </div>
      </form>
    <?php endif; ?>
  </section>
</div>

<?php if (empty($albums)): ?>
<style>
  .thumb-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: var(--lp-text-mute);
    font-size: var(--lp-fs-xs);
    background: var(--lp-cream-2);
    border-radius: var(--lp-radius);
  }
  .btn-group {
    display: flex;
    gap: var(--lp-1);
    flex-wrap: wrap;
  }
  .btn-group .btn {
    flex: 1 1 auto;
  }
  @media (max-width: 768px) {
    .table th:nth-child(8), .table td:nth-child(8),
    .table th:nth-child(9), .table td:nth-child(9) {
      display: none;
    }
  }
</style>
<?php endif; ?>
