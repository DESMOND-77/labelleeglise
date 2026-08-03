<?php
/** Carte d'unité (quartier / groupe / item / berger…).
 *  Variables : $icon, $title, $sub, $openUrl, $editUrl, $delUrl, $delMsg. */
$editUrl = $editUrl ?? null;
$delUrl  = $delUrl  ?? null;
$delMsg  = $delMsg  ?? 'Supprimer ?';
$actions = '';
if ($editUrl || $delUrl) {
    $actions = '<div class="card-actions">'
        . ($editUrl ? '<a class="icon-btn" title="Modifier" href="' . h($editUrl) . '">✎</a>' : '')
        . ($delUrl ? '<a class="icon-btn danger" title="Supprimer" data-confirm="' . h($delMsg) . '" href="' . h($delUrl) . '">🗑</a>' : '')
        . '</div>';
}
?>
<div class="unit-card" onclick="location.href='<?= h($openUrl) ?>'">
  <?= $actions ?>
  <div class="icon-wrap"><?= $icon ?></div>
  <h3><?= h($title) ?></h3>
  <p><?= h($sub) ?></p>
</div>
