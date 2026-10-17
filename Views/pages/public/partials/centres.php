<?php /* Nos centres. Variables : $centres, $addr (fn). */ ?>
<section id="centres" class="section" aria-labelledby="centres-titre">
  <div class="wrap">
    <h2 id="centres-titre">Nos centres</h2>
    <div class="grid">
      <?php foreach ($centres as $c): ?>
        <article class="card">
          <?php if ($c['photo'] !== ''): ?>
            <img class="card-photo" src="<?= h($c['photo']) ?>" alt="<?= h($c['nom']) ?>" loading="lazy" width="400" height="240">
          <?php endif; ?>
          <h3><?= h($c['nom']) ?></h3>
          <?php if ($c['intro'] !== ''): ?>
            <p><?= h($c['intro']) ?></p>
          <?php endif; ?>
          <p class="muted"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($addr($c)) ?></p>
          <?php if ($c['horaires'] !== []): ?>
            <ul class="slots">
              <?php foreach ($c['horaires'] as $s): ?>
                <li><strong><?= h($s['libelle']) ?></strong> — <?= h($s['jours']) ?>, <?= h($s['debut']) ?><?= $s['fin'] !== '' ? ' – ' . h($s['fin']) : '' ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($c['responsable'] !== ''): ?>
            <p class="muted"><i class="fa-solid fa-user" aria-hidden="true"></i> <?= h($c['responsable']) ?></p>
          <?php endif; ?>
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
  </div>
</section>
