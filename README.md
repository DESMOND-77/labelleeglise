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
│   └── components/        # Composants réutilisables
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
- **RBAC** : admin, responsable, berger (leader/pasteur/reverant), membre, porte d'accès.
- **Sections** : bacentas, centres, cultes (pointage), basontas, nouveaux, liste
  générale, bergers.
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

---

## 6. Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) — décision d'architecture, principes SOLID/DRY/KISS.
- [PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md) — rôle de chaque dossier/fichier.
- [CONTRIBUTING.md](CONTRIBUTING.md) — conventions, PSR-12, workflow de contribution.
</content>
