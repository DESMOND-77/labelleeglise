<?php /* Grille des classes / écoles + formulaire.
   Variables : $classes, $edit, $formateurs, $errors, $old, $csrf. */
$e = $edit ?? [];
$val = fn($k, $d = '') => h($old[$k] ?? ($e[$k] ?? $d));
?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['classes']) ?></h2><div class="sub">Cursus de discipleship proposés après le culte</div></div>
</div>

<form method="post" action="index.php" class="form-card classe-form">
  <input type="hidden" name="action" value="save_classe">
  <?= $csrf ?>
  <?php if (!empty($e['id'])): ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><?php endif; ?>
  <div class="form-group">
    <label>Nom de la classe</label>
    <input type="text" name="nom" value="<?= $val('nom') ?>" required>
    <?php if (!empty($errors['nom'])): ?><span class="form-error"><?= h($errors['nom']) ?></span><?php endif; ?>
  </div>
  <div class="form-grid">
    <div class="form-group">
      <label>Formateur</label>
      <select name="formateur_id">
        <option value="">—</option>
        <?php foreach ($formateurs as $f): ?>
          <option value="<?= (int) $f['id'] ?>" <?= (int) ($old['formateur_id'] ?? ($e['formateur_id'] ?? 0)) === (int) $f['id'] ? 'selected' : '' ?>><?= h(trim($f['prenom'] . ' ' . $f['nom'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Ordre de progression</label><input type="number" name="ordre" min="0" value="<?= $val('ordre', '0') ?>"></div>
    <div class="form-group"><label>Nombre de modules</label><input type="number" name="nb_modules" min="1" value="<?= $val('nb_modules', '1') ?>">
      <?php if (!empty($errors['nb_modules'])): ?><span class="form-error"><?= h($errors['nb_modules']) ?></span><?php endif; ?>
    </div>
    <div class="form-group"><label>Prochaine session</label><input type="date" name="prochaine_session" value="<?= $val('prochaine_session') ?>">
      <?php if (!empty($errors['prochaine_session'])): ?><span class="form-error"><?= h($errors['prochaine_session']) ?></span><?php endif; ?>
    </div>
    <div class="form-group"><label class="check-label"><input type="checkbox" name="actif" value="1" <?= !empty($old) ? (!empty($old['actif']) ? 'checked' : '') : (!isset($e['actif']) || $e['actif'] ? 'checked' : '') ?>> Classe active</label></div>
  </div>
  <div class="modal-actions">
    <?php if (!empty($e['id'])): ?><a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'classes'])) ?>">Annuler</a><?php endif; ?>
    <button type="submit" class="btn btn-primary"><?= !empty($e['id']) ? 'Enregistrer' : 'Ajouter la classe' ?></button>
  </div>
</form>

<div class="card-grid">
  <?php foreach ($classes as $c): ?>
    <div class="unit-card" onclick="location.href='<?= h(url('index.php', ['page' => 'classe', 'id' => $c['id']])) ?>'">
      <div class="card-actions">
        <a class="icon-btn" title="Modifier" href="<?= h(url('index.php', ['page' => 'classes', 'edit' => $c['id']])) ?>" onclick="event.stopPropagation()"><i class="fa-solid fa-pen"></i></a>
        <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cette classe et toutes ses inscriptions ?" href="<?= h(url('index.php', ['action' => 'delete_classe', 'id' => $c['id']])) ?>" onclick="event.stopPropagation()"><i class="fa-solid fa-trash"></i></a>
      </div>
      <div class="icon-wrap"><i class="fa-solid fa-graduation-cap"></i></div>
      <h3><?= h($c['nom']) ?></h3>
      <p><?= (int) $c['ordre'] ?> · <?= (int) $c['nb_inscrits'] ?> inscrit(s) · <?= (int) $c['nb_modules'] ?> module(s)<?= $c['actif'] ? '' : ' · inactive' ?></p>
    </div>
  <?php endforeach; ?>
  <?php if (!$classes): ?><?= empty_state('fa-graduation-cap', 'Aucune classe pour le moment.') ?><?php endif; ?>
</div>
