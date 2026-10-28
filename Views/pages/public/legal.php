<?php /* Page légale publique (mentions légales ou politique de
   confidentialité). Variables : $title, $text, $nom.
   Le texte est fourni par l'église - jamais inventé. */ ?>
<section id="contenu" class="lp-section section-legal" aria-labelledby="legal-titre">
  <div class="wrap narrow">
    <p class="lp-eyebrow"><?= h($nom) ?></p>
    <h1 class="lp-title" id="legal-titre"><?= h($title) ?></h1>
    <div class="prose legal-prose"><?= nl2br(h($text)) ?></div>
    <ul class="lp-actions">
      <li><a class="lp-btn lp-btn--ghost" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>#accueil">Retour à l'accueil</a></li>
    </ul>
  </div>
</section>
