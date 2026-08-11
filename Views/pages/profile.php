<?php
/* Fiche administrative d'un membre. Variables :
 * $member, $bacenta, $invite, $recu, $responsibilities, $accountStatusLabel,
 * $lastLoginLabel, $weekKey, $weekRangeLabel, $prevWeekKey, $nextWeekKey,
 * $weekRows, $stats, $hasWeeklyFollowup, $suiviWeek, $suiviFields, $weekDays,
 * $isSelf, $csrf. */
$rows = [
    ['Nom', $member['nom']],
    ['Prénom', $member['prenom']],
    ['Email', $member['email']],
    ['Téléphone', $member['telephone']],
    ['Quartier (résidence)', $member['quartier']],
    ['Date de naissance', $member['date_naissance']],
    ['Bacenta', $bacenta['nom'] ?? ''],
    ['Invité par', $invite ? full_name($invite) : ''],
    ['Reçu par (Akwaba)', $recu ? full_name($recu) : ''],
    ["Date d'arrivée", $member['date_recu']],
    ['Membre depuis', date('d/m/Y', strtotime($member['created_at']))],
    ['Dernière connexion', $lastLoginLabel],
];
?>
<?= back_button('Retour', url('index.php', ['page' => 'recherche'])) ?>
<?= section_toolbar(h(full_name($member)), 'Fiche administrative') ?>

<div class="profile-hero">
  <div class="profile-avatar">
    <?php if (!empty($member['photo_de_profil'])): ?>
      <img src="<?= h($member['photo_de_profil']) ?>" alt="<?= h(full_name($member)) ?>">
    <?php else: ?>
      <span><?= h(strtoupper(first_char(trim($member['prenom'] ?: $member['nom'] ?: '?')))) ?></span>
    <?php endif; ?>
  </div>
  <div class="profile-hero-info">
    <h3><?= h(full_name($member)) ?></h3>
    <!-- Rôle : distinct visuellement des responsabilités (spec §32-33). -->
    <span class="badge neutral"><i class="fa-solid fa-user-tag"></i> Rôle : <?= h(ROLE_LABELS[$member['role']] ?? $member['role']) ?></span>
    <?php if ($bacenta): ?><span class="badge success"><i class="fa-solid fa-church"></i> <?= h($bacenta['nom']) ?></span><?php endif; ?>
    <span class="badge <?= h(($member['account_status'] ?? 'active') === 'active' ? 'present' : (($member['account_status'] ?? '') === 'pending' ? 'neutral' : 'absent')) ?>">
      <i class="fa-solid fa-circle-info"></i> Statut du compte : <?= h($accountStatusLabel) ?>
    </span>
    <?php if ($isSelf): ?><a class="btn btn-outline btn-sm" href="<?= h(url('index.php', ['page' => 'profile'])) ?>" style="margin-top:8px;"><i class="fa-solid fa-user-pen"></i> Modifier mon profil</a><?php endif; ?>
  </div>
</div>

<div class="profile-grid">
  <div class="fiche-card">
    <?= info_rows_html($rows) ?>
  </div>
  <div class="presence-card">
    <div class="dash-section-title" style="margin-top:0;"><h2><i class="fa-solid fa-chart-column"></i> Présence</h2><span>Derniers événements</span></div>
    <div class="chart-card" style="margin-bottom:0;"><canvas id="profileChart"></canvas></div>
  </div>
</div>

<!-- Responsabilités — distinctes du rôle (spec §32-33) -->
<div class="dash-section-title"><h2><i class="fa-solid fa-id-card"></i> Responsabilités</h2><span>Indépendantes du rôle</span></div>
<?php if ($responsibilities): ?>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>Type</th><th>Structure</th></tr></thead><tbody>
    <?php foreach ($responsibilities as $r): ?>
      <tr><td><?= h($r['type']) ?></td><td><?= h($r['label']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
<?php else: ?>
  <?= empty_state('fa-inbox', 'Aucune responsabilité.') ?>
<?php endif; ?>

<!-- Présences — consultation par semaine, impression, export CSV (spec §21-27) -->
<div class="dash-section-title"><h2><i class="fa-solid fa-calendar-check"></i> Présences</h2><span><?= h($weekRangeLabel) ?></span></div>
<div class="section-toolbar">
  <div class="week-nav" style="display:flex;gap:8px;align-items:center;">
    <a class="btn btn-outline btn-sm" href="<?= h(url('index.php', ['page' => 'personProfile', 'membre' => $member['id'], 'semaine' => $prevWeekKey])) ?>"><i class="fa-solid fa-chevron-left"></i> Semaine préc.</a>
    <a class="btn btn-outline btn-sm" href="<?= h(url('index.php', ['page' => 'personProfile', 'membre' => $member['id'], 'semaine' => $nextWeekKey])) ?>">Semaine suiv. <i class="fa-solid fa-chevron-right"></i></a>
  </div>
  <div style="display:flex;gap:8px;">
    <a class="btn btn-outline btn-sm" target="_blank" href="<?= h(url('index.php', ['page' => 'attendancePrint', 'membre' => $member['id'], 'semaine' => $weekKey])) ?>"><i class="fa-solid fa-print"></i> Imprimer</a>
    <a class="btn btn-outline btn-sm" href="<?= h(url('index.php', ['action' => 'export_attendance', 'membre' => $member['id']])) ?>"><i class="fa-solid fa-file-csv"></i> Exporter CSV</a>
  </div>
</div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Culte</th><th>Centre</th><th>Bacenta</th></tr></thead><tbody>
  <?php if ($weekRows): ?>
    <?php foreach ($weekRows as $r): ?>
      <tr>
        <td><?= h(date('d/m/Y', strtotime((string) $r['date_presence']))) ?></td>
        <td><?= h($r['culte_nom'] ?? '') ?></td>
        <td><?= h($r['centre_nom'] ?? '') ?></td>
        <td><?= h($r['bacenta_nom'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
    <tr><td colspan="4"><?= empty_state('fa-inbox', 'Aucune présence enregistrée cette semaine.') ?></td></tr>
  <?php endif; ?>
</tbody></table></div>
<div class="section-toolbar">
  <div class="sub">
    Total des présences enregistrées : <b><?= (int) $stats['total'] ?></b>
    <?php if ($stats['last_date']): ?> · Dernière présence : <b><?= h(date('d/m/Y', strtotime((string) $stats['last_date']))) ?></b><?php endif; ?>
  </div>
</div>

<?php if ($hasWeeklyFollowup): ?>
<!-- Suivi hebdomadaire du berger — champs réels SUIVI_FIELDS (spec §34-38), jamais inventés -->
<div class="dash-section-title"><h2><i class="fa-solid fa-calendar-days"></i> Suivi hebdomadaire</h2><span><?= h($weekRangeLabel) ?></span></div>
<div class="section-toolbar">
  <div class="sub">Consulter/imprimer le suivi hebdomadaire (mêmes champs que le module existant).</div>
  <div style="display:flex;gap:8px;">
    <a class="btn btn-outline btn-sm" href="<?= h(url('index.php', ['page' => 'suiviBergers', 'membre' => $member['id'], 'semaine' => $weekKey])) ?>"><i class="fa-solid fa-pen"></i> Ouvrir le suivi</a>
    <a class="btn btn-outline btn-sm" target="_blank" href="<?= h(url('index.php', ['page' => 'suiviPrint', 'membre' => $member['id'], 'semaine' => $weekKey])) ?>"><i class="fa-solid fa-print"></i> Imprimer</a>
  </div>
</div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Champ</th><?php foreach ($weekDays as $d): ?><th><?= h($d) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach ($suiviFields as $f): ?>
    <tr>
      <td><?= h($f['label']) ?></td>
      <?php foreach ($weekDays as $d): ?>
        <?php if (!empty($f['sundayOnly']) && $d !== 'Dimanche'): ?>
          <td>—</td>
        <?php else: ?>
          <td><?= h($suiviWeek[$d][$f['key']] ?? '') ?: '—' ?></td>
        <?php endif; ?>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
