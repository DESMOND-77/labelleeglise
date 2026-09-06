<?php
/* Fiche présences imprimable — page autonome (pas de sidebar/topbar).
 * Variables : $member, $rows (list<{date_presence,statut,activity_type,activity_nom}>),
 *             $periodLabel, $from, $to, $type, $statut, $printedAt. */
$activityLabels = [
    'culte'     => 'Culte',
    'evenement' => 'Événement',
    'bacenta'   => 'Bacenta',
    'basonta'   => 'Basonta',
    'centre'    => 'Centre',
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fiche présences — <?= h(full_name($member)) ?> — <?= h(APP_NAME) ?></title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="assets/css/print.css">
  <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
</head>

<body>
  <div class="print-page">
    <div class="print-toolbar no-print">
      <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
      <button class="btn btn-outline" onclick="window.close()">Fermer</button>
    </div>

    <form method="get" action="index.php" class="print-toolbar no-print">
      <input type="hidden" name="page" value="attendancePrint">
      <input type="hidden" name="membre" value="<?= (int) $member['id'] ?>">
      <label>Du <input type="date" name="from" value="<?= h($from ?? '') ?>"></label>
      <label>au <input type="date" name="to" value="<?= h($to ?? '') ?>"></label>
      <label>Type
        <select name="type">
          <option value="">— Tous —</option>
          <?php foreach ($activityLabels as $k => $lbl): ?>
            <option value="<?= h($k) ?>" <?= ($type ?? '') === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Statut
        <select name="statut">
          <option value="">— Tous —</option>
          <?php foreach (PRESENCE_STATUTS as $k => $lbl): ?>
            <option value="<?= h($k) ?>" <?= ($statut ?? '') === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="btn btn-outline"><i class="fa-solid fa-filter"></i> Filtrer</button>
    </form>

    <div class="print-header">
      <div class="brand">⛪ <?= h(APP_NAME) ?></div>
      <div class="meta">Édité le <?= h($printedAt) ?></div>
    </div>

    <h1 class="print-title">Fiche de présences</h1>
    <p class="print-sub"><?= h(full_name($member)) ?> — <?= h(ROLE_LABELS[$member['role']] ?? $member['role']) ?> — <?= h($periodLabel) ?></p>

    <table class="print-table">
      <thead>
        <tr><th>Date</th><th>Semaine</th><th>Activité</th><th>Statut</th></tr>
      </thead>
      <tbody>
        <?php if ($rows): ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $ts = strtotime((string) $r['date_presence']);
              $typeLbl = $activityLabels[$r['activity_type']] ?? $r['activity_type'];
              $statutLbl = PRESENCE_STATUTS[$r['statut']] ?? ($r['statut'] ?: '—');
            ?>
            <tr>
              <td><?= h(date('d/m/Y', $ts)) ?></td>
              <td><?= h(date('o-\WW', $ts)) ?></td>
              <td><?= h(trim($typeLbl . ' — ' . ($r['activity_nom'] ?? ''), ' —')) ?></td>
              <td><?= presence_badge($statutLbl) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4">Aucune présence enregistrée pour ces critères.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="print-footer"><?= h(APP_NAME) ?> — Fiche générée automatiquement, à usage administratif.</div>
  </div>
</body>

</html>
