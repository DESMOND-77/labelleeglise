<?php
/* Liste des inscriptions en attente. Variable : $registrations (array). */
$registrations = $registrations ?? [];

$rows = '';
foreach ($registrations as $r) {
    $verified = (int) $r['email_verified'] === 1;
    $statusBadge = $verified
        ? '<span class="badge success"><i class="fa-solid fa-check"></i> Vérifié</span>'
        : '<span class="badge neutral"><i class="fa-solid fa-hourglass-half"></i> En attente</span>';
    $created = !empty($r['created_at']) ? date('d/m/Y à H:i', strtotime($r['created_at'])) : '—';
    $rows .= '<tr>'
        . '<td>' . h(full_name($r)) . '</td>'
        . '<td>' . h($r['email']) . '</td>'
        . '<td>' . h($r['telephone'] ?? '') . '</td>'
        . '<td>' . $statusBadge . '</td>'
        . '<td>' . h($created) . '</td>'
        . '<td><span class="badge neutral">' . h(ACCOUNT_STATUS_LABELS[$r['account_status']] ?? $r['account_status']) . '</span></td>'
        . '<td class="row-actions"><a class="btn btn-outline btn-sm" href="' . h(url('index.php', ['page' => 'admin_inscription', 'id' => $r['id']])) . '">Consulter</a></td>'
        . '</tr>';
}
$rows = $rows ?: '<tr><td colspan="7">' . empty_state('fa-inbox', 'Aucune inscription en attente pour le moment.') . '</td></tr>';
?>
<?= section_toolbar('Inscriptions en attente', count($registrations) . ' demande(s) à traiter') ?>
<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Nom</th>
        <th>Email</th>
        <th>Téléphone</th>
        <th>Email vérifié</th>
        <th>Date</th>
        <th>Statut</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody><?= $rows ?></tbody>
  </table>
</div>
