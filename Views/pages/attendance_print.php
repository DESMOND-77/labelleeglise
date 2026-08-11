<?php
/* Fiche présences imprimable — page autonome (pas de sidebar/topbar).
 * Variables : $member, $rows, $periodLabel, $printedAt. */
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>
  <div class="print-page">
    <div class="print-toolbar no-print">
      <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
      <button class="btn btn-outline" onclick="window.close()">Fermer</button>
    </div>

    <div class="print-header">
      <div class="brand">⛪ <?= h(APP_NAME) ?></div>
      <div class="meta">Édité le <?= h($printedAt) ?></div>
    </div>

    <h1 class="print-title">Fiche de présences</h1>
    <p class="print-sub"><?= h(full_name($member)) ?> — <?= h(ROLE_LABELS[$member['role']] ?? $member['role']) ?> — <?= h($periodLabel) ?></p>

    <table class="print-table">
      <thead>
        <tr><th>Date</th><th>Semaine</th><th>Culte</th><th>Centre</th><th>Bacenta</th><th>Statut</th></tr>
      </thead>
      <tbody>
        <?php if ($rows): ?>
          <?php foreach ($rows as $r): ?>
            <?php $ts = strtotime((string) $r['date_presence']); ?>
            <tr>
              <td><?= h(date('d/m/Y', $ts)) ?></td>
              <td><?= h(date('o-\WW', $ts)) ?></td>
              <td><?= h($r['culte_nom'] ?? '') ?></td>
              <td><?= h($r['centre_nom'] ?? '') ?></td>
              <td><?= h($r['bacenta_nom'] ?? '') ?></td>
              <td>Présent</td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6">Aucune présence enregistrée sur cette période.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="print-footer"><?= h(APP_NAME) ?> — Fiche générée automatiquement, à usage administratif.</div>
  </div>
</body>

</html>
