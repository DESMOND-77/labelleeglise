<?php /* Classement des responsables (admin + périmètre autorisé).
   Variables : $rows, $filters, $filterQuery, $isAdmin, $scopeRestricted, $currentUserId. */ ?>
<?php
$filterQuery = $filterQuery ?? [];
$fmtRate = static function ($rate) {
    return $rate === null ? 'Non évalué' : number_format((float) $rate, 1, ',', ' ') . ' %';
};
?>
<?= section_toolbar(
    SECTION_LABELS['rapport_classement'],
    'Classement réservé aux administrateurs et aux responsables autorisés, limité à leur périmètre. Jamais public.'
) ?>

<div class="suivi-restricted-note" role="note">
  <i class="fa-solid fa-lock"></i>
  <span><?= $isAdmin
      ? 'Vous consultez le classement global (toutes les structures).'
      : 'Vous consultez le classement de votre périmètre de gestion (centres et bacentas dont vous êtes responsable). Les résultats hors périmètre ne sont pas affichés.' ?></span>
</div>

<form method="get" action="index.php" class="suivi-filters">
  <input type="hidden" name="page" value="rapport_classement">
  <label>Période
    <select name="periode" onchange="this.form.submit()">
      <option value="semaine" <?= $filters['periode'] === 'semaine' ? 'selected' : '' ?>>Semaine ISO (en cours)</option>
      <option value="mois" <?= $filters['periode'] === 'mois' ? 'selected' : '' ?>>Mois civil (en cours)</option>
      <option value="custom" <?= $filters['periode'] === 'custom' ? 'selected' : '' ?>>Personnalisée</option>
    </select>
  </label>
  <label>Du
    <input type="date" name="from" value="<?= h((string) ($filters['from_input'] ?? '')) ?>">
  </label>
  <label>Au
    <input type="date" name="to" value="<?= h((string) ($filters['to_input'] ?? '')) ?>">
  </label>
  <div class="filter-actions"><button type="submit" class="btn btn-outline"><i class="fa-solid fa-filter"></i> Appliquer</button></div>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Rang</th>
        <th>Responsable</th>
        <th>Attendus</th>
        <th>Crédités</th>
        <th>Taux de réalisation</th>
        <th>Ponctualité</th>
        <th>Niveau</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8"><?= empty_state('fa-trophy', 'Aucune échéance attendue sur la période — classement « Non évalué ».') ?></td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $isSelf = (int) $r['user_id'] === (int) $currentUserId;
          $rank = (int) $r['rank'];
        ?>
          <tr style="<?= $isSelf ? 'background:var(--primary-soft)' : '' ?>">
            <td><span class="suivi-rank <?= $rank <= 3 ? 'top' : '' ?>"><?= $rank ?></span></td>
            <td>
              <div class="suivi-cell-main">
                <strong><?= h(trim((string) ($r['prenom'] ?? '') . ' ' . (string) ($r['nom'] ?? ''))) ?><?= $isSelf ? ' (vous)' : '' ?></strong>
                <span class="suivi-cell-sub"><?= (int) $r['denominator'] ?> échéance(s) créditables</span>
              </div>
            </td>
            <td><?= (int) $r['expected'] ?></td>
            <td><?= (int) $r['credited'] ?></td>
            <td><?= $fmtRate($r['completion_rate']) ?></td>
            <td><?= $fmtRate($r['punctuality_rate']) ?></td>
            <td><?= h((string) ($r['level_label'] ?? 'Non évalué')) ?></td>
            <td class="row-actions"><a class="btn btn-outline btn-sm" href="<?= h(url('index.php', ['page' => 'rapport_responsable', 'id' => (int) $r['user_id']])) ?>"><i class="fa-solid fa-user-check"></i> Fiche</a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<p class="suivi-cell-sub" style="margin-top:var(--space-3)">
  Taux = rapports crédités / (rapports crédités + échéances manquées). Les occurrences remplies par un autre responsable ne diminuent pas votre taux : elles sont neutres. « Non évalué » signifie aucune échéance créditable sur la période.
</p>
