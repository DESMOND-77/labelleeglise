<?php /* Rapport du Jour : sélecteur (centre + date) puis formulaire.
  Variables : $centres, $centreId, $date, $report, $bacentas, $bacentaId, $fields, $derived, $canEdit, $errors, $old, $csrf. */
$val = function (string $k, $default = '') use ($old, $report) {
    if (array_key_exists($k, $old)) {
        return $old[$k];
    }
    return $report[$k] ?? $default;
};
?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['rapports']) ?></h2><div class="sub">Formulaire de saisie</div></div>
  <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'rapports'])) ?>"><i class="fa-solid fa-arrow-left"></i> Retour à la liste</a>
</div>

<form method="get" action="index.php" class="rapport-picker">
  <input type="hidden" name="page" value="rapport">
  <label>Centre
    <select name="centre" onchange="this.form.submit()" <?= $report ? 'disabled' : '' ?>>
      <option value="">- Choisir -</option>
      <?php foreach ($centres as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) $centreId === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Date
    <input type="date" name="date" value="<?= h($date) ?>" max="<?= h(date('Y-m-d')) ?>" onchange="this.form.submit()" <?= $report ? 'disabled' : '' ?>>
  </label>
</form>

<?php if ($centreId === null): ?>
  <?= empty_state('fa-hand-pointer', 'Choisissez un centre et une date pour commencer.') ?>
<?php elseif ($date > date('Y-m-d')): ?>
  <div class="alert alert-danger">La date du rapport ne peut pas être postérieure à aujourd’hui.</div>
<?php elseif (!$bacentaId): ?>
  <div class="form-card">
    <form method="get" action="index.php">
      <input type="hidden" name="page" value="rapport">
      <input type="hidden" name="centre" value="<?= (int) $centreId ?>">
      <input type="hidden" name="date" value="<?= h($date) ?>">
      <div class="form-group">
        <label>Bacenta</label>
        <select name="bacenta" required>
          <option value="">- Choisir un bacenta -</option>
          <?php foreach ($bacentas as $b): ?><option value="<?= (int) $b['id'] ?>"><?= h($b['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="modal-actions"><button type="submit" class="btn btn-primary">Continuer</button></div>
    </form>
  </div>
<?php else: ?>

  <?php if (!empty($errors['_form'])): ?><div class="alert alert-danger"><?= h($errors['_form']) ?></div><?php endif; ?>
  <?php if ($report && !$canEdit): ?><div class="alert alert-info">Rapport créé par une autre personne - consultation seule.</div><?php endif; ?>

  <form method="post" action="index.php" class="form-card rapport-form">
    <input type="hidden" name="action" value="save_rapport_jour">
    <?= $csrf ?>
    <input type="hidden" name="centre_id" value="<?= (int) $centreId ?>">
    <input type="hidden" name="date_rapport" value="<?= h($date) ?>">

    <div class="form-grid">
      <div class="form-group"><label>Centre</label><input type="text" value="<?= h($centres[array_search($centreId, array_column($centres, 'id'), true)]['nom'] ?? '') ?>" disabled></div>
      <div class="form-group"><label>Date</label><input type="text" value="<?= h(date('d/m/Y', strtotime($date))) ?>" disabled></div>
    </div>

    <div class="form-grid">
      <div class="form-group"><label>Responsable du centre</label><input type="text" value="<?= h($derived['resp_centre_nom'] ?? '') ?>" disabled></div>
      
      <div class="form-group"><label>Responsable du bacenta</label><input type="text" value="<?= h($derived['resp_bacenta_nom'] ?? '') ?>" disabled></div>
    </div>

    <?php
    $groups = [];
    foreach ($fields as $f) { $groups[$f['group']][] = $f; }
    foreach ($groups as $groupName => $groupFields): ?>
      <h3 class="form-section-title"><?= h($groupName) ?></h3>
      <div class="form-grid">
        <?php foreach ($groupFields as $f): ?>
          <div class="form-group">
            <label><?= h($f['label']) ?></label>
            <?php if ($f['type'] === 'textarea'): ?>
              <textarea name="<?= h($f['key']) ?>" <?= $canEdit ? '' : 'disabled' ?>><?= h((string) $val($f['key'])) ?></textarea>
            <?php elseif ($f['type'] === 'int'): ?>
              <input type="number" min="0" step="1" name="<?= h($f['key']) ?>" value="<?= h((string) $val($f['key'], '0')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
            <?php elseif ($f['type'] === 'decimal'): ?>
              <input type="text" inputmode="decimal" name="<?= h($f['key']) ?>" value="<?= h((string) $val($f['key'], '0')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
            <?php else: ?>
              <input type="text" name="<?= h($f['key']) ?>" value="<?= h((string) $val($f['key'])) ?>" <?= $canEdit ? '' : 'disabled' ?>>
            <?php endif; ?>
            <?php if (!empty($errors[$f['key']])): ?><span class="form-error"><?= h($errors[$f['key']]) ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($canEdit): ?>
      <div class="modal-actions"><button type="submit" class="btn btn-primary"><?= $report ? 'Enregistrer les modifications' : 'Enregistrer le rapport' ?></button></div>
    <?php endif; ?>
  </form>
<?php endif; ?>
