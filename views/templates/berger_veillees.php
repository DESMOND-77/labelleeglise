<?php /* Veillées. Variables : $member, $veillees, $presentCount. */ ?>
<form class="inline-add-form" method="post" action="index.php">
  <input type="hidden" name="action" value="add_veillee">
  <?= csrf_field() ?>
  <input type="hidden" name="membre" value="<?= h($member['id']) ?>">
  <input type="date" name="date" required>
  <select name="present">
    <option value="1">Présent</option>
    <option value="0">Absent</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">+ Ajouter</button>
</form>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (!$veillees): ?>
        <tr><td colspan="3"><?= empty_state('🌙', 'Aucune veillée enregistrée.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($veillees as $v): ?>
          <tr>
            <td><?= h($v['date_veillee']) ?></td>
            <td><span class="badge <?= $v['present'] ? 'present' : 'absent' ?>"><?= $v['present'] ? 'Présent' : 'Absent' ?></span></td>
            <td class="row-actions">
              <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cette veillée ?"
                 href="<?= h(url('index.php', ['page' => 'bergerFiche', 'tab' => 'veillees', 'membre' => $member['id'], 'action' => 'delete_veillee', 'id' => $v['id']])) ?>">🗑</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<div class="totals-row">
  <?= total_chip('Présences', $presentCount . ' / ' . count($veillees)) ?>
</div>
