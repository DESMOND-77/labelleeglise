<?php /* En-tête collant de la vitrine. Variables : $nav, $nom, $brand,
   $brandSub, $ctaHref, $ctaLabel. */ ?>
<header class="site-header">
  <div class="wrap lp-header-row">
    <a class="lp-brand" href="#accueil">
      <span class="lp-brand-mark">
        <img src="<?= h(asset('assets/images/logo.png')) ?>" alt="" width="46" height="46">
      </span>
      <span class="lp-brand-text">
        <span class="lp-brand-name"><?= h($brand !== '' ? $brand : $nom) ?></span>
        <?php if ($brandSub !== ''): ?>
          <span class="lp-brand-sub"><?= h($brandSub) ?></span>
        <?php endif; ?>
      </span>
    </a>
    <button class="lp-nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Ouvrir le menu">
      <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <nav id="site-nav" class="lp-nav" aria-label="Navigation principale">
      <ul>
        <?php foreach ($nav as [$href, $label]): ?>
          <li><a href="<?= h($href) ?>"><?= h($label) ?></a></li>
        <?php endforeach; ?>
        <?php if ($ctaHref !== ''): ?>
          <li><a class="lp-btn lp-btn--primary" href="<?= h($ctaHref) ?>"><?= h($ctaLabel) ?></a></li>
        <?php endif; ?>
        <li><a class="lp-nav-login" href="<?= h(url('index.php', ['page' => 'login'])) ?>">Connexion</a></li>
      </ul>
    </nav>
  </div>
</header>
