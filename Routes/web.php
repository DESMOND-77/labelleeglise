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
use App\Controllers\RegistrationController;
use App\Controllers\AdminRegistrationController;
use App\Controllers\NotificationController;
use App\Controllers\PresenceController;
use App\Controllers\CalendrierController;
use App\Controllers\RapportController;
use App\Controllers\RapportSuiviController;
use App\Controllers\ClasseController;
use App\Controllers\BusController;
use App\Controllers\PublicController;
use App\Controllers\AdminSiteController;
use App\Controllers\NouveauMembreController;
use App\Controllers\AlbumController;
use App\Controllers\PwaSyncController;

/* ---------- Authentification (POST) ---------- */
Router::post('login', AuthController::class, 'login');
Router::post('verify_access', AuthController::class, 'verifyAccess');
Router::post('register', RegistrationController::class, 'submit');

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
Router::get('generale', SectionController::class, 'index');
Router::get('bergers', SectionController::class, 'index');

Router::get('bergerFiche', BergerController::class, 'fiche');
Router::get('suiviBergers', BergerController::class, 'suivi');

Router::get('agenda', CalendrierController::class, 'agenda');
Router::get('calendrier', CalendrierController::class, 'evenements');
Router::get('anniversaires', CalendrierController::class, 'anniversaires');

Router::get('rapports', RapportController::class, 'index');
Router::get('rapport', RapportController::class, 'form');
Router::get('rapport_suivi', RapportSuiviController::class, 'checklist');
Router::get('rapport_suivi_export', RapportSuiviController::class, 'export');
Router::get('rapport_dashboard', RapportSuiviController::class, 'dashboard');
Router::get('rapport_classement', RapportSuiviController::class, 'classement');
Router::get('rapport_responsable', RapportSuiviController::class, 'responsable');
Router::get('rapport_parametres', RapportSuiviController::class, 'parametres');
Router::get('nouveaux_membres', NouveauMembreController::class, 'index');
Router::get('nouveau_membre', NouveauMembreController::class, 'dossier');
Router::get('classes', ClasseController::class, 'index');
Router::get('classe', ClasseController::class, 'detail');
Router::get('budgetBus', BusController::class, 'index');

Router::get('finances', FinanceController::class, 'index');
Router::get('parametres', SettingsController::class, 'index');
Router::get('personProfile', ProfileController::class, 'index');

/* ---------- Profil libre-service + fiches imprimables (auth requise) ---------- */
Router::get('profile', ProfileController::class, 'me');
Router::get('attendancePrint', ProfileController::class, 'attendancePrint');
Router::get('suiviPrint', ProfileController::class, 'suiviPrint');
Router::get('presencePrint', PresenceController::class, 'matrixPrint');

/* ---------- Site public (accès sans connexion) ---------- */
Router::get('eglise', PublicController::class, 'index');
Router::get('albums', AlbumController::class, 'index');
Router::get('album', AlbumController::class, 'detail');
Router::get('login', PublicController::class, 'login');
Router::get('mentions_legales', PublicController::class, 'mentionsLegales');
Router::get('confidentialite', PublicController::class, 'confidentialite');

/* ---------- Administration du site public (admin uniquement) ---------- */
Router::get('site_public', AdminSiteController::class, 'index');

/* ---------- Inscription publique / vérification email (accès public) ---------- */
Router::get('register', RegistrationController::class, 'form');
Router::get('verify_email', RegistrationController::class, 'verify');

/* ---------- Changement d'email sécurisé (spec §12-13) ---------- */
Router::get('verify_email_change', ProfileController::class, 'verifyEmailChange');
Router::get('email_change_pending', ProfileController::class, 'emailChangePending');

/* ---------- Administration des inscriptions (admin uniquement) ---------- */
Router::get('admin_inscriptions', AdminRegistrationController::class, 'index');
Router::get('admin_inscription', AdminRegistrationController::class, 'show');

/* ---------- Centre de notifications ---------- */
Router::get('notifications', NotificationController::class, 'index');

/* ---------- PWA : API JSON de synchronisation hors ligne ----------
 * Le mode hors ligne n'est jamais un contournement de l'autorisation serveur
 * (plan §1) : ces routes ré-authentifient, ré-autorisent et revalident chaque
 * opération avant de la committer. Elles ne réutilisent PAS le POST HTML
 * historique comme API (plan §7.1).
 */
Router::get('pwa/v1/manifest', PwaSyncController::class, 'manifest');
Router::get('pwa/v1/assets', PwaSyncController::class, 'assets');
Router::get('pwa/v1/bootstrap', PwaSyncController::class, 'bootstrap');
Router::get('pwa/v1/status', PwaSyncController::class, 'status');
Router::post('pwa/v1/sync', PwaSyncController::class, 'sync');
Router::post('pwa/v1/public/register', PwaSyncController::class, 'publicRegister');

/* ---------- PWA : écran de gestion des données hors ligne (session requise) ---------- */
Router::get('pwa_offline', PwaSyncController::class, 'panel');
