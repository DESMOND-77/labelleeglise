<?php
/* Agenda unifié : grille mensuelle (rendu serveur) + panneau détail du jour.
   Variables : $canManage, $ym, $year, $monthNo, $prevYm, $nextYm, $date, $today,
               $month (['ym','byDate','counts']), $dayDetail (['events','birthdays']),
               $weeks (6×7 de ['date','day','adjacent']), $responsables, $monthsFr,
               $errors, $old, $csrf. */

$dowLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

/** Libellé « Mardi 15 septembre 2026 » pour un Y-m-d. */
$prettyDate = static function (string $d) use ($monthsFr): string {
    $ts = strtotime($d);
    return WEEK_DAYS[(int) date('N', $ts) - 1] . ' ' . (int) date('j', $ts)
        . ' ' . mb_strtolower($monthsFr[(int) date('n', $ts) - 1]) . ' ' . date('Y', $ts);
};

/** Rendu HTML d'un panneau détail (réutilisé au chargement ; le JS le réécrit au clic). */
$renderPanel = static function (array $detail) {
    $events = $detail['events'] ?? [];
    $birthdays = $detail['birthdays'] ?? [];
    ob_start(); ?>
    <section class="agenda-detail-block">
      <h4 class="agenda-detail-title">Événements</h4>
      <?php if (!$events): ?>
        <p class="agenda-empty">Aucun événement.</p>
      <?php else: ?>
        <ul class="agenda-detail-list">
          <?php foreach ($events as $e): ?>
            <li>
              <a href="<?= h(url('index.php', ['page' => 'calendrier', 'evt' => (int) $e['id']])) ?>">
                <?php if (!empty($e['heure_debut'])): ?><span class="agenda-time"><?= h($e['heure_debut']) ?><?= !empty($e['heure_fin']) ? '–' . h($e['heure_fin']) : '' ?></span> <?php endif; ?>
                <?= h((string) $e['nom']) ?>
              </a>
              <?php if (!empty($e['lieu'])): ?><span class="agenda-place"><?= h((string) $e['lieu']) ?></span><?php endif; ?>
              <?php if (!empty($e['is_multi_day'])): ?><span class="agenda-badge">plusieurs jours</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <section class="agenda-detail-block">
      <h4 class="agenda-detail-title">Anniversaires</h4>
      <?php if (!$birthdays): ?>
        <p class="agenda-empty">Aucun anniversaire.</p>
      <?php else: ?>
        <ul class="agenda-detail-list">
          <?php foreach ($birthdays as $b): ?>
            <li>🎂 <?= h((string) $b['nom']) ?><?php if (isset($b['age']) && $b['age'] !== null): ?> - <?= (int) $b['age'] ?> ans<?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php if (!$events && !$birthdays): ?>
      <p class="agenda-empty agenda-empty-global">Aucune activité programmée pour cette journée.</p>
    <?php endif; ?>
    <?php return ob_get_clean();
};
?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['agenda']) ?></h2><div class="sub">Événements et anniversaires du mois</div></div>
  <form method="get" action="index.php" class="agenda-gotobar">
    <input type="hidden" name="page" value="agenda">
    <label for="agenda-goto">Aller à une date</label>
    <input type="date" id="agenda-goto" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
    <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'agenda'])) ?>">Aujourd'hui</a>
  </form>
</div>

<div class="agenda-layout">
  <div class="agenda-cal">
    <div class="agenda-cal-head">
      <a class="agenda-nav" aria-label="Mois précédent" href="<?= h(url('index.php', ['page' => 'agenda', 'ym' => $prevYm])) ?>">‹</a>
      <h3 class="agenda-month"><?= h($monthsFr[$monthNo - 1]) ?> <?= (int) $year ?></h3>
      <a class="agenda-nav" aria-label="Mois suivant" href="<?= h(url('index.php', ['page' => 'agenda', 'ym' => $nextYm])) ?>">›</a>
    </div>

    <div class="agenda-grid" role="grid">
      <?php foreach ($dowLabels as $lbl): ?>
        <div class="agenda-dow" role="columnheader"><?= h($lbl) ?></div>
      <?php endforeach; ?>
      <?php foreach ($weeks as $week): ?>
        <?php foreach ($week as $cell): $d = $cell['date']; ?>
          <?php
            $c = $month['counts'][$d] ?? ['events' => 0, 'birthdays' => 0];
            $cls = 'agenda-day';
            if ($d === $today) {
                $cls .= ' is-today';
            }
            if ($d === $date) {
                $cls .= ' is-selected';
            }
            if ($cell['adjacent']) {
                $cls .= ' is-adjacent';
            }
          ?>
          <a class="<?= $cls ?>" role="gridcell" href="<?= h(url('index.php', ['page' => 'agenda', 'ym' => $ym, 'date' => $d])) ?>"
             data-date="<?= h($d) ?>" aria-label="<?= h($prettyDate($d)) ?>"<?= $d === $date ? ' aria-current="date"' : '' ?>>
            <span class="agenda-num"><?= (int) $cell['day'] ?></span>
            <span class="agenda-dots">
              <?php if (($c['events'] ?? 0) > 0): ?><span class="dot dot-event" title="Événement"></span><?php endif; ?>
              <?php if (($c['birthdays'] ?? 0) > 0): ?><span class="dot dot-birthday" title="Anniversaire"></span><?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>

    <p class="agenda-legend">
      <span class="dot dot-event"></span> Événement
      <span class="dot dot-birthday"></span> Anniversaire
    </p>
  </div>

  <div class="agenda-side">
    <h3 class="agenda-detail-day" id="agenda-day-title"><?= h($prettyDate($date)) ?></h3>
    <div id="agenda-day"><?= $renderPanel($dayDetail) ?></div>
  </div>
</div>

<?php if ($canManage): ?>
  <div class="agenda-forms">
    <?php $ev = []; $val = static fn($k) => h($old[$k] ?? ($ev[$k] ?? '')); ?>
    <form method="post" action="index.php" class="form-card cal-form">
      <input type="hidden" name="action" value="save_evenement">
      <?= $csrf ?>
      <h3>Nouvel événement</h3>
      <div class="form-group">
        <label>Nom de l'événement</label>
        <input type="text" name="nom" value="<?= $val('nom') ?>" required>
        <?php if (!empty($errors['nom'])): ?><span class="form-error"><?= h($errors['nom']) ?></span><?php endif; ?>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Début</label>
          <input type="datetime-local" name="date_debut" value="<?= h($old['date_debut'] ?? '') ?>" required>
          <?php if (!empty($errors['date_debut'])): ?><span class="form-error"><?= h($errors['date_debut']) ?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Fin (facultatif)</label>
          <input type="datetime-local" name="date_fin" value="<?= h($old['date_fin'] ?? '') ?>">
          <?php if (!empty($errors['date_fin'])): ?><span class="form-error"><?= h($errors['date_fin']) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group"><label>Lieu</label><input type="text" name="lieu" value="<?= $val('lieu') ?>"></div>
        <div class="form-group">
          <label>Responsable</label>
          <select name="responsable_id">
            <option value="">-</option>
            <?php foreach ($responsables as $r): ?>
              <option value="<?= (int) $r['id'] ?>" <?= (int) ($old['responsable_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= h(trim($r['prenom'] . ' ' . $r['nom'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-actions"><button type="submit" class="btn btn-primary">Ajouter l'événement</button></div>
    </form>

    <form method="post" action="index.php" class="form-card cal-form">
      <input type="hidden" name="action" value="save_anniversaire">
      <?= $csrf ?>
      <h3>Nouvel anniversaire</h3>
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
            <option value="">-</option>
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
  </div>
<?php endif; ?>

<script type="application/json" id="agenda-data"><?= json_encode(
    $month['byDate'],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
