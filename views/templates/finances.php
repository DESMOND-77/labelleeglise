<?php /* Finances. Variables : $year, $monthKey, $monthLabel, $bacentasTotal, $centresTotal, $globalTotal, $bacentasRows, $centresRows. */ ?>
<div class="stats-grid">
  <?= stat_card('Total Bacentas (' . $year . ')', format_fcfa($bacentasTotal), '#6C63FF') ?>
  <?= stat_card('Total Centres (' . $year . ')', format_fcfa($centresTotal), '#8B85FF') ?>
  <?= stat_card('Cumul global (' . $year . ')', format_fcfa($globalTotal), '#4CAF8E') ?>
</div>

<div class="dash-section-title"><h2>Bacentas — Offrandes des vendredis</h2><span>Mois en cours : <?= h($monthLabel) ?></span></div>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Bacenta</th><th>Total semaine (mois en cours)</th><th>Total du mois</th><th>Total annuel <?= $year ?></th></tr></thead>
    <tbody><?= $bacentasRows ?: '<tr><td colspan="4">' . empty_state('📭', 'Aucune donnée.') . '</td></tr>' ?></tbody>
  </table>
</div>

<div class="dash-section-title"><h2>Centres — Offrandes des mercredis</h2><span>Mois en cours : <?= h($monthLabel) ?></span></div>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Centre</th><th>Total semaine (mois en cours)</th><th>Total du mois</th><th>Total annuel <?= $year ?></th></tr></thead>
    <tbody><?= $centresRows ?: '<tr><td colspan="4">' . empty_state('📭', 'Aucune donnée.') . '</td></tr>' ?></tbody>
  </table>
</div>

<div class="dash-section-title"><h2>Comparatif</h2><span>Bacentas vs Centres — cumul <?= $year ?></span></div>
<div class="chart-card"><canvas id="financeChart"></canvas></div>
