<?php /* Nos centres : cartes « lieu » dominées par la photo. Variables : $centres, $addr (fn). */ ?>
<section id="centres" class="section" aria-labelledby="centres-titre">
  <div class="wrap">
    <header class="section-head">
      <h2 id="centres-titre">Nos centres</h2>
      <p class="section-sub">Trouvez le lieu de culte le plus proche de chez vous.</p>
    </header>
    <div class="place-grid">
      <?php foreach ($centres as $c): ?>
        <article class="place">
          <div class="place-media<?= $c['photo'] === '' ? ' is-empty' : '' ?>">
            <?php if ($c['photo'] !== ''): ?>
              <img src="<?= h($c['photo']) ?>" alt="<?= h($c['nom']) ?>" loading="lazy" width="800" height="600">
            <?php else: ?>
              <i class="fa-solid fa-church" aria-hidden="true"></i>
            <?php endif; ?>
            <div class="place-title">
              <h3><?= h($c['nom']) ?></h3>
              <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($addr($c)) ?></p>
            </div>
          </div>
          <div class="place-body">
            <?php if ($c['intro'] !== ''): ?>
              <p><?= h($c['intro']) ?></p>
            <?php endif; ?>
            <?php if ($c['horaires'] !== []): ?>
              <ul class="slots">
                <?php foreach ($c['horaires'] as $s): ?>
                  <li><strong><?= h($s['libelle']) ?></strong><span><?= h($s['jours']) ?>, <?= h($s['debut']) ?><?= $s['fin'] !== '' ? ' – ' . h($s['fin']) : '' ?></span></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if ($c['responsable'] !== ''): ?>
              <p class="muted"><i class="fa-solid fa-user" aria-hidden="true"></i> <?= h($c['responsable']) ?></p>
            <?php endif; ?>
            <p class="card-actions">
              <?php if ($c['itineraire'] !== ''): ?>
                <a class="btn btn-primary" href="<?= h($c['itineraire']) ?>" target="_blank" rel="noopener noreferrer">Obtenir l'itinéraire<span class="sr-only"> (<?= h($c['nom']) ?>, nouvel onglet)</span></a>
              <?php endif; ?>
              <?php foreach ($c['contact'] as $kind => $link): ?>
                <a class="btn" href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= h($kind === 'email' ? 'E-mail' : ($kind === 'whatsapp' ? 'WhatsApp' : 'Appeler')) ?><span class="sr-only"> (<?= h($c['nom']) ?>)</span></a>
              <?php endforeach; ?>
            </p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
