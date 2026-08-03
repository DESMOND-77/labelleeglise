<?php
/**
 * La Belle Église — Configuration générale
 * ----------------------------------------------------------------
 * Modèle de données : schéma fourni par l'utilisateur
 * (base `la_belle_eglise_db`, tables centres / users / bacentas /
 * basontas / cultes / presences / offrandes / dimes / visites /
 * suivi_hebdo / examens / veillees / presentation / equipe /
 * centres_presentation).
 */

declare(strict_types=1);
    
/* ---------- Base de données ---------- */
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'la_belle_eglise_db';
const DB_USER = 'root';
const DB_PASS = '36090';
const DB_CHARSET = 'utf8mb4';

/* ---------- Application ---------- */
const APP_NAME = 'La Belle Église';
const APP_URL  = ''; // vide = chemins relatifs

/* ---------- Uploads ---------- */
const UPLOAD_DIR = __DIR__ . '/uploads';
const MAX_PHOTO_BYTES = 4 * 1024 * 1024; // 4 Mo

/* ---------- Rôles (enum de la table users) ---------- */
const ROLE_LABELS = [
    'admin'       => 'Administrateur',
    'leader'      => 'Leader',
    'assistant'   => 'Assistant',
    'pasteur'     => 'Pasteur',
    'reverant'    => 'Révérend',
    'membre'      => 'Membre',
    'responsable' => 'Responsable',
];
// Les "bergers" (fiche berger + suivi hebdomadaire) = ces rôles.
const BERGER_ROLES = [ 'leader', 'pasteur', 'reverant' ];

/* ---------- Constantes métier ---------- */

const BASSACENTA_DEFAULT = [ 'Sion', 'Bethel' ];
const BASONTAS_DEFAULT   = [ 'Chorale', 'Ashers', 'Film Start', 'Perfect Sound', 'Akwaba', 'Singing Start' ];
const CULTES_DEFAULT     = [ "Culte d'Impact", 'Culte Aman', 'Cultes Tschalac', 'Culte des Leaders' ];

const SECTION_LABELS = [
    'apropos'            => 'Présentation de l\'église',
    'centresPresentation'=> 'Présentation des centres',
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
];

const SECTION_ICONS = [
    'apropos'            => 'ℹ️',
    'centresPresentation'=> '🏫',
    'accueil'            => '🏠',
    'bacentas'           => '⛪',
    'centres'            => '🏛️',
    'cultes'             => '🙏',
    'basontas'           => '🎤',
    'nouveaux'           => '✨',
    'generale'           => '📋',
    'bergers'            => '🐑',
    'suiviBergers'       => '📅',
    'finances'           => '💰',
    'parametres'         => '⚙️',
];

// "apropos" doit rester en toute première position du menu (exigence produit).
const NAV_ORDER = [ 'apropos', 'centresPresentation', 'accueil', 'bacentas', 'centres', 'cultes',
                    'basontas', 'nouveaux', 'generale', 'bergers', 'suiviBergers', 'finances', 'parametres' ];

/* ---------- Champs d'un membre (table users) ---------- */

// Champs affichés/édités pour tout membre (sauf cas particuliers ci-dessous).
const BASE_USER_FIELDS = [ 'nom', 'prenom', 'telephone', 'email', 'quartier', 'date_naissance', 'role', 'bacenta_id', 'compte_actif' ];

const FIELD_LABELS = [
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
    'photo_de_profil'=> 'Photo de profil',
    // Présences (affichées par type)
    'presenceCulte'   => 'Présence Culte',
    'presenceBasonta' => 'Présence Basonta',
    'presenceCentre'  => 'Présence Centre',
    'presenceBacenta' => 'Présence Bacenta',
];

const PRESENCE_FIELDS = [ 'presenceCulte', 'presenceBasonta', 'presenceCentre', 'presenceBacenta' ];

// Champs supplémentaires dans les formulaires/tables selon la section.
const SECTION_EXTRA_FIELDS = [
    'nouveaux' => [ 'invite_par', 'recu_par', 'date_recu' ],
    'generale' => [ 'invite_par', 'recu_par', 'date_recu' ],
];

const CHART_POLES = [
    [ 'key' => 'bacentas', 'label' => 'Bacentas',               'color' => '#6C63FF' ],
    [ 'key' => 'centres',  'label' => 'Centres',                'color' => '#8B85FF' ],
    [ 'key' => 'cultes',   'label' => 'Cultes',                 'color' => '#FF9F7A' ],
    [ 'key' => 'basontas', 'label' => 'Basontas',               'color' => '#4CAF8E' ],
    [ 'key' => 'nouveaux', 'label' => 'Nouveaux membres',       'color' => '#4FB6E8' ],
    [ 'key' => 'generale', 'label' => 'Liste générale des membres', 'color' => '#F2B84B' ],
    [ 'key' => 'bergers',  'label' => 'Liste des bergers',      'color' => '#E86A6A' ],
];

const SLIDES = [
    [ 'gradient' => 'linear-gradient(135deg,#6C63FF,#8B85FF)', 'title' => 'Bienvenue à la belle église', 'subtitle' => 'Ensemble, grandissons dans la foi et la communion fraternelle.' ],
    [ 'gradient' => 'linear-gradient(135deg,#4CAF8E,#6C63FF)', 'title' => 'Une famille unie', 'subtitle' => 'Chaque bacenta, chaque centre, chaque basonta compte.' ],
    [ 'gradient' => 'linear-gradient(135deg,#FF9F7A,#E86A6A)', 'title' => 'Accueillons les nouveaux membres', 'subtitle' => 'Chaque visage est important à nos yeux.' ],
];

const OFFERING_DAY_LABEL = [ 'bacentas' => 'Vendredi', 'centres' => 'Mercredi' ];

const DIMANCHES_LABELS = [ 'Dimanche 1', 'Dimanche 2', 'Dimanche 3', 'Dimanche 4' ];

const MONTHS_FR = [ 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre' ];
const MONTHS_FR_SHORT = [ 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc' ];

const TEAM_CATEGORIES = [ 'Révérend', 'Pasteur', 'Berger', 'Leader', 'Autre' ];

const WEEK_DAYS = [ 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche' ];

const SUIVI_FIELDS = [
    [ 'key' => 'priere',         'label' => 'Temps de prière quotidien', 'type' => 'text' ],
    [ 'key' => 'meditation',     'label' => 'Temps de méditation', 'type' => 'text' ],
    [ 'key' => 'jourFlow',       'label' => 'Jour de prière du flow', 'type' => 'select' ],
    [ 'key' => 'livre',          'label' => 'Livre lu', 'type' => 'text' ],
    [ 'key' => 'themeEveque',    'label' => 'Thème — Prédication de l\'Évêque écoutée', 'type' => 'text' ],
    [ 'key' => 'themeReverend',  'label' => 'Thème — Prédication du Révérend écoutée', 'type' => 'text' ],
    [ 'key' => 'visites',        'label' => 'Personne(s) visitée(s) en semaine', 'type' => 'text' ],
    [ 'key' => 'invitesDimanche','label' => 'Personne(s) invitée(s) pour dimanche', 'type' => 'text' ],
    [ 'key' => 'invitesApres',   'label' => 'Invité(s) après le culte / deep sea fishing', 'type' => 'text', 'sundayOnly' => true ],
];
