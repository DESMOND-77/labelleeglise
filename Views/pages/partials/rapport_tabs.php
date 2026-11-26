<?php /* Onglets internes de l'espace « Rapports » (plan §13.1).
   Variables : $active ('suivi'|'dashboard'|'classement'|'parametres'),
               $sub    ('list'|'checklist', optionnel, onglet suivi uniquement),
               $retour (route de retour après « Nouveau rapport », whitelistée côté contrôleur). */
$user = current_user();
$isAdmin = $user && ($user['role'] ?? '') === 'admin';
$active = $active ?? 'suivi';
$sub = $sub ?? null;
$retour = $retour ?? ($active === 'suivi' ? ($sub === 'list' ? 'rapports' : 'rapport_suivi') : 'rapport_suivi');
$overdue = function_exists('rapport_suivi_overdue_count') ? rapport_suivi_overdue_count($user) : 0;

$tabs = [];
if (function_exists('auth_can_report_any') && (auth_can_report_any() || auth_can_view_rapport_suivi())) {
    $tabs['suivi'] = ['label' => 'Suivi des rapports', 'icon' => 'fa-solid fa-clipboard-check', 'params' => ['page' => 'rapport_suivi'], 'overdue' => $overdue];
}
if (function_exists('auth_can_view_rapport_suivi') && auth_can_view_rapport_suivi()) {
    $tabs['dashboard'] = ['label' => 'Tableau de bord', 'icon' => 'fa-solid fa-chart-line', 'params' => ['page' => 'rapport_dashboard'], 'overdue' => 0];
}
if (function_exists('auth_can_view_rapport_classement') && auth_can_view_rapport_classement()) {
    $tabs['classement'] = ['label' => 'Classements & félicitations', 'icon' => 'fa-solid fa-trophy', 'params' => ['page' => 'rapport_classement'], 'overdue' => 0];
}
if ($isAdmin) {
    $tabs['parametres'] = ['label' => 'Paramètres', 'icon' => 'fa-solid fa-sliders', 'params' => ['page' => 'rapport_parametres'], 'overdue' => 0];
}
?>
<nav class="rapport-tabs" aria-label="Espace Rapports">
  <div class="rapport-tabs-list">
    <?php foreach ($tabs as $key => $tab): $isActive = $active === $key; ?>
      <a class="rapport-tab<?= $isActive ? ' is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?> href="<?= h(url('index.php', $tab['params'])) ?>"><i class="<?= h($tab['icon']) ?>" aria-hidden="true"></i><span><?= h($tab['label']) ?></span><?php if ($key === 'suivi' && (int) $tab['overdue'] > 0): ?><span class="rapport-tab-badge" title="Échéances en retard"><?= (int) $tab['overdue'] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (function_exists('auth_can_report_any') && auth_can_report_any()): ?>
    <a class="btn btn-primary btn-sm rapport-tabs-cta" href="<?= h(url('index.php', ['page' => 'rapport', 'retour' => $retour])) ?>"><i class="fa-solid fa-plus"></i> Nouveau rapport</a>
  <?php endif; ?>
</nav>
<?php if ($active === 'suivi' && isset($tabs['suivi'])): ?>
<div class="rapport-subnav" role="group" aria-label="Vue du suivi">
  <a class="rapport-subnav-item<?= $sub === 'list' ? ' is-active' : '' ?>"<?= $sub === 'list' ? ' aria-current="page"' : '' ?> href="<?= h(url('index.php', ['page' => 'rapports'])) ?>"><i class="fa-solid fa-file-lines" aria-hidden="true"></i> Rapports enregistrés</a>
  <a class="rapport-subnav-item<?= $sub !== 'list' ? ' is-active' : '' ?>"<?= $sub !== 'list' ? ' aria-current="page"' : '' ?> href="<?= h(url('index.php', ['page' => 'rapport_suivi'])) ?>"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Échéances &amp; checklist</a>
</div>
<?php endif; ?>
