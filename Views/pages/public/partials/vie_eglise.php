<?php /* Ministères, groupes et activités. Variables : $ministries.
   Une Bacenta est un rassemblement de prière ; une Basonta est un
   groupe ou un service (spec §4.6) - les deux ne sont jamais
   confondus. Aucune liste de membres n'est exposée. */ ?>
<section id="vie-eglise" class="section section-alt" aria-labelledby="vie-titre">
  <div class="wrap">
    <h2 id="vie-titre">Vie de l'église</h2>
    <?php if ($ministries['bacentas'] !== []): ?>
      <h3>Bacentas - rassemblements de prière</h3>
      <div class="grid">
        <?php foreach ($ministries['bacentas'] as $m): ?>
          <article class="card">
            <h4><?= h($m['nom']) ?></h4>
            <p><?= nl2br(h($m['description'])) ?></p>
            <?php if ($m['horaires'] !== ''): ?>
              <p class="muted"><i class="fa-solid fa-clock" aria-hidden="true"></i> <?= h($m['horaires']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($ministries['basontas'] !== []): ?>
      <h3>Basontas - groupes et services</h3>
      <div class="grid">
        <?php foreach ($ministries['basontas'] as $m): ?>
          <article class="card">
            <h4><?= h($m['nom']) ?></h4>
            <p><?= nl2br(h($m['description'])) ?></p>
            <?php if ($m['horaires'] !== ''): ?>
              <p class="muted"><i class="fa-solid fa-clock" aria-hidden="true"></i> <?= h($m['horaires']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
