# PROJECT_STRUCTURE — La Belle Église

Rôle détaillé de chaque dossier et fichier de l'application.

```
labelleeglise/
│
├── index.php                  # Front controller : bootstrap + dispatch (page/action)
├── install.php                # Installation (schéma + seed). À supprimer en production.
│
├── Bootstrap/                 # Lancement de l'application
│   ├── autoload.php           # Autoloader PSR-4 léger (sans Composer)
│   └── init.php               # Initialisation (config, session, erreurs, helpers)
│
├── Config/                    # Toute la configuration
│   ├── app.php                # Nom, URL, fuseau, uploads (max poids)
│   ├── auth.php               # Rôles + comptes bergers
│   ├── constants.php          # Constantes métier (sections, champs, libellés, menus)
│   ├── database.php           # Identifiants MySQL / MariaDB
│   └── paths.php              # Chemins absolus (racine, assets, storage, vues…)
│
├── Routes/
│   └── web.php                # Déclaration des routes (page → contrôleur/méthode)
│
├── app/                       # Logique applicative (namespace App\)
│   ├── Core/                  # Micro-framework maison
│   │   ├── Router.php         # Routeur (Router::get/post + dispatch)
│   │   ├── Database.php       # Connexion PDO singleton
│   │   ├── Query.php          # Helpers de requêtes (all/one/value/run/raw)
│   │   ├── View.php           # Moteur de rendu des vues
│   │   ├── Request.php        # Abstraction de la requête HTTP
│   │   ├── Response.php       # Réponse HTTP + redirections
│   │   ├── Session.php        # Gestion de session
│   │   ├── Csrf.php           # Jetons CSRF
│   │   ├── Logger.php         # Journalisation (Storage/logs/)
│   │   ├── Cache.php          # Cache fichier (Storage/cache/)
│   │   ├── Upload.php         # Gestion des uploads (validation, stockage)
│   │   └── Validator.php      # Validation centralisée
│   │
│   ├── Controllers/           # Contrôleurs HTTP (requête → service → vue)
│   │   ├── AuthController.php       # Login, logout, porte d'accès
│   │   ├── DashboardController.php  # Accueil (stats, graphiques)
│   │   ├── SectionController.php    # bacentas/centres/cultes/basontas/listes
│   │   ├── BergerController.php     # Fiche berger + suivi hebdo
│   │   ├── FinanceController.php    # Finances & offrandes
│   │   ├── SettingsController.php   # Paramètres (comptes, responsables)
│   │   ├── AboutController.php      # Présentation (à propos, équipe, centres)
│   │   ├── ProfileController.php    # Profil membre + recherche
│   │   ├── ActionsController.php    # Actions POST / suppressions
│   │   ├── RegistrationController.php      # Inscription publique + vérification email
│   │   ├── AdminRegistrationController.php # Administration des inscriptions (admin)
│   │   └── NotificationController.php      # Centre de notifications (page complète)
│   │
│   ├── Services/              # Logique métier
│   │   ├── AuthenticationService.php # Authentification
│   │   ├── MemberService.php        # Gestion des membres
│   │   ├── AttendanceService.php    # Présences
│   │   ├── ContributionService.php  # Offrandes & dîmes
│   │   ├── StatisticsService.php    # Statistiques & synthèse
│   │   ├── ReportService.php        # Rapports
│   │   ├── MailService.php          # Envoi d'emails (PHPMailer vendorisé)
│   │   ├── NotificationService.php  # Centre de notifications in-app
│   │   ├── RegistrationService.php  # Inscription/vérification/activation
│   │   └── BacentaMembershipService.php # Affectation de membres à un bacenta
│   │
│   ├── Repositories/          # Accès aux données (tout le SQL)
│   │   ├── CentreRepository.php
│   │   ├── BacentaRepository.php
│   │   ├── BasontaRepository.php
│   │   ├── CulteRepository.php
│   │   ├── MemberRepository.php
│   │   ├── UserRepository.php
│   │   ├── AttendanceRepository.php
│   │   ├── ContributionRepository.php
│   │   ├── BergerRepository.php
│   │   ├── CMSRepository.php
│   │   └── NotificationRepository.php
│   │
│   ├── Middleware/            # Couches transverses
│   │   ├── AuthMiddleware.php
│   │   ├── AdminMiddleware.php
│   │   ├── CsrfMiddleware.php
│   │   └── GateMiddleware.php
│   │
│   ├── Auth/
│   │   ├── AuthenticationService.php # Connexion, session
│   │   ├── RbacService.php           # RBAC (admin/responsable/berger)
│   │   └── compat.php                # Wrappers globaux (current_user, get_user_scope…)
│   │
│   ├── Helpers/
│   │   ├── helpers.php         # h, url, redirect, dates, semaines ISO, uploads…
│   │   └── rendering.php       # Partiels (breadcrumb, cards, badges, tables…)
│   │
│   ├── Compat/                # Wrappers rétrocompatibles pour les vues
│   │   ├── data.php            # Expose les fonctions de données aux vues
│   │   ├── pages.php           # Rendu des pages génériques
│   │   ├── structure.php       # Bacentas/centres/cultes/basontas
│   │   ├── sections.php        # Détails des sections (suivi, culte)
│   │   ├── bergers.php         # Fiche berger + suivi hebdo
│   │   ├── finances.php        # Finances
│   │   ├── parametres.php      # Paramètres
│   │   ├── apropos.php         # À propos + équipe + centres
│   │   └── notifications.php   # Notifications + administration des inscriptions
│   │
│   └── (Traits/ facultatif)    # Traits réutilisables
│
├── Views/                     # Couche de présentation (HTML)
│   ├── layouts/
│   │   └── layout.php         # Coquille applicative (sidebar + topbar + contenu)
│   ├── components/            # Composants réutilisables
│   ├── emails/                # Templates HTML des emails (rendus par MailService)
│   │   ├── verify-email.php
│   │   ├── registration-admin.php
│   │   └── account-activated.php
│   └── pages/                 # Templates par page
│       ├── forms/             #   Formulaires (member, user, culte, article…)
│       ├── partials/          #   Fragments (unit_card, unit_card_add)
│       ├── register.php, verify_email.php  # Inscription publique (pages autonomes)
│       ├── admin_inscriptions.php, admin_inscription_detail.php  # Administration
│       ├── notifications.php  #   Centre de notifications (page complète)
│       └── *.php              #   Templates principaux
│
├── Database/                  # Base de données
│   ├── Migrations/
│   │   └── 2024_01_01_000000_create_schema.php  # Schéma SQL complet
│   └── Seeders/
│       └── DatabaseSeeder.php # Données de démonstration
│
├── Storage/                   # Données persistantes (écrits)
│   ├── logs/                  #   Journaux d'application
│   ├── cache/                 #   Cache fichier
│   └── sessions/              #   (optionnel) sessions
│
├── assets/                    # Ressources publiques web
│   ├── css/                   #   CSS modulaire (variables, reset, layout…)
│   ├── js/                    #   JavaScript (app.js)
│   └── images/                #   Logo, fonds, images
│
├── uploads/                   # Photos téléversées (profils, équipe, articles)
│
├── README.md                  # Vue d'ensemble + installation
├── ARCHITECTURE.md            # Décisions d'architecture
├── PROJECT_STRUCTURE.md       # Ce fichier
├── CONTRIBUTING.md            # Conventions de contribution
└── TODO.md                    # Suivi des milestones
```
</content>
