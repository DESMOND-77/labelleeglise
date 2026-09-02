# M4 — Calendrier événementiel + Calendrier d'anniversaires — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter deux pages : un **calendrier événementiel** (nom, date & heure début-fin, lieu, responsable — CRUD par les gestionnaires de calendrier) et un **calendrier d'anniversaires** (fusion automatique des `users.date_naissance` + saisies manuelles pour les personnes sans compte ; âge calculé si l'année est connue ; mois courant surligné). En complément, une occurrence d'événement devient pointable (addendum M1).

**Architecture:** Deux nouvelles tables (`evenements`, `anniversaires`) dans le fichier de migration unique. Code neuf en couches strictes : `CalendrierController` → `CalendrierService` → (`EvenementRepository` | `AnniversaireRepository`) → `App\Core\Query`. Deux nouvelles routes GET (`calendrier`, `anniversaires`) et quatre actions (`save_evenement`, `delete_evenement`, `save_anniversaire`, `delete_anniversaire`). Nouveau helper RBAC global `auth_can_manage_calendar()` = admin OU détenteur d'au moins une responsabilité `manager`. L'addendum M1 ajoute `presences.evenement_id`, étend le moteur de présence (`AttendanceRepository::UNIT_COLUMNS`) et l'action `save_presence_occurrence` au type `evenement`, et expose une fiche événement avec pointage.

**Tech Stack:** PHP 8 SSR, micro-framework maison, zéro dépendance. MySQL/MariaDB via `App\Core\Query`. Pas de PHPUnit — vérification = `php -l` + scripts d'assertion `php` exécutés contre la base de dev + smoke-render des vues.

**Spec:** `docs/superpowers/specs/2026-09-01-integration-modules-eglise-design.md` (§4 « M4 » + addendum M1)

## Global Constraints

- Zéro dépendance externe : pas de Composer, npm, build, Docker.
- PSR-12, `declare(strict_types=1)`, types sur paramètres et retours.
- Couches strictes : SQL uniquement dans un Repository ; HTML uniquement dans une View ; `$_POST`/`$_GET` uniquement dans un Controller / `ActionsController`.
- Schéma : instructions idempotentes uniquement, dans `Database/Migrations/2024_01_01_000000_create_schema.php`, gardées par `column_exists()` / `index_exists()` (existent). Toute nouvelle table est ajoutée à `down()`.
- CSS modulaire sous `assets/css/`, `@import` dans `assets/css/app.css`, variables de `assets/css/variables.css` (`--primary --card --border --text --text-muted --success --danger --warning --space-1..12 --radius* --shadow*`), aucun style/script inline dans une vue.
- Ne jamais casser une URL, l'auth, un formulaire existant. On ajoute des pages ; on n'en modifie aucune.
- RBAC sur les données, jamais seulement l'affichage. Un `responsable_id` / `evenement_id` / `id` reçu n'est jamais fait confiance : re-vérifier côté serveur.
- `install.php` reste supprimable.
- Comptes de démo : `admin@labelleeglise.ga` / `LBEGF` (admin) ; `berger.eric.bongo@labelleeglise.ga` / `BergerEB1` (berger, détient des responsabilités) ; `user@labelleeglise.ga` / `user1111` (membre simple).
- Base de dev joignable : MySQL `127.0.0.1:3306`, `root`, db `la_belle_eglise_db` (`.env` configuré).

## Décisions de cadrage (spec §7 + §4 M4 — tranchées ici, spec = autorité)

1. **Un seul `CalendrierService`** (pas d'`EvenementService` + `AnniversaireService` séparés) : les deux fonctionnalités partagent page et navigation et sont petites. Deux **repositories** distincts (un par table — norme projet).
2. **Gestion via `responsibilities`** : `auth_can_manage_calendar()` = `current_user` est admin OU possède ≥ 1 ligne dans `responsibilities` (n'importe quel `target_type`, `responsibility_type = 'manager'`). Édition/suppression d'un **événement** : admin OU `created_by` OU `responsable_id` de l'événement.
3. **Visibilité** : les deux pages exigent une session. Les contrôles d'édition (formulaires, boutons Supprimer) ne s'affichent que si `auth_can_manage_calendar()`. Un membre simple voit les calendriers en **lecture seule**. Liens de menu : admin (via `NAV_ORDER`) + utilisateurs `berger`-scope qui `auth_can_manage_calendar()`.
4. **Anniversaires manuels** : table `anniversaires` (nom, jour, mois, année facultative). Vue = fusion `users` (date_naissance non NULL) + `anniversaires`, triée par (mois, jour). Âge affiché seulement si l'année est connue (`users.date_naissance` complète, ou `anniversaires.annee` non NULL). Mois courant surligné. **Pas** de masquage de membres du calendrier (spec §6b : non demandé).
5. **Addendum M1 — pointage d'événement** : `save_presence_occurrence` accepte `unit_type = 'evenement'`, population = ensemble des membres (comme un culte). Accessible depuis une **fiche événement** `?page=calendrier&evt=<id>` (pointage d'une date). **Pas de matrice annuelle** pour un événement (non récurrent — dépourvu de sens). L'index `uniq_presence` est reconstruit pour inclure `evenement_id`.
6. **Vue calendrier = liste chronologique** (tableau trié par date), pas de grille mensuelle type agenda. YAGNI : la grille visuelle demanderait beaucoup de CSS/JS pour peu de valeur ; le texte « Vue type agenda (ou tableau chronologique) » de la spec autorise le tableau.

## File Structure

| Fichier | Rôle | Action |
|---|---|---|
| `Database/Migrations/2024_01_01_000000_create_schema.php` | Migration unique | Modifier : bloc « 11 » — tables `evenements`, `anniversaires`, `presences.evenement_id` + reconstruction `uniq_presence` ; `down()` |
| `Config/constants.php` | Constantes | Modifier : `SECTION_LABELS`, `SECTION_ICONS`, `NAV_ORDER` (`calendrier`, `anniversaires`) |
| `app/Auth/compat.php` | Wrappers RBAC globaux | Modifier : `auth_can_manage_calendar()`, `auth_can_edit_evenement(array $evt)` |
| `Views/layouts/layout.php` | Sidebar | Modifier : liens calendriers pour le scope `berger` gestionnaire de calendrier |
| `app/Repositories/EvenementRepository.php` | SQL événements | Créer |
| `app/Repositories/AnniversaireRepository.php` | SQL anniversaires manuels | Créer |
| `app/Services/CalendrierService.php` | Logique calendriers (événements + anniversaires fusionnés) | Créer |
| `app/Controllers/CalendrierController.php` | HTTP → vue | Créer : `evenements()`, `anniversaires()`, `evenementFiche()` |
| `Routes/web.php` | Routes | Modifier : `calendrier`, `anniversaires` |
| `Views/pages/calendrier.php` | Liste événements + formulaire + fiche | Créer |
| `Views/pages/anniversaires.php` | Calendrier d'anniversaires + formulaire manuel | Créer |
| `assets/css/calendrier.css` | Styles M4 | Créer + `@import` dans `app.css` |
| `app/Controllers/ActionsController.php` | Dispatch POST/GET | Modifier : `save_evenement`, `save_anniversaire` (postAction) ; `delete_evenement`, `delete_anniversaire` (getAction) ; `save_presence_occurrence` accepte `evenement` |
| `app/Repositories/AttendanceRepository.php` | SQL présences | Modifier : `UNIT_COLUMNS` + `'evenement' => 'evenement_id'` |
| `app/Compat/data.php` | Wrappers globaux | Modifier : `calendrier_service()` accessor si besoin (sinon `_repo(CalendrierService::class)`) |

---

### Task 1: Schéma — tables `evenements`, `anniversaires` + addendum présence événement

**Files:**
- Modify: `Database/Migrations/2024_01_01_000000_create_schema.php` (fin de `up()`, après le bloc « 10 » de M1 ; et `down()`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_schema_check.php`

**Interfaces:**
- Consumes: rien.
- Produces :
  - Table `evenements (id, nom VARCHAR(150) NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NULL, lieu VARCHAR(150) NULL, responsable_id INT NULL, created_by INT NULL, created_at TIMESTAMP)` — FK `responsable_id` / `created_by` → `users(id)` ON DELETE SET NULL ; index `idx_evt_debut (date_debut)`.
  - Table `anniversaires (id, nom VARCHAR(150) NOT NULL, jour TINYINT NOT NULL, mois TINYINT NOT NULL, annee SMALLINT NULL, created_by INT NULL, created_at TIMESTAMP)` — FK `created_by` → `users(id)` ON DELETE SET NULL ; index `idx_anniv_mois (mois, jour)`.
  - `presences.evenement_id INT NULL` — FK → `evenements(id)` ON DELETE CASCADE.
  - Index `uniq_presence` reconstruit : `(user_id, date_presence, culte_id, bacenta_id, basonta_id, centre_id, evenement_id)`.
  - `down()` : `evenements`, `anniversaires` ajoutées à la liste des tables droppées.

- [ ] **Step 1: Écrire l'assertion qui échoue**

Créer `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_schema_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;

function tbl(string $t): bool {
    return (bool) Query::value(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t]
    );
}
function col(string $t, string $c): bool {
    return (bool) Query::value(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$t, $c]
    );
}
function idxCols(string $t, string $i): array {
    $rows = Query::all(
        'SELECT column_name FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? ORDER BY seq_in_index', [$t, $i]
    );
    return array_map(static fn($r) => $r['column_name'] ?? $r['COLUMN_NAME'], $rows);
}

assert(tbl('evenements'), 'table evenements manquante');
assert(tbl('anniversaires'), 'table anniversaires manquante');
foreach (['nom','date_debut','date_fin','lieu','responsable_id','created_by','created_at'] as $c) {
    assert(col('evenements', $c), "evenements.$c manquante");
}
foreach (['nom','jour','mois','annee','created_by','created_at'] as $c) {
    assert(col('anniversaires', $c), "anniversaires.$c manquante");
}
assert(col('presences', 'evenement_id'), 'presences.evenement_id manquante');
assert(idxCols('presences', 'uniq_presence') === ['user_id','date_presence','culte_id','bacenta_id','basonta_id','centre_id','evenement_id'],
    'uniq_presence non reconstruit: ' . implode(',', idxCols('presences', 'uniq_presence')));

echo "OK m4 schema\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_schema_check.php`
Expected: FAIL — `AssertionError: table evenements manquante`.

- [ ] **Step 3: Ajouter le bloc de migration**

Dans `up()`, tout à la fin (après le bloc « 10. M1 — Présences par occurrence », avant l'accolade fermante) :

```php

    /* ---- 11. M4 — Calendriers (événements + anniversaires) --------------
     * a) evenements : nom, plage date/heure, lieu, responsable, créateur.
     * b) anniversaires : saisies manuelles (personnes sans compte). Les
     *    anniversaires des membres sont dérivés de users.date_naissance.
     * c) Addendum M1 : une occurrence d'événement devient pointable —
     *    presences.evenement_id + reconstruction de l'index uniq_presence.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS evenements (
            id INT NOT NULL AUTO_INCREMENT,
            nom VARCHAR(150) NOT NULL,
            date_debut DATETIME NOT NULL,
            date_fin DATETIME NULL,
            lieu VARCHAR(150) NULL,
            responsable_id INT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_evt_debut (date_debut),
            CONSTRAINT fk_evt_resp    FOREIGN KEY (responsable_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_evt_creator FOREIGN KEY (created_by)     REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS anniversaires (
            id INT NOT NULL AUTO_INCREMENT,
            nom VARCHAR(150) NOT NULL,
            jour TINYINT NOT NULL,
            mois TINYINT NOT NULL,
            annee SMALLINT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_anniv_mois (mois, jour),
            CONSTRAINT fk_anniv_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if (!column_exists($pdo, 'presences', 'evenement_id')) {
        $pdo->exec("ALTER TABLE presences ADD COLUMN evenement_id INT NULL AFTER basonta_id");
        $pdo->exec("ALTER TABLE presences ADD CONSTRAINT fk_pres_evt FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE");
    }
    // Reconstruire l'index d'unicité pour inclure evenement_id (idempotent :
    // on ne le refait que si la définition actuelle ne contient pas déjà
    // 7 colonnes).
    $uniqCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = 'presences' AND index_name = 'uniq_presence'"
    )->fetchColumn();
    if ($uniqCount === 6) {
        $pdo->exec("DROP INDEX uniq_presence ON presences");
        $pdo->exec(
            "CREATE UNIQUE INDEX uniq_presence
                ON presences (user_id, date_presence, culte_id, bacenta_id, basonta_id, centre_id, evenement_id)"
        );
    }
```

- [ ] **Step 4: Mettre à jour `down()`**

Dans `down()`, la liste `$tables` — ajouter `'evenements'` et `'anniversaires'` juste après `'presences'` :

```php
    $tables = ['responsibilities', 'notifications', 'users_basontas', 'presences', 'evenements', 'anniversaires', 'offrandes', 'visites', 'suivi_hebdo', 'dimes',
               'examens', 'veillees', 'cultes', 'basontas', 'bacentas', 'users',
               'centres_presentation', 'equipe', 'presentation', 'centres'];
```

- [ ] **Step 5: Appliquer la migration**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -r 'require "Bootstrap/init.php"; require "Database/Migrations/2024_01_01_000000_create_schema.php"; \Database\Migrations\up(); echo "up() OK\n";'`
Expected: `up() OK`.

- [ ] **Step 6: Relancer l'assertion, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_schema_check.php`
Expected: PASS — `OK m4 schema`

- [ ] **Step 7: Idempotence**

Run: `php -r 'require "Bootstrap/init.php"; require "Database/Migrations/2024_01_01_000000_create_schema.php"; \Database\Migrations\up(); echo "re-run OK\n";'`
Expected: `re-run OK`, aucune erreur (aucun `DROP INDEX`/`ADD CONSTRAINT` rejoué).

- [ ] **Step 8: Lint + commit**

```bash
php -l Database/Migrations/2024_01_01_000000_create_schema.php
git add Database/Migrations/2024_01_01_000000_create_schema.php
git commit -m "$(cat <<'EOF'
feat(calendriers): schéma evenements + anniversaires + présence d'événement

Bloc de migration 11 : tables evenements et anniversaires ; addendum M1
presences.evenement_id (FK CASCADE) et reconstruction de l'index unique
uniq_presence à 7 colonnes. down() mis à jour.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 2: RBAC + navigation

**Files:**
- Modify: `app/Auth/compat.php` (près de `auth_can_manage_responsibilities`, vers la ligne 97)
- Modify: `Config/constants.php` (`SECTION_LABELS`, `SECTION_ICONS`, `NAV_ORDER`)
- Modify: `Views/layouts/layout.php` (bloc scope `berger`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_rbac_check.php`

**Interfaces:**
- Consumes: rien.
- Produces :
  - `auth_can_manage_calendar(): bool` — `true` si `current_user()` est admin, ou si `App\Core\Query::value("SELECT COUNT(*) FROM responsibilities WHERE user_id = ? AND responsibility_type = 'manager'", [id])` > 0.
  - `auth_can_edit_evenement(array $evt): bool` — `true` si admin, ou `current_user()['id'] === (int) $evt['created_by']`, ou `=== (int) $evt['responsable_id']`.
  - `SECTION_LABELS['calendrier'] = 'Calendrier'`, `SECTION_LABELS['anniversaires'] = 'Anniversaires'` ; mêmes clés dans `SECTION_ICONS` (`<i class="fa-solid fa-calendar-day"></i>`, `<i class="fa-solid fa-cake-candles"></i>`) ; `NAV_ORDER` reçoit `'calendrier'` et `'anniversaires'` avant `'parametres'`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_rbac_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';

assert(function_exists('auth_can_manage_calendar'), 'auth_can_manage_calendar absente');
assert(function_exists('auth_can_edit_evenement'), 'auth_can_edit_evenement absente');
assert(isset(SECTION_LABELS['calendrier'], SECTION_LABELS['anniversaires']), 'SECTION_LABELS incomplet');
assert(isset(SECTION_ICONS['calendrier'], SECTION_ICONS['anniversaires']), 'SECTION_ICONS incomplet');
assert(in_array('calendrier', NAV_ORDER, true) && in_array('anniversaires', NAV_ORDER, true), 'NAV_ORDER incomplet');

// auth_can_edit_evenement logique pure (sans session : current_user() = null → false)
assert(auth_can_edit_evenement(['created_by' => 1, 'responsable_id' => 2]) === false, 'sans session, édition doit être refusée');

echo "OK m4 rbac\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_rbac_check.php`
Expected: FAIL — `AssertionError: auth_can_manage_calendar absente`.

- [ ] **Step 3: Ajouter les helpers RBAC**

`app/Auth/compat.php`, après `auth_can_manage_responsibilities()` :

```php
/** Gestionnaire de calendrier : admin OU détenteur d'≥1 responsabilité `manager`. */
function auth_can_manage_calendar(): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (($u['role'] ?? '') === 'admin') {
        return true;
    }
    return (int) \App\Core\Query::value(
        "SELECT COUNT(*) FROM responsibilities WHERE user_id = ? AND responsibility_type = 'manager'",
        [(int) $u['id']]
    ) > 0;
}

/** Édition/suppression d'UN événement : admin, son créateur, ou son responsable. */
function auth_can_edit_evenement(array $evt): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (($u['role'] ?? '') === 'admin') {
        return true;
    }
    $uid = (int) $u['id'];
    return $uid === (int) ($evt['created_by'] ?? 0) || $uid === (int) ($evt['responsable_id'] ?? 0);
}
```

Vérifier que `App\Core\Query` est utilisable ici (le fichier utilise probablement déjà des classes pleinement qualifiées — sinon `\App\Core\Query` en FQN suffit).

- [ ] **Step 4: Constantes de navigation**

`Config/constants.php` :
- `SECTION_LABELS` : ajouter `'calendrier' => 'Calendrier',` et `'anniversaires' => 'Anniversaires',` (par ex. juste après `'suiviBergers'`).
- `SECTION_ICONS` : ajouter `'calendrier' => '<i class="fa-solid fa-calendar-day"></i>',` et `'anniversaires' => '<i class="fa-solid fa-cake-candles"></i>',`.
- `NAV_ORDER` : insérer `'calendrier',` et `'anniversaires',` juste avant `'parametres'`.

- [ ] **Step 5: Liens de menu pour le scope berger**

`Views/layouts/layout.php`, dans la branche `if ($scope && $scope['kind'] === 'berger') { ... }`, à la fin du bloc (après les liens de responsabilités) :

```php
    if (auth_can_manage_calendar()) {
        foreach (['calendrier', 'anniversaires'] as $ck) {
            $navLis[] = '<li><a class="nav-item' . ($page === $ck ? ' active' : '') . '" href="' . h(url('index.php', ['page' => $ck])) . '"><span class="ico">' . SECTION_ICONS[$ck] . '</span><span class="label">' . h(SECTION_LABELS[$ck]) . '</span></a></li>';
        }
    }
```

(L'admin obtient déjà les deux entrées via la boucle `NAV_ORDER` du `else`.)

- [ ] **Step 6: Relancer l'assertion + lint + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_rbac_check.php
php -l app/Auth/compat.php && php -l Config/constants.php && php -l Views/layouts/layout.php
git add app/Auth/compat.php Config/constants.php Views/layouts/layout.php
git commit -m "$(cat <<'EOF'
feat(calendriers): helpers RBAC + entrées de navigation

auth_can_manage_calendar (admin ou détenteur d'une responsabilité
manager) et auth_can_edit_evenement (admin, créateur ou responsable).
calendrier + anniversaires dans SECTION_LABELS/ICONS/NAV_ORDER et dans le
menu du scope berger gestionnaire de calendrier.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 3: Repositories `EvenementRepository` + `AnniversaireRepository`

**Files:**
- Create: `app/Repositories/EvenementRepository.php`
- Create: `app/Repositories/AnniversaireRepository.php`
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_repo_check.php`

**Interfaces:**
- Consumes de Task 1 : tables `evenements`, `anniversaires`.
- Produces :
  - `App\Repositories\EvenementRepository`
    - `all(?string $fromDate = null): array` — événements (avec `resp_prenom`, `resp_nom` joints), triés par `date_debut ASC` ; si `$fromDate` fourni, `WHERE date_debut >= :fromDate`.
    - `find(int $id): ?array` — un événement (mêmes colonnes jointes).
    - `create(string $nom, string $dateDebut, ?string $dateFin, ?string $lieu, ?int $responsableId, ?int $createdBy): int`
    - `update(int $id, string $nom, string $dateDebut, ?string $dateFin, ?string $lieu, ?int $responsableId): void`
    - `delete(int $id): void`
  - `App\Repositories\AnniversaireRepository`
    - `all(): array` — saisies manuelles, triées `mois, jour`.
    - `find(int $id): ?array`
    - `create(string $nom, int $jour, int $mois, ?int $annee, ?int $createdBy): int`
    - `delete(int $id): void`

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_repo_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;
use App\Repositories\EvenementRepository;
use App\Repositories\AnniversaireRepository;

$er = new EvenementRepository();
$id = $er->create('ZZ_M4_EVT', '2027-05-01 09:00:00', '2027-05-01 17:00:00', 'Chapiteau', null, null);
$row = $er->find($id);
assert($row['nom'] === 'ZZ_M4_EVT', 'find KO');
assert(str_starts_with((string) $row['date_debut'], '2027-05-01'), 'date_debut KO');
$er->update($id, 'ZZ_M4_EVT2', '2027-05-02 10:00:00', null, null, null);
assert($er->find($id)['nom'] === 'ZZ_M4_EVT2', 'update KO');
$upcoming = $er->all('2027-01-01 00:00:00');
assert(count(array_filter($upcoming, fn($e) => (int) $e['id'] === $id)) === 1, 'all(from) KO');
$er->delete($id);
assert($er->find($id) === null, 'delete KO');

$ar = new AnniversaireRepository();
$aid = $ar->create('AKELE NZUE Leïla', 30, 11, null, null);
$got = $ar->find($aid);
assert($got['nom'] === 'AKELE NZUE Leïla' && (int) $got['jour'] === 30 && (int) $got['mois'] === 11, 'anniv find KO');
$ar->delete($aid);
assert($ar->find($aid) === null, 'anniv delete KO');

echo "OK m4 repo\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_repo_check.php`
Expected: FAIL — `Error: Class "App\Repositories\EvenementRepository" not found`.

- [ ] **Step 3: Créer `app/Repositories/EvenementRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Événements du calendrier événementiel.
 */
class EvenementRepository
{
    private const SELECT = 'SELECT e.*, ru.prenom AS resp_prenom, ru.nom AS resp_nom
                              FROM evenements e
                              LEFT JOIN users ru ON ru.id = e.responsable_id';

    /** @return array<int,array<string,mixed>> */
    public function all(?string $fromDate = null): array
    {
        if ($fromDate !== null) {
            return Query::all(self::SELECT . ' WHERE e.date_debut >= ? ORDER BY e.date_debut ASC', [$fromDate]);
        }
        return Query::all(self::SELECT . ' ORDER BY e.date_debut ASC');
    }

    public function find(int $id): ?array
    {
        return Query::one(self::SELECT . ' WHERE e.id = ?', [$id]);
    }

    public function create(string $nom, string $dateDebut, ?string $dateFin, ?string $lieu, ?int $responsableId, ?int $createdBy): int
    {
        return Query::run(
            'INSERT INTO evenements (nom, date_debut, date_fin, lieu, responsable_id, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$nom, $dateDebut, $dateFin, $lieu, $responsableId, $createdBy]
        );
    }

    public function update(int $id, string $nom, string $dateDebut, ?string $dateFin, ?string $lieu, ?int $responsableId): void
    {
        Query::run(
            'UPDATE evenements SET nom = ?, date_debut = ?, date_fin = ?, lieu = ?, responsable_id = ? WHERE id = ?',
            [$nom, $dateDebut, $dateFin, $lieu, $responsableId, $id]
        );
    }

    public function delete(int $id): void
    {
        Query::run('DELETE FROM evenements WHERE id = ?', [$id]);
    }
}
```

- [ ] **Step 4: Créer `app/Repositories/AnniversaireRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Saisies manuelles du calendrier d'anniversaires (personnes sans compte).
 * Les anniversaires des membres sont dérivés de users.date_naissance.
 */
class AnniversaireRepository
{
    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return Query::all('SELECT * FROM anniversaires ORDER BY mois, jour, nom');
    }

    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM anniversaires WHERE id = ?', [$id]);
    }

    public function create(string $nom, int $jour, int $mois, ?int $annee, ?int $createdBy): int
    {
        return Query::run(
            'INSERT INTO anniversaires (nom, jour, mois, annee, created_by) VALUES (?, ?, ?, ?, ?)',
            [$nom, $jour, $mois, $annee, $createdBy]
        );
    }

    public function delete(int $id): void
    {
        Query::run('DELETE FROM anniversaires WHERE id = ?', [$id]);
    }
}
```

- [ ] **Step 5: Relancer l'assertion, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_repo_check.php`
Expected: PASS — `OK m4 repo`

- [ ] **Step 6: Lint + commit**

```bash
php -l app/Repositories/EvenementRepository.php && php -l app/Repositories/AnniversaireRepository.php
git add app/Repositories/EvenementRepository.php app/Repositories/AnniversaireRepository.php
git commit -m "$(cat <<'EOF'
feat(calendriers): repositories EvenementRepository + AnniversaireRepository

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 4: `CalendrierService`

**Files:**
- Create: `app/Services/CalendrierService.php`
- Modify: `app/Compat/data.php` (accessor `calendrier_service()`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_service_check.php`

**Interfaces:**
- Consumes de Task 3 : `EvenementRepository`, `AnniversaireRepository`.
- Produces :
  - `App\Services\CalendrierService`
    - `__construct(?EvenementRepository $evt = null, ?AnniversaireRepository $anniv = null)`
    - `upcomingEvents(): array` — `all()` filtrés à `date_debut >= aujourd'hui 00:00`, tri chronologique.
    - `allEvents(): array` — tous, chronologiques.
    - `event(int $id): ?array`
    - `saveEvent(array $in, int $userId): array` — valide (`nom` requis, `date_debut` requise et parseable ; `date_fin` si fournie doit être ≥ `date_debut`) ; retourne `['ok' => bool, 'errors' => array<string,string>, 'id' => ?int]`. Sur update (`$in['id']` présent), n'écrit **pas** `created_by`.
    - `deleteEvent(int $id): void`
    - `birthdays(): array` — fusion : pour chaque `users` avec `date_naissance` non NULL → `['nom' => full name, 'jour' => (int), 'mois' => (int), 'annee' => (int|null), 'source' => 'membre', 'id' => user id]` ; pour chaque ligne `anniversaires` → `['nom' => ..., 'jour' => ..., 'mois' => ..., 'annee' => ..., 'source' => 'manuel', 'id' => anniv id]`. Trié par (mois, jour). Chaque entrée reçoit `age` = âge révolu cette année si `annee` connue, sinon `null`, et `is_current_month` = (mois === date('n')).
    - `saveBirthday(array $in, int $userId): array` — valide (`nom` requis ; `jour` 1..31 ; `mois` 1..12 ; `annee` vide ou 1900..année courante) ; crée une ligne `anniversaires` ; `['ok','errors','id']`.
    - `deleteBirthday(int $id): void`
  - `app/Compat/data.php` : `function calendrier_service(): \App\Services\CalendrierService { return _repo(\App\Services\CalendrierService::class); }`

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_service_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;
use App\Services\CalendrierService;

$svc = new CalendrierService();

// Événement : validation
$bad = $svc->saveEvent(['nom' => '', 'date_debut' => ''], 1);
assert($bad['ok'] === false && isset($bad['errors']['nom'], $bad['errors']['date_debut']), 'validation événement KO');

$badFin = $svc->saveEvent(['nom' => 'X', 'date_debut' => '2027-06-10T10:00', 'date_fin' => '2027-06-09T10:00'], 1);
assert($badFin['ok'] === false && isset($badFin['errors']['date_fin']), 'date_fin < date_debut doit être rejetée');

$ok = $svc->saveEvent(['nom' => 'ZZ Kermesse', 'date_debut' => '2027-06-10T10:00', 'date_fin' => '', 'lieu' => 'Cour'], 1);
assert($ok['ok'] === true && $ok['id'] > 0, 'création événement KO');
$eid = $ok['id'];
assert($svc->event($eid)['nom'] === 'ZZ Kermesse', 'event() KO');
$svc->saveEvent(['id' => $eid, 'nom' => 'ZZ Kermesse 2', 'date_debut' => '2027-06-11T10:00', 'date_fin' => ''], 999);
$after = $svc->event($eid);
assert($after['nom'] === 'ZZ Kermesse 2', 'update événement KO');
assert((int) $after['created_by'] === 1, 'update ne doit pas réécrire created_by');
$svc->deleteEvent($eid);
assert($svc->event($eid) === null, 'deleteEvent KO');

// Anniversaires : fusion + âge
$aid = $svc->saveBirthday(['nom' => 'ZZ Sans Compte', 'jour' => 30, 'mois' => 11, 'annee' => ''], 1);
assert($aid['ok'] === true, 'saveBirthday KO: ' . json_encode($aid['errors'] ?? []));
$list = $svc->birthdays();
$mine = array_values(array_filter($list, fn($b) => $b['source'] === 'manuel' && (int) $b['id'] === $aid['id']));
assert(count($mine) === 1 && $mine[0]['age'] === null, 'anniversaire manuel sans année => age null');
// tri (mois, jour) croissant
$keys = array_map(fn($b) => $b['mois'] * 100 + $b['jour'], $list);
$sorted = $keys; sort($sorted);
assert($keys === $sorted, 'liste anniversaires non triée par (mois, jour)');
$svc->deleteBirthday($aid['id']);

echo "OK m4 service\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_service_check.php`
Expected: FAIL — `Error: Class "App\Services\CalendrierService" not found`.

- [ ] **Step 3: Créer `app/Services/CalendrierService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Query;
use App\Repositories\AnniversaireRepository;
use App\Repositories\EvenementRepository;

/**
 * Calendrier événementiel + calendrier d'anniversaires.
 * Les anniversaires des membres sont dérivés de users.date_naissance ;
 * les personnes sans compte sont saisies dans la table `anniversaires`.
 */
class CalendrierService
{
    private EvenementRepository $evt;
    private AnniversaireRepository $anniv;

    public function __construct(?EvenementRepository $evt = null, ?AnniversaireRepository $anniv = null)
    {
        $this->evt = $evt ?? new EvenementRepository();
        $this->anniv = $anniv ?? new AnniversaireRepository();
    }

    /* ---------------- Événements ---------------- */

    public function allEvents(): array
    {
        return $this->evt->all();
    }

    public function upcomingEvents(): array
    {
        return $this->evt->all(date('Y-m-d 00:00:00'));
    }

    public function event(int $id): ?array
    {
        return $this->evt->find($id);
    }

    /**
     * @param array<string,mixed> $in champs bruts (nom, date_debut, date_fin, lieu, responsable_id, id?)
     * @return array{ok:bool,errors:array<string,string>,id:?int}
     */
    public function saveEvent(array $in, int $userId): array
    {
        $errors = [];
        $nom = trim((string) ($in['nom'] ?? ''));
        if ($nom === '') {
            $errors['nom'] = 'Le nom est obligatoire.';
        }
        $debut = $this->normalizeDateTime((string) ($in['date_debut'] ?? ''));
        if ($debut === null) {
            $errors['date_debut'] = 'La date de début est obligatoire et doit être valide.';
        }
        $finRaw = trim((string) ($in['date_fin'] ?? ''));
        $fin = $finRaw !== '' ? $this->normalizeDateTime($finRaw) : null;
        if ($finRaw !== '' && $fin === null) {
            $errors['date_fin'] = 'La date de fin est invalide.';
        } elseif ($debut !== null && $fin !== null && $fin < $debut) {
            $errors['date_fin'] = 'La date de fin doit être postérieure à la date de début.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $lieu = trim((string) ($in['lieu'] ?? '')) ?: null;
        $respId = (int) ($in['responsable_id'] ?? 0) ?: null;
        $id = (int) ($in['id'] ?? 0);
        if ($id) {
            $this->evt->update($id, $nom, $debut, $fin, $lieu, $respId);
        } else {
            $id = $this->evt->create($nom, $debut, $fin, $lieu, $respId, $userId);
        }
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }

    public function deleteEvent(int $id): void
    {
        $this->evt->delete($id);
    }

    /* ---------------- Anniversaires ---------------- */

    /**
     * @return list<array{nom:string,jour:int,mois:int,annee:?int,source:string,id:int,age:?int,is_current_month:bool}>
     */
    public function birthdays(): array
    {
        $out = [];
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');

        foreach (Query::all("SELECT id, prenom, nom, date_naissance FROM users WHERE date_naissance IS NOT NULL") as $u) {
            $ts = strtotime((string) $u['date_naissance']);
            if ($ts === false) {
                continue;
            }
            $y = (int) date('Y', $ts);
            $out[] = [
                'nom'    => trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')),
                'jour'   => (int) date('j', $ts),
                'mois'   => (int) date('n', $ts),
                'annee'  => $y > 1900 ? $y : null,
                'source' => 'membre',
                'id'     => (int) $u['id'],
            ];
        }
        foreach ($this->anniv->all() as $a) {
            $out[] = [
                'nom'    => (string) $a['nom'],
                'jour'   => (int) $a['jour'],
                'mois'   => (int) $a['mois'],
                'annee'  => $a['annee'] !== null ? (int) $a['annee'] : null,
                'source' => 'manuel',
                'id'     => (int) $a['id'],
            ];
        }

        usort($out, static fn($x, $y) => ($x['mois'] <=> $y['mois']) ?: ($x['jour'] <=> $y['jour']) ?: strcmp($x['nom'], $y['nom']));

        foreach ($out as &$b) {
            $b['age'] = $b['annee'] !== null ? max(0, $currentYear - $b['annee']) : null;
            $b['is_current_month'] = $b['mois'] === $currentMonth;
        }
        unset($b);
        return $out;
    }

    /**
     * @return array{ok:bool,errors:array<string,string>,id:?int}
     */
    public function saveBirthday(array $in, int $userId): array
    {
        $errors = [];
        $nom = trim((string) ($in['nom'] ?? ''));
        if ($nom === '') {
            $errors['nom'] = 'Le nom est obligatoire.';
        }
        $jour = (int) ($in['jour'] ?? 0);
        if ($jour < 1 || $jour > 31) {
            $errors['jour'] = 'Jour invalide (1–31).';
        }
        $mois = (int) ($in['mois'] ?? 0);
        if ($mois < 1 || $mois > 12) {
            $errors['mois'] = 'Mois invalide (1–12).';
        }
        $anneeRaw = trim((string) ($in['annee'] ?? ''));
        $annee = null;
        if ($anneeRaw !== '') {
            $annee = (int) $anneeRaw;
            if ($annee < 1900 || $annee > (int) date('Y')) {
                $errors['annee'] = 'Année invalide.';
            }
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }
        $id = $this->anniv->create($nom, $jour, $mois, $annee, $userId);
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }

    public function deleteBirthday(int $id): void
    {
        $this->anniv->delete($id);
    }

    /* ---------------- Helpers ---------------- */

    /** Accepte "Y-m-d\TH:i" (input datetime-local) ou "Y-m-d H:i(:s)". Renvoie "Y-m-d H:i:s" ou null. */
    private function normalizeDateTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace('T', ' ', $raw);
        $ts = strtotime($raw);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }
}
```

- [ ] **Step 4: Ajouter l'accessor compat**

`app/Compat/data.php`, avec les autres accessors (`_repo(...)`), ajouter :

```php
function calendrier_service(): \App\Services\CalendrierService { return _repo(\App\Services\CalendrierService::class); }
```

(Vérifier comment les autres services sont exposés dans `data.php` — reproduire l'idiome exact, `_repo(...)` ou `new ...`.)

- [ ] **Step 5: Relancer l'assertion, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_service_check.php`
Expected: PASS — `OK m4 service`

- [ ] **Step 6: Lint + commit**

```bash
php -l app/Services/CalendrierService.php && php -l app/Compat/data.php
git add app/Services/CalendrierService.php app/Compat/data.php
git commit -m "$(cat <<'EOF'
feat(calendriers): CalendrierService (événements + anniversaires fusionnés)

Validation des événements (nom/dates, date_fin >= date_debut), update qui
ne réécrit pas created_by. birthdays() fusionne users.date_naissance et la
table anniversaires, calcule l'âge si l'année est connue, marque le mois
courant, trie par (mois, jour). Validation des anniversaires manuels.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 5: Contrôleur, routes et vues (lecture + formulaires)

**Files:**
- Create: `app/Controllers/CalendrierController.php`
- Modify: `Routes/web.php`
- Create: `Views/pages/calendrier.php`
- Create: `Views/pages/anniversaires.php`
- Create: `assets/css/calendrier.css` + `@import` dans `assets/css/app.css`
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_view_check.php`

**Interfaces:**
- Consumes de Task 4 : `calendrier_service()`. De Task 2 : `auth_can_manage_calendar()`, `auth_can_edit_evenement()`.
- Produces :
  - `App\Controllers\CalendrierController::evenements(): void` — GET `?page=calendrier` : si `?evt=<id>` présent → délègue à `evenementFiche($id)` ; sinon liste. Variables passées à `pages/calendrier` : `$events` (liste `allEvents()`), `$canManage` (`auth_can_manage_calendar()`), `$edit` (événement en cours d'édition si `?edit=<id>` et droit, sinon null), `$responsables` (liste `SELECT id, prenom, nom FROM users WHERE role IN ('berger','ms','pasteur','reverant','admin') ORDER BY prenom, nom`), `$errors` (array, vide hors retour d'erreur), `$old` (repopulation), `$csrf`, `$mode` = `'list'`.
  - `CalendrierController::anniversaires(): void` — GET `?page=anniversaires` : passe `$birthdays` (`birthdays()`), `$canManage`, `$monthsFr` (`MONTHS_FR`), `$currentMonth` (`(int) date('n')`), `$errors`, `$old`, `$csrf`.
  - `CalendrierController::evenementFiche(int $id): void` — rendu d'une fiche événement (détails + emplacement du pointage, câblé en Task 7). Pour cette tâche : affiche les détails + un lien retour ; le bloc pointage est ajouté en Task 7.
  - Route `Router::get('calendrier', CalendrierController::class, 'evenements')` et `Router::get('anniversaires', CalendrierController::class, 'anniversaires')` + `use App\Controllers\CalendrierController;`.
  - `assets/css/calendrier.css` importé dans `app.css` juste après `@import url('presences.css');`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_view_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';

assert(is_file('app/Controllers/CalendrierController.php'), 'CalendrierController absent');
assert(is_file('Views/pages/calendrier.php'), 'vue calendrier absente');
assert(is_file('Views/pages/anniversaires.php'), 'vue anniversaires absente');
assert(is_file('assets/css/calendrier.css'), 'calendrier.css absent');

$routes = file_get_contents('Routes/web.php');
assert(str_contains($routes, "'calendrier'") && str_contains($routes, "'anniversaires'"), 'routes M4 absentes');
assert(str_contains($routes, 'use App\Controllers\CalendrierController;'), 'use CalendrierController absent');
assert(str_contains(file_get_contents('assets/css/app.css'), "@import url('calendrier.css')"), 'calendrier.css non importé');

// smoke-render des deux vues avec des données minimales
$evtHtml = view('pages/calendrier', [
    'events' => [], 'canManage' => true, 'edit' => null,
    'responsables' => [], 'errors' => [], 'old' => [], 'csrf' => '', 'mode' => 'list',
]);
assert(str_contains($evtHtml, 'save_evenement'), 'la vue calendrier ne poste pas save_evenement');

$anHtml = view('pages/anniversaires', [
    'birthdays' => [
        ['nom' => 'X Y', 'jour' => 30, 'mois' => 11, 'annee' => null, 'source' => 'manuel', 'id' => 1, 'age' => null, 'is_current_month' => false],
    ],
    'canManage' => true, 'monthsFr' => MONTHS_FR, 'currentMonth' => (int) date('n'),
    'errors' => [], 'old' => [], 'csrf' => '',
]);
assert(str_contains($anHtml, 'save_anniversaire'), 'la vue anniversaires ne poste pas save_anniversaire');
assert(str_contains($anHtml, 'X Y'), 'la vue anniversaires n\'affiche pas les entrées');

echo "OK m4 views\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_view_check.php`
Expected: FAIL — `AssertionError: CalendrierController absent`.

- [ ] **Step 3: Créer `app/Controllers/CalendrierController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Query;
use App\Core\Request;

/**
 * Calendrier événementiel + calendrier d'anniversaires.
 */
class CalendrierController extends Controller
{
    public function evenements(): void
    {
        if (!current_user()) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        $evt = (int) (Request::get('evt') ?? 0);
        if ($evt) {
            $this->evenementFiche($evt);
            return;
        }

        $svc = calendrier_service();
        $canManage = auth_can_manage_calendar();
        $editId = (int) (Request::get('edit') ?? 0);
        $edit = null;
        if ($editId && $canManage) {
            $edit = $svc->event($editId);
            if ($edit && !auth_can_edit_evenement($edit)) {
                $edit = null;
            }
        }

        render_page(SECTION_LABELS['calendrier'], view('pages/calendrier', [
            'events'       => $svc->allEvents(),
            'canManage'    => $canManage,
            'edit'         => $edit,
            'responsables' => Query::all("SELECT id, prenom, nom FROM users WHERE role IN ('berger','ms','pasteur','reverant','admin') ORDER BY prenom, nom"),
            'errors'       => [],
            'old'          => [],
            'csrf'         => csrf_field(),
            'mode'         => 'list',
        ]));
    }

    public function anniversaires(): void
    {
        if (!current_user()) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        render_page(SECTION_LABELS['anniversaires'], view('pages/anniversaires', [
            'birthdays'    => calendrier_service()->birthdays(),
            'canManage'    => auth_can_manage_calendar(),
            'monthsFr'     => MONTHS_FR,
            'currentMonth' => (int) date('n'),
            'errors'       => [],
            'old'          => [],
            'csrf'         => csrf_field(),
        ]));
    }

    private function evenementFiche(int $id): void
    {
        $evt = calendrier_service()->event($id);
        if (!$evt) {
            $this->redirect('index.php', ['page' => 'calendrier']);
        }
        render_page($evt['nom'], view('pages/calendrier', [
            'events'       => [],
            'canManage'    => auth_can_manage_calendar(),
            'edit'         => null,
            'responsables' => [],
            'errors'       => [],
            'old'          => [],
            'csrf'         => csrf_field(),
            'mode'         => 'fiche',
            'fiche'        => $evt,
            'canEditFiche' => auth_can_edit_evenement($evt),
        ]));
    }
}
```

> Note : `view('pages/calendrier', ...)` reçoit toujours les clés `events/canManage/edit/responsables/errors/old/csrf/mode` ; `fiche`/`canEditFiche` ne sont fournies qu'en `mode === 'fiche'`. La vue teste `$mode`.

- [ ] **Step 4: Routes**

`Routes/web.php` : ajouter `use App\Controllers\CalendrierController;` en tête, et près des autres pages GET :

```php
Router::get('calendrier', CalendrierController::class, 'evenements');
Router::get('anniversaires', CalendrierController::class, 'anniversaires');
```

- [ ] **Step 5: Créer `Views/pages/calendrier.php`**

```php
<?php /* Calendrier événementiel : liste + formulaire (ou fiche d'un événement).
   Variables : $events, $canManage, $edit, $responsables, $errors, $old, $csrf, $mode
               (+ $fiche, $canEditFiche si $mode === 'fiche'). */
if (($mode ?? 'list') === 'fiche'):
    $e = $fiche;
    $deb = strtotime((string) $e['date_debut']);
    $fin = $e['date_fin'] ? strtotime((string) $e['date_fin']) : null;
?>
<div class="section-toolbar">
  <div><h2><?= h($e['nom']) ?></h2><div class="sub">Fiche événement</div></div>
  <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'calendrier'])) ?>"><i class="fa-solid fa-arrow-left"></i> Retour au calendrier</a>
</div>
<div class="cal-fiche">
  <p><strong>Début :</strong> <?= h(date('d/m/Y H:i', $deb)) ?></p>
  <?php if ($fin): ?><p><strong>Fin :</strong> <?= h(date('d/m/Y H:i', $fin)) ?></p><?php endif; ?>
  <?php if ($e['lieu']): ?><p><strong>Lieu :</strong> <?= h($e['lieu']) ?></p><?php endif; ?>
  <?php if ($e['resp_prenom'] || $e['resp_nom']): ?><p><strong>Responsable :</strong> <?= h(trim(($e['resp_prenom'] ?? '') . ' ' . ($e['resp_nom'] ?? ''))) ?></p><?php endif; ?>
</div>
<div id="evt-presence"><!-- bloc de pointage ajouté en Task 7 --></div>
<?php return; endif; ?>

<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['calendrier']) ?></h2><div class="sub">Événements à venir et passés</div></div>
</div>

<?php if ($canManage): ?>
  <?php $e = $edit ?? []; $val = fn($k) => h($old[$k] ?? ($e[$k] ?? '')); ?>
  <form method="post" action="index.php" class="form-card cal-form">
    <input type="hidden" name="action" value="save_evenement">
    <?= $csrf ?>
    <?php if (!empty($e['id'])): ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><?php endif; ?>
    <div class="form-group">
      <label>Nom de l'événement</label>
      <input type="text" name="nom" value="<?= $val('nom') ?>" required>
      <?php if (!empty($errors['nom'])): ?><span class="form-error"><?= h($errors['nom']) ?></span><?php endif; ?>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Début</label>
        <input type="datetime-local" name="date_debut" value="<?= h($old['date_debut'] ?? (!empty($e['date_debut']) ? date('Y-m-d\TH:i', strtotime((string) $e['date_debut'])) : '')) ?>" required>
        <?php if (!empty($errors['date_debut'])): ?><span class="form-error"><?= h($errors['date_debut']) ?></span><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Fin (facultatif)</label>
        <input type="datetime-local" name="date_fin" value="<?= h($old['date_fin'] ?? (!empty($e['date_fin']) ? date('Y-m-d\TH:i', strtotime((string) $e['date_fin'])) : '')) ?>">
        <?php if (!empty($errors['date_fin'])): ?><span class="form-error"><?= h($errors['date_fin']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Lieu</label><input type="text" name="lieu" value="<?= $val('lieu') ?>"></div>
      <div class="form-group">
        <label>Responsable</label>
        <select name="responsable_id">
          <option value="">—</option>
          <?php foreach ($responsables as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= (int) ($old['responsable_id'] ?? ($e['responsable_id'] ?? 0)) === (int) $r['id'] ? 'selected' : '' ?>><?= h(trim($r['prenom'] . ' ' . $r['nom'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="modal-actions">
      <?php if (!empty($e['id'])): ?><a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'calendrier'])) ?>">Annuler</a><?php endif; ?>
      <button type="submit" class="btn btn-primary"><?= !empty($e['id']) ? 'Enregistrer' : 'Ajouter l\'événement' ?></button>
    </div>
  </form>
<?php endif; ?>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Date</th><th>Événement</th><th>Lieu</th><th>Responsable</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr></thead>
    <tbody>
      <?php if (!$events): ?>
        <tr><td colspan="<?= $canManage ? 5 : 4 ?>"><?= empty_state('fa-calendar-day', 'Aucun événement pour le moment.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($events as $ev): $ts = strtotime((string) $ev['date_debut']); ?>
          <tr>
            <td><?= h(date('d/m/Y H:i', $ts)) ?></td>
            <td><a href="<?= h(url('index.php', ['page' => 'calendrier', 'evt' => $ev['id']])) ?>"><?= h($ev['nom']) ?></a></td>
            <td><?= h($ev['lieu'] ?? '') ?></td>
            <td><?= h(trim(($ev['resp_prenom'] ?? '') . ' ' . ($ev['resp_nom'] ?? ''))) ?></td>
            <?php if ($canManage): ?>
              <td class="row-actions">
                <?php if (auth_can_edit_evenement($ev)): ?>
                  <a class="icon-btn" title="Modifier" href="<?= h(url('index.php', ['page' => 'calendrier', 'edit' => $ev['id']])) ?>"><i class="fa-solid fa-pen"></i></a>
                  <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cet événement ?" href="<?= h(url('index.php', ['action' => 'delete_evenement', 'id' => $ev['id']])) ?>"><i class="fa-solid fa-trash"></i></a>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
```

- [ ] **Step 6: Créer `Views/pages/anniversaires.php`**

```php
<?php /* Calendrier d'anniversaires : fusion membres + saisies manuelles.
   Variables : $birthdays, $canManage, $monthsFr, $currentMonth, $errors, $old, $csrf. */ ?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['anniversaires']) ?></h2><div class="sub">Anniversaires de l'année — mois courant surligné</div></div>
</div>

<?php if ($canManage): ?>
  <form method="post" action="index.php" class="form-card cal-form">
    <input type="hidden" name="action" value="save_anniversaire">
    <?= $csrf ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Nom</label>
        <input type="text" name="nom" value="<?= h($old['nom'] ?? '') ?>" required>
        <?php if (!empty($errors['nom'])): ?><span class="form-error"><?= h($errors['nom']) ?></span><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Jour</label>
        <input type="number" name="jour" min="1" max="31" value="<?= h($old['jour'] ?? '') ?>" required>
        <?php if (!empty($errors['jour'])): ?><span class="form-error"><?= h($errors['jour']) ?></span><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Mois</label>
        <select name="mois" required>
          <option value="">—</option>
          <?php foreach ($monthsFr as $i => $m): ?>
            <option value="<?= $i + 1 ?>" <?= (int) ($old['mois'] ?? 0) === $i + 1 ? 'selected' : '' ?>><?= h($m) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['mois'])): ?><span class="form-error"><?= h($errors['mois']) ?></span><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Année (facultatif)</label>
        <input type="number" name="annee" min="1900" max="<?= (int) date('Y') ?>" value="<?= h($old['annee'] ?? '') ?>">
        <?php if (!empty($errors['annee'])): ?><span class="form-error"><?= h($errors['annee']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="modal-actions"><button type="submit" class="btn btn-primary">Ajouter l'anniversaire</button></div>
  </form>
<?php endif; ?>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Date</th><th>Nom</th><th>Âge</th><th>Source</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr></thead>
    <tbody>
      <?php if (!$birthdays): ?>
        <tr><td colspan="<?= $canManage ? 5 : 4 ?>"><?= empty_state('fa-cake-candles', 'Aucun anniversaire enregistré.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($birthdays as $b): ?>
          <tr class="<?= $b['is_current_month'] ? 'anniv-current' : '' ?>">
            <td><?= (int) $b['jour'] ?> <?= h($monthsFr[$b['mois'] - 1] ?? '') ?></td>
            <td><?= h($b['nom']) ?></td>
            <td><?= $b['age'] !== null ? (int) $b['age'] . ' ans' : '—' ?></td>
            <td><?= $b['source'] === 'membre' ? 'Membre' : 'Saisie manuelle' ?></td>
            <?php if ($canManage): ?>
              <td class="row-actions">
                <?php if ($b['source'] === 'manuel'): ?>
                  <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cet anniversaire ?" href="<?= h(url('index.php', ['action' => 'delete_anniversaire', 'id' => $b['id']])) ?>"><i class="fa-solid fa-trash"></i></a>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
```

- [ ] **Step 7: Créer `assets/css/calendrier.css` + import**

```css
/* M4 — Calendriers (événements + anniversaires) */

.cal-form {
  margin-bottom: var(--space-6);
}

.cal-fiche p {
  margin: var(--space-2) 0;
}

.form-error {
  display: block;
  color: var(--danger);
  font-size: 13px;
  margin-top: var(--space-1);
}

tr.anniv-current td {
  background: var(--primary-soft);
  font-weight: 600;
}
```

Puis `assets/css/app.css` — après `@import url('presences.css');` :

```css
@import url('calendrier.css');
```

(Vérifier que `--primary-soft` existe dans `variables.css` ; sinon utiliser `--warning-soft` ou `--success-soft`.)

- [ ] **Step 8: Relancer l'assertion, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_view_check.php`
Expected: PASS — `OK m4 views`

- [ ] **Step 9: Lint + commit**

```bash
php -l app/Controllers/CalendrierController.php && php -l Routes/web.php && php -l Views/pages/calendrier.php && php -l Views/pages/anniversaires.php
git add app/Controllers/CalendrierController.php Routes/web.php Views/pages/calendrier.php Views/pages/anniversaires.php assets/css/calendrier.css assets/css/app.css
git commit -m "$(cat <<'EOF'
feat(calendriers): pages calendrier événementiel + anniversaires

CalendrierController (liste, fiche événement, calendrier d'anniversaires),
routes calendrier / anniversaires, vues avec formulaires conditionnés à
auth_can_manage_calendar, CSS assets/css/calendrier.css.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 6: Actions POST/GET (créer, modifier, supprimer)

**Files:**
- Modify: `app/Controllers/ActionsController.php` (postAction : `save_evenement`, `save_anniversaire` ; getAction : `delete_evenement`, `delete_anniversaire`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_action_check.php`

> **CSRF :** `postAction()` appelle `check_csrf()` une seule fois en tête, avant le `switch` (ligne ~248). Les nouveaux cas POST n'ajoutent donc **aucun** appel CSRF. Les suppressions passent par `getAction()` en GET, comme `delete_bacenta` (pas de CSRF sur les suppressions GET dans ce projet — reproduire tel quel, ne pas ajouter).

**Interfaces:**
- Consumes de Task 4 : `calendrier_service()`. De Task 2 : `auth_can_manage_calendar()`, `auth_can_edit_evenement()`.
- Produces :
  - POST `save_evenement` : `$this->requireUser()`, `auth_can_manage_calendar()` sinon `deny()`. Sur `id` présent : charge l'événement, `auth_can_edit_evenement()` sinon `deny()`. Appelle `calendrier_service()->saveEvent($_POST, currentUserId)`. Succès → `redirect(page=calendrier)`. Échec de validation → re-render la vue `pages/calendrier` avec `errors` + `old` (ou, plus simple et cohérent avec le reste du projet qui `redirect()` toujours : `redirect(page=calendrier, edit=<id?>)` — **choisir la re-render pour préserver la saisie**, voir Step 3).
  - POST `save_anniversaire` : idem, `calendrier_service()->saveBirthday($_POST, currentUserId)`.
  - GET `delete_evenement` : `requireUser()`, charge l'événement, `auth_can_edit_evenement()` sinon `deny()`, `deleteEvent()`, `redirect(page=calendrier)`.
  - GET `delete_anniversaire` : `requireUser()`, `auth_can_manage_calendar()` sinon `deny()`, `deleteBirthday((int) id)`, `redirect(page=anniversaires)`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_action_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
$post = file_get_contents('app/Controllers/ActionsController.php');
assert(str_contains($post, "case 'save_evenement'"), 'save_evenement absent');
assert(str_contains($post, "case 'save_anniversaire'"), 'save_anniversaire absent');
assert(str_contains($post, "case 'delete_evenement'"), 'delete_evenement absent');
assert(str_contains($post, "case 'delete_anniversaire'"), 'delete_anniversaire absent');
assert(str_contains($post, 'auth_can_manage_calendar()'), 'garde RBAC calendrier absente');
assert(str_contains($post, 'auth_can_edit_evenement('), 'garde propriétaire événement absente');
echo "OK m4 actions wiring\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_action_check.php`
Expected: FAIL — `AssertionError: save_evenement absent`.

- [ ] **Step 3: Ajouter les cas POST**

Repérer comment les cas `save_*` existants font le CSRF (par ex. `save_bacenta`). Reproduire ce mécanisme exact. Ajouter dans `postAction()`, groupés (par ex. après `save_presence_occurrence`) :

```php
            /* ---------- Calendriers (M4) ---------- */

            case 'save_evenement': {
                $user = $this->requireUser();
                if (!auth_can_manage_calendar()) {
                    $this->deny();
                }
                $id = (int) ($_POST['id'] ?? 0);
                if ($id) {
                    $existing = calendrier_service()->event($id);
                    if (!$existing || !auth_can_edit_evenement($existing)) {
                        $this->deny();
                    }
                }
                $res = calendrier_service()->saveEvent($_POST, (int) $user['id']);
                if (!$res['ok']) {
                    $editForForm = $id ? calendrier_service()->event($id) : null;
                    render_page(SECTION_LABELS['calendrier'], view('pages/calendrier', [
                        'events'       => calendrier_service()->allEvents(),
                        'canManage'    => true,
                        'edit'         => $editForForm,
                        'responsables' => Query::all("SELECT id, prenom, nom FROM users WHERE role IN ('berger','ms','pasteur','reverant','admin') ORDER BY prenom, nom"),
                        'errors'       => $res['errors'],
                        'old'          => $_POST,
                        'csrf'         => csrf_field(),
                        'mode'         => 'list',
                    ]));
                    return;
                }
                $this->redirect('index.php', ['page' => 'calendrier']);
                break;
            }

            case 'save_anniversaire': {
                $user = $this->requireUser();
                if (!auth_can_manage_calendar()) {
                    $this->deny();
                }
                $res = calendrier_service()->saveBirthday($_POST, (int) $user['id']);
                if (!$res['ok']) {
                    render_page(SECTION_LABELS['anniversaires'], view('pages/anniversaires', [
                        'birthdays'    => calendrier_service()->birthdays(),
                        'canManage'    => true,
                        'monthsFr'     => MONTHS_FR,
                        'currentMonth' => (int) date('n'),
                        'errors'       => $res['errors'],
                        'old'          => $_POST,
                        'csrf'         => csrf_field(),
                    ]));
                    return;
                }
                $this->redirect('index.php', ['page' => 'anniversaires']);
                break;
            }
```

> `check_csrf()` est déjà global en tête de `postAction()` — ne rien ajouter dans ces cas.

- [ ] **Step 4: Ajouter les cas GET (suppression)**

Dans `getAction()`, à côté de `delete_basonta` :

```php
            case 'delete_evenement': {
                $this->requireUser();
                $id = (int) ($_GET['id'] ?? 0);
                $evt = $id ? calendrier_service()->event($id) : null;
                if (!$evt || !auth_can_edit_evenement($evt)) {
                    $this->deny();
                }
                calendrier_service()->deleteEvent($id);
                $this->redirect('index.php', ['page' => 'calendrier']);
                break;
            }

            case 'delete_anniversaire': {
                $this->requireUser();
                if (!auth_can_manage_calendar()) {
                    $this->deny();
                }
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    calendrier_service()->deleteBirthday($id);
                }
                $this->redirect('index.php', ['page' => 'anniversaires']);
                break;
            }
```

- [ ] **Step 5: Relancer l'assertion + lint**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_action_check.php
php -l app/Controllers/ActionsController.php
```

- [ ] **Step 6: Vérification fonctionnelle (script, contre la base)**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_action_e2e.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;

$svc = calendrier_service();
$r = $svc->saveEvent(['nom' => 'ZZ E2E', 'date_debut' => '2028-01-01T12:00', 'date_fin' => ''], 1);
assert($r['ok'], 'create e2e KO');
$eid = $r['id'];
assert($svc->event($eid) !== null, 'event manquant');
$svc->deleteEvent($eid);
assert($svc->event($eid) === null, 'delete e2e KO');

$b = $svc->saveBirthday(['nom' => 'ZZ B2E', 'jour' => 1, 'mois' => 1, 'annee' => '2000'], 1);
assert($b['ok'], 'birthday create KO');
$found = array_filter($svc->birthdays(), fn($x) => $x['source'] === 'manuel' && (int) $x['id'] === $b['id']);
assert(count($found) === 1, 'birthday absent de la fusion');
$svc->deleteBirthday($b['id']);
echo "OK m4 action e2e\n";
```

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_action_e2e.php`
Expected: `OK m4 action e2e`.

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/ActionsController.php
git commit -m "$(cat <<'EOF'
feat(calendriers): actions save/delete pour événements et anniversaires

save_evenement / save_anniversaire (POST) : requireUser +
auth_can_manage_calendar, re-render avec erreurs sur validation KO,
contrôle auth_can_edit_evenement sur update. delete_evenement /
delete_anniversaire (GET) avec les mêmes gardes.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 7: Addendum M1 — pointage de présence d'un événement

**Files:**
- Modify: `app/Repositories/AttendanceRepository.php` (`UNIT_COLUMNS`)
- Modify: `app/Controllers/ActionsController.php` (`save_presence_occurrence` : accepter `evenement`)
- Modify: `Views/pages/calendrier.php` (bloc `#evt-presence` de la fiche)
- Modify: `app/Controllers/CalendrierController.php` (`evenementFiche` fournit la grille de pointage)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_evt_presence_check.php`

**Interfaces:**
- Consumes de Task 1 : `presences.evenement_id`. De M1 (déjà livré) : `AttendanceService::pointOccurrence`, `unit_presence_grid`, `save_unit_presence`, action `save_presence_occurrence`, `PRESENCE_STATUTS`.
- Produces :
  - `AttendanceRepository::UNIT_COLUMNS` inclut `'evenement' => 'evenement_id'` — `pointOccurrence`/`occurrenceStatuts`/`distinctDatesForUnit`/`matrixForUnit` fonctionnent avec `unitType = 'evenement'`.
  - `save_presence_occurrence` accepte `unit_type = 'evenement'` : population autorisée = `SELECT id FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant')` ; garde d'accès = `auth_can_manage_calendar()` OU `auth_can_edit_evenement($evt)` (l'événement est rechargé) ; redirection vers `?page=calendrier&evt=<id>&date=<date>`.
  - `CalendrierController::evenementFiche` passe `presenceGrid` (`unit_presence_grid('evenement', $id, $date, $members)`), `presenceDate`, `presenceStatuts`, `canPointe` à la vue.
  - `Views/pages/calendrier.php` (mode fiche) : si `$canPointe`, affiche un sélecteur de date + un tableau membre → `<select>` statut postant `save_presence_occurrence` avec `unit_type=evenement`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_evt_presence_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;
use App\Services\AttendanceService;

$svc = calendrier_service();
$r = $svc->saveEvent(['nom' => 'ZZ EVT PRES', 'date_debut' => '2028-03-03T10:00', 'date_fin' => ''], 1);
$eid = $r['id'];
$u = (int) Query::value("SELECT id FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY id LIMIT 1");

$att = new AttendanceService();
$att->pointOccurrence('evenement', $eid, '2028-03-03', [$u => 'present'], [$u]);
$grid = unit_presence_grid('evenement', $eid, '2028-03-03', [['id' => $u]]);
assert($grid[0]['statut'] === 'present', 'pointage événement KO');
$cnt = (int) Query::value('SELECT COUNT(*) FROM presences WHERE evenement_id = ? AND date_presence = ?', [$eid, '2028-03-03']);
assert($cnt === 1, "1 ligne attendue, vu $cnt");

// suppression de l'événement => CASCADE sur presences
$svc->deleteEvent($eid);
$cnt = (int) Query::value('SELECT COUNT(*) FROM presences WHERE evenement_id = ?', [$eid]);
assert($cnt === 0, "CASCADE KO, vu $cnt lignes orphelines");

$actions = file_get_contents('app/Controllers/ActionsController.php');
assert(str_contains($actions, "'evenement'") && str_contains($actions, 'save_presence_occurrence'), 'action pas étendue à evenement');
$repo = file_get_contents('app/Repositories/AttendanceRepository.php');
assert(str_contains($repo, "'evenement' => 'evenement_id'"), 'UNIT_COLUMNS pas étendu');

echo "OK m4 evt presence\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_evt_presence_check.php`
Expected: FAIL — `InvalidArgumentException: Type d'unité inconnu: evenement`.

- [ ] **Step 3: Étendre `UNIT_COLUMNS`**

`app/Repositories/AttendanceRepository.php` :

```php
    private const UNIT_COLUMNS = ['bacenta' => 'bacenta_id', 'cult' => 'culte_id', 'basonta' => 'basonta_id', 'evenement' => 'evenement_id'];
```

- [ ] **Step 4: Étendre `save_presence_occurrence`**

`app/Controllers/ActionsController.php`, cas `save_presence_occurrence` : élargir la whitelist et l'accès.

```php
                $unitType = (string) ($_POST['unit_type'] ?? '');
                $unitId = (int) ($_POST['unit_id'] ?? 0);
                if (!in_array($unitType, ['bacenta', 'cult', 'basonta', 'evenement'], true) || !$unitId) {
                    $this->deny();
                }
                if ($unitType === 'evenement') {
                    $evt = calendrier_service()->event($unitId);
                    if (!$evt || !(auth_can_manage_calendar() || auth_can_edit_evenement($evt))) {
                        $this->deny();
                    }
                } elseif (!can_manage_entity($unitType, $unitId)) {
                    $this->deny();
                }
```

Puis le `match ($unitType)` de la population autorisée reçoit une branche :

```php
                    'evenement' => array_map(static fn($m) => (int) $m['id'], Query::all("SELECT id FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant')")),
```

Et la redirection finale gère le type `evenement` :

```php
                if ($unitType === 'evenement') {
                    $this->redirect('index.php', ['page' => 'calendrier', 'evt' => $unitId, 'date' => $date]);
                }
                $pageKey = ['bacenta' => 'bacentas', 'cult' => 'cultes', 'basonta' => 'basontas'][$unitType];
                $this->redirect('index.php', ['page' => $pageKey, 'id' => $unitId, 'tab' => 'presences', 'date' => $date]);
```

- [ ] **Step 5: Fiche événement — grille de pointage (contrôleur)**

`app/Controllers/CalendrierController.php`, `evenementFiche()` — compléter le tableau passé à la vue :

```php
        $canPointe = auth_can_manage_calendar() || auth_can_edit_evenement($evt);
        $date = (string) (Request::get('date') ?: date('Y-m-d'));
        $members = $canPointe
            ? Query::all("SELECT * FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY prenom, nom")
            : [];
        render_page($evt['nom'], view('pages/calendrier', [
            'events'        => [],
            'canManage'     => auth_can_manage_calendar(),
            'edit'          => null,
            'responsables'  => [],
            'errors'        => [],
            'old'           => [],
            'csrf'          => csrf_field(),
            'mode'          => 'fiche',
            'fiche'         => $evt,
            'canEditFiche'  => auth_can_edit_evenement($evt),
            'canPointe'     => $canPointe,
            'presenceDate'  => $date,
            'presenceGrid'  => $canPointe ? unit_presence_grid('evenement', (int) $evt['id'], $date, $members) : [],
            'presenceStatuts' => PRESENCE_STATUTS,
        ]));
```

- [ ] **Step 6: Fiche événement — bloc de pointage (vue)**

`Views/pages/calendrier.php`, remplacer `<div id="evt-presence"><!-- ... --></div>` par :

```php
<?php if (!empty($canPointe)): ?>
<div class="section-toolbar"><div><h3>Pointage des présences</h3></div></div>
<form method="get" action="index.php" class="presence-datebar">
  <input type="hidden" name="page" value="calendrier">
  <input type="hidden" name="evt" value="<?= (int) $e['id'] ?>">
  <label>Date</label>
  <input type="date" name="date" value="<?= h($presenceDate) ?>" onchange="this.form.submit()">
</form>
<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_presence_occurrence">
  <?= $csrf ?>
  <input type="hidden" name="unit_type" value="evenement">
  <input type="hidden" name="unit_id" value="<?= (int) $e['id'] ?>">
  <input type="hidden" name="date" value="<?= h($presenceDate) ?>">
  <div class="table-wrap">
    <table class="data-table presence-table">
      <thead><tr><th>Membre</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($presenceGrid as $line): $u = $line['user']; ?>
          <tr>
            <td><?= h(full_name($u)) ?></td>
            <td>
              <select name="statut[<?= (int) $u['id'] ?>]">
                <option value="">—</option>
                <?php foreach ($presenceStatuts as $k => $lbl): ?>
                  <option value="<?= h($k) ?>" <?= $line['statut'] === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="modal-actions"><button type="submit" class="btn btn-primary" <?= $presenceGrid ? '' : 'disabled' ?>>Enregistrer les présences</button></div>
</form>
<?php endif; ?>
```

- [ ] **Step 7: Relancer l'assertion, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m4_evt_presence_check.php`
Expected: PASS — `OK m4 evt presence`

- [ ] **Step 8: Lint + smoke-render fiche**

```bash
php -l app/Repositories/AttendanceRepository.php && php -l app/Controllers/ActionsController.php && php -l app/Controllers/CalendrierController.php && php -l Views/pages/calendrier.php
php -r '
chdir("/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise"); require "Bootstrap/init.php";
$h = view("pages/calendrier", ["mode"=>"fiche","fiche"=>["id"=>1,"nom"=>"T","date_debut"=>"2028-01-01 10:00:00","date_fin"=>null,"lieu"=>null,"resp_prenom"=>null,"resp_nom"=>null],"canEditFiche"=>true,"canManage"=>true,"canPointe"=>true,"presenceDate"=>"2028-01-01","presenceGrid"=>[["user"=>["prenom"=>"A","nom"=>"B","id"=>1],"statut"=>""]],"presenceStatuts"=>PRESENCE_STATUTS,"events"=>[],"edit"=>null,"responsables"=>[],"errors"=>[],"old"=>[],"csrf"=>""]);
echo (str_contains($h,"unit_type") && str_contains($h,"evenement") && str_contains($h,"Pointage des présences")) ? "fiche render OK\n" : "fiche render KO\n";
'
```
Expected: lint clean, `fiche render OK`.

- [ ] **Step 9: Commit**

```bash
git add app/Repositories/AttendanceRepository.php app/Controllers/ActionsController.php app/Controllers/CalendrierController.php Views/pages/calendrier.php
git commit -m "$(cat <<'EOF'
feat(calendriers): pointage de présence d'une occurrence d'événement

Addendum M1 : AttendanceRepository::UNIT_COLUMNS reçoit
evenement => evenement_id ; save_presence_occurrence accepte
unit_type=evenement (accès via auth_can_manage_calendar ou
auth_can_edit_evenement, population = tous les membres). Bloc de pointage
sur la fiche événement.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

## Self-Review

**1. Spec coverage (§4 « M4 » + addendum M1) :**

| Exigence spec | Tâche |
|---|---|
| Table `evenements` (nom, date_debut, date_fin, lieu, responsable_id, created_by, created_at) | Task 1, Step 3 |
| Table `anniversaires` (nom, jour, mois, annee, created_by, created_at) | Task 1, Step 3 |
| Addendum M1 : `presences.evenement_id` + unique key reconstruit | Task 1, Step 3 |
| `down()` mis à jour | Task 1, Step 4 |
| Route `calendrier` + `anniversaires` → `CalendrierController` | Task 5, Steps 3-4 |
| Entrées `SECTION_LABELS`/`ICONS`/`NAV_ORDER` | Task 2, Step 4 |
| Vue liste chronologique + champs Date/Heure/Nom/Lieu/Responsable | Task 5, Step 5 (Décision #6 : tableau, pas grille agenda) |
| Anniversaires : récupération auto `users.date_naissance` **+** saisie manuelle | Task 4, Step 3 (`birthdays()`) + Task 5, Step 6 |
| Colonnes Nom/Prénom, jour+mois, Âge calculé auto | Task 4 (`age` si `annee` connue) + Task 5, Step 6 |
| Surlignage des anniversaires du mois courant | Task 4 (`is_current_month`) + Task 5, Step 7 (`.anniv-current`) |
| Actions `save_evenement`/`delete_evenement`/`save_anniversaire`/`delete_anniversaire` | Task 6 |
| RBAC via `responsibilities` (`auth_can_manage_calendar`) | Task 2, Step 3 |
| Édition/suppression événement = admin + `created_by` + `responsable_id` | Task 2, Step 3 (`auth_can_edit_evenement`) ; Task 6 Steps 3-4 |
| `save_presence_occurrence` accepte `unit_type='evenement'`, population = tous les membres, accessible depuis la fiche événement | Task 7 |
| CSS `assets/css/calendrier.css` | Task 5, Step 7 |
| Table d'appoint pour non-utilisateurs (ex. « AKELE NZUE Leïla 30/11 ») | Task 3 (`AnniversaireRepository`), Task 4 (`saveBirthday`) |
| Masquage de membres du calendrier | Hors périmètre — Décision #4 (spec §6b : non demandé) |
| Grille mensuelle type agenda | Non retenue — Décision #6 (tableau chronologique, autorisé par la spec) |
| Matrice annuelle de présence pour un événement | Non retenue — Décision #5 (événement non récurrent) |

**2. Placeholder scan :** chaque step fournit le code exact et la commande exacte avec sa sortie attendue. Les « vérifier l'idiome exact » (Task 4 Step 4 accessor `data.php`, Task 6 Step 3 appel CSRF) nomment précisément quoi copier et depuis quel voisin — ce sont des ancrages sur des conventions du dépôt, pas des TODO de logique.

**3. Type consistency :**
- `saveEvent()` / `saveBirthday()` renvoient toujours `['ok'=>bool,'errors'=>array,'id'=>?int]` — consommé identiquement par les actions (Task 6) et les scripts d'assertion (Task 4).
- `birthdays()` renvoie des entrées `{nom,jour,mois,annee,source,id,age,is_current_month}` — clés utilisées à l'identique dans `Views/pages/anniversaires.php` et `m4_service_check.php`.
- `unitType` pour le moteur de présence : `bacenta|cult|basonta|evenement` — `UNIT_COLUMNS` (Task 7 Step 3), l'action (Task 7 Step 4) et la fiche (Task 7 Step 6) alignés ; `evenement` mappe la colonne `evenement_id`.
- `auth_can_manage_calendar(): bool` et `auth_can_edit_evenement(array): bool` — signatures fixes, référencées en Tasks 2, 5, 6, 7.
- `EvenementRepository::create/update` : ordre des paramètres `(nom, dateDebut, dateFin, lieu, responsableId[, createdBy])` identique entre le repo (Task 3), le service (Task 4) et les assertions.

**4. Ordre des tâches :** 1 (schéma) → 2 (RBAC/nav, indépendant du schéma mais requis par la suite) → 3 (repos, dépend de 1) → 4 (service, dépend de 3) → 5 (contrôleur/vues, dépend de 4 et 2) → 6 (actions, dépend de 4/5) → 7 (addendum présence événement, dépend de 1/4/5/6 et du M1 déjà livré). Séquentiel strict.

---

## Execution Handoff

Sept tâches séquentielles. Chacune se termine par un livrable testable (script d'assertion contre la base de dev réelle + `php -l` + smoke-render des vues) et un commit.
