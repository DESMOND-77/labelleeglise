<?php /* Liste des Rapports du Jour.
   Variables : $rows, $centres, $filterCentre, $filterMois, $isAdmin, $currentUserId. */ ?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['rapports']) ?></h2><div class="sub">Remontées terrain par centre et par date</div></div>
  <a class="btn btn-primary" href="<?= h(url('index.php', ['page' => 'rapport'])) ?>"><i class="fa-solid fa-plus"></i> Nouveau rapport</a>
</div>

<form method="get" action="index.php" class="rapport-filters">
  <input type="hidden" name="page" value="rapports">
  <label>Centre
    <select name="centre" onchange="this.form.submit()">
      <option value="">Tous</option>
      <?php foreach ($centres as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) $filterCentre === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Mois
    <input type="month" name="mois" value="<?= h($filterMois ?? '') ?>" onchange="this.form.submit()">
  </label>
  <?php if ($filterCentre || $filterMois): ?><a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'rapports'])) ?>">Effacer</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Date</th><th>Centre</th><th>Bacenta</th><th>Présents</th><th>Offrande</th><th>Auteur</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7"><?= empty_state('fa-file-lines', 'Aucun rapport pour ces filtres.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h(date('d/m/Y', strtotime((string) $r['date_rapport']))) ?></td>
            <td><?= h($r['centre_nom']) ?></td>
            <td><?= h($r['bacenta_nom'] ?? '—') ?></td>
            <td><?= (int) $r['nb_presents'] ?></td>
            <td><?= h(number_format((float) $r['offrande'], 0, ',', ' ')) ?></td>
            <td><?= h(trim(($r['auteur_prenom'] ?? '') . ' ' . ($r['auteur_nom'] ?? ''))) ?></td>
            <td class="row-actions">
              <a class="icon-btn" title="<?= ($isAdmin || (int) $r['auteur_id'] === (int) $currentUserId) ? 'Modifier' : 'Consulter' ?>" href="<?= h(url('index.php', ['page' => 'rapport', 'id' => $r['id']])) ?>"><i class="fa-solid fa-<?= ($isAdmin || (int) $r['auteur_id'] === (int) $currentUserId) ? 'pen' : 'eye' ?>"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
