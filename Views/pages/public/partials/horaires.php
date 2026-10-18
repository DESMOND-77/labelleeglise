<?php /* Horaires et informations pratiques. Variables : $centres, $contact, $fuseau, $addr (fn). */ ?>
<section id="horaires" class="section" aria-labelledby="horaires-titre">
  <div class="wrap">
    <h2 id="horaires-titre">Horaires des cultes</h2>
    <?php if ($fuseau !== ''): ?>
      <p class="muted">Tous les horaires sont indiqués au fuseau horaire <?= h($fuseau) ?>.</p>
    <?php endif; ?>
    <?php if ($contact !== []): ?>
      <p class="contact-rapide">
        <?php foreach ($contact as $kind => $link): ?>
          <a href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
            <?= h($kind === 'telephone' ? 'Téléphone' : ($kind === 'whatsapp' ? 'WhatsApp' : 'E-mail')) ?> : <?= h($link['label']) ?></a>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
    <div class="grid">
      <?php foreach ($centres as $c): ?>
        <article class="card">
          <h3><?= h($c['nom']) ?></h3>
          <ul class="slots">
            <?php foreach ($c['horaires'] as $s): ?>
              <li><strong><?= h($s['libelle']) ?></strong> - <?= h($s['jours']) ?>, <?= h($s['debut']) ?><?= $s['fin'] !== '' ? ' – ' . h($s['fin']) : '' ?></li>
            <?php endforeach; ?>
          </ul>
          <p class="muted"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($addr($c)) ?></p>
          <p class="card-actions">
            <?php if ($c['itineraire'] !== ''): ?>
              <a class="btn" href="<?= h($c['itineraire']) ?>" target="_blank" rel="noopener noreferrer">Obtenir l'itinéraire<span class="sr-only"> (<?= h($c['nom']) ?>, nouvel onglet)</span></a>
            <?php endif; ?>
            <?php foreach ($c['contact'] as $kind => $link): ?>
              <a class="btn" href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= h($kind === 'email' ? 'E-mail' : ($kind === 'whatsapp' ? 'WhatsApp' : 'Appeler')) ?><span class="sr-only"> (<?= h($c['nom']) ?>)</span></a>
            <?php endforeach; ?>
          </p>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if (count($centres) > 1): ?>
      <p class="section-suite">Détail et contacts de chaque centre : <a href="#centres">Nos centres</a>.</p>
    <?php endif; ?>
  </div>
</section>
