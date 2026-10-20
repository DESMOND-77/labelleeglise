<?php /* Accueil (hero). Variables : $nom, $slogan, $welcome, $hero_image, $hero_image_alt, $hero_slides, $ctaHref, $ctaLabel, $hasCentres. */ ?>
<?php
// Carrousel (assets/carousel/) ; à défaut, l'image d'accueil unique des réglages.
$slides = $hero_slides;
if ($slides === [] && $hero_image !== '') {
    $slides = [['src' => $hero_image, 'type' => 'image']];
}
$alt = $hero_image_alt !== '' ? $hero_image_alt : $nom;
?>
<section id="accueil" class="hero<?= $slides !== [] ? ' hero-media' : '' ?>">
  <?php if ($slides !== []): ?>
    <div class="hero-carousel" data-hero-carousel>
      <?php foreach ($slides as $i => $slide): ?>
        <?php if ($slide['type'] === 'video'): ?>
          <video class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" src="<?= h($slide['src']) ?>"
                 muted playsinline preload="metadata" aria-hidden="true"></video>
        <?php else: ?>
          <img class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" src="<?= h($slide['src']) ?>"
               alt="<?= h($alt) ?>"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
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
