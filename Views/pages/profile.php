<?php /* Fiche profil d'un membre. Variable : $member. */
$bacenta = !empty($member['bacenta_id']) ? get_bacenta((int) $member['bacenta_id']) : null;
$invite = !empty($member['invite_par']) ? get_user((int) $member['invite_par']) : null;
$recu = !empty($member['recu_par']) ? get_user((int) $member['recu_par']) : null;
$rows = [
    ['Nom', $member['nom']],
    ['Prénom', $member['prenom']],
    ['Email', $member['email']],
    ['Téléphone', $member['telephone']],
    ['Quartier (résidence)', $member['quartier']],
    ['Date de naissance', $member['date_naissance']],
    ['Rôle', ROLE_LABELS[$member['role']] ?? $member['role']],
    ['Bacenta', $bacenta['nom'] ?? ''],
    ['Invité par', $invite ? full_name($invite) : ''],
    ['Reçu par (Akwaba)', $recu ? full_name($recu) : ''],
    ["Date d'arrivée", $member['date_recu']],
    ['Membre depuis', date('d/m/Y', strtotime($member['created_at']))],
];
?>
<?= back_button('Retour', url('index.php', ['page' => 'recherche'])) ?>
<?= section_toolbar(h(full_name($member)), 'Fiche profil') ?>

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
    <span class="badge neutral"><?= h(ROLE_LABELS[$member['role']] ?? $member['role']) ?></span>
    <?php if ($bacenta): ?><span class="badge success"><i class="fa-solid fa-church"></i> <?= h($bacenta['nom']) ?></span><?php endif; ?>
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
