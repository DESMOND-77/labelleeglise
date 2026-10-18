<?php /* À propos. Variables : $histoire, $accroche, $equipe. */ ?>
<section id="a-propos" class="section section-alt" aria-labelledby="apropos-titre">
  <div class="wrap narrow">
    <h2 id="apropos-titre">À propos</h2>
    <?php if ($histoire !== ''): ?>
      <div class="prose"><?= nl2br(h($histoire)) ?></div>
    <?php elseif ($accroche !== ''): ?>
      <div class="prose"><?= h($accroche) ?></div>
    <?php endif; ?>
    <?php if ($equipe !== []): ?>
      <h3 class="team-title">Notre équipe</h3>
      <?php foreach ($equipe as $g): ?>
        <h4><?= h($g['label']) ?></h4>
        <ul class="team">
          <?php foreach ($g['members'] as $m): ?>
            <li class="team-member">
              <?php if ($m['photo'] !== ''): ?>
                <img src="<?= h($m['photo']) ?>" alt="<?= h($m['nom']) ?>" loading="lazy" width="72" height="72">
              <?php endif; ?>
              <div>
                <strong><?= h($m['nom']) ?></strong>
                <span class="muted"><?= h($m['role']) ?></span>
                <?php if ($m['bio'] !== ''): ?><p><?= h($m['bio']) ?></p><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>
