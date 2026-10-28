<?php /* Première visite et FAQ : accordéon natif <details>/<summary>
   (clavier et lecteurs d'écran pris en charge par le navigateur,
   contenu indexable, aucun JavaScript requis). Variables : $faq. */ ?>
<section id="visite" class="lp-section lp-section--surface" aria-labelledby="visite-titre">
  <div class="wrap narrow">
    <header class="lp-section-head">
      <p class="lp-eyebrow">Première visite</p>
      <h2 class="lp-title" id="visite-titre">Questions fréquentes</h2>
    </header>
    <div class="lp-faq">
      <?php foreach ($faq as $f): ?>
        <details class="lp-faq-item">
          <summary><?= h($f['question']) ?></summary>
          <div class="lp-faq-answer"><?= nl2br(h($f['reponse'])) ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
