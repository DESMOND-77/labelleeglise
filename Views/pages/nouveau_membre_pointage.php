<?php /* Pointeur de présence des nouveaux membres (liste à cocher, date du jour).
   Variables : $roster, $date, $csrf. */
$presenceBadge = static function (int $nb): string {
    $cls = $nb >= 3 ? 'success' : ($nb > 0 ? 'warning' : 'neutral');
    return '<span class="badge ' . $cls . '">' . $nb . '/3</span>';
};
$checkedCount = count(array_filter($roster, static fn($r) => !empty($r['already_present'])));
?>
<?= back_button('Retour à la liste', url('index.php', ['page' => 'nouveaux_membres'])) ?>
<div class="section-toolbar">
  <div><h2>Pointage - Nouveaux membres</h2><div class="sub">Cocher les personnes présentes au culte du dimanche pour la date choisie</div></div>
  <form method="get" action="index.php" class="rapport-filters">
    <input type="hidden" name="page" value="nouveaux_membres">
    <input type="hidden" name="pointage" value="1">
    <label>Date
      <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
    </label>
  </form>
</div>

<?php
$errorLabels = [
    'aucun_culte_dimanche' => 'Aucun culte n\'est marqué « Culte du dimanche ». Cochez cette option sur un culte (page Cultes) puis recommencez.',
    'date_invalide'        => 'La date choisie est invalide.',
    'introuvable'          => 'Nouveau membre introuvable.',
];
?>
<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= h($errorLabels[$error] ?? 'Le pointage n\'a pas pu être enregistré.') ?></div>
<?php elseif (!empty($noSundayCulte)): ?>
  <div class="alert alert-danger"><?= h($errorLabels['aucun_culte_dimanche']) ?></div>
<?php elseif ($saved !== null): ?>
  <div class="alert alert-success"><?= (int) $saved ?> présence(s) enregistrée(s) pour le <?= h($date) ?>.</div>
<?php endif; ?>

<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_nouveaux_membres_pointage">
  <?= $csrf ?>
  <input type="hidden" name="date" value="<?= h($date) ?>">

  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th></th><th>Nom</th><th>Bacenta</th><th>Présences</th></tr></thead>
      <tbody>
        <?php if (!$roster): ?>
          <tr><td colspan="4"><?= empty_state('fa-user-plus', 'Aucun nouveau membre actif à pointer.') ?></td></tr>
        <?php else: ?>
          <?php foreach ($roster as $r): ?>
            <tr>
              <td><input type="checkbox" name="present_ids[]" value="<?= (int) $r['id'] ?>" <?= !empty($r['already_present']) ? 'checked' : '' ?>></td>
              <td><?= h(trim($r['prenom'] . ' ' . $r['nom'])) ?></td>
              <td><?= h($r['bacenta_nom'] ?? '-') ?></td>
              <td><?= $presenceBadge((int) ($r['nb_presences'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="modal-actions">
    <span class="completion-chip"><?= $checkedCount ?> / <?= count($roster) ?> déjà pointés pour cette date</span>
    <button type="submit" class="btn btn-primary">Enregistrer le pointage</button>
  </div>
</form>
