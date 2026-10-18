<?php
/**
 * La Belle Église Intenationale Franceville - Point d'entrée (front controller).
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

use App\Controllers\RegistrationController;

/* ---------- Actions en POST (écritures, login, porte d'accès, inscription) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route = $_POST['action'] ?? '';
    if ($route === 'login' || $route === 'verify_access') {
        (new AuthController())->{$route}();
    } elseif ($route === 'register') {
        (new RegistrationController())->submit();
    } else {
        (new ActionsController())->postAction();
    }
    exit;
}

/* ---------- Pages publiques (accessibles sans connexion) ---------- */
$page = $_GET['page'] ?? 'accueil';
// `/` sans paramètre : vitrine publique pour un visiteur non connecté (un
// utilisateur connecté garde le tableau de bord). Un ancien `?error=` sans
// `page` (échec de connexion) reste routé vers l'écran de connexion.
if (!isset($_GET['page']) && !isset($_GET['error']) && !current_user()) {
    $page = 'eglise';
}
$publicPages = ['eglise', 'login', 'register', 'verify_email', 'verify_email_change', 'email_change_pending', 'mentions_legales', 'confidentialite'];

if (in_array($page, $publicPages, true)) {
    require_once __DIR__ . '/Routes/web.php';
    if (Router::dispatch('GET', $page)) {
        exit;
    }
}

/* ---------- Anciennes pages de présentation : redirection 301 pour les anonymes ---------- */
if (!current_user() && in_array($page, ['apropos', 'centresPresentation'], true)) {
    $anchor = $page === 'apropos' ? 'a-propos' : 'centres';
    header('Location: ' . url('index.php', ['page' => 'eglise']) . '#' . $anchor, true, 301);
    exit;
}

/* ---------- Authentification ---------- */
$user = current_user();
if (!$user) {
    (new AuthController())->loginForm();
    exit;
}

/* ---------- Routage des pages ---------- */
// Dépendances de routage : charger les routes déclarées puis dispatcher.
require_once __DIR__ . '/Routes/web.php';

if (!Router::dispatch('GET', $page)) {
    // Page par défaut (comportement identique à l'ancien dispatcher).
    (new AuthController())->loginForm(); // ne devrait pas être atteint ; sécurité.
    exit;
}
