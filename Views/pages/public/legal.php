<?php /* Page légale publique (mentions légales ou politique de
   confidentialité). Variables : $title, $text, $nom.
   Le texte est fourni par l'église — jamais inventé. */ ?>
<section id="contenu" class="section section-legal" aria-labelledby="legal-titre">
  <div class="wrap narrow">
    <h1 id="legal-titre"><?= h($title) ?></h1>
    <div class="prose"><?= nl2br(h($text)) ?></div>
    <p><a class="btn" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>#accueil">Retour à l'accueil</a></p>
  </div>
</section>
