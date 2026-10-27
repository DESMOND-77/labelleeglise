<?php /* Contact. Variables : $contact, $socials, $adresse. */
$socialIcons = ['facebook' => 'fa-brands fa-facebook-f', 'youtube' => 'fa-brands fa-youtube', 'instagram' => 'fa-brands fa-instagram'];
$socialLabels = ['facebook' => 'Facebook', 'youtube' => 'YouTube', 'instagram' => 'Instagram'];
?>
<section id="contact" class="lp-section" aria-labelledby="contact-titre">
  <div class="wrap">
    <div class="lp-contact-grid">
      <div>
        <header class="lp-section-head">
          <p class="lp-eyebrow">Nous joindre</p>
          <h2 class="lp-title" id="contact-titre">Restons en contact</h2>
          <p class="lp-lead">Une question sur les cultes, les groupes de prière ou l'accueil des nouveaux venus ? Écrivez-nous, nous vous répondons avec plaisir.</p>
        </header>
        <ul class="lp-contact-list">
          <?php foreach ($contact as $kind => $link): ?>
            <li>
              <a href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                <i class="fa-solid <?= h($kind === 'telephone' ? 'fa-phone' : ($kind === 'whatsapp' ? 'fa-brands fa-whatsapp' : 'fa-envelope')) ?>" aria-hidden="true"></i>
                <span>
                  <span class="lp-contact-kind"><?= h($kind === 'telephone' ? 'Téléphone' : ($kind === 'whatsapp' ? 'WhatsApp' : 'E-mail')) ?></span>
                  <span class="lp-contact-value"><?= h($link['label']) ?></span>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?php if ($adresse !== '' || $socials !== []): ?>
        <aside class="lp-contact-aside">
          <h3>Nous rendre visite</h3>
          <?php if ($adresse !== ''): ?>
            <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($adresse) ?></p>
          <?php endif; ?>
          <?php if ($socials !== []): ?>
            <ul class="lp-social-row">
              <?php foreach ($socials as $net => $href): ?>
                <li>
                  <a class="lp-social-btn" href="<?= h($href) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= h($socialLabels[$net]) ?>"><i class="<?= h($socialIcons[$net]) ?>" aria-hidden="true"></i></a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </aside>
      <?php endif; ?>
    </div>
  </div>
</section>
