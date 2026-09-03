<?php

/**
 * Constantes métier de l'application.
 * (anciennement config.php : rôles, sections, champs, libellés…)
 */

declare(strict_types=1);

// Rôles (enum de la table users). ROLE ≠ RESPONSABILITÉ ≠ PÉRIMÈTRE — voir
// docs/roles-and-permissions.md. `responsable` reste dans l'ENUM SQL pour
// compatibilité/rollback (voir migration) mais n'est plus un rôle actif :
// il a été remplacé par `berger` + le modèle de responsabilités.
define('ROLE_LABELS', [
    'admin'     => 'Administrateur',
    'pasteur'   => 'Pasteur',
    'reverant'  => 'Révérend',
    'berger'    => 'Berger',
    'ms'        => 'MS',
    'leader'    => 'Leader',
    'membre'    => 'Membre',
    'assistant' => 'Assistant',
]);

/*
 * Trois regroupements de rôles, DISTINCTS et NE DEVANT JAMAIS être
 * conflatés (voir prompts/REMANIEMENT…md §11) :
 *
 * - BERGER_ROLES : rôles ayant une fiche berger + un suivi hebdomadaire
 *   PERSONNELS, et un périmètre "bacenta d'appartenance" verrouillé
 *   (users.bacenta_id). Comportement historique préservé pour
 *   leader/pasteur/reverant ; berger/ms nouvellement inclus.
 * - CENTER_BACENTA_RESPONSIBILITY_ROLES : rôles pouvant RECEVOIR une
 *   responsabilité de centre ou de bacenta (table `responsibilities`).
 * - CULT_RESPONSIBILITY_ROLES : rôles pouvant RECEVOIR une responsabilité
 *   de culte.
 * - WEEKLY_FOLLOWUP_ROLES : rôles disposant de la permission
 *   `weekly_followup.manage_own` (admin inclus : accès global en lecture).
 */
define('BERGER_ROLES', ['leader', 'pasteur', 'reverant', 'berger', 'ms']);
define('CENTER_BACENTA_RESPONSIBILITY_ROLES', ['berger', 'ms', 'pasteur']);
define('CULT_RESPONSIBILITY_ROLES', ['pasteur', 'reverant']);
define('WEEKLY_FOLLOWUP_ROLES', ['admin', 'pasteur', 'reverant', 'berger', 'ms', 'leader']);

/* ---------- Constantes métier ---------- */

define('BACENTAS_DEFAULT', ['Sion', 'Bethel']);
define('BASONTAS_DEFAULT', ['Chorale', 'Ushers', 'Film Start', 'Perfect Sound', 'Akwaba', 'Singing Start']);
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
    'calendrier'         => 'Calendrier',
    'anniversaires'      => 'Anniversaires',
    'rapports'           => 'Rapports du Jour',
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
    'calendrier'         => '<i class="fa-solid fa-calendar-day"></i>',
    'anniversaires'      => '<i class="fa-solid fa-cake-candles"></i>',
    'rapports'           => '<i class="fa-solid fa-file-lines"></i>',
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
    'calendrier',
    'anniversaires',
    'rapports',
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

define('PRESENCE_STATUTS', ['present' => 'Présent', 'absent' => 'Absent', 'excuse' => 'Excusé']);

define('RAPPORT_JOUR_FIELDS', [
    ['key' => 'nb_presents',       'label' => 'Nombre de présents',               'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'nb_adultes',        'label' => 'Nombre d\'adultes',                'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'nb_enfants',        'label' => 'Nombre d\'enfants',                'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'nb_anciens',        'label' => 'Nombre d\'anciens',                'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'nb_nouveaux',       'label' => 'Nombre de nouveaux',               'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'nb_nes_de_nouveau', 'label' => 'Nombre de nés de nouveau',         'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'offrande',          'label' => 'Montant de l\'offrande',           'type' => 'decimal',  'group' => 'Finances'],
    ['key' => 'assistants',        'label' => 'Noms des assistants',               'type' => 'textarea', 'group' => 'Équipe'],
    ['key' => 'livre_enseigne',    'label' => 'Livre enseigné',                  'type' => 'text',     'group' => 'Enseignement', 'max' => 150],
    ['key' => 'chapitre_enseigne', 'label' => 'Chapitre enseigné',               'type' => 'text',     'group' => 'Enseignement', 'max' => 80],
]);

define('CLASSES_CURSUS', [
    'Manuel du nouveau croyant',
    'Sept grands principes',
    'Ce que signifie être un chrétien fort',
    'École de la fondation solide',
    'École de la vie victorieuse',
    'École de la parole',
    "École de l'apologétique",
]);

define('EXAM_STATUTS', ['non_passe' => 'Non passé', 'reussi' => 'Réussi', 'echoue' => 'Échoué']);

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
    ['key' => 'mixlr',        'label' => 'Diffusion Mixlr (lien ou statut)', 'type' => 'text', 'sundayOnly' => true],
    ['key' => 'ushers',       'label' => "Nombre d'ushers", 'type' => 'number', 'sundayOnly' => true],
    ['key' => 'themeSemaine', 'label' => 'Thème de la semaine', 'type' => 'text', 'optional' => true],
]);
