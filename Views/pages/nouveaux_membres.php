<?php /* Liste des nouveaux membres (suivi pré-intégration).
   Variables : $rows, $isAdmin, $responsables, $bacentas, $filters, $csrf. */
$statutBadge = static function (string $statut): string {
    $cls = ['nouveau' => 'neutral', 'en_suivi' => 'warning', 'pret_integration' => 'warning', 'integre' => 'success'][$statut] ?? 'neutral';
    return '<span class="badge ' . $cls . '">' . h(NOUVEAU_MEMBRE_STATUT_LABELS[$statut] ?? $statut) . '</span>';
};
$presenceBadge = static function (int $nb): string {
    $cls = $nb >= 3 ? 'success' : ($nb > 0 ? 'warning' : 'neutral');
    return '<span class="badge ' . $cls . '">' . $nb . '/3</span>';
};
?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['nouveaux_membres']) ?></h2><div class="sub">Suivi des nouvelles personnes avant leur intégration à la liste générale</div></div>
  <div class="toolbar-actions">
    <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'nouveaux_membres', 'pointage' => 1])) ?>"><i class="fa-solid fa-calendar-check"></i> Pointer les présences</a>
    <a class="btn btn-primary" href="<?= h(url('index.php', ['page' => 'nouveau_membre', 'form' => 1])) ?>"><i class="fa-solid fa-plus"></i> Ajouter un nouveau membre</a>
  </div>
</div>

<form method="get" action="index.php" class="rapport-filters">
  <input type="hidden" name="page" value="nouveaux_membres">
  <label>Recherche
    <input type="text" name="q" value="<?= h($filters['q'] ?? '') ?>" placeholder="Nom, prénom, téléphone">
  </label>
  <label>Bacenta
    <select name="bacenta_id" onchange="this.form.submit()">
      <option value="">Tous</option>
      <?php foreach ($bacentas as $b): ?>
        <option value="<?= (int) $b['id'] ?>" <?= (int) ($filters['bacenta_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= h($b['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Statut
    <select name="statut" onchange="this.form.submit()">
      <option value="">Tous</option>
      <?php foreach (NOUVEAU_MEMBRE_STATUT_LABELS as $sk => $sl): ?>
        <option value="<?= h($sk) ?>" <?= ($filters['statut'] ?? '') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if ($filters): ?><a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'nouveaux_membres'])) ?>">Effacer</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Nom</th><th>Téléphone</th><th>Bacenta</th><th>Présences</th><th>1ère visite</th><th>Statut</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7"><?= empty_state('fa-user-plus', 'Aucun nouveau membre pour ces filtres.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h(trim($r['prenom'] . ' ' . $r['nom'])) ?></td>
            <td><?= h($r['telephone'] ?? '') ?></td>
            <td><?= h($r['bacenta_nom'] ?? '-') ?></td>
            <td><?= $presenceBadge((int) ($r['nb_presences'] ?? 0)) ?></td>
            <td><?= $r['date_premiere_visite'] ? h(date('d/m/Y', strtotime((string) $r['date_premiere_visite']))) : '-' ?></td>
            <td><?= $statutBadge($r['statut']) ?></td>
            <td class="row-actions">
              <?php if ($r['statut'] !== 'integre'): ?>
              <form method="post" action="index.php" style="display:inline-flex;gap:4px;align-items:center;">
                <?= $csrf ?>
                <input type="hidden" name="action" value="save_nouveau_membre_presence">
                <input type="hidden" name="nouveau_membre_id" value="<?= (int) $r['id'] ?>">
                <input type="date" name="date" value="<?= h(date('Y-m-d')) ?>" class="row-date-input">
                <button type="submit" class="icon-btn" title="Enregistrer une présence"><i class="fa-solid fa-calendar-check"></i></button>
              </form>
              <?php endif; ?>
              <a class="icon-btn" title="Voir le dossier" href="<?= h(url('index.php', ['page' => 'nouveau_membre', 'id' => $r['id']])) ?>"><i class="fa-solid fa-folder-open"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
