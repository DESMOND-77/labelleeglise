<?php /* Événements à venir (publiés et futurs uniquement). Variables : $evenements. */
$mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
?>
<section id="evenements" class="lp-section" aria-labelledby="evenements-titre">
  <div class="wrap">
    <header class="lp-section-head">
      <p class="lp-eyebrow">Agenda</p>
      <h2 class="lp-title" id="evenements-titre">Événements à venir</h2>
    </header>
    <div class="lp-card-grid">
      <?php foreach ($evenements as $e):
          $ts = strtotime($e['iso']) ?: null; ?>
        <article class="lp-event" data-reveal>
          <div class="lp-event-media<?= $e['affiche'] === '' ? ' is-empty' : '' ?>">
            <?php if ($e['affiche'] !== ''): ?>
              <img src="<?= h($e['affiche']) ?>" alt="<?= h($e['nom']) ?>" loading="lazy" width="800" height="450">
            <?php else: ?>
              <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
            <?php endif; ?>
            <?php if ($ts): ?>
              <span class="lp-event-date" aria-hidden="true"><b><?= date('j', $ts) ?></b><span><?= h($mois[(int) date('n', $ts) - 1]) ?></span></span>
            <?php endif; ?>
          </div>
          <div class="lp-event-body">
            <p class="lp-event-when"><time datetime="<?= h($e['iso']) ?>"><?= h($e['quand']) ?></time></p>
            <h3><?= h($e['nom']) ?></h3>
            <?php if ($e['lieu'] !== ''): ?>
              <p class="lp-event-place"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($e['lieu']) ?></p>
            <?php endif; ?>
            <?php if ($e['description'] !== ''): ?>
              <p class="lp-event-desc"><?= nl2br(h($e['description'])) ?></p>
            <?php endif; ?>
            <?php if ($e['lien_inscription'] !== ''): ?>
              <ul class="lp-actions">
                <li>
                  <a class="lp-btn lp-btn--dark lp-btn--sm" href="<?= h($e['lien_inscription']) ?>" target="_blank" rel="noopener noreferrer">S'inscrire<span class="sr-only"> (<?= h($e['nom']) ?>, nouvel onglet)</span></a>
                </li>
              </ul>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
