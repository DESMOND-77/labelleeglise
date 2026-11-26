<?php /* Album : listing public des galeries publiées.
   Variables : $albums, $years, $categories, $anneeFilter, $categorieFilter. */ ?>
<section id="albums" class="lp-section lp-section--cream2" aria-labelledby="albums-titre">
  <div class="wrap">
    <nav class="album-page-nav" aria-label="Navigation de la galerie">
      <a class="lp-btn lp-btn--ghost lp-btn--sm" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à l’accueil
      </a>
    </nav>

    <header class="lp-section-head lp-section-head--center">
      <p class="lp-eyebrow">Galerie multimédia</p>
      <h1 class="lp-title" id="albums-titre">Nos albums photo</h1>
      <p class="lp-lead">Revivez les temps forts de La Belle Église à travers nos galeries photos.</p>
    </header>

    <?php $hasActiveFilters = ($anneeFilter ?? '') !== '' || ($categorieFilter ?? '') !== ''; ?>
    <?php if ($albums !== [] || $hasActiveFilters): ?>
      <div class="album-filters">
        <form method="get" action="index.php" class="album-filter-form" role="search" aria-label="Filtrer les albums">
          <input type="hidden" name="page" value="albums">
          <label for="album-annee">Année
            <select id="album-annee" name="annee">
              <option value="">Toutes</option>
              <?php foreach ($years as $y): ?>
                <option value="<?= (int) $y ?>" <?= $anneeFilter == (string) $y ? 'selected' : '' ?>><?= (int) $y ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label for="album-categorie">Catégorie
            <select id="album-categorie" name="categorie">
              <option value="">Toutes</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= h($cat) ?>" <?= $categorieFilter === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button class="lp-btn lp-btn--primary" type="submit">Filtrer</button>
          <?php if ($hasActiveFilters): ?>
            <a class="lp-btn lp-btn--ghost" href="index.php?page=albums">Réinitialiser</a>
          <?php endif; ?>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($albums === []): ?>
      <div class="empty-state album-empty-state" role="status">
        <div class="emoji"><i class="fa-regular fa-image" aria-hidden="true"></i></div>
        <h2><?= $hasActiveFilters ? 'Aucun album ne correspond à votre recherche' : 'Galerie bientôt disponible' ?></h2>
        <p><?= $hasActiveFilters ? 'Essayez de modifier vos filtres ou réinitialisez-les pour afficher tous les albums.' : 'Aucun album n’a encore été publié. Revenez bientôt découvrir nos photos.' ?></p>
        <?php if ($hasActiveFilters): ?>
          <a class="lp-btn lp-btn--primary" href="<?= h(url('index.php', ['page' => 'albums'])) ?>">Afficher tous les albums</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <p class="album-results-count" aria-live="polite">
        <?= count($albums) ?> album<?= count($albums) > 1 ? 's' : '' ?>
        <?= $hasActiveFilters ? 'correspondant' . (count($albums) > 1 ? 's' : '') . ' à votre recherche' : 'à découvrir' ?>
      </p>
      <div class="album-grid" aria-label="Albums">
        <?php foreach ($albums as $a): ?>
          <article class="album-card" data-reveal>
            <div class="album-card-media<?= empty($a['couverture']) ? ' is-empty' : '' ?>">
              <?php if (!empty($a['couverture'])): ?>
                <a href="<?= h(url('index.php', ['page' => 'album', 'id' => (int) $a['id']])) ?>">
                  <img src="<?= h($a['couverture']) ?>" alt="<?= h($a['titre']) ?>" loading="lazy" width="400" height="400">
                </a>
              <?php else: ?>
                <a href="<?= h(url('index.php', ['page' => 'album', 'id' => (int) $a['id']])) ?>">
                  <i class="fa-regular fa-image" aria-hidden="true"></i>
                </a>
              <?php endif; ?>
              <?php if ($a['annee'] !== null): ?>
                <span class="album-card-year"><?= (int) $a['annee'] ?></span>
              <?php endif; ?>
            </div>
            <div class="album-card-body">
              <a href="<?= h(url('index.php', ['page' => 'album', 'id' => (int) $a['id']])) ?>" class="album-card-link">
                <h3><?= h($a['titre']) ?></h3>
              </a>
              <?php if ($a['categorie'] !== ''): ?>
                <span class="album-card-cat"><?= h($a['categorie']) ?></span>
              <?php endif; ?>
              <?php if ($a['description'] !== ''): ?>
                <p class="album-card-desc"><?= h($a['description']) ?></p>
              <?php endif; ?>
              <div class="album-card-meta">
                <span class="album-card-count"><i class="fa-regular fa-images" aria-hidden="true"></i> <?= (int) ($a['nb_photos'] ?? 0) ?> photo<?= (int) ($a['nb_photos'] ?? 0) > 1 ? 's' : '' ?></span>
                <?php if ($a['annee'] !== null): ?>
                  <span class="album-card-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= (int) $a['annee'] ?></span>
                <?php endif; ?>
              </div>
              <a href="<?= h(url('index.php', ['page' => 'album', 'id' => (int) $a['id']])) ?>" class="album-card-cta" aria-label="Voir l’album <?= h($a['titre']) ?>">
                Voir l’album <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
