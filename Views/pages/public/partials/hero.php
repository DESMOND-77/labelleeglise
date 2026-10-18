<?php /* Accueil (hero). Variables : $nom, $slogan, $welcome, $hero_image, $hero_image_alt, $ctaHref, $ctaLabel, $hasCentres. */ ?>
<section id="accueil" class="hero<?= $hero_image !== '' ? ' hero-media' : '' ?>">
  <?php if ($hero_image !== ''): ?>
    <img class="hero-bg" src="<?= h($hero_image) ?>"
         alt="<?= h($hero_image_alt !== '' ? $hero_image_alt : $nom) ?>"
         width="1600" height="900" fetchpriority="high">
    <div class="hero-overlay" aria-hidden="true"></div>
  <?php endif; ?>
  <div class="wrap hero-inner">
    <h1><?= h($nom) ?></h1>
    <?php if ($slogan !== ''): ?>
      <p class="hero-slogan"><?= h($slogan) ?></p>
    <?php endif; ?>
    <?php if ($welcome !== ''): ?>
      <p class="hero-lead"><?= h($welcome) ?></p>
    <?php endif; ?>
    <p class="hero-actions">
      <?php if ($ctaHref !== ''): ?>
        <a class="btn btn-primary btn-lg" href="<?= h($ctaHref) ?>"><?= h($ctaLabel) ?></a>
      <?php endif; ?>
      <?php if ($hasCentres): ?>
        <a class="btn btn-ghost btn-lg" href="#centres">Découvrir nos centres</a>
      <?php endif; ?>
    </p>
  </div>
</section>
