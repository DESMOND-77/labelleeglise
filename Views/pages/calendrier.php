<?php /* Calendrier événementiel : liste + formulaire (ou fiche d'un événement).
   Variables : $events, $canManage, $edit, $responsables, $errors, $old, $csrf, $mode
               (+ $fiche, $canEditFiche si $mode === 'fiche'). */
if (($mode ?? 'list') === 'fiche'):
    $e = $fiche;
    $deb = strtotime((string) $e['date_debut']);
    $fin = $e['date_fin'] ? strtotime((string) $e['date_fin']) : null;
?>
<div class="section-toolbar">
  <div><h2><?= h($e['nom']) ?></h2><div class="sub">Fiche événement</div></div>
  <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'calendrier'])) ?>"><i class="fa-solid fa-arrow-left"></i> Retour au calendrier</a>
</div>
<div class="cal-fiche">
  <p><strong>Début :</strong> <?= h(date('d/m/Y H:i', $deb)) ?></p>
  <?php if ($fin): ?><p><strong>Fin :</strong> <?= h(date('d/m/Y H:i', $fin)) ?></p><?php endif; ?>
  <?php if ($e['lieu']): ?><p><strong>Lieu :</strong> <?= h($e['lieu']) ?></p><?php endif; ?>
  <?php if ($e['resp_prenom'] || $e['resp_nom']): ?><p><strong>Responsable :</strong> <?= h(trim(($e['resp_prenom'] ?? '') . ' ' . ($e['resp_nom'] ?? ''))) ?></p><?php endif; ?>
</div>
<?php if (!empty($canPointe)): ?>
<div class="section-toolbar"><div><h3>Pointage des présences</h3></div></div>
<form method="get" action="index.php" class="presence-datebar">
  <input type="hidden" name="page" value="calendrier">
  <input type="hidden" name="evt" value="<?= (int) $e['id'] ?>">
  <label>Date</label>
  <input type="date" name="date" value="<?= h($presenceDate) ?>" onchange="this.form.submit()">
</form>
<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_presence_occurrence">
  <?= $csrf ?>
  <input type="hidden" name="unit_type" value="evenement">
  <input type="hidden" name="unit_id" value="<?= (int) $e['id'] ?>">
  <input type="hidden" name="date" value="<?= h($presenceDate) ?>">
  <div class="table-wrap">
    <table class="data-table presence-table">
      <thead><tr><th>Membre</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($presenceGrid as $line): $u = $line['user']; ?>
          <tr>
            <td><?= h(full_name($u)) ?></td>
            <td>
              <select name="statut[<?= (int) $u['id'] ?>]">
                <option value="">—</option>
                <?php foreach ($presenceStatuts as $k => $lbl): ?>
                  <option value="<?= h($k) ?>" <?= $line['statut'] === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="modal-actions"><button type="submit" class="btn btn-primary" <?= $presenceGrid ? '' : 'disabled' ?>>Enregistrer les présences</button></div>
</form>
<?php endif; ?>
<?php return; endif; ?>

<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['calendrier']) ?></h2><div class="sub">Événements à venir et passés</div></div>
</div>

<?php if ($canManage): ?>
  <?php $e = $edit ?? []; $val = fn($k) => h($old[$k] ?? ($e[$k] ?? '')); ?>
  <form method="post" action="index.php" class="form-card cal-form">
    <input type="hidden" name="action" value="save_evenement">
    <?= $csrf ?>
    <?php if (!empty($e['id'])): ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><?php endif; ?>
    <div class="form-group">
      <label>Nom de l'événement</label>
      <input type="text" name="nom" value="<?= $val('nom') ?>" required>
      <?php if (!empty($errors['nom'])): ?><span class="form-error"><?= h($errors['nom']) ?></span><?php endif; ?>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Début</label>
        <input type="datetime-local" name="date_debut" value="<?= h($old['date_debut'] ?? (!empty($e['date_debut']) ? date('Y-m-d\TH:i', strtotime((string) $e['date_debut'])) : '')) ?>" required>
        <?php if (!empty($errors['date_debut'])): ?><span class="form-error"><?= h($errors['date_debut']) ?></span><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Fin (facultatif)</label>
        <input type="datetime-local" name="date_fin" value="<?= h($old['date_fin'] ?? (!empty($e['date_fin']) ? date('Y-m-d\TH:i', strtotime((string) $e['date_fin'])) : '')) ?>">
        <?php if (!empty($errors['date_fin'])): ?><span class="form-error"><?= h($errors['date_fin']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Lieu</label><input type="text" name="lieu" value="<?= $val('lieu') ?>"></div>
      <div class="form-group">
        <label>Responsable</label>
        <select name="responsable_id">
          <option value="">—</option>
          <?php foreach ($responsables as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= (int) ($old['responsable_id'] ?? ($e['responsable_id'] ?? 0)) === (int) $r['id'] ? 'selected' : '' ?>><?= h(trim($r['prenom'] . ' ' . $r['nom'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="modal-actions">
      <?php if (!empty($e['id'])): ?><a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'calendrier'])) ?>">Annuler</a><?php endif; ?>
      <button type="submit" class="btn btn-primary"><?= !empty($e['id']) ? 'Enregistrer' : 'Ajouter l\'événement' ?></button>
    </div>
  </form>
<?php endif; ?>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Date</th><th>Événement</th><th>Lieu</th><th>Responsable</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr></thead>
    <tbody>
      <?php if (!$events): ?>
        <tr><td colspan="<?= $canManage ? 5 : 4 ?>"><?= empty_state('fa-calendar-day', 'Aucun événement pour le moment.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($events as $ev): $ts = strtotime((string) $ev['date_debut']); ?>
          <tr>
            <td><?= h(date('d/m/Y H:i', $ts)) ?></td>
            <td><a href="<?= h(url('index.php', ['page' => 'calendrier', 'evt' => $ev['id']])) ?>"><?= h($ev['nom']) ?></a></td>
            <td><?= h($ev['lieu'] ?? '') ?></td>
            <td><?= h(trim(($ev['resp_prenom'] ?? '') . ' ' . ($ev['resp_nom'] ?? ''))) ?></td>
            <?php if ($canManage): ?>
              <td class="row-actions">
                <?php if (auth_can_edit_evenement($ev)): ?>
                  <a class="icon-btn" title="Modifier" href="<?= h(url('index.php', ['page' => 'calendrier', 'edit' => $ev['id']])) ?>"><i class="fa-solid fa-pen"></i></a>
                  <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cet événement ?" href="<?= h(url('index.php', ['action' => 'delete_evenement', 'id' => $ev['id']])) ?>"><i class="fa-solid fa-trash"></i></a>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
