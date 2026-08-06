<?php /* Accueil : carrousel + stats + graphiques + synthèse.
           Variables : $slides, $poles, $counts, $year, $offrandes, $stats, $narrative. */
$trendMeta = [
    'up'   => ['icon' => '<i class="fa-solid fa-arrow-trend-up"></i> Hausse', 'cls' => 'up'],
    'down' => ['icon' => '<i class="fa-solid fa-arrow-trend-down"></i> Baisse', 'cls' => 'down'],
    'flat' => ['icon' => '<i class="fa-solid fa-arrow-right"></i> Stable', 'cls' => 'flat'],
];
$fmt = fn($n) => ($n >= 0 ? '+' : '') . $n;
?>
<div class="welcome-banner">
  <div>
    <h2>Bonjour <?= h(trim($user['prenom'] ?? 'Administrateur')) ?> 👋</h2>
    <p>Voici un aperçu de l'activité de l'église pour l'année <?= h($year) ?>.</p>
  </div>
  <div class="welcome-badge">
    <i class="fa-solid fa-sack-dollar"></i>
    <div>
      <span>Offrandes cumulées</span>
      <strong><?= format_fcfa($offrandes) ?></strong>
    </div>
  </div>
</div>

<div class="carousel">
  <div class="carousel-track" id="carouselTrack">
    <?php foreach ($slides as $s): ?>
      <div class="slide" style="background:<?= h($s['gradient']) ?>">
        <h2><?= h($s['title']) ?></h2>
        <p><?= h($s['subtitle']) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <button class="carousel-arrow prev" id="carouselPrev"><i class="fa-solid fa-chevron-left"></i></button>
  <button class="carousel-arrow next" id="carouselNext"><i class="fa-solid fa-chevron-right"></i></button>
  <div class="carousel-dots" id="carouselDots">
    <?php foreach ($slides as $i => $s): ?>
      <span data-dot="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>"></span>
    <?php endforeach; ?>
  </div>
</div>

<div class="section-toolbar">
  <div>
    <h2>Indicateurs clés</h2>
    <div class="sub">Répartition des membres par pôle</div>
  </div>
</div>
<div class="stats-grid">
  <?php foreach ($poles as $i => $p): ?>
    <?= stat_card($p['label'], (string) $counts[$i], $p['color'], 'membres enregistrés') ?>
  <?php endforeach; ?>
</div>

<div class="dash-section-title"><h2>Évolution mensuelle par indicateur</h2><span>Cumul sur les 6 derniers mois</span></div>
<div class="mini-charts-grid">
  <?php foreach ($poles as $p): ?>
    <div class="mini-chart-card">
      <div class="mini-chart-head"><h4><?= h($p['label']) ?></h4><span class="dot" style="background:<?= h($p['color']) ?>"></span></div>
      <canvas id="chart-<?= h($p['key']) ?>"></canvas>
    </div>
  <?php endforeach; ?>
</div>

<div class="dash-section-title"><h2>Comparaison des indicateurs</h2><span>Effectifs actuels, tous pôles confondus</span></div>
<div class="chart-card"><canvas id="barChart"></canvas></div>

<div class="dash-section-title"><h2>Résumé statistique</h2><span>Variation, moyenne mensuelle et tendance par pôle</span></div>
<div class="summary-grid">
  <?php foreach ($stats as $s): ?>
    <?php $tm = $trendMeta[$s['trend']]; ?>
    <div class="summary-card" style="border-left-color:<?= h($s['color']) ?>">
      <h4><?= h($s['label']) ?></h4>
      <div class="summary-row"><span>Total actuel</span><b><?= (int) $s['current'] ?></b></div>
      <div class="summary-row"><span>Variation (dernier mois)</span><b><?= $fmt($s['variation']) ?></b></div>
      <div class="summary-row"><span>Moyenne mensuelle</span><b><?= $fmt(round($s['average'], 1)) ?></b></div>
      <div class="summary-row"><span>Tendance</span><span class="trend-pill <?= $tm['cls'] ?>"><?= $tm['icon'] ?></span></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="narrative-card">
  <h3>Synthèse des tendances</h3>
  <ul>
    <?php foreach ($narrative as $line): ?>
      <li><?= $line ?></li>
    <?php endforeach; ?>
  </ul>
</div>
