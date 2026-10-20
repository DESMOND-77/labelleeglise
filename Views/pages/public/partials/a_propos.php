<?php /* À propos et équipe. Variables : $histoire, $accroche, $equipe. */ ?>
<section id="a-propos" class="section" aria-labelledby="apropos-titre">
  <div class="wrap">
    <header class="section-head">
      <h2 id="apropos-titre">À propos</h2>
    </header>
    <?php if ($histoire !== '' || $accroche !== ''): ?>
      <div class="prose prose-lead"><?= $histoire !== '' ? nl2br(h($histoire)) : h($accroche) ?></div>
    <?php endif; ?>
    <?php if ($equipe !== []): ?>
      <?php foreach ($equipe as $g): ?>
        <h3 class="team-title"><?= h($g['label']) ?></h3>
        <ul class="team">
          <?php foreach ($g['members'] as $m): ?>
            <li class="person">
              <div class="person-photo<?= $m['photo'] === '' ? ' is-empty' : '' ?>">
                <?php if ($m['photo'] !== ''): ?>
                  <img src="<?= h($m['photo']) ?>" alt="<?= h($m['nom']) ?>" loading="lazy" width="480" height="600">
                <?php else: ?>
                  <span aria-hidden="true"><?= h(mb_strtoupper(mb_substr(trim($m['nom']), 0, 1))) ?></span>
                <?php endif; ?>
              </div>
              <strong><?= h($m['nom']) ?></strong>
              <span class="muted"><?= h($m['role']) ?></span>
              <?php if ($m['bio'] !== ''): ?><p><?= h($m['bio']) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>
