<?php /* Prédications et culte en ligne : liens vers les plateformes
   officielles uniquement (aucun streaming intégré). Variables :
   $predication, $direct. */ ?>
<section id="medias" class="lp-section lp-section--ink" aria-labelledby="medias-titre">
  <div class="wrap">
    <header class="lp-section-head">
      <p class="lp-eyebrow">Ressources</p>
      <h2 class="lp-title" id="medias-titre">Prédications et culte en ligne</h2>
    </header>
    <div class="lp-media-grid">
      <?php if ($predication['url'] !== ''): ?>
        <article class="lp-resource" data-reveal>
          <span class="lp-resource-icon"><i class="fa-solid fa-play" aria-hidden="true"></i></span>
          <h3>Dernière prédication</h3>
          <?php if ($predication['titre'] !== ''): ?>
            <p class="lp-resource-title"><?= h($predication['titre']) ?></p>
          <?php endif; ?>
          <?php if ($predication['date'] !== ''): ?>
            <p><time datetime="<?= h($predication['date']) ?>"><?= h($predication['date']) ?></time></p>
          <?php endif; ?>
          <ul class="lp-actions">
            <li>
              <a class="lp-btn lp-btn--primary lp-btn--sm" href="<?= h($predication['url']) ?>" target="_blank" rel="noopener noreferrer">Regarder la prédication<span class="sr-only"> (nouvel onglet)</span></a>
            </li>
          </ul>
        </article>
      <?php endif; ?>
      <?php if ($direct !== ''): ?>
        <article class="lp-resource" data-reveal>
          <span class="lp-resource-icon"><i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i></span>
          <h3>Culte en direct</h3>
          <p>Diffusion en direct sur la plateforme officielle de l'église.</p>
          <ul class="lp-actions">
            <li>
              <a class="lp-btn lp-btn--primary lp-btn--sm" href="<?= h($direct) ?>" target="_blank" rel="noopener noreferrer">Regarder le culte en direct<span class="sr-only"> (nouvel onglet)</span></a>
            </li>
          </ul>
        </article>
      <?php endif; ?>
    </div>
  </div>
</section>
