<?php /* Examens. Variables : $member, $examens. */ ?>
<form class="inline-add-form" method="post" action="index.php">
  <input type="hidden" name="action" value="add_examen">
  <?= csrf_field() ?>
  <input type="hidden" name="membre" value="<?= h($member['id']) ?>">
  <input type="text" name="nom" placeholder="Nom de l'examen" required>
  <input type="date" name="date">
  <button type="submit" class="btn btn-primary btn-sm">+ Ajouter</button>
</form>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Examen</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (!$examens): ?>
        <tr><td colspan="3"><?= empty_state('🎓', 'Aucun examen enregistré.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($examens as $ex): ?>
          <tr>
            <td><?= h($ex['nom']) ?></td>
            <td><?= h($ex['date_examen'] ?? '') ?: '—' ?></td>
            <td class="row-actions">
              <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cet examen ?"
                 href="<?= h(url('index.php', ['page' => 'bergerFiche', 'tab' => 'examens', 'membre' => $member['id'], 'action' => 'delete_examen', 'id' => $ex['id']])) ?>">🗑</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
