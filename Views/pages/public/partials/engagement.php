<?php /* Dons, bénévolat et engagement - uniquement avec une
   destination réelle et vérifiée. Variables : $lien_don,
   $don_usage, $hasContact. */ ?>
<section id="engagement" class="section section-alt" aria-labelledby="engagement-titre">
  <div class="wrap narrow">
    <h2 id="engagement-titre">S'engager</h2>
    <article class="card">
      <h3>Faire un don</h3>
      <?php if ($don_usage !== ''): ?>
        <p><?= nl2br(h($don_usage)) ?></p>
      <?php endif; ?>
      <p class="card-actions">
        <a class="btn btn-primary" href="<?= h($lien_don) ?>" target="_blank" rel="noopener noreferrer">Faire un don<span class="sr-only"> (plateforme officielle, nouvel onglet)</span></a>
      </p>
    </article>
    <?php if ($hasContact): ?>
      <article class="card">
        <h3>Devenir bénévole</h3>
        <p>Contactez l'église pour participer aux activités et aux services.</p>
        <p class="card-actions">
          <a class="btn" href="#contact">Nous contacter</a>
        </p>
      </article>
    <?php endif; ?>
  </div>
</section>
