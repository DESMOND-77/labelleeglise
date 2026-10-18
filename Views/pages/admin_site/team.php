<?php /* Équipe pastorale - publication par personne, jamais par défaut.
   Variables : $equipe, $csrf. */ ?>
<div class="page-header">
  <h2>Équipe pastorale</h2>
  <p class="sub">Seules les personnes explicitement cochées apparaissent sur le site public.</p>
</div>

<div class="alert alert-info" role="note">
  <strong>Consentement requis.</strong> Une case cochée n'est pas une preuve d'autorisation :
  ne publiez un nom, un rôle, une photo ou une présentation que si la personne a donné
  son accord explicite. La publication est désactivée par défaut pour tout nouveau membre.
</div>

<form class="form-card" method="post" action="index.php" data-track-dirty>
  <input type="hidden" name="action" value="save_equipe_public">
  <?= $csrf ?>

  <?php if (!$equipe): ?>
    <?= empty_state('fa-users', 'Aucun membre d\'équipe enregistré. Ajoutez-en via « Présentation de l\'église » (gestion de l\'équipe).') ?>
  <?php endif; ?>

  <?php foreach ($equipe as $m): ?>
    <div class="team-row">
      <label class="check-label">
        <input type="checkbox" name="publie[]" value="<?= (int) $m['id'] ?>"<?= !empty($m['publie']) ? ' checked' : '' ?>>
        <span class="team-row-name"><?= h($m['nom_affichage']) ?></span>
        <span class="muted"><?= h($m['role_affichage']) ?><?= $m['categorie'] !== 'Autre' ? ' - ' . h($m['categorie']) : '' ?></span>
      </label>
      <?php if (!empty($m['photo'] ?? '')): ?>
        <img class="preview-thumb" src="<?= h($m['photo']) ?>" alt="">
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Enregistrer l'équipe</button>
    <a class="btn" href="<?= h(url('index.php', ['page' => 'apropos'])) ?>">Gérer les membres (ajout, modification, suppression)</a>
  </div>
</form>
