<?php

/**
 * Smoke test statique de navigation (plan §13 / PR #16) - sans base de données.
 *
 *   php tests/navigation-smoke.php
 *
 * Vérifie la cohérence des constantes de menu, des routes, des vues et des
 * permissions de l'espace « Rapports ». Ne remplace pas la recette par rôle.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$ok = static function (bool $cond, string $label) use (&$failures, &$checks): void {
    $checks++;
    if ($cond) {
        echo "  ✓ {$label}\n";
    } else {
        $failures[] = $label;
        echo "  ✗ {$label}\n";
    }
};

echo "== Navigation - smoke test ==\n";

$constants = file_get_contents($root . '/Config/constants.php');
$layout = file_get_contents($root . '/Views/layouts/layout.php');
$routes = file_get_contents($root . '/Routes/web.php');

/* ---------- 1. Chargement des constantes ---------- */
require_once $root . '/Config/constants.php';
$ok(defined('NAV_GROUPS'), 'NAV_GROUPS est définie');
$ok(defined('NAV_RAPPORTS_ALIAS_PAGES'), 'NAV_RAPPORTS_ALIAS_PAGES est définie');
$ok(!defined('NAV_ORDER'), 'NAV_ORDER n’existe plus (menu par domaines)');

/* ---------- 2. Cohérence des groupes et libellés ---------- */
$labels = SECTION_LABELS;
$icons = SECTION_ICONS;
$seen = [];
foreach (NAV_GROUPS as $groupId => $group) {
    $ok(!empty($group['label']) && !empty($group['icon']), "groupe « {$groupId} » : libellé et icône renseignés");
    $ok(!empty($group['items']), "groupe « {$groupId} » : au moins un élément (sinon il n’est pas rendu)");
    foreach ($group['items'] as $key) {
        $ok(isset($labels[$key]), "élément « {$key} » : SECTION_LABELS renseigné");
        $ok(isset($icons[$key]), "élément « {$key} » : SECTION_ICONS renseigné");
        $ok(!in_array($key, $seen, true), "élément « {$key} » : unique (aucun doublon entre menus/groupes)");
        $seen[] = $key;
    }
}

/* ---------- 3. Suivi Hebdo. des Bergers : retiré du menu général, conservé en personnel ---------- */
foreach (NAV_GROUPS as $groupId => $group) {
    if ($groupId === 'perso') {
        $ok(in_array('suiviBergers', $group['items'], true), 'groupe « Mon espace » : suivi personnel du berger conservé');
        $ok(in_array('bergerFiche', $group['items'], true), 'groupe « Mon espace » : fiche berger conservée');
    } else {
        $ok(!in_array('suiviBergers', $group['items'], true), "groupe « {$groupId} » : pas d’entrée générale « Suivi Hebdo. des Bergers » (plan §13.3)");
    }
}
$ok(!str_contains($layout, 'NAV_ORDER'), 'layout : aucune référence à NAV_ORDER');
$ok(str_contains($routes, "Router::get('suiviBergers'"), 'route suiviBergers conservée');
$ok(str_contains($routes, "Router::get('bergers'"), 'route bergers (liste) conservée');

/* ---------- 4. Espace « Rapports » consolidé ---------- */
$ok(SECTION_LABELS['rapports'] === 'Rapports', 'entrée principale renommée « Rapports »');
foreach (['rapports'] as $key) {
    $ok(in_array($key, NAV_GROUPS['gestion']['items'] ?? [], true), "« {$key} » placé dans Gestion & finances");
}
foreach (['rapport_suivi', 'rapport_dashboard', 'rapport_classement', 'rapport_parametres'] as $alias) {
    foreach (NAV_GROUPS as $groupId => $group) {
        $ok(!in_array($alias, $group['items'], true), "« {$alias} » n’a plus d’entrée latérale (porté par les onglets) - groupe « {$groupId} »");
    }
    $ok(in_array($alias, NAV_RAPPORTS_ALIAS_PAGES, true), "« {$alias} » met en évidence l’entrée « Rapports »");
}
foreach (['rapports', 'rapport', 'rapport_suivi', 'rapport_dashboard', 'rapport_classement', 'rapport_responsable', 'rapport_parametres', 'rapport_suivi_export'] as $alias) {
    $ok(in_array($alias, NAV_RAPPORTS_ALIAS_PAGES, true), "route directe « {$alias} » préservée (alias d’activation)");
}
foreach (['rapports', 'rapport', 'rapport_suivi', 'rapport_suivi_export', 'rapport_dashboard', 'rapport_classement', 'rapport_responsable', 'rapport_parametres'] as $page) {
    $ok(str_contains($routes, "Router::get('{$page}'"), "route « {$page} » déclarée");
}

/* ---------- 5. Vues : onglets internes ---------- */
$tabsPartial = $root . '/Views/pages/partials/rapport_tabs.php';
$ok(is_file($tabsPartial), 'partiel d’onglets « Rapports » présent');
foreach (['rapports', 'rapport_suivi', 'rapport_dashboard', 'rapport_classement', 'rapport_parametres'] as $view) {
    $src = @file_get_contents($root . "/Views/pages/{$view}.php");
    $ok($src !== false && str_contains($src, "pages/partials/rapport_tabs"), "vue « {$view} » intègre les onglets internes");
}
$ok(is_file($root . '/Views/pages/rapport_responsable.php'), 'fiche « rapport_responsable » conservée (page de détail, sans onglet)');

/* ---------- 6. Permissions / accessibilité ---------- */
$ok(str_contains($layout, 'aria-current="page"'), 'lien actif annoncé aux technologies d’assistance (aria-current)');
$ok(str_contains($layout, 'aria-expanded'), 'groupes repliables exposent aria-expanded');
$ok(str_contains($layout, 'aria-controls="nav-group-'), 'groupes reliés à leur liste (aria-controls)');
$ok(str_contains($layout, 'nav-group-toggle'), 'boutons de groupe présents (navigation clavier)');
$tabs = @file_get_contents($tabsPartial) ?: '';
$ok(str_contains($tabs, 'aria-current="page"'), 'onglet actif annoncé (aria-current)');
$ok(str_contains($tabs, 'Nouveau rapport'), 'action « Nouveau rapport » visible dans l’espace Rapports');
$ok(str_contains($tabs, 'rapport_parametres') && str_contains($tabs, '$isAdmin'), 'onglet Paramètres réservé aux administrateurs');
$ok(str_contains($tabs, 'rapport_classement') && str_contains($tabs, 'auth_can_view_rapport_classement'), 'onglet Classements conditionné à la permission');

/* ---------- 7. Flux « Nouveau rapport » -> retour au contexte ---------- */
$form = @file_get_contents($root . '/Views/pages/rapport_form.php') ?: '';
$ok(str_contains($form, 'name="retour"'), 'formulaire : champ caché « retour » (whitelisté côté contrôleur)');
$ok(str_contains($form, "Retour"), 'formulaire : action de retour visible');
$controller = @file_get_contents($root . '/app/Controllers/ActionsController.php') ?: '';
$ok(str_contains($controller, "'rapport_suivi', 'rapport_dashboard'") || str_contains($controller, "'rapports', 'rapport_suivi', 'rapport_dashboard'"), 'redirection post-enregistrement : contexte de suivi (whitelist)');

/* ---------- 8. Badge « échéances en retard » ---------- */
$compat = @file_get_contents($root . '/app/Compat/rapport_suivi.php') ?: '';
$ok(str_contains($compat, 'rapport_suivi_overdue_count'), 'helper de comptage des échéances en retard présent');
$ok(str_contains($layout, 'rapport_suivi_overdue_count'), 'menu : badge des échéances en retard câblé');
$ok(str_contains($tabs, 'rapport-tab-badge'), 'onglet Suivi : badge des échéances en retard câblé');

echo "\n{$checks} vérifications, " . count($failures) . " échec(s).\n";
if ($failures) {
    exit(1);
}
echo "OK - navigation conforme au plan §13 (statique).\n";
