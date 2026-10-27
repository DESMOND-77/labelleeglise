<?php /* Dons, bénévolat et engagement - uniquement avec une
   destination réelle et vérifiée. Variables : $lien_don,
   $don_usage, $hasContact. */
$usages = $don_usage !== ''
    ? array_values(array_filter(array_map('trim', preg_split('/\R+/', $don_usage) ?: []), static fn($l) => $l !== ''))
    : [];
?>
<section id="engagement" class="lp-giving" aria-labelledby="engagement-titre">
  <div class="wrap lp-giving-inner">
    <div data-reveal>
      <p class="lp-eyebrow">Soutenir l'œuvre</p>
      <h2 class="lp-title" id="engagement-titre">S'engager à nos côtés</h2>
      <?php if ($usages !== []): ?>
        <ul class="lp-giving-usages">
          <?php foreach ($usages as $u): ?>
            <li>
              <i class="fa-solid fa-check" aria-hidden="true"></i>
              <span><?= nl2br(h($u)) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="lp-giving-card" data-reveal>
      <h3>Faire un don</h3>
      <p>Votre don soutient la mission de l'église via la plateforme officielle.</p>
      <ul class="lp-actions">
        <li>
          <a class="lp-btn lp-btn--primary lp-btn--lg" href="<?= h($lien_don) ?>" target="_blank" rel="noopener noreferrer">Faire un don<span class="sr-only"> (plateforme officielle, nouvel onglet)</span></a>
        </li>
        <?php if ($hasContact): ?>
          <li>
            <a class="lp-btn lp-btn--ghost-light lp-btn--lg" href="#contact">Devenir bénévole</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</section>
