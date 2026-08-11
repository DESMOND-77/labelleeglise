<?php
/* Fiche suivi hebdomadaire imprimable — page autonome. Champs réels
 * SUIVI_FIELDS (jamais inventés). Variables : $member, $weekKey,
 * $weekRangeLabel, $week (matrice jour => champ => valeur), $fields, $days,
 * $printedAt. */
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Suivi hebdomadaire — <?= h(full_name($member)) ?> — <?= h(APP_NAME) ?></title>
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

    <h1 class="print-title">Suivi hebdomadaire du berger</h1>
    <p class="print-sub"><?= h(full_name($member)) ?> — <?= h(ROLE_LABELS[$member['role']] ?? $member['role']) ?> — <?= h($weekRangeLabel) ?></p>

    <table class="print-table">
      <thead>
        <tr>
          <th>Champ</th>
          <?php foreach ($days as $d): ?><th><?= h($d) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fields as $f): ?>
          <tr>
            <td><?= h($f['label']) ?></td>
            <?php foreach ($days as $d): ?>
              <?php if (!empty($f['sundayOnly']) && $d !== 'Dimanche'): ?>
                <td>—</td>
              <?php else: ?>
                <td><?= h($week[$d][$f['key']] ?? '') ?: '—' ?></td>
              <?php endif; ?>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="print-footer"><?= h(APP_NAME) ?> — Fiche générée automatiquement, à usage administratif.</div>
  </div>
</body>

</html>
