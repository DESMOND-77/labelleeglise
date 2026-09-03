<?php /* Détail d'une classe : infos + inscription + tableau des inscrits/examens.
   Variables : $classe, $inscrits, $candidates, $statuts, $errors, $old, $csrf. */ ?>
<div class="section-toolbar">
  <div><h2><?= h($classe['nom']) ?></h2>
    <div class="sub">
      <?= (int) $classe['nb_modules'] ?> module(s) · ordre <?= (int) $classe['ordre'] ?>
      <?php if ($classe['formateur_prenom'] || $classe['formateur_nom']): ?> · Formateur : <?= h(trim(($classe['formateur_prenom'] ?? '') . ' ' . ($classe['formateur_nom'] ?? ''))) ?><?php endif; ?>
      <?php if (!empty($classe['prochaine_session'])): ?> · Prochaine session : <?= h(date('d/m/Y', strtotime((string) $classe['prochaine_session']))) ?><?php endif; ?>
    </div>
  </div>
  <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'classes'])) ?>"><i class="fa-solid fa-arrow-left"></i> Toutes les classes</a>
</div>

<form method="post" action="index.php" class="inline-add-form">
  <input type="hidden" name="action" value="save_classe_inscrit">
  <?= $csrf ?>
  <input type="hidden" name="classe_id" value="<?= (int) $classe['id'] ?>">
  <select name="user_id" required>
    <option value="">— Inscrire un membre —</option>
    <?php foreach ($candidates as $u): ?>
      <option value="<?= (int) $u['id'] ?>"><?= h(trim($u['prenom'] . ' ' . $u['nom'])) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">+ Inscrire</button>
</form>

<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_classe_inscrits">
  <?= $csrf ?>
  <input type="hidden" name="classe_id" value="<?= (int) $classe['id'] ?>">
  <div class="table-wrap">
    <table class="data-table classe-inscrits">
      <thead><tr><th>Membre</th><th>Modules validés</th><th>Examen oral</th><th>Examen écrit</th><th>Note</th><th>Date</th><th>Statut</th><th></th></tr></thead>
      <tbody>
        <?php if (!$inscrits): ?>
          <tr><td colspan="8"><?= empty_state('fa-users', 'Aucun inscrit pour le moment.') ?></td></tr>
        <?php else: ?>
          <?php foreach ($inscrits as $i): $iid = (int) $i['id']; ?>
            <tr>
              <td><?= h(trim(($i['prenom'] ?? '') . ' ' . ($i['nom'] ?? ''))) ?></td>
              <td><input type="number" name="inscrit[<?= $iid ?>][modules_valides]" min="0" max="<?= (int) $classe['nb_modules'] ?>" value="<?= (int) $i['modules_valides'] ?>"></td>
              <td><select name="inscrit[<?= $iid ?>][exam_oral]"><?php foreach ($statuts as $k => $lbl): ?><option value="<?= h($k) ?>" <?= $i['exam_oral'] === $k ? 'selected' : '' ?>><?= h($lbl) ?></option><?php endforeach; ?></select></td>
              <td><select name="inscrit[<?= $iid ?>][exam_ecrit]"><?php foreach ($statuts as $k => $lbl): ?><option value="<?= h($k) ?>" <?= $i['exam_ecrit'] === $k ? 'selected' : '' ?>><?= h($lbl) ?></option><?php endforeach; ?></select></td>
              <td><input type="text" inputmode="decimal" name="inscrit[<?= $iid ?>][exam_note]" value="<?= h($i['exam_note'] ?? '') ?>"></td>
              <td><input type="date" name="inscrit[<?= $iid ?>][exam_date]" value="<?= h($i['exam_date'] ?? '') ?>"></td>
              <td><span class="badge <?= $i['statut'] === 'termine' ? 'present' : '' ?>"><?= $i['statut'] === 'termine' ? 'Terminé' : 'Inscrit' ?></span></td>
              <td class="row-actions">
                <a class="icon-btn danger" title="Retirer" data-confirm="Retirer cet inscrit ?" href="<?= h(url('index.php', ['action' => 'remove_classe_inscrit', 'id' => $iid])) ?>"><i class="fa-solid fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($inscrits): ?><div class="modal-actions"><button type="submit" class="btn btn-primary">Enregistrer les évaluations</button></div><?php endif; ?>
</form>
