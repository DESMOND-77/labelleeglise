<?php /* Calendrier d'anniversaires : fusion membres + saisies manuelles.
   Variables : $birthdays, $canManage, $monthsFr, $currentMonth, $errors, $old, $csrf. */ ?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['anniversaires']) ?></h2><div class="sub">Anniversaires de l'année — mois courant surligné</div></div>
</div>

<?php if ($canManage): ?>
  <form method="post" action="index.php" class="form-card cal-form">
    <input type="hidden" name="action" value="save_anniversaire">
    <?= $csrf ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Nom</label>
        <input type="text" name="nom" value="<?= h($old['nom'] ?? '') ?>" required>
        <?php if (!empty($errors['nom'])): ?><span class="form-error"><?= h($errors['nom']) ?></span><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Jour</label>
        <input type="number" name="jour" min="1" max="31" value="<?= h($old['jour'] ?? '') ?>" required>
        <?php if (!empty($errors['jour'])): ?><span class="form-error"><?= h($errors['jour']) ?></span><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Mois</label>
        <select name="mois" required>
          <option value="">—</option>
          <?php foreach ($monthsFr as $i => $m): ?>
            <option value="<?= $i + 1 ?>" <?= (int) ($old['mois'] ?? 0) === $i + 1 ? 'selected' : '' ?>><?= h($m) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['mois'])): ?><span class="form-error"><?= h($errors['mois']) ?></span><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Année (facultatif)</label>
        <input type="number" name="annee" min="1900" max="<?= (int) date('Y') ?>" value="<?= h($old['annee'] ?? '') ?>">
        <?php if (!empty($errors['annee'])): ?><span class="form-error"><?= h($errors['annee']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="modal-actions"><button type="submit" class="btn btn-primary">Ajouter l'anniversaire</button></div>
  </form>
<?php endif; ?>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Date</th><th>Nom</th><th>Âge</th><th>Source</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr></thead>
    <tbody>
      <?php if (!$birthdays): ?>
        <tr><td colspan="<?= $canManage ? 5 : 4 ?>"><?= empty_state('fa-cake-candles', 'Aucun anniversaire enregistré.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($birthdays as $b): ?>
          <tr class="<?= $b['is_current_month'] ? 'anniv-current' : '' ?>">
            <td><?= (int) $b['jour'] ?> <?= h($monthsFr[$b['mois'] - 1] ?? '') ?></td>
            <td><?= h($b['nom']) ?></td>
            <td><?= $b['age'] !== null ? (int) $b['age'] . ' ans' : '—' ?></td>
            <td><?= $b['source'] === 'membre' ? 'Membre' : 'Saisie manuelle' ?></td>
            <?php if ($canManage): ?>
              <td class="row-actions">
                <?php if ($b['source'] === 'manuel'): ?>
                  <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cet anniversaire ?" href="<?= h(url('index.php', ['action' => 'delete_anniversaire', 'id' => $b['id']])) ?>"><i class="fa-solid fa-trash"></i></a>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
