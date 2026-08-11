<?php
/* Fiche détaillée d'une inscription. Variables : $registration, $csrf. */
$r = $registration;
$verified = (int) $r['email_verified'] === 1;
$created = !empty($r['created_at']) ? date('d/m/Y à H:i', strtotime($r['created_at'])) : '—';
$verifiedAt = !empty($r['email_verified_at']) ? date('d/m/Y à H:i', strtotime($r['email_verified_at'])) : null;
?>
<?= back_button('Retour aux inscriptions', url('index.php', ['page' => 'admin_inscriptions'])) ?>
<?= section_toolbar('Demande de ' . h(full_name($r)), h($r['email'])) ?>

<div class="form-page">
  <div class="form-card">
    <div class="info-rows">
      <div class="info-row"><span>Nom complet</span><b><?= h(full_name($r)) ?></b></div>
      <div class="info-row"><span>Email</span><b><?= h($r['email']) ?></b></div>
      <div class="info-row"><span>Téléphone</span><b><?= h($r['telephone'] ?? '—') ?></b></div>
      <div class="info-row"><span>Rôle</span><b><?= h(ROLE_LABELS[$r['role']] ?? $r['role']) ?></b></div>
      <div class="info-row"><span>Date d'inscription</span><b><?= h($created) ?></b></div>
      <div class="info-row">
        <span>Statut email</span>
        <b>
          <?php if ($verified): ?>
            <span class="badge success"><i class="fa-solid fa-check"></i> Vérifié<?= $verifiedAt ? (' le ' . h($verifiedAt)) : '' ?></span>
          <?php else: ?>
            <span class="badge neutral"><i class="fa-solid fa-hourglass-half"></i> Non vérifié</span>
          <?php endif; ?>
        </b>
      </div>
      <div class="info-row">
        <span>Statut du compte</span>
        <b><span class="badge neutral"><?= h(ACCOUNT_STATUS_LABELS[$r['account_status']] ?? $r['account_status']) ?></span></b>
      </div>
    </div>

    <?php if ($r['account_status'] === 'pending'): ?>
      <div class="modal-actions">
        <form method="post" action="index.php">
          <input type="hidden" name="action" value="admin_reject_account">
          <?= $csrf ?>
          <input type="hidden" name="id" value="<?= h($r['id']) ?>">
          <button type="submit" class="btn btn-outline btn-danger" data-confirm="Refuser cette inscription et supprimer le compte ?">Refuser</button>
        </form>
        <form method="post" action="index.php">
          <input type="hidden" name="action" value="admin_activate_account">
          <?= $csrf ?>
          <input type="hidden" name="id" value="<?= h($r['id']) ?>">
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-circle-check"></i> Activer le compte</button>
        </form>
      </div>
      <?php if (!$verified): ?>
        <div class="alert alert-warning" style="margin-top:var(--space-4)">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span>L'adresse email de ce membre n'est pas encore vérifiée. Vous pouvez tout de même activer le compte si nécessaire, mais il est recommandé d'attendre la vérification.</span>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert alert-success" style="margin-top:var(--space-4)">
        <i class="fa-solid fa-circle-check"></i>
        <span>Ce compte est déjà <?= h(mb_strtolower(ACCOUNT_STATUS_LABELS[$r['account_status']] ?? $r['account_status'])) ?>.</span>
      </div>
    <?php endif; ?>
  </div>
</div>
