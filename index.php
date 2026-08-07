<?php
/**
 * La Belle Église — Point d'entrée (front controller).
 * -------------------------------------------------------------
 * Bootstrap de l'application, dispatch des actions et des pages.
 * Toutes les URL existantes (`index.php?page=...`, POST d'actions,
 * suppressions en GET) sont préservées.
 */

declare(strict_types=1);

require_once __DIR__ . '/Bootstrap/init.php';

use App\Core\Router;
use App\Controllers\ActionsController;
use App\Controllers\AuthController;

/* ---------- Actions en GET (déconnexion, suppressions) ---------- */
if (isset($_GET['action']) && $_GET['action'] !== '') {
    (new ActionsController())->getAction();
}

/* ---------- Actions en POST (écritures, login, porte d'accès) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route = $_POST['action'] ?? '';
    if ($route === 'login' || $route === 'verify_access') {
        (new AuthController())->{$route}();
    } else {
        (new ActionsController())->postAction();
    }
    exit;
}

/* ---------- Authentification ---------- */
$user = current_user();
if (!$user) {
    (new AuthController())->loginForm();
    exit;
}

/* ---------- Routage des pages ---------- */
$page = $_GET['page'] ?? 'accueil';

// Dépendances de routage : charger les routes déclarées puis dispatcher.
require_once __DIR__ . '/Routes/web.php';

if (!Router::dispatch('GET', $page)) {
    // Page par défaut (comportement identique à l'ancien dispatcher).
    (new AuthController())->loginForm(); // ne devrait pas être atteint ; sécurité.
    exit;
}
