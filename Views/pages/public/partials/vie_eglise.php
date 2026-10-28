<?php /* Ministères, groupes et activités. Variables : $ministries.
   Une Bacenta est un rassemblement de prière ; une Basonta est un
   groupe ou un service (spec §4.6) - les deux ne sont jamais
   confondus. Aucune liste de membres n'est exposée. */ ?>
<section id="vie-eglise" class="lp-section lp-section--ink" aria-labelledby="vie-titre">
  <div class="wrap">
    <header class="lp-section-head">
      <p class="lp-eyebrow">Communauté</p>
      <h2 class="lp-title" id="vie-titre">Vie de l'église</h2>
    </header>

    <?php if ($ministries['bacentas'] !== []): ?>
      <div class="lp-group">
        <h3 class="lp-group-title">Bacentas · rassemblements de prière</h3>
        <ul class="lp-ministry-grid">
          <?php foreach ($ministries['bacentas'] as $m): ?>
            <li class="lp-ministry" data-reveal>
              <h4><?= h($m['nom']) ?></h4>
              <p><?= nl2br(h($m['description'])) ?></p>
              <?php if ($m['horaires'] !== ''): ?>
                <p class="lp-ministry-when"><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= h($m['horaires']) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($ministries['basontas'] !== []): ?>
      <div class="lp-group">
        <h3 class="lp-group-title">Basontas · groupes et services</h3>
        <ul class="lp-ministry-grid">
          <?php foreach ($ministries['basontas'] as $m): ?>
            <li class="lp-ministry" data-reveal>
              <h4><?= h($m['nom']) ?></h4>
              <p><?= nl2br(h($m['description'])) ?></p>
              <?php if ($m['horaires'] !== ''): ?>
                <p class="lp-ministry-when"><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= h($m['horaires']) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</section>
