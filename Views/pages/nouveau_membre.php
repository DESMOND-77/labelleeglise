<?php /* Dossier d'un nouveau membre.
   Variables : $nm, $isAdmin, $responsables, $csrf. */
$statutBadge = ['nouveau' => 'neutral', 'en_suivi' => 'warning', 'pret_integration' => 'warning', 'integre' => 'success'][$nm['statut']] ?? 'neutral';
$nb = (int) ($nm['nb_presences'] ?? 0);
$presenceBadgeCls = $nb >= 3 ? 'success' : ($nb > 0 ? 'warning' : 'neutral');
$fullName = trim(($nm['prenom'] ?? '') . ' ' . ($nm['nom'] ?? ''));
?>
<?= back_button('Retour', url('index.php', ['page' => 'nouveaux_membres'])) ?>
<?= section_toolbar(h($fullName), 'Dossier - suivi nouveau membre') ?>

<div class="profile-hero">
  <div class="profile-avatar"><span><?= h(strtoupper(first_char($fullName ?: '?'))) ?></span></div>
  <div class="profile-hero-info">
    <h3><?= h($fullName) ?></h3>
    <span class="badge <?= h($statutBadge) ?>"><i class="fa-solid fa-circle-info"></i> <?= h(NOUVEAU_MEMBRE_STATUT_LABELS[$nm['statut']] ?? $nm['statut']) ?></span>
    <span class="badge <?= h($presenceBadgeCls) ?>"><i class="fa-solid fa-calendar-check"></i> <?= $nb ?>/3 présences</span>
    <?php if ($nm['bacenta_nom']): ?><span class="badge neutral"><i class="fa-solid fa-church"></i> <?= h($nm['bacenta_nom']) ?></span><?php endif; ?>
  </div>
</div>

<div class="profile-grid">
  <div class="fiche-card">
    <?= info_rows_html([
        ['Sexe', $nm['sexe'] === 'F' ? 'Féminin' : ($nm['sexe'] === 'M' ? 'Masculin' : '')],
        ['Date de naissance', $nm['date_naissance'] ? date('d/m/Y', strtotime((string) $nm['date_naissance'])) : ''],
        ['Téléphone', $nm['telephone']],
        ['Quartier (résidence)', $nm['quartier']],
        ['Date de première visite', $nm['date_premiere_visite'] ? date('d/m/Y', strtotime((string) $nm['date_premiere_visite'])) : ''],
        ['Invité par', $nm['invite_par_nom'] ? trim($nm['invite_par_prenom'] . ' ' . $nm['invite_par_nom']) : ''],
        ['Reçu par (Akwaba)', $nm['recu_par_nom'] ? trim($nm['recu_par_prenom'] . ' ' . $nm['recu_par_nom']) : ''],
        ['Observations', $nm['observations']],
        ['Enregistré le', date('d/m/Y', strtotime((string) $nm['created_at']))],
        ['Intégré le', $nm['date_integration'] ? date('d/m/Y H:i', strtotime((string) $nm['date_integration'])) : ''],
    ]) ?>
  </div>

  <div class="presence-card" id="attribution">
    <div class="dash-section-title" style="margin-top:0;"><h2><i class="fa-solid fa-user-tag"></i> Responsable de suivi</h2></div>
    <?php if ($nm['resp_nom']): ?>
      <p>Actuellement suivi par <b><?= h(trim($nm['resp_prenom'] . ' ' . $nm['resp_nom'])) ?></b> (<?= h(ROLE_LABELS[$nm['resp_role']] ?? $nm['resp_role']) ?>).</p>
    <?php else: ?>
      <p class="text-muted">Aucun responsable attribué pour le moment.</p>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <form method="post" action="index.php" class="form-inline">
      <?= $csrf ?>
      <input type="hidden" name="action" value="assign_nouveau_membre">
      <input type="hidden" name="nouveau_membre_id" value="<?= (int) $nm['id'] ?>">
      <div class="form-group">
        <label>Attribuer / réattribuer à</label>
        <select name="responsable_id">
          <option value="">- Aucun -</option>
          <?php foreach ($responsables as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= (int) $nm['responsable_id'] === (int) $r['id'] ? 'selected' : '' ?>>
              <?= h(trim($r['prenom'] . ' ' . $r['nom'])) ?> - <?= h(ROLE_LABELS[$r['role']] ?? $r['role']) ?><?= $r['bacenta_nom'] ? ' (' . h($r['bacenta_nom']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Enregistrer l'attribution</button>
    </form>
    <?php endif; ?>

    <?php if ($nm['attributions']): ?>
      <div class="table-wrap" style="margin-top:var(--space-4);">
        <table class="data-table"><thead><tr><th>Attribué à</th><th>Par</th><th>Date</th></tr></thead><tbody>
          <?php foreach ($nm['attributions'] as $a): ?>
            <tr>
              <td><?= $a['resp_nom'] ? h(trim($a['resp_prenom'] . ' ' . $a['resp_nom'])) : '<span class="text-muted">Retiré</span>' ?></td>
              <td><?= h(trim($a['par_prenom'] . ' ' . $a['par_nom'])) ?></td>
              <td><?= h(date('d/m/Y H:i', strtotime((string) $a['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="dash-section-title"><h2><i class="fa-solid fa-calendar-check"></i> Présences aux cultes du dimanche</h2><span><?= $nb ?>/3 validées</span></div>

<?php if ($nm['statut'] !== 'integre'): ?>
<form method="post" action="index.php" class="form-inline" style="margin-bottom:var(--space-4);">
  <?= $csrf ?>
  <input type="hidden" name="action" value="save_nouveau_membre_presence">
  <input type="hidden" name="nouveau_membre_id" value="<?= (int) $nm['id'] ?>">
  <div class="form-group"><label>Date du culte</label><input type="date" name="date" value="<?= h(date('Y-m-d')) ?>" required></div>
  <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Enregistrer une présence</button>
</form>
<?php endif; ?>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Date</th><th>Culte</th><th>Statut</th><th></th></tr></thead>
    <tbody>
      <?php if (!$nm['presences']): ?>
        <tr><td colspan="4"><?= empty_state('fa-calendar-xmark', 'Aucune présence enregistrée.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($nm['presences'] as $p): ?>
          <tr>
            <td><?= h(date('d/m/Y', strtotime((string) $p['date_presence']))) ?></td>
            <td><?= h($p['culte_nom']) ?></td>
            <td><span class="badge <?= $p['statut'] === 'present' ? 'success' : 'neutral' ?>"><?= h(PRESENCE_STATUTS[$p['statut']] ?? $p['statut']) ?></span></td>
            <td class="row-actions">
              <?php if ($nm['statut'] !== 'integre'): ?>
              <form method="post" action="index.php" onsubmit="return confirm('Annuler cette présence ?');">
                <?= $csrf ?>
                <input type="hidden" name="action" value="cancel_nouveau_membre_presence">
                <input type="hidden" name="nouveau_membre_id" value="<?= (int) $nm['id'] ?>">
                <input type="hidden" name="presence_id" value="<?= (int) $p['id'] ?>">
                <button type="submit" class="icon-btn" title="Annuler"><i class="fa-solid fa-xmark"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
