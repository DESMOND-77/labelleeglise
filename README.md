# ⛪ La Belle Église — Application PHP modulaire

Application **PHP** (server-side rendering) de gestion d'église, construite sur un
**modèle de données MySQL/MariaDB**. Ce projet a été réorganisé selon une
**architecture modulaire professionnelle** inspirée de Laravel, tout en restant
**100 % compatible hébergement mutualisé** : aucune dépendance, aucun Composer,
aucun CLI, aucune installation. Il suffit de copier les fichiers dans la racine web.

- **Base de données** : `la_belle_eglise_db`
- **Contrôleur frontal** : `index.php` (toutes les URL passent par `index.php?page=...`)
- **Rendu** : server-side (PHP), JavaScript léger (Chart.js, confirmations, menu)

---

## 1. Installation

### Prérequis
- **PHP ≥ 8.0** avec `pdo_mysql` (mbstring recommandée, sinon repli automatique)
- **MySQL** ou **MariaDB ≥ 10.2**
- Un serveur web pointant vers ce dossier

### Étapes
1. **Copier le projet** dans votre dossier web (ex. `/var/www/html/labelleeglise`).
2. **Créer la base de données** et un utilisateur :

   ```sql
   CREATE DATABASE la_belle_eglise_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'lbegf'@'localhost' IDENTIFIED BY 'VOTRE_MOT_DE_PASSE';
   GRANT ALL PRIVILEGES ON la_belle_eglise_db.* TO 'lbegf'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. **Adapter les identifiants** dans `Config/database.php`.
4. **Installer le schéma + les données de démonstration** :

   ```bash
   php install.php
   ```

   (ou ouvrir `install.php` dans le navigateur).

5. Ouvrir `index.php` et se connecter.

> ⚠️ Après installation, **supprimez `install.php`**. Le relancer **réinitialise**
> entièrement la base (drop des tables + seed).

### Démarrage rapide en local

```bash
cd labelleeglise
php -S 127.0.0.1:8000
# puis http://127.0.0.1:8000/index.php
```

---

## 2. Comptes de démonstration (connexion par email)

| Email | Mot de passe | Périmètre |
|---|---|---|
| `admin@labelleeglise.ga` | `LBEGF` | Accès complet (admin) |
| `user@labelleeglise.ga` | `user1111` | Membre (pages publiques) |
| `resp.bacenta.sion@labelleeglise.ga` | `ESKLna` | Responsable du Bacenta Sion |
| `berger.eric.bongo@labelleeglise.ga` | `BergerEB1` | Berger (leader) — fiche, suivi, bacenta |

---

## 3. Structure du projet

```
labelleeglise/
├── index.php              # Point d'entrée unique (front controller)
├── install.php            # Installation (droppable en production)
├── Bootstrap/             # Autoloader PSR-4 léger + initialisation
├── Config/                # Configuration (BDD, app, auth, constantes, chemins)
├── Routes/                # Déclaration des routes (web.php)
├── app/
│   ├── Core/              # Noyau framework-libre (Router, Database, View…)
│   ├── Controllers/       # Contrôleurs (requêtes HTTP, rendu)
│   ├── Services/          # Logique métier (business layer)
│   ├── Repositories/      # Accès aux données (tout le SQL)
│   ├── Middleware/        # Auth, Admin, CSRF, Gate
│   ├── Auth/              # Session, RBAC, porte d'accès
│   ├── Helpers/           # Fonctions utilitaires
│   └── Compat/            # Wrappers de compatibilité pour les vues
├── Views/
│   ├── layouts/           # Coquille applicative
│   ├── pages/             # Templates de pages (+ forms/, partials/)
│   ├── components/        # Composants réutilisables
│   └── emails/            # Templates HTML des emails (PHPMailer)
├── Database/
│   ├── Migrations/        # Schéma SQL
│   └── Seeders/           # Données de démonstration
├── Storage/
│   ├── logs/              # Journaux
│   ├── cache/             # Cache
│   └── sessions/          # (optionnel) sessions
├── assets/                # CSS modulaire + JS + images (public)
├── uploads/               # Photos téléversées (public)
└── docs (README, ARCHITECTURE, CONTRIBUTING, PROJECT_STRUCTURE)
```

---

## 4. Fonctionnalités

- **Authentification par email**, comptes actifs, mots de passe hachés (BCrypt).
- **Inscription publique + vérification email + validation admin** (voir §7 ci-dessous).
- **Centre de notifications** in-app (cloche topbar + page dédiée).
- **RBAC** : admin, responsable, berger (leader/pasteur/reverant), membre, porte d'accès.
- **Sections** : bacentas, centres, cultes (pointage), basontas, nouveaux, liste
  générale, bergers.
- **Affectation de membres aux bacentas** par leur responsable (voir §7.6).
- **Suivi & offrandes** : visites + offrandes par bacenta/centre, totaux mois/année.
- **Suivi hebdomadaire des bergers** : tableau Jour × Champ, % de réalisation.
- **Fiche berger** : infos, dîmes, examens, veillées.
- **Finances** : cumuls Bacentas/Centres, graphique comparatif.
- **Tableau de bord** : carrousel, compteurs, évolution sur 6 mois, synthèse.
- **Paramètres** : comptes (CRUD users) + attribution des responsables.
- **Présentation** : à propos + équipe + articles des centres.
- **Recherche globale** (admin) → fiche profil avec donut de présence.

---

## 5. Sécurité

- Requêtes préparées PDO partout ; sorties échappées ; jetons CSRF sur tout POST.
- Mots de passe hachés (BCrypt) ; `install.php` à supprimer en production.
- `uploads/` accessible en écriture ; validation des types/images au téléversement.
- Jeton de vérification d'email généré par `random_bytes()`, stocké haché (SHA-256),
  usage unique, expiration 24h (voir §7.2).
- Rôle **toujours** forcé à `membre` côté serveur à l'inscription publique — jamais lu
  depuis la requête HTTP.
- Activation de compte et affectation aux bacentas protégées par un contrôle
  d'autorisation serveur (`AdminMiddleware`, RBAC) — jamais un simple bouton masqué.

---

## 6. Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) — décision d'architecture, principes SOLID/DRY/KISS.
- [PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md) — rôle de chaque dossier/fichier.
- [CONTRIBUTING.md](CONTRIBUTING.md) — conventions, PSR-12, workflow de contribution.

---

## 7. Inscription publique, vérification email, validation admin, notifications, affectation

### 7.1 Vue d'ensemble du workflow

```
Visiteur → Formulaire d'inscription (?page=register)
        → Compte créé : role=membre, email_verified=0, account_status=pending
        → Email de vérification envoyé (lien ?page=verify_email&token=...)
        → Visiteur clique → email_verified=1 (account_status reste "pending")
        → Tous les comptes role=admin sont notifiés (email + notification in-app)
        → Un admin consulte la demande (Administration → Inscriptions)
        → Admin clique "Activer le compte" → account_status=active, compte_actif=1
        → Email de confirmation envoyé à l'utilisateur
        → L'utilisateur peut se connecter
        → Un responsable de bacenta voit ce membre dans "Ajouter des membres"
          (bacenta géré, section Membres) et l'affecte (transaction SQL)
```

### 7.2 Schéma de base de données

Colonnes ajoutées à `users` (ajoutées de façon idempotente — voir
`Database/Migrations/2024_01_01_000000_create_schema.php`, fonction `up()` ;
compatible ré-exécution sur une base déjà à jour) :

| Colonne                     | Type                                         | Rôle |
|------------------------------|-----------------------------------------------|------|
| `email_verified`             | `TINYINT(1) NOT NULL DEFAULT 0`               | Email confirmé via le lien reçu |
| `email_verified_at`          | `DATETIME NULL`                               | Horodatage de la confirmation |
| `verification_token`         | `VARCHAR(255) NULL`, indexé                   | **Hash SHA-256** du jeton (jamais la valeur en clair) |
| `verification_expires_at`    | `DATETIME NULL`                               | Expiration du jeton (24h après inscription) |
| `account_status`             | `ENUM('pending','active','disabled')`, défaut `pending` | Statut de validation administrative |

`compte_actif` (colonne préexistante, déjà utilisée par le login) reste
synchronisée automatiquement : `1` quand `account_status = active`, `0` sinon —
tout le code existant qui lit `compte_actif` continue de fonctionner sans
modification.

> Compatibilité ascendante : lors de la migration, tous les comptes déjà
> `compte_actif = 1` sont automatiquement marqués `email_verified = 1` et
> `account_status = 'active'`, pour ne jamais bloquer les connexions
> existantes. Le seeder de démonstration applique la même règle.

Nouvelle table `notifications` (centre de notifications in-app) :

| Colonne        | Type                                   |
|-----------------|-----------------------------------------|
| `id`            | `INT AUTO_INCREMENT PRIMARY KEY`        |
| `recipient_id`  | `INT NOT NULL` (FK `users.id`, cascade) |
| `type`          | `VARCHAR(60)` (ex. `new_registration`)  |
| `title`         | `VARCHAR(150)`                          |
| `message`       | `TEXT`                                  |
| `link`          | `VARCHAR(255)` (URL relative interne)   |
| `is_read`       | `TINYINT(1) NOT NULL DEFAULT 0`         |
| `created_at`    | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP`   |

**Étape manuelle requise après mise à jour du code** : exécuter à nouveau
`php install.php` (environnement de développement — réinitialise tout) **ou**,
en production, exécuter uniquement les nouvelles instructions idempotentes du
fichier de migration (ALTER TABLE + CREATE TABLE notifications) via un petit
script `php -r` appelant `\Database\Migrations\up()` sans passer par `down()`.
`up()` ne supprime jamais de données ; seul `down()` (appelé par `install.php`)
réinitialise la base.

### 7.3 Emails (PHPMailer)

`app/Services/MailService.php` centralise l'envoi (aucun HTML dans les
contrôleurs/services métier) via l'installation **vendorisée** de PHPMailer
(`app/Core/PHPMailer/`, mappée en PSR-4 sur le namespace `PHPMailer\PHPMailer`
dans `Bootstrap/autoload.php` — aucun Composer). Templates HTML séparés :

- `Views/emails/verify-email.php` — lien de vérification (durée 24h affichée).
- `Views/emails/registration-admin.php` — notification admin (identité,
  contact, date, statut, bouton "Voir la demande").
- `Views/emails/account-activated.php` — confirmation d'activation.

**Configuration SMTP** (`Config/mail.php`) — lue depuis des **variables
d'environnement** (jamais codées en dur) :

```
SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_ENCRYPTION,
SMTP_AUTH, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, APP_BASE_URL, SMTP_DEBUG
```

À définir sur le serveur de production (configuration Apache/PHP-FPM,
panneau d'hébergement, ou `putenv()` dans un fichier non versionné chargé
avant `Bootstrap/init.php`). **Tant que `SMTP_HOST` n'est pas configuré, les
emails ne sont pas envoyés** : ils sont journalisés dans `Storage/logs/mail.log`
(et `Storage/logs/app-*.log`) sans jamais faire échouer l'inscription, la
vérification ou l'activation en cours — une erreur d'envoi est toujours
non bloquante.

### 7.4 Nouvelles routes

| Méthode | `?page=`             | Contrôleur · méthode                         | Accès |
|---------|----------------------|-----------------------------------------------|-------|
| GET     | `register`           | `RegistrationController::form`                 | Public |
| POST    | (`action=register`)  | `RegistrationController::submit`               | Public + CSRF |
| GET     | `verify_email`       | `RegistrationController::verify`               | Public (`?token=`) |
| GET     | `admin_inscriptions` | `AdminRegistrationController::index`           | Admin |
| GET     | `admin_inscription`  | `AdminRegistrationController::show`            | Admin (`?id=`) |
| POST    | (`action=admin_activate_account`) | `ActionsController::postAction`   | Admin + CSRF |
| POST    | (`action=admin_reject_account`)   | `ActionsController::postAction`   | Admin + CSRF |
| GET     | `notifications`      | `NotificationController::index`                | Connecté |
| GET     | (`action=notification_open`)         | `ActionsController::getAction` | Connecté |
| GET     | (`action=notification_mark_read`)    | `ActionsController::getAction` | Connecté |
| GET     | (`action=notification_mark_all_read`)| `ActionsController::getAction` | Connecté |
| POST    | (`action=bacenta_assign_members`)    | `ActionsController::postAction` | Responsable/Admin + CSRF |

`register` et `verify_email` sont les deux seules pages accessibles **sans
connexion** (voir `index.php`) ; toutes les autres pages ci-dessus exigent une
session active, et les pages `admin_*` exigent en plus le rôle `admin`
(`AdminMiddleware`, vérifié côté serveur).

### 7.5 Connexion

`AuthenticationService::authenticate()` distingue précisément la raison d'un
refus de connexion (email/mot de passe invalide, email non vérifié, compte
en attente de validation admin, compte désactivé) et `Views/pages/login.php`
affiche un message dédié pour chacun de ces cas.

### 7.6 Affectation des membres à un bacenta

Sur la fiche d'un bacenta (onglet **Membres**), en plus du formulaire
d'ajout existant (création d'un nouveau membre), une section **« Ajouter des
membres »** liste les comptes `role=membre`, `account_status=active`,
`email_verified=1`, sans bacenta, avec recherche (nom/prénom/email/téléphone)
et sélection multiple. Le bacenta cible n'est **jamais** accepté tel quel
depuis le formulaire : `BacentaMembershipService::authorizedBacentaId()`
revérifie qu'il fait bien partie des bacentas gérés par le responsable
courant (`RbacService::myBacentaIds()`) ou que l'utilisateur est admin.
Chaque identifiant de membre soumis est ensuite **revalidé individuellement**
(`UserRepository::findEligibleUnassignedMember()`) avant d'être affecté, le
tout dans une transaction SQL unique (`Query::transaction()`).

### 7.7 Permissions — résumé

| Action                              | Qui |
|--------------------------------------|-----|
| S'inscrire publiquement               | Tout le monde (non connecté) |
| Vérifier son email                    | Le porteur du lien reçu par email |
| Consulter la liste des inscriptions   | `role = admin` uniquement |
| Activer / refuser une inscription     | `role = admin` uniquement (contrôle serveur) |
| Voir/marquer ses notifications        | Le destinataire connecté (`recipient_id`) |
| Affecter des membres à un bacenta     | `role = admin`, ou `role = responsable` **uniquement pour les bacentas qu'il gère** |
</content>
