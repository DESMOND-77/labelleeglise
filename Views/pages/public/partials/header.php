<?php /* En-tête de la vitrine. Variables : $nav, $nom, $ctaHref, $ctaLabel. */ ?>
<header class="site-header">
  <div class="wrap header-row">
    <a class="brand" href="#accueil">
      <img src="<?= h(asset('assets/images/logo.png')) ?>" alt="" width="40" height="40">
      <span><?= h($nom) ?></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Menu">
      <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <nav id="site-nav" class="site-nav" aria-label="Navigation principale">
      <ul>
        <?php foreach ($nav as [$href, $label]): ?>
          <li><a href="<?= h($href) ?>"><?= h($label) ?></a></li>
        <?php endforeach; ?>
        <?php if ($ctaHref !== ''): ?>
          <li><a class="btn btn-primary btn-nav" href="<?= h($ctaHref) ?>"><?= h($ctaLabel) ?></a></li>
        <?php endif; ?>
        <li><a class="nav-login" href="<?= h(url('index.php', ['page' => 'login'])) ?>">Connexion</a></li>
      </ul>
    </nav>
  </div>
</header>
