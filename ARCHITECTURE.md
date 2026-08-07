# ARCHITECTURE — La Belle Église

Ce projet adopte une **architecture modulaire** inspirée de Laravel, sans framework.
Il est structuré selon les principes **Clean Architecture**, **MVC/MVT**, **SOLID**,
**DRY**, **KISS** et **YAGNI**, tout en restant exécutable immédiatement sur un
hébergement mutualisé (aucun Composer, aucun CLI, aucune installation).

---

## 1. Principes directeurs

- **Séparation des préoccupations** (Separation of Concerns) : chaque couche a un rôle unique.
- **MVC / MVT** : `Controllers` / `Views` ; le modèle est réparti entre `Repositories`
  (accès données), `Entities` implicites et `Services` (logique métier).
- **SOLID** : responsabilité unique, ouverture/fermeture, substitution, ségrégation
  d'interfaces, injection de dépendances légère.
- **DRY / KISS / YAGNI** : aucune logique dupliquée, solutions simples, pas d'anti-anticipation.
- **PSR-12** : style de code standardisé.
- **Léger** : aucun framework externe, aucun package manager, autoloader maison.

---

## 2. Flux de requête (Request Lifecycle)

```
Navigateur
   │  index.php?page=bacentas&id=1
   ▼
index.php  (front controller)
   │  Bootstrap/init.php  (autoloader, config, session, erreurs, helpers)
   ▼
POST ?  → ActionsController / AuthController (CSRF, logique d'écriture, redirect)
   │
GET ?   → current_user() → login si non connecté
   ▼
Routes/web.php  (Router::get('bacentas', SectionController::class, 'index'))
   ▼
Router::dispatch('GET', $page)
   ▼
Controller  (récupère la requête, appelle le Service/Repository)
   ▼
Service  (logique métier)  ──► Repository  (SQL préparé)  ──► Database (PDO)
   ▼
View  (rendu HTML via l'engine)  →  layout  +  pages/…
```

---

## 3. Couches

### 3.1 Contrôleurs (`app/Controllers`)

- Gèrent la **requête HTTP** (lecture `$_GET` / `$_POST`).
- Appellent la **validation**, les **services** et les **repositories**.
- Rendent les **vues**.
- **Aucun SQL**, **aucun HTML**, **aucune logique métier**.

### 3.2 Services (`app/Services`)

- Contiennent la **logique métier** (calculs, flux métier, orchestrations).
- Exemples : `StatisticsService`, `AttendanceService`, `ContributionService`,
  `AuthenticationService`, `MemberService`, `ReportService`.

### 3.3 Repositories (`app/Repositories`)

- Seuls responsables de l'**accès aux données** (tout le SQL).
- Exemples : `CentreRepository`, `MemberRepository`, `BacentaRepository`,
  `BasontaRepository`, `CulteRepository`, `AttendanceRepository`,
  `ContributionRepository`, `BergerRepository`, `CMSRepository`, `UserRepository`.

### 3.4 Core (`app/Core`)

- Micro-framework maison : `Router`, `Database` (PDO singleton), `Query`,
  `View` (moteur de rendu), `Request`, `Response`, `Session`, `Csrf`,
  `Logger`, `Cache`, `Upload`, `Validator`.

### 3.5 Middleware (`app/Middleware`)

- Couches transverses : `AuthMiddleware`, `AdminMiddleware`, `CsrfMiddleware`,
  `GateMiddleware`.

### 3.6 Auth (`app/Auth`)

- Authentification, RBAC, porte d'accès : `AuthenticationService`, `RbacService`.

### 3.7 Helpers (`app/Helpers`)

- Fonctions utilitaires globales : échappement, URL, dates, semaines ISO, CSRF,
  formulaires, uploads.

### 3.8 Compat (`app/Compat`)

- **Wrappers de compatibilité** exposant les fonctions globales utilisées par les
  vues (héritées de l'ancienne base de code) au-dessus des repositories/services.
  Garantit la **rétrocompatibilité** et évite de réécrire toutes les vues.

### 3.9 Views (`Views/`)

- **HTML uniquement** (avec de simples boucles/conditions `<?= ?>`).
- `layouts/` : coquille (sidebar, topbar).
- `pages/` : templates de chaque page (+ `forms/`, `partials/`).
- `components/` : composants réutilisables.

---

## 4. Routage

Le routage est **déclaratif** et préserve toutes les URL existantes
(`index.php?page=...`). Voir `Routes/web.php` :

```php
Router::get('bacentas', SectionController::class, 'index');
Router::post('save_histoire', ActionsController::class, 'saveHistoire');
```

Chaque clé `page` mappe un contrôleur + une méthode.

---

## 5. Configuration

Toute la configuration est **centralisée** dans `Config/` :

| Fichier | Rôle |
| --- | --- |
| `Config/database.php` | Identifiants MySQL/MariaDB |
| `Config/app.php` | Nom de l'app, URL, fuseau, uploads |
| `Config/auth.php` | Rôles, comptes bergers |
| `Config/constants.php` | Constantes métier (sections, champs, libellés) |
| `Config/paths.php` | Chemins absolus |

Aucun identifiant ni chemin n'est **hardcodé** ailleurs.

---

## 6. Base de données

- **Abstraction** : `app/Core/Database` (PDO singleton) + `app/Core/Query`
  (helpers `all/one/value/run/raw`).
- **Une seule connexion** réutilisée (performance).
- **Toutes les requêtes sont préparées** (anti-injection SQL).
- La configuration vient de `Config/database.php`.

---

## 7. Sécurité

- **XSS** : tout est échappé via `h()`.
- **CSRF** : jeton sur chaque POST, vérifié via `check_csrf()`.
- **SQL Injection** : requêtes préparées partout.
- **Mots de passe** : hachés (BCrypt).
- **Sessions** : `session_regenerate_id()` au login, session sécurisée.
- **Uploads** : validation type/image + taille, nom aléatoire.

---

## 8. Performance

- Une seule connexion PDO (singleton).
- Requêtes regroupées dans les repositories (pas de duplication).
- Autoloader léger (chargement à la demande).
- Cache simple dans `Storage/cache/` (`app/Core/Cache`).

---

## 9. Compatibilité hébergement mutualisé

- **Aucun Composer** : autoloader PSR-4 maison (`Bootstrap/autoload.php`).
- **Aucun CLI** : rien à lancer après copie.
- **Aucune installation** : `index.php` est le point d'entrée.
- **`assets/` et `uploads/`** restent à la racine pour préserver les URL/images.
- **Écritures** : `Storage/logs/`, `Storage/cache/`, `Storage/sessions/`
  (créés dynamiquement si nécessaire).
</content>
