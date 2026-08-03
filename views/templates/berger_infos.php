<?php /* Infos d'un berger. Variable : $member (table users). */
$bacenta = !empty($member['bacenta_id']) ? get_bacenta((int) $member['bacenta_id']) : null;
$rows = [
    ['Nom', $member['nom']],
    ['Prénom', $member['prenom']],
    ['Email', $member['email']],
    ['Téléphone', $member['telephone']],
    ['Quartier (résidence)', $member['quartier']],
    ['Date de naissance', $member['date_naissance']],
    ['Rôle', ROLE_LABELS[$member['role']] ?? $member['role']],
    ['Bacenta', $bacenta['nom'] ?? ''],
];
?>
<div class="fiche-card">
  <?= info_rows_html($rows) ?>
  <div class="modal-actions" style="justify-content:flex-start;margin-top:18px;">
    <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'bergers', 'form' => 'membre', 'id' => $member['id'], 'retour' => 'fiche'])) ?>">✎ Modifier les informations</a>
  </div>
</div>
