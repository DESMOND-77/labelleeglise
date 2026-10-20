<?php /* Événements à venir (publiés et futurs uniquement). Variables : $evenements. */
$mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
?>
<section id="evenements" class="section section-alt" aria-labelledby="evenements-titre">
  <div class="wrap">
    <header class="section-head">
      <h2 id="evenements-titre">Événements à venir</h2>
    </header>
    <div class="event-grid">
      <?php foreach ($evenements as $i => $e):
          $ts = strtotime($e['iso']) ?: null; ?>
        <article class="event<?= $i === 0 && count($evenements) > 1 ? ' event-featured' : '' ?>">
          <div class="event-media<?= $e['affiche'] === '' ? ' is-empty' : '' ?>">
            <?php if ($e['affiche'] !== ''): ?>
              <img src="<?= h($e['affiche']) ?>" alt="<?= h($e['nom']) ?>" loading="lazy" width="800" height="450">
            <?php else: ?>
              <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
            <?php endif; ?>
            <?php if ($ts): ?>
              <span class="event-date" aria-hidden="true"><b><?= date('j', $ts) ?></b><?= h($mois[(int) date('n', $ts) - 1]) ?></span>
            <?php endif; ?>
          </div>
          <div class="event-body">
            <h3><?= h($e['nom']) ?></h3>
            <p class="event-when"><time datetime="<?= h($e['iso']) ?>"><?= h($e['quand']) ?></time></p>
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
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
