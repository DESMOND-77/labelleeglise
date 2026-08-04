<?php /* Grille des centres. Variables : $cards, $addCard, $addUrl, $isAdmin, $count. */ ?>
<?= section_toolbar('Présentation des centres', 'Articles officiels de tous les centres de l\'église', $addUrl ? '<a class="btn btn-primary" href="' . h($addUrl) . '">+ Ajouter un centre</a>' : '') ?>
<div class="card-grid">
  <?= $cards ?>
  <?= $addCard ?>
</div>
<?php if ($count === 0): ?>
  <?= empty_state('fa-school', 'Aucun centre présenté pour le moment.') ?>
<?php endif; ?>
