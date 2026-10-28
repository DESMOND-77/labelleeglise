<?php /* Stats admin du suivi. Variables : $pctWeek, $pctYear. */ ?>
<div class="dash-section-title"><h2><i class="fa-solid fa-chart-column"></i> Indicateurs de réalisation</h2><span>Réservé à l'administrateur</span></div>
<div class="stats-grid">
  <?= stat_card('Réalisation - semaine sélectionnée', $pctWeek . ' %', '#2B3A67') ?>
  <?= stat_card('Cumul annuel ' . current_year(), $pctYear . ' %', '#2F7F79') ?>
</div>
<div class="chart-card"><canvas id="suiviChart"></canvas></div>
