<?php /* Accueil (hero plein écran). Variables : $nom, $slogan, $welcome,
   $hero_image, $hero_image_alt, $hero_slides, $ctaHref, $ctaLabel,
   $hasCentres, $nextEvent, $centres, $adresse. */
$slides = $hero_slides;
if ($slides === [] && $hero_image !== '') {
    $slides = [['src' => $hero_image, 'type' => 'image']];
}
$alt = $hero_image_alt !== '' ? $hero_image_alt : $nom;

/* Repères factuels du hero : uniquement des données publiées. */
$facts = [];
if ($centres !== [] && $centres[0]['horaires'] !== []) {
    $slot = $centres[0]['horaires'][0];
    $facts[] = ['icon' => 'fa-regular fa-clock', 'text' => $slot['jours'] . ' · ' . $slot['debut'] . ($slot['fin'] !== '' ? ' – ' . $slot['fin'] : '')];
}
if ($adresse !== '') {
    $facts[] = ['icon' => 'fa-solid fa-location-dot', 'text' => $adresse];
}
?>
<section id="accueil" class="lp-hero<?= $slides !== [] ? ' has-media' : '' ?>">
  <?php if ($slides !== []): ?>
    <div class="lp-hero-media" data-hero-carousel>
      <?php foreach ($slides as $i => $slide): ?>
        <?php if ($slide['type'] === 'video'): ?>
          <video class="lp-hero-slide<?= $i === 0 ? ' is-active' : '' ?>" src="<?= h($slide['src']) ?>"
                 muted playsinline preload="metadata" aria-hidden="true"></video>
        <?php else: ?>
          <img class="lp-hero-slide<?= $i === 0 ? ' is-active' : '' ?>" src="<?= h($slide['src']) ?>"
               alt="<?= h($alt) ?>"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <div class="lp-hero-overlay" aria-hidden="true"></div>
  <?php endif; ?>

  <div class="wrap lp-hero-inner">
    <div class="lp-hero-content">
      <?php if ($slogan !== ''): ?>
        <p class="lp-eyebrow"><?= h($slogan) ?></p>
      <?php endif; ?>
      <h1 class="lp-hero-title"><?= h($nom) ?></h1>
      <?php if ($welcome !== ''): ?>
        <p class="lp-hero-lead"><?= h($welcome) ?></p>
      <?php endif; ?>
      <?php if ($ctaHref !== '' || $hasCentres): ?>
        <ul class="lp-actions">
          <?php if ($ctaHref !== ''): ?>
            <li><a class="lp-btn lp-btn--primary lp-btn--lg" href="<?= h($ctaHref) ?>"><?= h($ctaLabel) ?></a></li>
          <?php endif; ?>
          <?php if ($hasCentres): ?>
            <li><a class="lp-btn lp-btn--ghost-light lp-btn--lg" href="#centres">Découvrir nos centres</a></li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>
    </div>

    <?php if ($nextEvent !== null || $facts !== []): ?>
      <div class="lp-hero-meta">
        <?php if ($nextEvent !== null): ?>
          <a class="lp-hero-event" href="#evenements">
            <span class="lp-hero-event-date">
              <b><?= h($nextEvent['jour']) ?></b>
              <span><?= h($nextEvent['mois']) ?></span>
            </span>
            <span class="lp-hero-event-text">
              <span class="lp-hero-event-label">Prochain événement</span>
              <span class="lp-hero-event-title"><?= h($nextEvent['nom']) ?></span>
              <?php if ($nextEvent['lieu'] !== ''): ?>
                <span class="lp-hero-event-place"><?= h($nextEvent['lieu']) ?></span>
              <?php endif; ?>
            </span>
          </a>
        <?php endif; ?>
        <?php if ($facts !== []): ?>
          <ul class="lp-hero-facts">
            <?php foreach ($facts as $fact): ?>
              <li><i class="<?= h($fact['icon']) ?>" aria-hidden="true"></i><?= h($fact['text']) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
