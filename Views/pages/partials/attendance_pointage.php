<?php
/* Composant de pointage unifié (bacenta / basonta / culte / événement).
   Contrat : $unitType, $unitId, $unitLabel, $date, $dateBarUrl (array de params GET),
             $grid (list<{user,statut}>), $summary ({present,absent,excuse,non_renseigne,total}),
             $statuts (PRESENCE_STATUTS), $joursHint (string), $csrf (string), $canPointe (bool).
   Sans JS : radios + submit fonctionnent ; recherche/filtres/compteurs live/tout-présent = enrichissement. */

$segClass = ['present' => 'seg-present', 'absent' => 'seg-absent', 'excuse' => 'seg-excuse'];
?>
<div class="attendance-pointage" data-total="<?= (int) $summary['total'] ?>">

  <form method="get" action="index.php" class="presence-datebar">
    <?php foreach ($dateBarUrl as $k => $v): ?>
      <input type="hidden" name="<?= h((string) $k) ?>" value="<?= h((string) $v) ?>">
    <?php endforeach; ?>
    <label>Date</label>
    <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
    <?php if ($joursHint !== ''): ?><span class="presence-hint">Jours habituels : <?= h($joursHint) ?></span><?php endif; ?>
  </form>

  <div class="attendance-toolbar js-only is-hidden">
    <input type="search" class="attendance-search" aria-label="Rechercher un membre" placeholder="Rechercher un membre…">
    <div class="attendance-filters" role="group" aria-label="Filtrer par statut">
      <button type="button" class="attendance-filter is-active" data-filter="all">Tous</button>
      <button type="button" class="attendance-filter" data-filter="present">Présents</button>
      <button type="button" class="attendance-filter" data-filter="absent">Absents</button>
      <button type="button" class="attendance-filter" data-filter="excuse">Excusés</button>
      <button type="button" class="attendance-filter" data-filter="non_renseigne">Non renseignés</button>
    </div>
    <?php if ($canPointe): ?>
      <div class="attendance-bulk">
        <button type="button" class="btn btn-outline attendance-all-present">✓ Tout présent</button>
        <button type="button" class="btn btn-outline attendance-reset">Réinitialiser</button>
      </div>
    <?php endif; ?>
  </div>

  <div class="attendance-counts">
    <span><b data-count="total"><?= (int) $summary['total'] ?></b> membres</span>
    · <span class="c-present"><b data-count="present"><?= (int) $summary['present'] ?></b> présents</span>
    · <span class="c-absent"><b data-count="absent"><?= (int) $summary['absent'] ?></b> absents</span>
    · <span class="c-excuse"><b data-count="excuse"><?= (int) $summary['excuse'] ?></b> excusés</span>
    · <span class="c-none"><b data-count="non_renseigne"><?= (int) $summary['non_renseigne'] ?></b> non renseignés</span>
  </div>

  <?php
  $renderRows = static function () use ($grid, $statuts, $segClass, $canPointe): void {
      if (!$grid) {
          echo empty_state('fa-users', 'Aucun membre à pointer pour cette unité.');
          return;
      }
      echo '<div class="attendance-list">';
      foreach ($grid as $line) {
          $u = $line['user'];
          $st = (string) $line['statut'];
          $uid = (int) $u['id'];
          $name = full_name($u);
          echo '<div class="attendance-row" data-name="' . h(mb_strtolower($name)) . '" data-status="' . h($st !== '' ? $st : 'non_renseigne') . '">';
          echo '<span class="attendance-name">' . h($name) . '</span>';
          if ($canPointe) {
              echo '<div class="segmented" role="group" aria-label="Statut de ' . h($name) . '">';
              foreach ($statuts as $key => $label) {
                  echo '<label class="' . $segClass[$key] . '"><input type="radio" name="statut[' . $uid . ']" value="' . h((string) $key) . '"'
                      . ($st === (string) $key ? ' checked' : '') . '><span>' . h((string) $label) . '</span></label>';
              }
              echo '<label class="seg-none"><input type="radio" name="statut[' . $uid . ']" value=""'
                  . ($st === '' ? ' checked' : '') . '><span>- Non renseigné</span></label>';
              echo '</div>';
          } else {
              echo '<span>' . presence_badge($st !== '' ? ($statuts[$st] ?? $st) : null) . '</span>';
          }
          echo '</div>';
      }
      echo '</div>';
  };
  ?>

  <?php if ($canPointe): ?>
    <form method="post" action="index.php">
      <input type="hidden" name="action" value="save_presence_occurrence">
      <?= $csrf ?>
      <input type="hidden" name="unit_type" value="<?= h($unitType) ?>">
      <input type="hidden" name="unit_id" value="<?= (int) $unitId ?>">
      <input type="hidden" name="date" value="<?= h($date) ?>">
      <?php $renderRows(); ?>
      <div class="modal-actions">
        <button type="submit" class="btn btn-primary" <?= $grid ? '' : 'disabled' ?>>Enregistrer le pointage</button>
      </div>
    </form>
  <?php else: ?>
    <?php $renderRows(); ?>
  <?php endif; ?>

</div>
