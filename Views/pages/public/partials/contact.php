<?php /* Contact. Variables : $contact, $socials, $adresse. */
$socialIcons = ['facebook' => 'fa-brands fa-facebook', 'youtube' => 'fa-brands fa-youtube', 'instagram' => 'fa-brands fa-instagram'];
$socialLabels = ['facebook' => 'Facebook', 'youtube' => 'YouTube', 'instagram' => 'Instagram'];
?>
<section id="contact" class="section" aria-labelledby="contact-titre">
  <div class="wrap">
    <h2 id="contact-titre">Nous contacter</h2>
    <?php if ($adresse !== ''): ?>
      <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($adresse) ?></p>
    <?php endif; ?>
    <ul class="contact-list">
      <?php foreach ($contact as $kind => $link): ?>
        <li><a href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
          <?= h($kind === 'telephone' ? 'Téléphone' : ($kind === 'whatsapp' ? 'WhatsApp' : 'E-mail')) ?> : <?= h($link['label']) ?></a></li>
      <?php endforeach; ?>
      <?php foreach ($socials as $net => $href): ?>
        <li><a href="<?= h($href) ?>" target="_blank" rel="noopener noreferrer"><i class="<?= h($socialIcons[$net]) ?>" aria-hidden="true"></i> <?= h($socialLabels[$net]) ?><span class="sr-only"> (nouvel onglet)</span></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
