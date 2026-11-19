<?php /* À propos et équipe. Variables : $histoire, $accroche, $equipe,
   $impact (tuiles chiffrées réellement renseignées), $responsable
   (responsable de Franceville mis en avant), $image, $imageAlt,
   $aboutTitle. Aucune statistique n'est inventée : une tuile sans valeur
   n'est pas affichée, et la section n'existe que si un texte est publié. */
$paragraphes = $histoire !== ''
    ? array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $histoire) ?: []), static fn($p) => $p !== ''))
    : [];
$teamCount = 0;
foreach ($equipe as $g) {
    $teamCount += count($g['members']);
}
?>
<section id="a-propos" class="lp-section" aria-labelledby="apropos-titre">
  <div class="wrap">
    <div class="lp-about-grid">
      <div class="lp-about-media" data-reveal>
        <?php if ($image !== ''): ?>
          <img src="<?= h($image) ?>" alt="<?= h($imageAlt) ?>" loading="lazy" width="880" height="1100">
        <?php else: ?>
          <div class="lp-media-placeholder">
            <i class="fa-solid fa-church" aria-hidden="true"></i>
            <span><?= h($imageAlt) ?></span>
          </div>
        <?php endif; ?>
      </div>

      <div class="lp-about-body" data-reveal>
        <p class="lp-eyebrow">À propos</p>
        <h2 class="lp-title" id="apropos-titre"><?= h($aboutTitle) ?></h2>
        <?php if ($accroche !== ''): ?>
          <p class="lp-lead"><?= h($accroche) ?></p>
        <?php endif; ?>
        <?php if ($paragraphes !== []): ?>
          <div class="lp-about-text">
            <?php foreach ($paragraphes as $p): ?>
              <p><?= nl2br(h($p)) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($responsable !== null): ?>
      <section class="lp-section lp-section--cream2 lp-responsable" data-reveal aria-labelledby="responsable-titre">
        <div class="wrap">
          <div class="lp-responsable-grid">
            <div class="lp-responsable-body">
              <p class="lp-eyebrow">Responsable à Franceville</p>
              <h2 class="lp-title" id="responsable-titre"><?= h($responsable['nom_affichage'] ?? '') ?></h2>
              <?php if ($responsable['role'] !== ''): ?>
                <span class="lp-person-role"><?= h($responsable['role']) ?></span>
              <?php endif; ?>
              <?php if ($responsable['bio'] !== ''): ?>
                <p class="lp-responsable-bio"><?= nl2br(h($responsable['bio'])) ?></p>
              <?php endif; ?>
            </div>
            <div class="lp-responsable-media" data-reveal>
              <?php if (!empty($responsable['photo'])): ?>
                <img src="<?= h($responsable['photo']) ?>" alt="<?= h($responsable['nom_affichage'] ?? '') ?>" loading="lazy" width="480" height="600">
              <?php else: ?>
                <div class="lp-responsable-media is-empty"><span aria-hidden="true"><?= h($responsable['emoji'] ?? '👤') ?></span></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($teamCount > 0): ?>
      <div class="lp-team" data-reveal>
        <p class="lp-eyebrow">Notre équipe</p>
        <?php foreach ($equipe as $g): ?>
          <h3 class="lp-title lp-title--sm"><?= h($g['label']) ?></h3>
          <ul class="lp-team-grid">
            <?php foreach ($g['members'] as $m): ?>
              <li class="lp-person">
                <div class="lp-person-photo<?= $m['photo'] === '' ? ' is-empty' : '' ?>">
                  <?php if ($m['photo'] !== ''): ?>
                    <img src="<?= h($m['photo']) ?>" alt="<?= h($m['nom']) ?>" loading="lazy" width="480" height="600">
                  <?php else: ?>
                    <span aria-hidden="true"><?= h(mb_strtoupper(mb_substr(trim($m['nom']), 0, 1))) ?></span>
                  <?php endif; ?>
                </div>
                <strong><?= h($m['nom']) ?></strong>
                <?php if ($m['role'] !== ''): ?>
                  <span class="lp-person-role"><?= h($m['role']) ?></span>
                <?php endif; ?>
                <?php if ($m['bio'] !== ''): ?><p><?= h($m['bio']) ?></p><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
