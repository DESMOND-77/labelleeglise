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
    '<li><a class="nav-item' . ($page === 'apropos' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'apropos'])) . '"><span class="ico">' . SECTION_ICONS['apropos'] . '</span><span class="label">' . h(SECTION_LABELS['apropos']) . '</span></a></li>',
    '<li><a class="nav-item' . ($page === 'centresPresentation' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'centresPresentation'])) . '"><span class="ico">' . SECTION_ICONS['centresPresentation'] . '</span><span class="label">' . h(SECTION_LABELS['centresPresentation']) . '</span></a></li>',
];

$navLis = [];
if ($scope && $scope['kind'] === 'berger') {
    $navLis[] = '<li><a class="nav-item' . ($page === 'bergerFiche' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'bergerFiche', 'membre' => $scope['user_id']])) . '"><span class="ico"><i class="fa-solid fa-clipboard-list"></i></span><span class="label">Ma fiche berger</span></a></li>';
    $navLis[] = '<li><a class="nav-item' . ($page === 'suiviBergers' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'suiviBergers', 'membre' => $scope['user_id']])) . '"><span class="ico"><i class="fa-solid fa-calendar-days"></i></span><span class="label">Mon suivi hebdomadaire</span></a></li>';
    if ($scope['bacenta_id']) {
        $grp = get_bacenta($scope['bacenta_id']);
        $navLis[] = '<li><a class="nav-item' . ($page === 'bacentas' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'bacentas', 'id' => $scope['bacenta_id']])) . '"><span class="ico"><i class="fa-solid fa-church"></i></span><span class="label">Mon Bacenta — ' . h($grp['nom'] ?? '') . '</span></a></li>';
    }
    // Responsabilités réelles (table `responsibilities`, spec §17) : liens
    // additifs vers les sections de gestion correspondantes, indépendants
    // du rôle lui-même (ROLE ≠ RESPONSABILITÉ).
    if (!empty($scope['responsible_center_ids'])) {
        $navLis[] = '<li><a class="nav-item' . ($page === 'centres' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'centres'])) . '"><span class="ico"><i class="fa-solid fa-landmark"></i></span><span class="label">Mes centres</span></a></li>';
    }
    if (!empty($scope['responsible_bacenta_ids']) && !$scope['bacenta_id']) {
        $navLis[] = '<li><a class="nav-item' . ($page === 'bacentas' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'bacentas'])) . '"><span class="ico"><i class="fa-solid fa-church"></i></span><span class="label">Mes bacentas</span></a></li>';
    }
    if (!empty($scope['responsible_cult_ids'])) {
        $navLis[] = '<li><a class="nav-item' . ($page === 'cultes' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'cultes'])) . '"><span class="ico"><i class="fa-solid fa-hands-praying"></i></span><span class="label">Mes cultes</span></a></li>';
    }
} elseif ($scope && $scope['kind'] === 'responsable') {
    $target = scope_target();
    $navLis[] = '<li><a class="nav-item' . ($page === 'bacentas' ? ' active' : '') . '" href="' . h(url('index.php', $target)) . '"><span class="ico"><i class="fa-solid fa-lock"></i></span><span class="label">Mon Bacenta</span></a></li>';
} elseif ($user && $user['role'] !== 'admin') {
    // compte public : pages publiques uniquement
} else {
    // 'apropos' et 'centresPresentation' sont déjà fournis par $publicLis
    // (toujours en première position — voir array_merge ci-dessous) : les
    // ignorer ici pour ne pas les afficher deux fois dans le menu admin.
    foreach (NAV_ORDER as $key) {
        if (in_array($key, ['apropos', 'centresPresentation'], true)) {
            continue;
        }
        $active = $page === $key ? ' active' : '';
        $navLis[] = '<li><a class="nav-item' . $active . '" href="' . h(url('index.php', ['page' => $key])) . '"><span class="ico">' . SECTION_ICONS[$key] . '</span><span class="label">' . h(SECTION_LABELS[$key]) . '</span></a></li>';
    }
}

// Calendriers : lien pour tout gestionnaire de calendrier non-admin
// (l'admin les a déjà via la boucle NAV_ORDER ci-dessus).
if ($user && !$isAdmin && auth_can_manage_calendar()) {
    foreach (['calendrier', 'anniversaires'] as $ck) {
        $navLis[] = '<li><a class="nav-item' . ($page === $ck ? ' active' : '') . '" href="' . h(url('index.php', ['page' => $ck])) . '"><span class="ico">' . SECTION_ICONS[$ck] . '</span><span class="label">' . h(SECTION_LABELS[$ck]) . '</span></a></li>';
    }
}

// Rapport du Jour : lien pour tout responsable de bacenta non-admin
// (l'admin l'a déjà via la boucle NAV_ORDER).
if ($user && !$isAdmin && auth_can_report_any()) {
    $navLis[] = '<li><a class="nav-item' . ($page === 'rapports' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'rapports'])) . '"><span class="ico">' . SECTION_ICONS['rapports'] . '</span><span class="label">' . h(SECTION_LABELS['rapports']) . '</span></a></li>';
}

// Classes / Écoles : lien pour tout gestionnaire de classes non-admin
// (l'admin l'a déjà via la boucle NAV_ORDER).
if ($user && !$isAdmin && auth_can_manage_classes()) {
    $navLis[] = '<li><a class="nav-item' . ($page === 'classes' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'classes'])) . '"><span class="ico">' . SECTION_ICONS['classes'] . '</span><span class="label">' . h(SECTION_LABELS['classes']) . '</span></a></li>';
}

/* ---------- Inscriptions en attente (admin) ---------- */
$pendingRegistrationsCount = 0;
if ($isAdmin) {
    $pendingRegistrationsCount = count(get_pending_registrations());
    $badge = $pendingRegistrationsCount ? '<span class="nav-badge">' . $pendingRegistrationsCount . '</span>' : '';
    $navLis[] = '<li><a class="nav-item' . ($page === 'admin_inscriptions' || $page === 'admin_inscription' ? ' active' : '')
        . '" href="' . h(url('index.php', ['page' => 'admin_inscriptions'])) . '"><span class="ico">' . SECTION_ICONS['admin_inscriptions']
        . '</span><span class="label">Inscriptions</span>' . $badge . '</a></li>';
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
$crumbs = [['label' => SECTION_LABELS[$page] ?? $title, 'url' => url('index.php', ['page' => $page])]];
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
<title><?= h($title) ?> — <?= h(APP_NAME) ?></title>
<!-- Espace de gestion interne (session requise) : jamais indexé. -->
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" sizes="96x96" href="assets/images/favicon-96x96.png">
<link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml" sizes="any">
<link rel="icon" href="assets/images/favicon.ico">
<link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
<link rel="manifest" href="assets/images/site.webmanifest">
<meta name="theme-color" content="#4F46E5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">

<link rel="stylesheet" href="assets/css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-shell" id="appShell">
  <aside class="sidebar" id="appSidebar">
    <button class="sidebar-collapse-btn" id="sidebarCollapse" title="Réduire le menu" aria-label="Réduire le menu">
      <i class="fa-solid fa-chevron-left"></i>
    </button>
    <div class="brand">
      <div class="brand-logo"><img src="/assets/images/logo.png" alt="La Belle Église Internationale Franceville"></div>
      <div class="brand-text">
        <strong><?= h(APP_NAME) ?></strong>
        <span>Gestion des membres</span>
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
            <a class="profile-menu-item" href="<?= h(url('index.php', ['page' => 'apropos'])) ?>"><i class="fa-solid fa-circle-info"></i> À propos</a>
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
</body>
</html>
