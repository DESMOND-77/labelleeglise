<?php

/**
 * Constantes métier de l'application.
 * (anciennement config.php : rôles, sections, champs, libellés…)
 */

declare(strict_types=1);

// Rôles (enum de la table users) — voir Config/auth.php pour les libellés.
define('ROLE_LABELS', [
    'admin'       => 'Administrateur',
    'leader'      => 'Leader',
    'assistant'   => 'Assistant',
    'pasteur'     => 'Pasteur',
    'reverant'    => 'Révérend',
    'membre'      => 'Membre',
    'responsable' => 'Responsable',
]);

// Les "bergers" (fiche berger + suivi hebdomadaire) = ces rôles.
define('BERGER_ROLES', ['leader', 'pasteur', 'reverant']);

/* ---------- Constantes métier ---------- */

define('BACENTAS_DEFAULT', ['Sion', 'Bethel']);
define('BASONTAS_DEFAULT', ['Chorale', 'Ashers', 'Film Start', 'Perfect Sound', 'Akwaba', 'Singing Start']);
define('CULTES_DEFAULT', ["Culte d'Impact", 'Culte Aman', 'Cultes Tschalac', 'Culte des Leaders']);

define('SECTION_LABELS', [
    'apropos'            => 'Présentation de l\'église',
    'centresPresentation' => 'Présentation des centres',
    'accueil'            => 'Accueil',
    'bacentas'           => 'Bacentas',
    'centres'            => 'Centres',
    'cultes'             => 'Cultes',
    'basontas'           => 'Basontas',
    'nouveaux'           => 'Nouveaux membres',
    'generale'           => 'Liste générale des membres',
    'bergers'            => 'Liste des bergers',
    'suiviBergers'       => 'Suivi Hebdo. des Bergers',
    'finances'           => 'Finances & Offrandes',
    'parametres'         => 'Paramètres',
    'bergerFiche'        => 'Fiche Berger',
    'personProfile'      => 'Fiche membre',
    'admin_inscriptions' => 'Inscriptions en attente',
    'admin_inscription'  => 'Demande d\'inscription',
    'notifications'      => 'Notifications',
]);

define('SECTION_ICONS', [
    'apropos'            => '<i class="fa-solid fa-circle-info"></i>',
    'centresPresentation' => '<i class="fa-solid fa-school"></i>',
    'accueil'            => '<i class="fa-solid fa-house"></i>',
    'bacentas'           => '<i class="fa-solid fa-church"></i>',
    'centres'            => '<i class="fa-solid fa-landmark"></i>',
    'cultes'             => '<i class="fa-solid fa-hands-praying"></i>',
    'basontas'           => '<i class="fa-solid fa-microphone"></i>',
    'nouveaux'           => '<i class="fa-solid fa-star"></i>',
    'generale'           => '<i class="fa-solid fa-clipboard-list"></i>',
    'bergers'            => '<i class="fa-solid fa-people-roof"></i>',
    'suiviBergers'       => '<i class="fa-solid fa-calendar-days"></i>',
    'finances'           => '<i class="fa-solid fa-sack-dollar"></i>',
    'parametres'         => '<i class="fa-solid fa-gear"></i>',
    'admin_inscriptions' => '<i class="fa-solid fa-user-plus"></i>',
]);

/* ---------- Inscription publique / statut de compte ---------- */

define('ACCOUNT_STATUS_LABELS', [
    'pending'  => 'En attente de validation',
    'active'   => 'Actif',
    'disabled' => 'Désactivé',
]);

define('NOTIFICATION_TYPE_ICONS', [
    'new_registration' => '<i class="fa-solid fa-user-plus"></i>',
    'account_activated' => '<i class="fa-solid fa-circle-check"></i>',
]);

// "apropos" doit rester en toute première position du menu (exigence produit).
define('NAV_ORDER', [
    'apropos',
    'centresPresentation',
    'accueil',
    'bacentas',
    'centres',
    'cultes',
    'basontas',
    'nouveaux',
    'generale',
    'bergers',
    'suiviBergers',
    'finances',
    'parametres'
]);

/* ---------- Champs d'un membre (table users) ---------- */

define('BASE_USER_FIELDS', ['nom', 'prenom', 'telephone', 'email', 'quartier', 'date_naissance', 'role', 'bacenta_id', 'compte_actif']);

define('FIELD_LABELS', [
    'nom'            => 'Nom',
    'prenom'         => 'Prénom',
    'telephone'      => 'Téléphone',
    'email'          => 'Email (identifiant de connexion)',
    'quartier'       => 'Quartier (résidence)',
    'date_naissance' => 'Date de naissance',
    'role'           => 'Rôle',
    'bacenta_id'     => 'Bacenta d\'appartenance',
    'compte_actif'   => 'Compte actif',
    'invite_par'     => 'Invité par',
    'recu_par'       => 'Reçu par (Akwaba)',
    'date_recu'      => 'Date d\'arrivée',
    'photo_de_profil' => 'Photo de profil',
    'presenceCulte'   => 'Présence Culte',
    'presenceBasonta' => 'Présence Basonta',
    'presenceCentre'  => 'Présence Centre',
    'presenceBacenta' => 'Présence Bacenta',
]);

define('PRESENCE_FIELDS', ['presenceCulte', 'presenceBasonta', 'presenceCentre', 'presenceBacenta']);

define('SECTION_EXTRA_FIELDS', [
    'nouveaux' => ['invite_par', 'recu_par', 'date_recu'],
    'generale' => ['invite_par', 'recu_par', 'date_recu'],
]);

define('CHART_POLES', [
    ['key' => 'bacentas', 'label' => 'Bacentas',               'color' => '#4F46E5'],
    ['key' => 'centres',  'label' => 'Centres',                'color' => '#6366F1'],
    ['key' => 'cultes',   'label' => 'Cultes',                 'color' => '#2563EB'],
    ['key' => 'basontas', 'label' => 'Basontas',               'color' => '#22C55E'],
    ['key' => 'nouveaux', 'label' => 'Nouveaux membres',       'color' => '#F59E0B'],
    ['key' => 'generale', 'label' => 'Liste générale des membres', 'color' => '#EF4444'],
    ['key' => 'bergers',  'label' => 'Liste des bergers',      'color' => '#8B5CF6'],
]);

define('SLIDES', [
    ['gradient' => 'linear-gradient(135deg,#4F46E5,#6366F1)', 'title' => 'Bienvenue à la belle église', 'subtitle' => 'Ensemble, grandissons dans la foi et la communion fraternelle.'],
    ['gradient' => 'linear-gradient(135deg,#22C55E,#4F46E5)', 'title' => 'Une famille unie', 'subtitle' => 'Chaque bacenta, chaque centre, chaque basonta compte.'],
    ['gradient' => 'linear-gradient(135deg,#2563EB,#8B5CF6)', 'title' => 'Accueillons les nouveaux membres', 'subtitle' => 'Chaque visage est important à nos yeux.'],
]);

define('OFFERING_DAY_LABEL', ['bacentas' => 'Vendredi', 'centres' => 'Mercredi']);

define('DIMANCHES_LABELS', ['Dimanche 1', 'Dimanche 2', 'Dimanche 3', 'Dimanche 4']);

define('MONTHS_FR', ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre']);
define('MONTHS_FR_SHORT', ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc']);

define('TEAM_CATEGORIES', ['Révérend', 'Pasteur', 'Berger', 'Leader', 'Autre']);

define('WEEK_DAYS', ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']);

define('SUIVI_FIELDS', [
    ['key' => 'priere',         'label' => 'Temps de prière quotidien', 'type' => 'text'],
    ['key' => 'meditation',     'label' => 'Temps de méditation', 'type' => 'text'],
    ['key' => 'jourFlow',       'label' => 'Jour de prière du flow', 'type' => 'select'],
    ['key' => 'livre',          'label' => 'Livre lu', 'type' => 'text'],
    ['key' => 'themeEveque',    'label' => 'Thème — Prédication de l\'Évêque écoutée', 'type' => 'text'],
    ['key' => 'themeReverend',  'label' => 'Thème — Prédication du Révérend écoutée', 'type' => 'text'],
    ['key' => 'visites',        'label' => 'Personne(s) visitée(s) en semaine', 'type' => 'text'],
    ['key' => 'invitesDimanche', 'label' => 'Personne(s) invitée(s) pour dimanche', 'type' => 'text'],
    ['key' => 'invitesApres',   'label' => 'Invité(s) après le culte / deep sea fishing', 'type' => 'text', 'sundayOnly' => true],
]);
