<?php /* Nos centres : cartes « lieu » dominées par la photo.
   Variables : $centres, $addr (fn). */ ?>
<section id="centres" class="lp-section lp-section--surface" aria-labelledby="centres-titre">
  <div class="wrap">
    <header class="lp-section-head">
      <p class="lp-eyebrow">Où nous trouver</p>
      <h2 class="lp-title" id="centres-titre">Nos centres</h2>
      <p class="lp-lead">Trouvez le lieu de culte le plus proche de chez vous.</p>
    </header>
    <div class="lp-card-grid">
      <?php foreach ($centres as $c): ?>
        <article class="lp-place<?= $c['article'] !== null ? ' has-article' : '' ?>" data-reveal>
          <div class="lp-place-media<?= $c['photo'] === '' ? ' is-empty' : '' ?>">
            <?php if ($c['photo'] !== ''): ?>
              <img src="<?= h($c['photo']) ?>" alt="<?= h($c['nom']) ?>" loading="lazy" width="800" height="500">
            <?php else: ?>
              <i class="fa-solid fa-church" aria-hidden="true"></i>
            <?php endif; ?>
            <div class="lp-place-title">
              <h3>
                <?php if ($c['article'] !== null): ?>
                  <button type="button" class="lp-place-open" data-centre-modal="centre-modal-<?= (int) $c['id'] ?>" aria-haspopup="dialog"><?= h($c['nom']) ?><span class="sr-only"> : présentation du centre</span></button>
                <?php else: ?>
                  <?= h($c['nom']) ?>
                <?php endif; ?>
              </h3>
              <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($addr($c)) ?></p>
            </div>
          </div>
          <div class="lp-place-body">
            <?php if ($c['intro'] !== ''): ?>
              <p><?= h($c['intro']) ?></p>
            <?php endif; ?>
            <?php if ($c['horaires'] !== []): ?>
              <ul class="lp-slots-mini">
                <?php foreach ($c['horaires'] as $s): ?>
                  <li>
                    <strong><?= h($s['libelle']) ?></strong>
                    <span><?= h($s['jours']) ?>, <?= h($s['debut']) ?><?= $s['fin'] !== '' ? ' – ' . h($s['fin']) : '' ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if ($c['responsable'] !== ''): ?>
              <p><i class="fa-solid fa-user" aria-hidden="true"></i> <?= h($c['responsable']) ?></p>
            <?php endif; ?>
            <?php if ($c['itineraire'] !== '' || $c['contact'] !== []): ?>
              <ul class="lp-actions">
                <?php if ($c['itineraire'] !== ''): ?>
                  <li>
                    <a class="lp-btn lp-btn--dark lp-btn--sm" href="<?= h($c['itineraire']) ?>" target="_blank" rel="noopener noreferrer">Itinéraire<span class="sr-only"> (<?= h($c['nom']) ?>, nouvel onglet)</span></a>
                  </li>
                <?php endif; ?>
                <?php foreach ($c['contact'] as $kind => $link): ?>
                  <li>
                    <a class="lp-btn lp-btn--ghost lp-btn--sm" href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= h($kind === 'email' ? 'E-mail' : ($kind === 'whatsapp' ? 'WhatsApp' : 'Appeler')) ?><span class="sr-only"> (<?= h($c['nom']) ?>)</span></a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php foreach ($centres as $c): ?>
  <?php if ($c['article'] === null) { continue; } $a = $c['article']; ?>
  <dialog class="centre-modal" id="centre-modal-<?= (int) $c['id'] ?>" aria-labelledby="centre-modal-titre-<?= (int) $c['id'] ?>">
    <div class="centre-modal-card">
      <button type="button" class="centre-modal-close" data-centre-modal-close aria-label="Fermer"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      <?php if ($c['photo'] !== ''): ?>
        <img class="centre-modal-photo" src="<?= h($c['photo']) ?>" alt="<?= h($c['nom']) ?>" loading="lazy">
      <?php endif; ?>
      <div class="centre-modal-body">
        <h3 id="centre-modal-titre-<?= (int) $c['id'] ?>"><?= h($c['nom']) ?></h3>
        <?php if ($a['intro'] !== ''): ?><p class="centre-modal-intro"><?= nl2br(h($a['intro'])) ?></p><?php endif; ?>
        <?php if ($a['stats'] !== []): ?>
          <ul class="centre-modal-stats">
            <?php foreach ($a['stats'] as $label => $n): ?>
              <li><strong><?= (int) $n ?></strong><span><?= h($label) ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php foreach (['vision' => 'Vision', 'direction' => 'Direction & encadrement', 'origine' => 'Origine'] as $key => $label): ?>
          <?php if ($a[$key] !== ''): ?>
            <h4><?= h($label) ?></h4>
            <p><?= nl2br(h($a[$key])) ?></p>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($a['objectifs'] !== []): ?>
          <h4>Objectifs</h4>
          <ul class="centre-modal-list">
            <?php foreach ($a['objectifs'] as $o): ?><li><?= h($o) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </dialog>
<?php endforeach; ?>
