<?php /* À propos. Variables : $p, $isAdmin, $groups, $editHistoireUrl, $addTeamUrl. */ ?>
<div class="about-hero">
  <div class="logo-badge"><i class="fa-solid fa-church"></i></div>
  <h2>La Belle Église</h2>
  <p><?= h($p['accroche'] ?? '') ?></p>
</div>

<?= section_toolbar('Notre histoire', '', $editHistoireUrl ? '<a class="icon-btn" title="Modifier" href="' . h($editHistoireUrl) . '"><i class="fa-solid fa-pen"></i></a>' : '') ?>
<div class="narrative-card"><p style="white-space:pre-line;"><?= h($p['histoire'] ?? '') ?></p></div>

<?= section_toolbar('Révérends, Pasteurs, Bergers & Leaders', "L'équipe pastorale de l'église", $addTeamUrl ? '<a class="btn btn-primary" href="' . h($addTeamUrl) . '">+ Ajouter un membre de l\'équipe</a>' : '') ?>

<?php if (!$groups): ?>
  <?= empty_state('fa-hands-praying', 'Aucun membre de l\'équipe pour le moment.') ?>
<?php else: ?>
  <?php foreach ($groups as $g): ?>
    <div class="team-category">
      <h3 class="team-category-title"><?= h($g['label']) ?></h3>
      <div class="team-grid">
        <?php foreach ($g['members'] as $m): ?>
          <?= team_card_html($m, $isAdmin) ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
