<?php /* Pied de page. Variables : $nom, $adresse, $contact,
   $socials, $nav, $centres, $has_mentions_legales, $has_confidentialite. */
$socialLabels = ['facebook' => 'Facebook', 'youtube' => 'YouTube', 'instagram' => 'Instagram'];
$socialIcons = ['facebook' => 'fa-brands fa-facebook-f', 'youtube' => 'fa-brands fa-youtube', 'instagram' => 'fa-brands fa-instagram'];
?>
<footer class="site-footer">
  <div class="wrap">
    <div class="lp-footer-grid">
      <div class="lp-footer-brand">
        <a class="lp-brand" href="#accueil">
          <span class="lp-brand-mark">
            <img src="<?= h(asset('assets/images/logo.png')) ?>" alt="" width="46" height="46">
          </span>
          <span class="lp-brand-text">
            <span class="lp-brand-name"><?= h($nom) ?></span>
          </span>
        </a>
        <?php if ($adresse !== ''): ?>
          <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($adresse) ?></p>
        <?php endif; ?>
        <?php if ($contact !== []): ?>
          <ul class="lp-footer-list">
            <?php foreach ($contact as $kind => $link): ?>
              <li>
                <a href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= h($link['label']) ?></a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($socials !== []): ?>
          <ul class="lp-social-row">
            <?php foreach ($socials as $net => $href): ?>
              <li>
                <a class="lp-social-btn" href="<?= h($href) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= h($socialLabels[$net]) ?>">
                  <i class="<?= h($socialIcons[$net]) ?>" aria-hidden="true"></i>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <?php if (count($nav) > 1): ?>
        <nav class="lp-footer-col" aria-label="Navigation de pied de page">
          <p class="lp-footer-heading">Le site</p>
          <ul class="lp-footer-list">
            <?php foreach ($nav as [$href, $label]): ?>
              <li><a href="<?= h($href) ?>"><?= h($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </nav>
      <?php endif; ?>

      <?php if ($centres !== []): ?>
        <div class="lp-footer-col">
          <p class="lp-footer-heading">Nos centres</p>
          <ul class="lp-footer-list">
            <?php foreach ($centres as $c): ?>
              <li><a href="#centres"><?= h($c['nom']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($has_mentions_legales || $has_confidentialite): ?>
        <div class="lp-footer-col">
          <p class="lp-footer-heading">Informations légales</p>
          <ul class="lp-footer-list">
            <?php if ($has_mentions_legales): ?>
              <li><a href="<?= h(url('index.php', ['page' => 'mentions_legales'])) ?>">Mentions légales</a></li>
            <?php endif; ?>
            <?php if ($has_confidentialite): ?>
              <li><a href="<?= h(url('index.php', ['page' => 'confidentialite'])) ?>">Politique de confidentialité</a></li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>

    <div class="lp-footer-bottom">
      <p>&copy; <?= (int) date('Y') ?> <?= h($nom) ?></p>
      <p><a href="<?= h(url('index.php', ['page' => 'login'])) ?>">Connexion</a></p>
    </div>
  </div>
</footer>
