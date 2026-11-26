<?php
/**
 * Coquille applicative : sidebar + topbar + contenu.
 * Variables attendues : $title, $content.
 */
$user    = current_user();
$scope   = get_user_scope();
$allowed = get_allowed_sections();
$page    = nav('page');
$isAdmin = $user && $user['role'] === 'admin';

/* ---------- Liens du menu ---------- */
$publicLis = [
    '<li><a class="nav-item' . ($page === 'eglise' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'eglise'])) . '"><span class="ico">' . SECTION_ICONS['apropos'] . '</span><span class="label">Notre église</span></a></li>',
];

// Normalisation de la mise en évidence : toutes les routes du module « Rapports »
// activent l'entrée unique « Rapports » ; « nouveau_membre » active « Nouveaux membres ».
$navActiveKey = $page;
if (in_array($page, NAV_RAPPORTS_ALIAS_PAGES, true)) {
    $navActiveKey = 'rapports';
} elseif ($page === 'nouveau_membre') {
    $navActiveKey = 'nouveaux_membres';
} elseif ($page === 'admin_inscription') {
    $navActiveKey = 'admin_inscriptions';
}

// Badge « échéances de rapport en retard » : visible uniquement par qui voit le module.
$rapportOverdue = function_exists('rapport_suivi_overdue_count') ? (int) rapport_suivi_overdue_count($user) : 0;
$rapportsBadge = $rapportOverdue > 0 ? '<span class="nav-badge" title="Échéances de rapport en retard">' . $rapportOverdue . '</span>' : '';

$navItems = [];
$makeNavItem = static function (string $key, string $label, string $icon, array $params = [], string $badge = '') use ($navActiveKey): string {
    $active = $navActiveKey === $key;
    $cls = 'nav-item' . ($active ? ' active' : '');
    $aria = $active ? ' aria-current="page"' : '';
    if ($params === []) {
        $params = ['page' => $key];
    }
    return '<li><a class="' . $cls . '"' . $aria . ' href="' . h(url('index.php', $params)) . '"><span class="ico">' . $icon . '</span><span class="label">' . h($label) . '</span>' . $badge . '</a></li>';
};

if ($scope && $scope['kind'] === 'berger') {
    // Accès personnels du berger (jamais retirés de la navigation).
    $navItems['bergerFiche'] = $makeNavItem('bergerFiche', 'Ma fiche berger', '<i class="fa-solid fa-clipboard-list"></i>', ['page' => 'bergerFiche', 'membre' => $scope['user_id']]);
    $navItems['suiviBergers'] = $makeNavItem('suiviBergers', 'Mon suivi hebdomadaire', '<i class="fa-solid fa-calendar-days"></i>', ['page' => 'suiviBergers', 'membre' => $scope['user_id']]);

    // Responsabilités réelles (table `responsibilities`, spec §17) : liens
    // additifs vers les sections de gestion correspondantes, indépendants
    // du rôle lui-même (ROLE ≠ RESPONSABILITÉ).
    if (!empty($scope['responsible_center_ids'])) {
        $navItems['centres'] = $makeNavItem('centres', 'Mes centres', '<i class="fa-solid fa-landmark"></i>');
    }
    if (!empty($scope['responsible_bacenta_ids'])) {
        $navItems['bacentas'] = $makeNavItem('bacentas', 'Mes bacentas', SECTION_ICONS['bacentas']);
    }
    if (!empty($scope['responsible_basonta_ids'])) {
        $navItems['basontas'] = $makeNavItem('basontas', 'Mes basontas', SECTION_ICONS['basontas']);
    }
    if (!empty($scope['responsible_cult_ids'])) {
        $navItems['cultes'] = $makeNavItem('cultes', 'Mes cultes', SECTION_ICONS['cultes']);
    }
} elseif ($scope && $scope['kind'] === 'responsable') {
    $target = scope_target();
    $navItems['bacentas'] = $makeNavItem('bacentas', 'Mon Bacenta', '<i class="fa-solid fa-lock"></i>', $target ?: ['page' => 'bacentas']);
} elseif ($user && $user['role'] === 'admin') {
    $navItems['accueil'] = $makeNavItem('accueil', SECTION_LABELS['accueil'], SECTION_ICONS['accueil']);
    foreach (['bacentas', 'centres', 'cultes', 'basontas', 'nouveaux_membres', 'generale', 'bergers',
              'agenda', 'classes', 'rapports', 'budgetBus', 'finances',
              'parametres', 'albums'] as $key) {
        $navItems[$key] = $makeNavItem(
            $key,
            SECTION_LABELS[$key],
            SECTION_ICONS[$key],
            ['page' => $key],
            $key === 'rapports' ? $rapportsBadge : ''
        );
    }
} elseif ($user && $user['role'] !== 'admin') {
    // compte public : pages publiques uniquement
}

// Liens conditionnels non-admin (identiques aux permissions actuelles) :
// chacun conserve son contrôle d'accès ; rien n'est ajouté pour les comptes sans droit.
if ($user && !$isAdmin) {
    if (auth_can_manage_calendar()) {
        $navItems['agenda'] = $makeNavItem('agenda', SECTION_LABELS['agenda'], SECTION_ICONS['agenda']);
    }
    // Espace « Rapports » consolidé : une seule entrée (onglets internes sur les écrans).
    // Cible : la liste si l'utilisateur peut saisir, sinon la checklist de suivi.
    if (auth_can_report_any() || auth_can_view_rapport_suivi()) {
        $rapportTarget = auth_can_report_any() ? 'rapports' : 'rapport_suivi';
        $navItems['rapports'] = $makeNavItem('rapports', SECTION_LABELS['rapports'], SECTION_ICONS['rapports'], ['page' => $rapportTarget], $rapportsBadge);
    }
    if (auth_can_manage_classes()) {
        $navItems['classes'] = $makeNavItem('classes', SECTION_LABELS['classes'], SECTION_ICONS['classes']);
    }
    if (auth_can_manage_any_centre()) {
        $navItems['budgetBus'] = $makeNavItem('budgetBus', SECTION_LABELS['budgetBus'], SECTION_ICONS['budgetBus']);
    }
    if (auth_can_manage_nouveaux_membres()) {
        $navItems['nouveaux_membres'] = $makeNavItem('nouveaux_membres', SECTION_LABELS['nouveaux_membres'], SECTION_ICONS['nouveaux_membres']);
    }
}

// Inscriptions en attente et Site public : réservés aux administrateurs.
if ($isAdmin) {
    $pendingRegistrationsCount = count(get_pending_registrations());
    $pendingBadge = $pendingRegistrationsCount ? '<span class="nav-badge">' . $pendingRegistrationsCount . '</span>' : '';
    $navItems['admin_inscriptions'] = $makeNavItem('admin_inscriptions', 'Inscriptions', SECTION_ICONS['admin_inscriptions'], ['page' => 'admin_inscriptions'], $pendingBadge);
    $navItems['site_public'] = $makeNavItem('site_public', SECTION_LABELS['site_public'], SECTION_ICONS['site_public']);
}

/* ---------- Regroupement par domaines (repliables, aucun doublon) ---------- */
$navLis = [];
// « Accueil » reste une synthèse générale hors groupes (toujours visible, une entrée).
if (isset($navItems['accueil'])) {
    $navLis[] = $navItems['accueil'];
    unset($navItems['accueil']);
}
foreach (NAV_GROUPS as $groupId => $group) {
    $items = [];
    foreach ($group['items'] as $key) {
        if (isset($navItems[$key])) {
            $items[$key] = $navItems[$key];
        }
    }
    if ($items === []) {
        continue; // groupe vide : non rendu
    }
    $hasActive = isset($items[$navActiveKey]);
    $count = count($items);
    $navLis[] = '<li class="nav-group' . ($hasActive ? ' has-active' : '') . '" data-group="' . h($groupId) . '">'
        . '<button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-' . h($groupId) . '">'
        . '<span class="ico"><i class="' . h($group['icon']) . '" aria-hidden="true"></i></span>'
        . '<span class="label">' . h($group['label']) . '</span>'
        . '<span class="nav-group-count" aria-hidden="true">' . $count . '</span>'
        . '<i class="fa-solid fa-chevron-down nav-group-caret" aria-hidden="true"></i>'
        . '</button>'
        . '<ul class="nav-group-items" id="nav-group-' . h($groupId) . '">'
        . implode('', $items)
        . '</ul></li>';
}

/* ---------- Notifications (dropdown topbar) ---------- */
$unreadNotifCount = $user ? unread_notifications_count((int) $user['id']) : 0;
$recentNotifs = $user ? recent_notifications((int) $user['id'], 8) : [];
$notifItemsHtml = '';
foreach ($recentNotifs as $n) {
    $isUnread = !(int) $n['is_read'];
    $icon = NOTIFICATION_TYPE_ICONS[$n['type']] ?? '<i class="fa-solid fa-bell"></i>';
    $when = !empty($n['created_at']) ? date('d/m/Y H:i', strtotime($n['created_at'])) : '';
    $notifItemsHtml .= '<a class="notif-item' . ($isUnread ? ' unread' : '') . '" href="' . h(url('index.php', ['action' => 'notification_open', 'id' => $n['id']])) . '">'
        . '<span class="notif-item-icon">' . $icon . '</span>'
        . '<span class="notif-item-body"><strong>' . h($n['title']) . '</strong><span>' . h($n['message']) . '</span><time>' . h($when) . '</time></span>'
        . '</a>';
}
if ($notifItemsHtml === '') {
    $notifItemsHtml = '<div class="notif-empty">Aucune notification pour le moment.</div>';
}

/* ---------- Fil d'Ariane ---------- */
if (in_array($page, NAV_RAPPORTS_ALIAS_PAGES, true)) {
    // Espace « Rapports » : la fiche est une page de détail rattachée au module.
    $rapportHome = ($user && (($user['role'] ?? '') === 'admin' || auth_can_report_any())) ? 'rapports' : 'rapport_suivi';
    $crumbChild = [
        'rapports' => 'Liste des rapports',
        'rapport' => 'Saisie de rapport',
        'rapport_suivi_export' => SECTION_LABELS['rapport_suivi'],
    ][$page] ?? (SECTION_LABELS[$page] ?? $title);
    $crumbs = [
        ['label' => SECTION_LABELS['rapports'], 'url' => url('index.php', ['page' => $rapportHome])],
        ['label' => $crumbChild, 'url' => ''],
    ];
} else {
    $crumbs = [['label' => SECTION_LABELS[$page] ?? $title, 'url' => url('index.php', ['page' => $page])]];
}
if (in_array($page, ['bacentas', 'cultes', 'basontas'], true) && nav('id')) {
    if ($page === 'bacentas') {
        $b = get_bacenta(nav('id'));
        if ($b) {
            $crumbs[] = ['label' => $b['nom'], 'url' => ''];
        }
    } elseif ($page === 'cultes') {
        $c = get_culte(nav('id'));
        if ($c) {
            $crumbs[] = ['label' => $c['nom'], 'url' => ''];
        }
    } elseif ($page === 'basontas') {
        $b = get_basonta(nav('id'));
        if ($b) {
            $crumbs[] = ['label' => $b['nom'], 'url' => ''];
        }
    }
} elseif ($page === 'centres' && nav('id')) {
    $c = get_centre(nav('id'));
    if ($c) {
        $crumbs[] = ['label' => $c['nom'], 'url' => ''];
    }
} elseif ($page === 'suiviBergers' && nav('membre')) {
    $m = get_user(nav('membre'));
    if ($m) {
        $crumbs[] = ['label' => full_name($m), 'url' => ''];
    }
} elseif ($page === 'bergerFiche' && nav('membre')) {
    $m = get_user(nav('membre'));
    if ($m) {
        $crumbs[] = ['label' => full_name($m), 'url' => ''];
    }
} elseif ($page === 'centresPresentation' && nav('id')) {
    $c = get_centre_article(nav('id'));
    if ($c) {
        $crumbs[] = ['label' => $c['centre_nom'], 'url' => ''];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?> - <?= h(APP_NAME) ?></title>
<!-- Espace de gestion interne (session requise) : jamais indexé. -->
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" sizes="96x96" href="<?= h(asset('assets/images/favicon-96x96.png')) ?>">
<link rel="icon" href="<?= h(asset('assets/images/favicon.svg')) ?>" type="image/svg+xml" sizes="any">
<link rel="icon" href="<?= h(asset('assets/images/favicon.ico')) ?>">
<link rel="apple-touch-icon" href="<?= h(asset('assets/images/apple-touch-icon.png')) ?>">
<?php if (!pwa_enabled()): ?>
<link rel="manifest" href="<?= h(asset('assets/images/site.webmanifest')) ?>">
<?php endif; ?>
<meta name="theme-color" content="#0B1020" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0B1020" media="(prefers-color-scheme: dark)">
<!-- PWA : manifest dynamique (URLs résolues selon la racine de déploiement),
     feuille de style et charge utile d'amorçage du client hors ligne. -->
<?= pwa_head_tags() ?>

<?php
// Cache-busting : l'hébergeur/le navigateur gardent app.css (et ses @import) en cache.
$cssVersion = 0;
foreach (glob(__DIR__ . '/../../assets/css/*.css') ?: [] as $cssFile) {
    $cssVersion = max($cssVersion, (int) @filemtime($cssFile));
}
?>
<link rel="stylesheet" href="assets/css/app.css?v=<?= $cssVersion ?>">
<?php if (($page ?? '') === 'site_public'): ?><link rel="stylesheet" href="assets/css/admin_site.css?v=<?= $cssVersion ?>"><?php endif; ?>
<?php if (in_array($page ?? '', ['rapports', 'rapport', 'rapport_suivi', 'rapport_dashboard', 'rapport_classement', 'rapport_responsable', 'rapport_parametres'], true)): ?><link rel="stylesheet" href="assets/css/rapport_suivi.css?v=<?= $cssVersion ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= h(FONT_AWESOME_CSS) ?>">
<script src="<?= h(CHART_JS) ?>"></script>
</head>
<body>
<div class="app-shell" id="appShell">
  <aside class="sidebar" id="appSidebar">
    <button class="sidebar-collapse-btn" id="sidebarCollapse" title="Réduire le menu" aria-label="Réduire le menu">
      <i class="fa-solid fa-chevron-left"></i>
    </button>
    <div class="brand">
      <div class="brand-logo"><img src="<?= h(asset('assets/images/logo.png')) ?>" alt="La Belle Église Intenationale Franceville"></div>
      <div class="brand-text">
        <strong><?= h(APP_NAME) ?></strong>
        <span><?= h(APP_LOCATION) ?></span>
      </div>
    </div>
    <nav class="side-nav" aria-label="Navigation principale">
      <ul id="navLinks">
        <?= implode('', array_merge($publicLis, $navLis)) ?>
      </ul>
    </nav>
    <div class="side-user">
      <div class="user-avatar" id="userAvatar"><?= h(strtoupper(first_char(trim($user['prenom'] ?? '?')))) ?></div>
      <div class="user-meta">
        <strong id="userName"><?= h(full_name($user)) ?></strong>
        <span id="userRole"><?= h(ROLE_LABELS[$user['role']] ?? $user['role']) ?></span>
      </div>
      <a class="logout-btn" href="<?= h(url('index.php', ['action' => 'logout'])) ?>" title="Déconnexion" aria-label="Déconnexion"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <button class="menu-toggle" id="menuToggle" title="Menu" aria-label="Ouvrir le menu"><i class="fa-solid fa-bars"></i></button>
      <div class="topbar-left">
        <h1 id="pageTitle"><?= h(SECTION_LABELS[$page] ?? $title) ?></h1>
        <div class="breadcrumb" id="breadcrumb"><?= breadcrumb_html($crumbs) ?></div>
      </div>
      <?php if ($isAdmin): ?>
      <div class="global-search">
        <form action="index.php" method="get" role="search">
          <input type="hidden" name="page" value="recherche">
          <input type="search" id="globalSearchInput" name="q" placeholder="Rechercher une personne…"
                 value="<?= h(nav('q')) ?>" autocomplete="off" aria-label="Rechercher une personne">
        </form>
      </div>
      <?php endif; ?>
      <div class="topbar-actions">
        <div class="notif-menu" id="notifMenu">
          <button class="topbar-icon-btn" id="notifTrigger" title="Notifications" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
            <i class="fa-solid fa-bell"></i>
            <?php if ($unreadNotifCount): ?><span class="dot"></span><?php endif; ?>
          </button>
          <div class="notif-menu-list">
            <div class="notif-menu-head">
              <strong>Notifications</strong>
              <?php if ($unreadNotifCount): ?>
                <a href="<?= h(url('index.php', ['action' => 'notification_mark_all_read'])) ?>">Tout marquer comme lu</a>
              <?php endif; ?>
            </div>
            <div class="notif-menu-items"><?= $notifItemsHtml ?></div>
            <a class="notif-menu-footer" href="<?= h(url('index.php', ['page' => 'notifications'])) ?>">Voir toutes les notifications</a>
          </div>
        </div>
        <div class="profile-menu" id="profileMenu">
          <button class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
            <div class="user-avatar"><?= h(strtoupper(first_char(trim($user['prenom'] ?? '?')))) ?></div>
            <span class="profile-name"><?= h(full_name($user)) ?></span>
            <i class="fa-solid fa-chevron-down chevron"></i>
          </button>
          <div class="profile-menu-list">
            <div class="profile-menu-head">
              <strong><?= h(full_name($user)) ?></strong>
              <span><?= h(ROLE_LABELS[$user['role']] ?? $user['role']) ?></span>
            </div>
            <a class="profile-menu-item" href="<?= h(url('index.php', ['page' => 'profile'])) ?>"><i class="fa-solid fa-user-pen"></i> Mon profil</a>
            <?php if (pwa_enabled()): ?><a class="profile-menu-item" href="<?= h(url('index.php', ['page' => 'pwa_offline'])) ?>"><i class="fa-solid fa-cloud-arrow-down"></i> Données hors ligne</a><?php endif; ?>
            <a class="profile-menu-item" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>"><i class="fa-solid fa-circle-info"></i> Notre église</a>
            <a class="profile-menu-item danger" href="<?= h(url('index.php', ['action' => 'logout'])) ?>"><i class="fa-solid fa-arrow-right-from-bracket"></i> Déconnexion</a>
          </div>
        </div>
      </div>
    </header>
    <section class="content" id="contentArea">
      <?= $content ?>
    </section>
  </main>
</div>

<?php if (isset($charts) && is_array($charts)): ?>
<script>
  window.__LBEGF_CHARTS__ = <?= json_encode($charts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<?php endif; ?>
<script src="assets/js/app.js"></script>
<script src="assets/js/attendance.js" defer></script>
<script src="assets/js/table-paginate.js" defer></script>
<?php if (($page ?? '') === 'site_public'): ?><script src="assets/js/admin_site.js" defer></script><?php endif; ?>
<?php if (($page ?? '') === 'agenda'): ?><script src="assets/js/agenda.js" defer></script><?php endif; ?>
<?= pwa_foot_tags() ?>
</body>
</html>
