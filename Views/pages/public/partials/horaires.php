<?php /* Horaires et informations pratiques (bande sombre).
   Variables : $centres, $contact, $fuseau, $addr (fn). */ ?>
<section id="horaires" class="lp-section lp-section--ink" aria-labelledby="horaires-titre">
  <div class="wrap">
    <header class="lp-section-head">
      <p class="lp-eyebrow">Cultes &amp; rencontres</p>
      <h2 class="lp-title" id="horaires-titre">Horaires des cultes</h2>
      <?php if ($fuseau !== ''): ?>
        <p class="lp-lead">Tous les horaires sont indiqués au fuseau <?= h($fuseau) ?>.</p>
      <?php endif; ?>
    </header>

    <?php if ($contact !== []): ?>
      <ul class="lp-quick-contact">
        <?php foreach ($contact as $kind => $link): ?>
          <li>
            <a href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
              <i class="fa-solid <?= h($kind === 'telephone' ? 'fa-phone' : ($kind === 'whatsapp' ? 'fa-brands fa-whatsapp' : 'fa-envelope')) ?>" aria-hidden="true"></i>
              <?= h($kind === 'telephone' ? 'Téléphone' : ($kind === 'whatsapp' ? 'WhatsApp' : 'E-mail')) ?> : <?= h($link['label']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="lp-schedule-grid">
      <?php foreach ($centres as $c): ?>
        <article class="lp-schedule" data-reveal>
          <header class="lp-schedule-head">
            <h3><?= h($c['nom']) ?></h3>
            <p class="lp-schedule-place"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($addr($c)) ?></p>
          </header>
          <ul class="lp-slots">
            <?php foreach ($c['horaires'] as $s): ?>
              <li>
                <span class="lp-slot-label"><?= h($s['libelle']) ?></span>
                <span class="lp-slot-time"><?= h($s['debut']) ?><?= $s['fin'] !== '' ? ' – ' . h($s['fin']) : '' ?></span>
                <span class="lp-slot-days"><?= h($s['jours']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if ($c['itineraire'] !== '' || $c['contact'] !== []): ?>
            <ul class="lp-actions">
              <?php if ($c['itineraire'] !== ''): ?>
                <li>
                  <a class="lp-btn lp-btn--primary lp-btn--sm" href="<?= h($c['itineraire']) ?>" target="_blank" rel="noopener noreferrer">Itinéraire<span class="sr-only"> (<?= h($c['nom']) ?>, nouvel onglet)</span></a>
                </li>
              <?php endif; ?>
              <?php foreach ($c['contact'] as $kind => $link): ?>
                <li>
                  <a class="lp-btn lp-btn--ghost-light lp-btn--sm" href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= h($kind === 'email' ? 'E-mail' : ($kind === 'whatsapp' ? 'WhatsApp' : 'Appeler')) ?><span class="sr-only"> (<?= h($c['nom']) ?>)</span></a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
