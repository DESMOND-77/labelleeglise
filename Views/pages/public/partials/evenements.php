<?php /* Événements à venir (publiés et futurs uniquement). Variables : $evenements. */ ?>
<section id="evenements" class="section" aria-labelledby="evenements-titre">
  <div class="wrap">
    <h2 id="evenements-titre">Événements à venir</h2>
    <div class="grid">
      <?php foreach ($evenements as $e): ?>
        <article class="card">
          <?php if ($e['affiche'] !== ''): ?>
            <img class="card-photo" src="<?= h($e['affiche']) ?>" alt="<?= h($e['nom']) ?>" loading="lazy" width="400" height="240">
          <?php endif; ?>
          <h3><?= h($e['nom']) ?></h3>
          <p><time datetime="<?= h($e['iso']) ?>"><?= h($e['quand']) ?></time></p>
          <?php if ($e['lieu'] !== ''): ?>
            <p class="muted"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($e['lieu']) ?></p>
          <?php endif; ?>
          <?php if ($e['description'] !== ''): ?>
            <p><?= nl2br(h($e['description'])) ?></p>
          <?php endif; ?>
          <?php if ($e['lien_inscription'] !== ''): ?>
            <p class="card-actions">
              <a class="btn btn-primary" href="<?= h($e['lien_inscription']) ?>" target="_blank" rel="noopener noreferrer">S'inscrire<span class="sr-only"> (<?= h($e['nom']) ?>, nouvel onglet)</span></a>
            </p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
