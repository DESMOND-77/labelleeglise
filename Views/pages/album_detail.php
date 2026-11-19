<?php /* Détail public d'un album : grille de photos + visionneuse lightbox.
   Variables : $album (avec photos). */ ?>
<section id="album-detail" class="lp-section lp-section--cream2" aria-labelledby="album-detail-titre">
  <div class="wrap">
    <header class="lp-section-head">
      <a class="lp-btn lp-btn--ghost lp-btn--sm" href="index.php?page=albums"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour aux albums</a>
      <p class="lp-eyebrow" style="margin-top:var(--lp-4)">Galerie multimédia</p>
      <h1 class="lp-title" id="album-detail-titre"><?= h($album['titre']) ?></h1>
      <?php if ($album['description'] !== ''): ?>
        <p class="lp-lead"><?= nl2br(h($album['description'])) ?></p>
      <?php endif; ?>
      <div class="album-detail-meta">
        <?php if ($album['categorie'] !== ''): ?>
          <span class="album-detail-chip"><i class="fa-solid fa-tag" aria-hidden="true"></i> <?= h($album['categorie']) ?></span>
        <?php endif; ?>
        <?php if ($album['annee'] !== null): ?>
          <span class="album-detail-chip"><i class="fa-solid fa-calendar" aria-hidden="true"></i> <?= (int) $album['annee'] ?></span>
        <?php endif; ?>
        <span class="album-detail-chip"><i class="fa-regular fa-images" aria-hidden="true"></i> <?= count($album['photos']) ?> photo<?= count($album['photos']) > 1 ? 's' : '' ?></span>
      </div>
    </header>

    <?php if (empty($album['photos'])): ?>
      <div class="empty-state">
        <div class="emoji"><i class="fa-regular fa-image"></i></div>
        <p>Aucune photo dans cet album pour le moment.</p>
      </div>
    <?php else: ?>
      <div class="album-photo-grid" aria-label="Photos de l'album « <?= h($album['titre']) ?> »">
        <?php foreach ($album['photos'] as $p): ?>
          <figure class="album-photo-item" data-reveal>
            <button type="button" class="album-photo-open" data-lightbox="<?= (int) $p['id'] ?>" aria-label="Agrandir <?= h($p['legende'] ?? '') ?>">
              <img src="<?= h($p['fichier']) ?>" alt="<?= h($p['legende'] ?? $album['titre']) ?>" loading="lazy" width="600" height="600">
              <?php if ($p['legende'] !== ''): ?>
                <figcaption><?= h($p['legende']) ?></figcaption>
              <?php endif; ?>
            </button>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Lightbox (visionneuse plein écran) -->
<div class="album-lightbox" id="albumLightbox" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="lightboxTitle">
  <div class="album-lightbox-backdrop" data-lightbox-close></div>
  <div class="album-lightbox-content">
    <button type="button" class="album-lightbox-close" data-lightbox-close aria-label="Fermer la visionneuse"><i class="fa-solid fa-xmark"></i></button>
    <button type="button" class="album-lightbox-prev" data-lightbox-prev aria-label="Photo précédente"><i class="fa-solid fa-chevron-left"></i></button>
    <div class="album-lightbox-image-wrap">
      <img class="album-lightbox-image" id="lightboxImage" src="" alt="">
      <h2 id="lightboxTitle" class="sr-only"></h2>
      <p id="lightboxCaption" class="album-lightbox-caption"></p>
    </div>
    <button type="button" class="album-lightbox-next" data-lightbox-next aria-label="Photo suivante"><i class="fa-solid fa-chevron-right"></i></button>
    <div class="album-lightbox-counter" id="lightboxCounter"></div>
  </div>
</div>

<script src="assets/js/album.js" defer></script>
