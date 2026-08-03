<?php
/**
 * La Belle Église — Point d'entrée (front controller).
 *
 * Toutes les pages passent par index.php ?page=... ; toutes les écritures
 * par des actions POST (avec jeton CSRF) ; les suppressions par ?action=...
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/render.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/pages.php';
require_once __DIR__ . '/pages_sections.php';
require_once __DIR__ . '/pages_bergers.php';
require_once __DIR__ . '/pages_finances.php';
require_once __DIR__ . '/pages_parametres.php';
require_once __DIR__ . '/pages_apropos.php';

start_session();

/* ---------- Actions en GET (déconnexion, suppressions) ---------- */
handle_get_action();

/* ---------- Actions en POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post_action();
}

/* ---------- Authentification ---------- */
$user = current_user();
if (!$user) {
    page_login();
    exit;
}

/* ---------- Routage des pages ---------- */
page_content();
