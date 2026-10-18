<?php /* Première visite et FAQ : accordéon natif <details>/<summary>
   (clavier et lecteurs d'écran pris en charge par le navigateur,
   contenu indexable, aucun JavaScript requis). Variables : $faq. */ ?>
<section id="visite" class="section" aria-labelledby="visite-titre">
  <div class="wrap narrow">
    <h2 id="visite-titre">Première visite - questions fréquentes</h2>
    <?php foreach ($faq as $f): ?>
      <details class="faq-item">
        <summary><?= h($f['question']) ?></summary>
        <div class="faq-reponse"><?= nl2br(h($f['reponse'])) ?></div>
      </details>
    <?php endforeach; ?>
  </div>
</section>
