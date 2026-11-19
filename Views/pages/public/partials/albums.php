<?php /* Aperçu des albums publiés sur le site public.
   Variables : $albums. */ ?>
<?php if ($albums === []): return; ?>
<?php else: ?>
<section id="albums" class="lp-section lp-section--ink" aria-labelledby="albums-titre">
  <div class="wrap">
    <div class="lp-section-head">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:var(--lp-3)">
        <div>
          <p class="lp-eyebrow">Galerie multimédia</p>
          <h2 class="lp-title" id="albums-titre">Nos albums</h2>
          <p class="lp-lead">Découvrez nos galeries photos des temps forts de l’église.</p>
        </div>
        <a class="lp-btn lp-btn--primary" href="<?= h(url('index.php', ['page' => 'albums'])) ?>">
          Voir tous les albums<span class="sr-only"> (nouvelle page)</span>
        </a>
      </div>
    </div>

    <div class="album-grid" aria-label="Albums récents">
      <?php foreach (array_slice($albums, 0, 6) as $a): ?>
        <article class="album-card" data-reveal>
          <div class="album-card-media<?= empty($a['couverture']) ? ' is-empty' : '' ?>">
            <?php if (!empty($a['couverture'])): ?>
              <a href="<?= h(url('index.php', ['page' => 'album', 'id' => (int) $a['id']])) ?>">
                <img src="<?= h($a['couverture']) ?>" alt="<?= h($a['titre']) ?>" loading="lazy" width="360" height="360">
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
            <div class="album-card-meta">
              <span class="album-card-count"><i class="fa-regular fa-images" aria-hidden="true"></i> <?= (int) ($a['nb_photos'] ?? 0) ?> photo<?= (int) ($a['nb_photos'] ?? 0) > 1 ? 's' : '' ?></span>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if (count($albums) > 6): ?>
      <div class="form-actions" style="margin-top:var(--lp-6);justify-content:center">
        <a class="lp-btn lp-btn--primary" href="<?= h(url('index.php', ['page' => 'albums'])) ?>">Voir tous les albums</a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>