<?php /* Prédications et culte en ligne : liens vers les plateformes
   officielles uniquement (aucun streaming intégré). Variables :
   $predication, $direct. */ ?>
<section id="medias" class="section section-alt" aria-labelledby="medias-titre">
  <div class="wrap">
    <h2 id="medias-titre">Prédications et culte en ligne</h2>
    <?php if ($predication['url'] !== ''): ?>
      <article class="card media-card">
        <h3>Dernière prédication</h3>
        <?php if ($predication['titre'] !== ''): ?>
          <p><strong><?= h($predication['titre']) ?></strong></p>
        <?php endif; ?>
        <?php if ($predication['date'] !== ''): ?>
          <p class="muted"><time datetime="<?= h($predication['date']) ?>"><?= h($predication['date']) ?></time></p>
        <?php endif; ?>
        <p class="card-actions">
          <a class="btn btn-primary" href="<?= h($predication['url']) ?>" target="_blank" rel="noopener noreferrer">Regarder la prédication<span class="sr-only"> (nouvel onglet)</span></a>
        </p>
      </article>
    <?php endif; ?>
    <?php if ($direct !== ''): ?>
      <p class="media-direct">
        <a class="btn btn-primary" href="<?= h($direct) ?>" target="_blank" rel="noopener noreferrer">Regarder le culte en direct<span class="sr-only"> (nouvel onglet)</span></a>
      </p>
    <?php endif; ?>
  </div>
</section>
