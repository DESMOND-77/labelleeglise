<?php

/**
 * Déclaration des routes de l'application.
 * -------------------------------------------------------------
 * Chaque clé correspond au paramètre `?page=` (URLs préservées).
 * Les écritures passent par ActionsController::postAction (POST),
 * les suppressions/déconnexions par ActionsController::getAction (GET).
 */

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\SectionController;
use App\Controllers\BergerController;
use App\Controllers\FinanceController;
use App\Controllers\SettingsController;
use App\Controllers\AboutController;
use App\Controllers\ProfileController;
use App\Controllers\ActionsController;

/* ---------- Authentification (POST) ---------- */
Router::post('login', AuthController::class, 'login');
Router::post('verify_access', AuthController::class, 'verifyAccess');

/* ---------- Actions d'écriture (POST) ---------- */
Router::post('action', ActionsController::class, 'postAction');

/* ---------- Actions GET (déconnexion, suppressions) ---------- */
Router::get('action', ActionsController::class, 'getAction');

/* ---------- Pages ---------- */
Router::get('accueil', DashboardController::class, 'index');
Router::get('recherche', DashboardController::class, 'search');

Router::get('apropos', AboutController::class, 'apropos');
Router::get('centresPresentation', AboutController::class, 'centresPresentation');

Router::get('bacentas', SectionController::class, 'index');
Router::get('centres', SectionController::class, 'index');
Router::get('cultes', SectionController::class, 'index');
Router::get('basontas', SectionController::class, 'index');
Router::get('nouveaux', SectionController::class, 'index');
Router::get('generale', SectionController::class, 'index');
Router::get('bergers', SectionController::class, 'index');

Router::get('bergerFiche', BergerController::class, 'fiche');
Router::get('suiviBergers', BergerController::class, 'suivi');

Router::get('finances', FinanceController::class, 'index');
Router::get('parametres', SettingsController::class, 'index');
Router::get('personProfile', ProfileController::class, 'index');
