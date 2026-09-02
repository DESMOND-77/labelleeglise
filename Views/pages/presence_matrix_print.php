<?php
/* Matrice annuelle imprimable — page autonome (pas de sidebar/topbar).
 * Variables : $unit, $year, $matrix, $statuts, $printedAt. */
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Présences <?= (int) $year ?> — <?= h($unit['nom']) ?> — <?= h(APP_NAME) ?></title>
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

    <h1 class="print-title">Présences <?= (int) $year ?></h1>
    <p class="print-sub"><?= h($unit['nom']) ?></p>

    <?php if (!$matrix['dates']): ?>
      <p>Aucune présence enregistrée pour cette unité en <?= (int) $year ?>.</p>
    <?php else: ?>
    <table class="print-table">
      <thead>
        <tr><th>Membre</th>
          <?php foreach ($matrix['dates'] as $d): ?><th><?= h(date('d/m', strtotime($d))) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($matrix['rows'] as $row): ?>
          <tr><td><?= h(full_name($row['user'])) ?></td>
            <?php foreach ($matrix['dates'] as $d): $s = $row['cells'][$d] ?? ''; ?>
              <td><?= $s ? h(mb_substr($statuts[$s], 0, 1)) : '—' ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p>P = Présent · A = Absent · E = Excusé</p>
    <?php endif; ?>

    <div class="print-footer"><?= h(APP_NAME) ?> — Fiche générée automatiquement, à usage administratif.</div>
  </div>
</body>

</html>
