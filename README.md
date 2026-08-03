# ⛪ La Belle Église — Application PHP (rendu serveur + MySQL)

Application **PHP** (server-side rendering) de gestion d'église, construite sur le
**modèle de données fourni** : base `la_belle_eglise_db` (tables `centres`, `users`,
`bacentas`, `basontas`, `cultes`, `presences`, `offrandes`, `dimes`, `visites`,
`suivi_hebdo`, `examens`, `veillees`, `presentation`, `equipe`, `centres_presentation`).

Toutes les pages sont générées côté serveur, les écritures passent par des formulaires
POST avec jeton CSRF, les mots de passe sont hachés. Seul un JavaScript léger subsiste
(graphiques Chart.js via CDN, carrousel, confirmations, menu mobile).

---

## 1. Installation

### Prérequis
- PHP ≥ 8.0 avec **pdo_mysql** (mbstring recommandée, sinon repli automatique)
- MySQL **ou** MariaDB ≥ 10.2
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

   > Le schéma d'origine utilisait `utf8mb4_0900_ai_ci` (propre à MySQL 8) ;
   > `utf8mb4_unicode_ci` est utilisé pour rester compatible MariaDB.

3. **Adapter les identifiants** dans `config.php` (constantes `DB_*`).
4. **Installer le schéma + les données de démonstration** :

   ```bash
   php install.php
   ```

   (ou ouvrir `install.php` dans le navigateur).

5. Ouvrir `index.php` et se connecter avec un email.

> ⚠️ Après installation, **supprimez `install.php`**. Relancer `php install.php`
> **réinitialise** entièrement la base (drop des tables + seed).

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

## 3. Modèle de données (correspondances avec l'ancienne app)

| Table | Rôle |
|---|---|
| `centres` | **La structure** (l'ancien niveau "quartier" de navigation) |
| `users` | Tous les membres / comptes. `quartier` = adresse/résidence (texte libre) |
| `bacentas` | Bacentas, rattachés à un centre (`centre_id`) + `responsable_id` |
| `basontas` | Ministères (Chorale…) — membres via `users_basontas` |
| `cultes` | Événements de culte (date, heures, responsable) |
| `presences` | Présences par événement (culte / bacenta / basonta / centre) |
| `offrandes` | Offrandes d'un bacenta **ou** d'un centre (mois + semaine 0-3) |
| `dimes`, `examens`, `veillees`, `suivi_hebdo` | Fiche berger + suivi hebdomadaire |
| `visites` | Visites effectuées (membre connu ou nouveau contact) |
| `presentation`, `equipe`, `centres_presentation` | Site vitrine (à propos, équipe, articles) |

**Définitions clés**
- **Centres** = structure de navigation (ex. Mingara, Mbaya…) ; les bacentas y sont
  rattachés. Le champ `users.quartier` n'est **pas** une structure : c'est l'adresse.
- **Bergers** = rôles `leader`, `pasteur`, `reverant` (constante `BERGER_ROLES` dans
  `config.php`, modifiable).
- **Nouveaux membres** = users ayant `invite_par`, `recu_par` ou `date_recu` renseignés.
- **Présences** : pointage par événement (page d'un culte : cocher les présents) **et**
  menu rapide « Présent / — » dans la fiche membre (dernier événement de chaque type).

---

## 4. Structure du projet

```
labelleeglise/
├── index.php              # Point d'entrée unique (front controller)
├── config.php             # Identifiants MySQL + constantes métier
├── helpers.php            # Échappement, dates, semaines ISO, CSRF, uploads…
├── db.php                 # Connexion PDO + micro-requêtes (qall/qone/qval/qexec)
├── auth.php               # Session, login (email), RBAC (responsable/berger), porte d'accès
├── data.php               # Couche d'accès aux données (CRUD, présences, stats)
├── actions.php            # Actions POST (écritures) + suppressions
├── pages*.php             # Fonctions de rendu des pages
├── install.php            # Création des tables + seed (à supprimer ensuite)
├── seed.php               # Données de démonstration
├── render.php             # Moteur de rendu (view/layout) + partiels
├── uploads/               # Photos téléversées (profils, équipe, articles)
├── assets/                # css/app.css + js/app.js (Chart.js, carrousel…)
└── views/templates/       # Templates serveur (HTML + <?= ?>)
```

---

## 5. Fonctionnalités

- **Authentification par email**, comptes actifs (`compte_actif`), mots de passe hachés.
- **RBAC** :
  - `admin` → accès complet ;
  - `responsable` → ses bacentas/cultes/basontas (responsable_id) + leurs centres ;
  - `leader`/`pasteur`/`reverant` (berger) → sa fiche, son suivi hebdo, son bacenta ;
  - `membre`/`assistant` → pages publiques ;
  - porte d'accès : confirmation d'identité (email ou nom + mot de passe) pour
    déverrouiller une liste/entité hors périmètre.
- **Sections** : bacentas (détail : Membres / Suivi & Offrandes), centres (structure :
  bacentas / offrandes), cultes (pointage de présence + présents), basontas (membres +
  ajout/retrait), nouveaux, liste générale, bergers (avec filtres).
- **Suivi & Offrandes** : 4 visites + 4 offrandes par mois et par bacenta ; offrandes
  des centres ; totaux mois/année.
- **Suivi hebdomadaire des bergers** : tableau Jour × Champ, % de réalisation
  semaine/année, graphique admin, sélecteur de date → semaine ISO.
- **Fiche berger** : infos, dîmes (12 mois/année), examens, veillées.
- **Finances** : cumuls Bacentas/Centres, tableau par entité, graphique comparatif.
- **Tableau de bord** : carrousel, compteurs par pôle, évolution cumulée sur 6 mois,
  comparaison, résumé statistique + synthèse.
- **Paramètres** : comptes (CRUD users, protection du dernier admin) + attribution des
  responsables (bacentas/basontas/cultes).
- **Présentation de l'église** : accroche + histoire (éditables), équipe regroupée par
  catégorie (Révérends, Pasteurs, Bergers, Leaders, Autres), photos.
- **Présentation des centres** : articles officiels liés aux centres (CRUD).
- **Recherche globale** (admin) → fiche profil avec donut de présence.

---

## 6. Sécurité

- Requêtes préparées PDO partout ; sorties échappées ; jetons CSRF sur tout POST.
- Mots de passe hachés (BCrypt) ; `install.php` à supprimer en production.
- `uploads/` accessible en écriture.
