# M6 — Classes / Écoles post-culte (discipleship) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gérer les cursus de discipleship proposés après le culte : une grille des classes (nom, formateur, nombre de modules, prochaine session, actif/inactif — CRUD complet), et pour chaque classe la liste des inscrits avec, par inscrit, le nombre de modules validés et le statut des examens oral / écrit. Quand un inscrit valide **les deux** examens (`reussi`), il est marqué `termine` et **automatiquement inscrit dans la classe d'`ordre` immédiatement supérieur**.

**Architecture:** Code neuf en couches strictes : `ClasseController` → `ClasseService` → `ClasseRepository` → `App\Core\Query`. Deux nouvelles tables (`classes`, `classe_inscrits`) dans le fichier de migration unique, plus un **seed idempotent des 7 cursus dans la migration elle-même** (le `DatabaseSeeder` fait un TRUNCATE d'une liste figée et ne rejoue pas — inadapté). Deux routes GET (`classes` grille, `classe` détail), quatre actions (`save_classe`, `save_classe_inscrit` en POST ; `delete_classe`, `remove_classe_inscrit` en GET). Nouveau helper RBAC `auth_can_manage_classes()`. La progression automatique se fait dans `ClasseService` sous `Query::transaction()`, idempotente via `UNIQUE(classe_id, user_id)`.

**Tech Stack:** PHP 8 SSR, micro-framework maison, zéro dépendance. MySQL/MariaDB via `App\Core\Query`. Pas de PHPUnit — vérification = `php -l` + scripts d'assertion `php` contre la base de dev + smoke-render des vues.

**Spec:** `docs/superpowers/specs/2026-09-01-integration-modules-eglise-design.md` (§4 « M6 »)

## Global Constraints

- Zéro dépendance externe : pas de Composer, npm, build, Docker.
- PSR-12, `declare(strict_types=1)`, types sur paramètres et retours.
- Couches strictes : SQL uniquement dans un Repository ; HTML uniquement dans une View ; `$_POST`/`$_GET` uniquement dans un Controller / `ActionsController`.
- Schéma : instructions idempotentes uniquement dans `Database/Migrations/2024_01_01_000000_create_schema.php` (`CREATE TABLE IF NOT EXISTS` ; le seed des cursus gardé par `SELECT COUNT(*) FROM classes = 0`). Toute nouvelle table est ajoutée à `down()`.
- CSS modulaire sous `assets/css/`, `@import` dans `assets/css/app.css`, variables de `assets/css/variables.css` (`--primary --primary-soft --card --border --text --text-soft --text-muted --success --danger --warning --space-1..12 --radius --radius-md --radius-sm --shadow-sm --shadow-xs`), aucun style/script inline dans une vue (`onchange="this.form.submit()"` = motif projet établi, autorisé).
- Ne jamais casser une URL, l'auth, un formulaire existant. On ajoute une page.
- RBAC sur les données, jamais seulement l'affichage. Un `classe_id` / `user_id` / `id` reçu n'est jamais fait confiance : re-vérifier côté serveur (`auth_can_manage_classes()`), revalider le `user_id` d'un inscrit (doit être un compte membre plausible).
- `check_csrf()` est déjà appelé une fois en tête de `ActionsController::postAction()` — les nouveaux cas POST n'en ajoutent aucun ; les suppressions GET ne sont pas protégées CSRF dans ce projet (motif `delete_evenement`) — reproduire tel quel.
- `install.php` reste supprimable.
- Comptes de démo : `admin@labelleeglise.ga` / `LBEGF` (admin) ; `berger.eric.bongo@labelleeglise.ga` / `BergerEB1` (berger) ; `resp.bacenta.sion@labelleeglise.ga` / `ESKLna` (responsable) ; `user@labelleeglise.ga` / `user1111` (membre).
- Base de dev joignable : MySQL `127.0.0.1:3306`, `root`, db `la_belle_eglise_db` (`.env` configuré). Si vide, repeupler avec `Database\Seeders\seed()` (non destructif du schéma) — ne pas committer d'artefact.

## Décisions de cadrage (spec §4 M6 + Q/R — tranchées ici, spec = autorité)

1. **Seed des 7 cursus dans la migration**, gardé par `classes` vide. Le `DatabaseSeeder` n'est pas modifié.
2. **`auth_can_manage_classes()`** = admin **OU** (`role ∈ {'berger','ms','pasteur','reverant'}` **ET** l'utilisateur possède ≥ 1 ligne `responsibilities` avec `responsibility_type = 'manager'`). `leader` est volontairement exclu (spec). C'est aussi le gate d'accès aux deux pages et à toutes les actions.
3. **Progression automatique** (dans `ClasseService::saveInscrit`, sous `Query::transaction()`) :
   - Déclencheur : après upsert d'un `classe_inscrit`, si `exam_oral === 'reussi'` **ET** `exam_ecrit === 'reussi'`.
   - Effet : `UPDATE classe_inscrits SET statut = 'termine' WHERE id = :inscritId`. Puis `SELECT id FROM classes WHERE actif = 1 AND ordre > :ordreCourant ORDER BY ordre ASC, id ASC LIMIT 1`. Si trouvée : `INSERT IGNORE INTO classe_inscrits (classe_id, user_id) VALUES (:nextId, :userId)` (les colonnes non fournies prennent leurs `DEFAULT` : `modules_valides = 0`, examens `non_passe`, `statut = 'inscrit'`).
   - **Idempotent** : re-sauvegarder ne duplique jamais (contrainte `UNIQUE(classe_id, user_id)` + `INSERT IGNORE`).
   - **Pas de rétrogradation** : si un statut d'examen repasse à `echoue`/`non_passe`, on ne touche NI à `statut` (il reste ce qu'il était, y compris `termine`), NI à l'inscription de la classe suivante. `statut` n'est jamais remis à `inscrit` par le service.
4. **`modules_valides`** est borné à `[0, classes.nb_modules]` de la classe de l'inscrit, côté service.
5. **`ordre`** n'a pas de contrainte `UNIQUE` (schéma spec). Le seed donne 1..7 distincts. Si l'admin crée deux classes de même `ordre`, la progression `LIMIT 1` en choisit une (déterministe : `ordre ASC, id ASC`). Documenté, non bloquant.
6. **Formateur** : `formateur_id` est un `<select>` de comptes `role ∈ {'berger','ms','pasteur','reverant','leader','admin'}`, facultatif (`NULL`).
7. **Inscription d'un membre** : `<select>` des comptes `role IN ('membre','leader','assistant','pasteur','reverant')` pas encore inscrits à cette classe (motif `MemberRepository::candidatesForBasonta`).
8. **Suppressions** : `delete_classe` et `remove_classe_inscrit` en GET via `getAction()` (motif `delete_evenement` / `basonta_remove_member`), gardées par `auth_can_manage_classes()`. `DELETE classes` fait tomber ses `classe_inscrits` par `ON DELETE CASCADE`.
9. **Suivi/examens** : les colonnes `exam_note` / `exam_date` sont facultatives et purement informatives (pas de règle métier dessus). Une seule note/date par inscrit (pas d'historique) — spec ne demande pas d'historique.
10. **Deux actions d'inscrit distinctes** (imbrication `<form>` valide — motif `Views/pages/suivi_week.php`) :
    - `save_classe_inscrit` (singulier) — l'`inline-add-form` en haut de la fiche : `{classe_id, user_id}` seul → crée l'inscription (valeurs par défaut).
    - `save_classe_inscrits` (pluriel) — **un seul `<form>` englobant toute la table** des inscrits, champs indexés `name="inscrit[<inscritId>][modules_valides]"`, `[exam_oral]`, `[exam_ecrit]`, `[exam_note]`, `[exam_date]`, un seul bouton « Enregistrer ». L'action itère `$_POST['inscrit']`, retrouve le `user_id` de chaque inscritId via `repo->findInscrit`, et appelle `saveInscrit(...)` par ligne (progression auto incluse).

## Constante `CLASSES_CURSUS`

Dans `Config/constants.php` (près de `RAPPORT_JOUR_FIELDS`) :

```php
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
```

## Schéma

```sql
CREATE TABLE IF NOT EXISTS classes (
    id INT NOT NULL AUTO_INCREMENT,
    nom VARCHAR(150) NOT NULL,
    formateur_id INT NULL,
    ordre INT NOT NULL DEFAULT 0,
    nb_modules INT NOT NULL DEFAULT 1,
    prochaine_session DATE NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_classe_ordre (ordre),
    CONSTRAINT fk_classe_formateur FOREIGN KEY (formateur_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classe_inscrits (
    id INT NOT NULL AUTO_INCREMENT,
    classe_id INT NOT NULL,
    user_id INT NOT NULL,
    modules_valides INT NOT NULL DEFAULT 0,
    exam_oral  ENUM('non_passe','reussi','echoue') NOT NULL DEFAULT 'non_passe',
    exam_ecrit ENUM('non_passe','reussi','echoue') NOT NULL DEFAULT 'non_passe',
    exam_note DECIMAL(5,2) NULL,
    exam_date DATE NULL,
    statut ENUM('inscrit','termine') NOT NULL DEFAULT 'inscrit',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_inscrit (classe_id, user_id),
    CONSTRAINT fk_ci_classe FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## File Structure

| Fichier | Rôle | Action |
|---|---|---|
| `Database/Migrations/2024_01_01_000000_create_schema.php` | Migration unique | Modifier : bloc « 13 » (`classes`, `classe_inscrits`, seed des 7 cursus si vide) ; `down()` |
| `Config/constants.php` | Constantes | Modifier : `CLASSES_CURSUS`, `EXAM_STATUTS`, `SECTION_LABELS`, `SECTION_ICONS`, `NAV_ORDER` |
| `app/Auth/compat.php` | Wrappers RBAC globaux | Modifier : `auth_can_manage_classes()` |
| `Views/layouts/layout.php` | Sidebar | Modifier : lien `classes` (motif hoist M4/M5) |
| `app/Repositories/ClasseRepository.php` | SQL classes + inscrits | Créer |
| `app/Services/ClasseService.php` | Validation + progression automatique | Créer |
| `app/Controllers/ClasseController.php` | HTTP → vue | Créer : `index()`, `detail()` |
| `Routes/web.php` | Routes | Modifier : `classes`, `classe` |
| `Views/pages/classes.php` | Grille des classes + formulaire | Créer |
| `Views/pages/classe_detail.php` | Détail classe + inscrits + examens | Créer |
| `assets/css/classes.css` | Styles M6 | Créer + `@import` dans `app.css` |
| `app/Controllers/ActionsController.php` | Dispatch POST/GET | Modifier : `save_classe`, `save_classe_inscrit` (postAction) ; `delete_classe`, `remove_classe_inscrit` (getAction) |
| `app/Compat/data.php` | Wrappers globaux | Modifier : accessor `classe_service()` |

---

### Task 1: Schéma `classes` + `classe_inscrits` + seed des 7 cursus + constantes

**Files:**
- Modify: `Database/Migrations/2024_01_01_000000_create_schema.php` (fin de `up()` après le bloc « 12 » de M5 ; `down()`)
- Modify: `Config/constants.php` (`CLASSES_CURSUS`, `EXAM_STATUTS` près de `RAPPORT_JOUR_FIELDS`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_schema_check.php`

**Interfaces:**
- Consumes: rien.
- Produces :
  - Table `classes` conforme au bloc SQL ci-dessus (FK `formateur_id` → `users(id) ON DELETE SET NULL`, `KEY idx_classe_ordre`).
  - Table `classe_inscrits` conforme (2 ENUM `('non_passe','reussi','echoue')`, ENUM `statut ('inscrit','termine')`, `UNIQUE(classe_id, user_id)`, FK CASCADE vers `classes` et `users`).
  - Seed : si `SELECT COUNT(*) FROM classes = 0`, insérer 7 lignes `(nom = CLASSES_CURSUS[i], ordre = i+1, nb_modules = 1, actif = 1)`.
  - `down()` : `'classes'` et `'classe_inscrits'` ajoutés à `$tables` (après `'rapports_jour'`).
  - Constante `CLASSES_CURSUS` (7 chaînes, ordre du spec) ; `EXAM_STATUTS` (`non_passe|reussi|echoue` → libellés).

- [ ] **Step 1: Écrire l'assertion qui échoue**

Créer `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_schema_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;

function tbl(string $t): bool {
    return (bool) Query::value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t]);
}
function col(string $t, string $c): ?array {
    return Query::one('SELECT DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$t, $c]);
}
function idxCols(string $t, string $i): array {
    return array_map(static fn($r) => $r['COLUMN_NAME'] ?? $r['column_name'],
        Query::all('SELECT COLUMN_NAME FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? ORDER BY SEQ_IN_INDEX', [$t, $i]));
}

assert(tbl('classes'), 'table classes manquante');
assert(tbl('classe_inscrits'), 'table classe_inscrits manquante');
foreach (['nom','formateur_id','ordre','nb_modules','prochaine_session','actif','created_at'] as $c) {
    assert(col('classes', $c) !== null, "classes.$c manquante");
}
foreach (['classe_id','user_id','modules_valides','exam_oral','exam_ecrit','exam_note','exam_date','statut','created_at'] as $c) {
    assert(col('classe_inscrits', $c) !== null, "classe_inscrits.$c manquante");
}
$eo = col('classe_inscrits', 'exam_oral');
assert(stripos($eo['COLUMN_TYPE'], "enum('non_passe','reussi','echoue')") === 0, 'exam_oral ENUM incorrect: ' . $eo['COLUMN_TYPE']);
assert(stripos(col('classe_inscrits', 'statut')['COLUMN_TYPE'], "enum('inscrit','termine')") === 0, 'statut ENUM incorrect');
assert(idxCols('classe_inscrits', 'uniq_inscrit') === ['classe_id','user_id'], 'uniq_inscrit KO: ' . implode(',', idxCols('classe_inscrits','uniq_inscrit')));

$cursus = Query::all('SELECT nom, ordre FROM classes ORDER BY ordre');
assert(count($cursus) >= 7, 'les 7 cursus ne sont pas semés (' . count($cursus) . ')');
assert($cursus[0]['nom'] === 'Manuel du nouveau croyant' && (int) $cursus[0]['ordre'] === 1, 'cursus 1 KO');
assert($cursus[6]['nom'] === "École de l'apologétique" && (int) $cursus[6]['ordre'] === 7, 'cursus 7 KO');

assert(defined('CLASSES_CURSUS') && count(CLASSES_CURSUS) === 7, 'CLASSES_CURSUS KO');
assert(defined('EXAM_STATUTS') && array_keys(EXAM_STATUTS) === ['non_passe','reussi','echoue'], 'EXAM_STATUTS KO');

echo "OK m6 schema\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_schema_check.php`
Expected: FAIL — `AssertionError: table classes manquante` (ou `CLASSES_CURSUS KO`).

- [ ] **Step 3: Ajouter les constantes**

`Config/constants.php`, après `define('RAPPORT_JOUR_FIELDS', [ … ]);` — coller les deux `define(...)` de la section « Constante `CLASSES_CURSUS` » ci-dessus, verbatim.

- [ ] **Step 4: Ajouter le bloc de migration**

`Database/Migrations/2024_01_01_000000_create_schema.php`, dans `up()`, tout à la fin (après le bloc « 12. M5 — Rapport du Jour », avant l'accolade fermante de `up()`) :

```php

    /* ---- 13. M6 — Classes / Écoles post-culte (discipleship) -----------
     * classes : cursus (nom, formateur, ordre de progression, nb de modules,
     * prochaine session, actif). classe_inscrits : un inscrit par (classe,
     * user) — modules validés + statut des examens oral/écrit. Progression
     * automatique gérée côté service (ClasseService). Les 7 cursus par
     * défaut sont semés ici si la table est vide (le DatabaseSeeder fait un
     * TRUNCATE d'une liste figée et ne rejoue pas — inadapté).
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS classes (
            id INT NOT NULL AUTO_INCREMENT,
            nom VARCHAR(150) NOT NULL,
            formateur_id INT NULL,
            ordre INT NOT NULL DEFAULT 0,
            nb_modules INT NOT NULL DEFAULT 1,
            prochaine_session DATE NULL,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_classe_ordre (ordre),
            CONSTRAINT fk_classe_formateur FOREIGN KEY (formateur_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS classe_inscrits (
            id INT NOT NULL AUTO_INCREMENT,
            classe_id INT NOT NULL,
            user_id INT NOT NULL,
            modules_valides INT NOT NULL DEFAULT 0,
            exam_oral  ENUM('non_passe','reussi','echoue') NOT NULL DEFAULT 'non_passe',
            exam_ecrit ENUM('non_passe','reussi','echoue') NOT NULL DEFAULT 'non_passe',
            exam_note DECIMAL(5,2) NULL,
            exam_date DATE NULL,
            statut ENUM('inscrit','termine') NOT NULL DEFAULT 'inscrit',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_inscrit (classe_id, user_id),
            CONSTRAINT fk_ci_classe FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
            CONSTRAINT fk_ci_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Seed idempotent des 7 cursus : uniquement si aucune classe n'existe.
    if ((int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn() === 0) {
        $cursus = [
            'Manuel du nouveau croyant',
            'Sept grands principes',
            'Ce que signifie être un chrétien fort',
            'École de la fondation solide',
            'École de la vie victorieuse',
            'École de la parole',
            "École de l'apologétique",
        ];
        $ins = $pdo->prepare('INSERT INTO classes (nom, ordre, nb_modules, actif) VALUES (?, ?, 1, 1)');
        foreach ($cursus as $i => $nom) {
            $ins->execute([$nom, $i + 1]);
        }
    }
```

> Le tableau de cursus est répété littéralement ici (et non lu depuis `CLASSES_CURSUS`) car le fichier de migration ne charge pas `Config/constants.php`. Garder les deux listes identiques.

- [ ] **Step 5: Mettre à jour `down()`**

Dans `down()`, la liste `$tables` — ajouter `'classes'` et `'classe_inscrits'` juste après `'rapports_jour'` :

```php
    $tables = ['responsibilities', 'notifications', 'users_basontas', 'presences', 'evenements', 'anniversaires', 'rapports_jour', 'classe_inscrits', 'classes', 'offrandes', 'visites', 'suivi_hebdo', 'dimes',
               'examens', 'veillees', 'cultes', 'basontas', 'bacentas', 'users',
               'centres_presentation', 'equipe', 'presentation', 'centres'];
```

- [ ] **Step 6: Appliquer + GREEN + idempotence**

```bash
cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise
php -r 'require "Bootstrap/init.php"; require "Database/Migrations/2024_01_01_000000_create_schema.php"; \Database\Migrations\up(); echo "up() OK\n";'
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_schema_check.php
php -r 'require "Bootstrap/init.php"; require "Database/Migrations/2024_01_01_000000_create_schema.php"; \Database\Migrations\up(); echo "re-run OK\n";'
php -r 'require "Bootstrap/init.php"; echo "classes count after 2 runs: " . App\Core\Query::value("SELECT COUNT(*) FROM classes") . "\n";'
```
Expected: `up() OK` → `OK m6 schema` → `re-run OK` → `classes count after 2 runs: 7` (le seed ne rejoue pas).

- [ ] **Step 7: Lint + commit**

```bash
php -l Database/Migrations/2024_01_01_000000_create_schema.php && php -l Config/constants.php
git add Database/Migrations/2024_01_01_000000_create_schema.php Config/constants.php
git commit -m "$(cat <<'EOF'
feat(classes): schéma classes + classe_inscrits + seed des 7 cursus

Bloc de migration 13 : classes (nom, formateur, ordre, nb_modules,
prochaine_session, actif) et classe_inscrits (modules_valides, examens
oral/écrit ENUM, statut, UNIQUE(classe_id, user_id)). Seed idempotent des
7 cursus si la table est vide. Constantes CLASSES_CURSUS / EXAM_STATUTS.
down() mis à jour.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: RBAC + navigation

**Files:**
- Modify: `app/Auth/compat.php` (près de `auth_can_report_any`)
- Modify: `Config/constants.php` (`SECTION_LABELS`, `SECTION_ICONS`, `NAV_ORDER`)
- Modify: `Views/layouts/layout.php` (bloc hoist)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_rbac_check.php`

**Interfaces:**
- Consumes: rien.
- Produces :
  - `auth_can_manage_classes(): bool` — `current_user()` null → false ; admin → true ; sinon `in_array($u['role'] ?? '', ['berger','ms','pasteur','reverant'], true)` **ET** `(int) \App\Core\Query::value("SELECT COUNT(*) FROM responsibilities WHERE user_id = ? AND responsibility_type = 'manager'", [$uid]) > 0`.
  - `SECTION_LABELS['classes'] = 'Classes & Écoles'` ; `SECTION_ICONS['classes'] = '<i class="fa-solid fa-graduation-cap"></i>'` ; `'classes'` dans `NAV_ORDER` après `'rapports'`, avant `'parametres'`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_rbac_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';

assert(function_exists('auth_can_manage_classes'), 'auth_can_manage_classes absente');
assert(isset(SECTION_LABELS['classes'], SECTION_ICONS['classes']), 'SECTION_LABELS/ICONS classes manquant');
assert(in_array('classes', NAV_ORDER, true), 'classes absent de NAV_ORDER');
assert(auth_can_manage_classes() === false, 'sans session auth_can_manage_classes doit être false');

echo "OK m6 rbac\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_rbac_check.php`
Expected: FAIL — `AssertionError: auth_can_manage_classes absente`.

- [ ] **Step 3: Helper RBAC**

`app/Auth/compat.php`, après `auth_can_report_for_centre()` :

```php
/** Gestion des classes/écoles post-culte : admin OU rôle pastoral désigné (manager). */
function auth_can_manage_classes(): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (($u['role'] ?? '') === 'admin') {
        return true;
    }
    if (!in_array($u['role'] ?? '', ['berger', 'ms', 'pasteur', 'reverant'], true)) {
        return false;
    }
    return (int) \App\Core\Query::value(
        "SELECT COUNT(*) FROM responsibilities WHERE user_id = ? AND responsibility_type = 'manager'",
        [(int) $u['id']]
    ) > 0;
}
```

- [ ] **Step 4: Constantes de navigation**

`Config/constants.php` :
- `SECTION_LABELS` : `'classes' => 'Classes & Écoles',` (après `'rapports'`).
- `SECTION_ICONS` : `'classes' => '<i class="fa-solid fa-graduation-cap"></i>',`.
- `NAV_ORDER` : `'classes',` juste après `'rapports'`, avant `'parametres'`.

- [ ] **Step 5: Lien de menu (motif hoist)**

`Views/layouts/layout.php` — juste après le bloc `if ($user && !$isAdmin && auth_can_report_any()) { … }` ajouté par M5 :

```php
// Classes / Écoles : lien pour tout gestionnaire de classes non-admin
// (l'admin l'a déjà via la boucle NAV_ORDER).
if ($user && !$isAdmin && auth_can_manage_classes()) {
    $navLis[] = '<li><a class="nav-item' . ($page === 'classes' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'classes'])) . '"><span class="ico">' . SECTION_ICONS['classes'] . '</span><span class="label">' . h(SECTION_LABELS['classes']) . '</span></a></li>';
}
```

- [ ] **Step 6: GREEN + lint + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_rbac_check.php
php -l app/Auth/compat.php && php -l Config/constants.php && php -l Views/layouts/layout.php
git add app/Auth/compat.php Config/constants.php Views/layouts/layout.php
git commit -m "$(cat <<'EOF'
feat(classes): helper RBAC auth_can_manage_classes + entrée de navigation

admin, ou rôle pastoral (berger/ms/pasteur/reverant) détenant une
responsabilité manager. classes dans SECTION_LABELS/ICONS/NAV_ORDER et
dans le menu des gestionnaires non-admin (motif hoist M4/M5).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: `ClasseRepository`

**Files:**
- Create: `app/Repositories/ClasseRepository.php`
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_repo_check.php`

**Interfaces:**
- Consumes de Task 1 : tables `classes`, `classe_inscrits`.
- Produces : `App\Repositories\ClasseRepository`
  - `all(): array` — `SELECT c.*, f.prenom AS formateur_prenom, f.nom AS formateur_nom, (SELECT COUNT(*) FROM classe_inscrits ci WHERE ci.classe_id = c.id) AS nb_inscrits FROM classes c LEFT JOIN users f ON f.id = c.formateur_id ORDER BY c.ordre, c.id`
  - `find(int $id): ?array` — mêmes colonnes jointes, `WHERE c.id = ?`
  - `create(string $nom, ?int $formateurId, int $ordre, int $nbModules, ?string $prochaineSession, int $actif): int`
  - `update(int $id, string $nom, ?int $formateurId, int $ordre, int $nbModules, ?string $prochaineSession, int $actif): void`
  - `delete(int $id): void` — `DELETE FROM classes WHERE id = ?`
  - `nextActiveClassId(int $ordre): ?int` — `SELECT id FROM classes WHERE actif = 1 AND ordre > ? ORDER BY ordre ASC, id ASC LIMIT 1`
  - `inscritsOf(int $classeId): array` — `SELECT ci.*, u.prenom, u.nom, u.email FROM classe_inscrits ci JOIN users u ON u.id = ci.user_id WHERE ci.classe_id = ? ORDER BY u.prenom, u.nom`
  - `findInscrit(int $id): ?array` — `SELECT * FROM classe_inscrits WHERE id = ?`
  - `findInscritByClasseUser(int $classeId, int $userId): ?array`
  - `insertInscrit(int $classeId, int $userId): int` — `INSERT IGNORE INTO classe_inscrits (classe_id, user_id) VALUES (?, ?)` ; renvoie l'id existant ou nouveau (relire via `findInscritByClasseUser` si `rowCount() === 0`)
  - `updateInscrit(int $id, int $modulesValides, string $examOral, string $examEcrit, ?float $examNote, ?string $examDate): void` — n'écrit PAS `statut` (géré par le service)
  - `setInscritStatut(int $id, string $statut): void`
  - `deleteInscrit(int $id): void`
  - `candidates(int $classeId): array` — `SELECT id, prenom, nom FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') AND id NOT IN (SELECT user_id FROM classe_inscrits WHERE classe_id = ?) ORDER BY prenom, nom`
  - `formateurCandidates(): array` — `SELECT id, prenom, nom FROM users WHERE role IN ('berger','ms','pasteur','reverant','leader','admin') ORDER BY prenom, nom`

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_repo_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;
use App\Repositories\ClasseRepository;

$u1 = (int) Query::value('SELECT id FROM users ORDER BY id LIMIT 1');
$repo = new ClasseRepository();

$cid = $repo->create('ZZ_M6_A', null, 90, 3, null, 1);
$cid2 = $repo->create('ZZ_M6_B', null, 91, 2, '2029-09-09', 1);
$row = $repo->find($cid);
assert($row['nom'] === 'ZZ_M6_A' && (int) $row['nb_modules'] === 3, 'find classe KO');
assert((int) $row['nb_inscrits'] === 0, 'nb_inscrits initial KO');

assert($repo->nextActiveClassId(90) === $cid2, 'nextActiveClassId KO: ' . var_export($repo->nextActiveClassId(90), true));
assert($repo->nextActiveClassId(91) === null, 'nextActiveClassId au-delà du dernier doit être null');

$iid = $repo->insertInscrit($cid, $u1);
assert($iid > 0, 'insertInscrit KO');
assert($repo->insertInscrit($cid, $u1) === $iid, 'insertInscrit ré-appelé doit renvoyer le même id (INSERT IGNORE)');
$repo->updateInscrit($iid, 2, 'reussi', 'non_passe', 14.5, '2029-05-05');
$ins = $repo->findInscrit($iid);
assert((int) $ins['modules_valides'] === 2 && $ins['exam_oral'] === 'reussi' && (float) $ins['exam_note'] === 14.5, 'updateInscrit KO');
assert($ins['statut'] === 'inscrit', 'updateInscrit ne doit pas toucher statut');
$repo->setInscritStatut($iid, 'termine');
assert($repo->findInscrit($iid)['statut'] === 'termine', 'setInscritStatut KO');

$cands = $repo->candidates($cid);
assert(count(array_filter($cands, fn($c) => (int) $c['id'] === $u1)) === 0, 'candidates doit exclure les déjà-inscrits');

$repo->deleteInscrit($iid);
assert($repo->findInscrit($iid) === null, 'deleteInscrit KO');
$repo->delete($cid); $repo->delete($cid2);
echo "OK m6 repo\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_repo_check.php`
Expected: FAIL — `Error: Class "App\Repositories\ClasseRepository" not found`.

- [ ] **Step 3: Créer `app/Repositories/ClasseRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Classes / écoles post-culte et inscriptions.
 */
class ClasseRepository
{
    private const SELECT = "SELECT c.*, f.prenom AS formateur_prenom, f.nom AS formateur_nom,
                                   (SELECT COUNT(*) FROM classe_inscrits ci WHERE ci.classe_id = c.id) AS nb_inscrits
                              FROM classes c
                              LEFT JOIN users f ON f.id = c.formateur_id";

    public function all(): array
    {
        return Query::all(self::SELECT . ' ORDER BY c.ordre, c.id');
    }

    public function find(int $id): ?array
    {
        return Query::one(self::SELECT . ' WHERE c.id = ?', [$id]);
    }

    public function create(string $nom, ?int $formateurId, int $ordre, int $nbModules, ?string $prochaineSession, int $actif): int
    {
        return Query::run(
            'INSERT INTO classes (nom, formateur_id, ordre, nb_modules, prochaine_session, actif) VALUES (?, ?, ?, ?, ?, ?)',
            [$nom, $formateurId, $ordre, $nbModules, $prochaineSession, $actif]
        );
    }

    public function update(int $id, string $nom, ?int $formateurId, int $ordre, int $nbModules, ?string $prochaineSession, int $actif): void
    {
        Query::run(
            'UPDATE classes SET nom = ?, formateur_id = ?, ordre = ?, nb_modules = ?, prochaine_session = ?, actif = ? WHERE id = ?',
            [$nom, $formateurId, $ordre, $nbModules, $prochaineSession, $actif, $id]
        );
    }

    public function delete(int $id): void
    {
        Query::run('DELETE FROM classes WHERE id = ?', [$id]);
    }

    public function nextActiveClassId(int $ordre): ?int
    {
        $id = Query::value('SELECT id FROM classes WHERE actif = 1 AND ordre > ? ORDER BY ordre ASC, id ASC LIMIT 1', [$ordre]);
        return $id ? (int) $id : null;
    }

    /* ---------------- Inscrits ---------------- */

    public function inscritsOf(int $classeId): array
    {
        return Query::all(
            'SELECT ci.*, u.prenom, u.nom, u.email
               FROM classe_inscrits ci JOIN users u ON u.id = ci.user_id
              WHERE ci.classe_id = ? ORDER BY u.prenom, u.nom',
            [$classeId]
        );
    }

    public function findInscrit(int $id): ?array
    {
        return Query::one('SELECT * FROM classe_inscrits WHERE id = ?', [$id]);
    }

    public function findInscritByClasseUser(int $classeId, int $userId): ?array
    {
        return Query::one('SELECT * FROM classe_inscrits WHERE classe_id = ? AND user_id = ?', [$classeId, $userId]);
    }

    /** INSERT IGNORE ; renvoie l'id (existant ou nouveau). */
    public function insertInscrit(int $classeId, int $userId): int
    {
        Query::run('INSERT IGNORE INTO classe_inscrits (classe_id, user_id) VALUES (?, ?)', [$classeId, $userId]);
        $row = $this->findInscritByClasseUser($classeId, $userId);
        return $row ? (int) $row['id'] : 0;
    }

    public function updateInscrit(int $id, int $modulesValides, string $examOral, string $examEcrit, ?float $examNote, ?string $examDate): void
    {
        Query::run(
            'UPDATE classe_inscrits SET modules_valides = ?, exam_oral = ?, exam_ecrit = ?, exam_note = ?, exam_date = ? WHERE id = ?',
            [$modulesValides, $examOral, $examEcrit, $examNote, $examDate, $id]
        );
    }

    public function setInscritStatut(int $id, string $statut): void
    {
        Query::run('UPDATE classe_inscrits SET statut = ? WHERE id = ?', [$statut, $id]);
    }

    public function deleteInscrit(int $id): void
    {
        Query::run('DELETE FROM classe_inscrits WHERE id = ?', [$id]);
    }

    /* ---------------- Listes de sélection ---------------- */

    public function candidates(int $classeId): array
    {
        return Query::all(
            "SELECT id, prenom, nom FROM users
              WHERE role IN ('membre','leader','assistant','pasteur','reverant')
                AND id NOT IN (SELECT user_id FROM classe_inscrits WHERE classe_id = ?)
              ORDER BY prenom, nom",
            [$classeId]
        );
    }

    public function formateurCandidates(): array
    {
        return Query::all(
            "SELECT id, prenom, nom FROM users
              WHERE role IN ('berger','ms','pasteur','reverant','leader','admin')
              ORDER BY prenom, nom"
        );
    }
}
```

- [ ] **Step 4: GREEN + lint + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_repo_check.php
php -l app/Repositories/ClasseRepository.php
git add app/Repositories/ClasseRepository.php
git commit -m "$(cat <<'EOF'
feat(classes): ClasseRepository (classes CRUD + inscrits + listes de sélection)

nextActiveClassId (classe d'ordre supérieur pour la progression),
insertInscrit (INSERT IGNORE idempotent), updateInscrit qui ne touche pas
statut, setInscritStatut, candidates/formateurCandidates.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: `ClasseService` (validation + progression automatique)

**Files:**
- Create: `app/Services/ClasseService.php`
- Modify: `app/Compat/data.php` (accessor `classe_service()`, forme `_repo(...)`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_service_check.php`

**Interfaces:**
- Consumes de Task 3 : `ClasseRepository`. De Task 1 : `EXAM_STATUTS`.
- Produces : `App\Services\ClasseService`
  - `__construct(?ClasseRepository $repo = null)`
  - `all(): array` / `find(int $id): ?array` / `inscrits(int $classeId): array` / `candidates(int $id): array` / `formateurCandidates(): array` / `findInscrit(int $id): ?array` — délégations.
  - `saveClasse(array $in): array` — `['ok'=>bool,'errors'=>array<string,string>,'id'=>?int]`. Valide : `nom` requis ; `ordre` entier ≥ 0 ; `nb_modules` entier ≥ 1 ; `prochaine_session` vide ou `Y-m-d` valide ; `formateur_id` (facultatif) `?: null` ; `actif` → `0|1`. `id` présent → update.
  - `saveInscrit(array $in): array` — `['ok'=>bool,'errors'=>array<string,string>,'id'=>?int,'promoted_to'=>?int]`.
    - Valide : `classe_id` entier > 0 et classe existante ; `user_id` entier > 0 et compte existant (`SELECT id FROM users WHERE id = ?`) — sinon `errors`. `exam_oral` / `exam_ecrit` ∈ `array_keys(EXAM_STATUTS)` (défaut `non_passe`). `exam_note` vide ou float ; `exam_date` vide ou `Y-m-d`. `modules_valides` entier, **borné à `[0, classe.nb_modules]`**.
    - `Query::transaction(function () { … })` :
      1. `insertInscrit(classe_id, user_id)` → `$inscritId` (crée si absent).
      2. `updateInscrit($inscritId, modules, oral, ecrit, note, date)`.
      3. Si `oral === 'reussi' && ecrit === 'reussi'` : `setInscritStatut($inscritId, 'termine')` ; `$nextId = repo->nextActiveClassId(classe.ordre)` ; si `$nextId`, `repo->insertInscrit($nextId, user_id)` → `promoted_to = $nextId` (sinon `promoted_to = null`).
    - Renvoie `id => $inscritId`, `promoted_to => $nextId|null`.
  - `deleteInscrit(int $id): void` / `deleteClasse(int $id): void`.
  - `app/Compat/data.php` : `function classe_service(): \App\Services\ClasseService { return _repo(\App\Services\ClasseService::class); }`

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_service_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;
use App\Services\ClasseService;

$u1 = (int) Query::value("SELECT id FROM users WHERE role = 'membre' ORDER BY id LIMIT 1");
$svc = new ClasseService();

// validation classe
$bad = $svc->saveClasse(['nom' => '', 'nb_modules' => '0']);
assert($bad['ok'] === false && isset($bad['errors']['nom'], $bad['errors']['nb_modules']), 'validation classe KO: ' . json_encode($bad['errors']));

// deux classes consécutives
$a = $svc->saveClasse(['nom' => 'ZZ M6 SVC A', 'ordre' => '80', 'nb_modules' => '3', 'actif' => '1']);
$b = $svc->saveClasse(['nom' => 'ZZ M6 SVC B', 'ordre' => '81', 'nb_modules' => '2', 'actif' => '1']);
assert($a['ok'] && $b['ok'], 'création classes KO');
$ca = $a['id']; $cb = $b['id'];

// modules_valides borné à nb_modules (3)
$r = $svc->saveInscrit(['classe_id' => $ca, 'user_id' => $u1, 'modules_valides' => '9', 'exam_oral' => 'non_passe', 'exam_ecrit' => 'non_passe']);
assert($r['ok'] === true, 'saveInscrit KO: ' . json_encode($r['errors'] ?? []));
$iid = $r['id'];
$ins = $svc->find($ca); // touche pas, juste smoke
$row = Query::one('SELECT * FROM classe_inscrits WHERE id = ?', [$iid]);
assert((int) $row['modules_valides'] === 3, 'modules_valides doit être borné à nb_modules (3), vu ' . $row['modules_valides']);
assert($row['statut'] === 'inscrit', 'statut initial doit être inscrit');
assert($r['promoted_to'] === null, 'pas de promotion sans les 2 examens réussis');

// user_id inexistant → erreur
$badU = $svc->saveInscrit(['classe_id' => $ca, 'user_id' => '999999', 'exam_oral' => 'non_passe', 'exam_ecrit' => 'non_passe']);
assert($badU['ok'] === false && isset($badU['errors']['user_id']), 'user_id inexistant doit être refusé');

// les 2 examens réussis → termine + promotion vers cb
$p = $svc->saveInscrit(['classe_id' => $ca, 'user_id' => $u1, 'modules_valides' => '3', 'exam_oral' => 'reussi', 'exam_ecrit' => 'reussi']);
assert($p['ok'] === true && $p['promoted_to'] === $cb, 'promotion attendue vers ' . $cb . ', vu ' . var_export($p['promoted_to'], true));
assert(Query::one('SELECT statut FROM classe_inscrits WHERE id = ?', [$iid])['statut'] === 'termine', 'statut doit passer à termine');
assert(Query::value('SELECT COUNT(*) FROM classe_inscrits WHERE classe_id = ? AND user_id = ?', [$cb, $u1]) == 1, 'inscription auto en classe suivante KO');

// idempotence : re-sauvegarder ne duplique pas
$p2 = $svc->saveInscrit(['classe_id' => $ca, 'user_id' => $u1, 'modules_valides' => '3', 'exam_oral' => 'reussi', 'exam_ecrit' => 'reussi']);
assert(Query::value('SELECT COUNT(*) FROM classe_inscrits WHERE classe_id = ? AND user_id = ?', [$cb, $u1]) == 1, 'promotion re-jouée ne doit pas dupliquer');

Query::run('DELETE FROM classes WHERE id IN (?, ?)', [$ca, $cb]); // CASCADE nettoie les inscrits
echo "OK m6 service\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_service_check.php`
Expected: FAIL — `Error: Class "App\Services\ClasseService" not found`.

- [ ] **Step 3: Créer `app/Services/ClasseService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Query;
use App\Repositories\ClasseRepository;

/**
 * Classes / écoles post-culte : validation + progression automatique.
 * Quand un inscrit valide les deux examens (oral + écrit à 'reussi'), il
 * est marqué 'termine' et inscrit dans la classe d'ordre supérieur.
 */
class ClasseService
{
    private ClasseRepository $repo;

    public function __construct(?ClasseRepository $repo = null)
    {
        $this->repo = $repo ?? new ClasseRepository();
    }

    public function all(): array { return $this->repo->all(); }
    public function find(int $id): ?array { return $this->repo->find($id); }
    public function findInscrit(int $id): ?array { return $this->repo->findInscrit($id); }
    public function inscrits(int $classeId): array { return $this->repo->inscritsOf($classeId); }
    public function candidates(int $classeId): array { return $this->repo->candidates($classeId); }
    public function formateurCandidates(): array { return $this->repo->formateurCandidates(); }
    public function deleteInscrit(int $id): void { $this->repo->deleteInscrit($id); }
    public function deleteClasse(int $id): void { $this->repo->delete($id); }

    /** @return array{ok:bool,errors:array<string,string>,id:?int} */
    public function saveClasse(array $in): array
    {
        $errors = [];
        $nom = trim((string) ($in['nom'] ?? ''));
        if ($nom === '') {
            $errors['nom'] = 'Le nom est obligatoire.';
        }
        $ordre = (int) ($in['ordre'] ?? 0);
        if ($ordre < 0) {
            $errors['ordre'] = "L'ordre doit être positif.";
        }
        $nbModules = (int) ($in['nb_modules'] ?? 1);
        if ($nbModules < 1) {
            $errors['nb_modules'] = 'Au moins un module.';
        }
        $session = trim((string) ($in['prochaine_session'] ?? ''));
        if ($session !== '') {
            $ts = strtotime($session);
            if ($ts === false || date('Y-m-d', $ts) !== $session) {
                $errors['prochaine_session'] = 'Date invalide (AAAA-MM-JJ).';
            }
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $formateurId = (int) ($in['formateur_id'] ?? 0) ?: null;
        $actif = !empty($in['actif']) ? 1 : 0;
        $sessionVal = $session !== '' ? $session : null;
        $id = (int) ($in['id'] ?? 0);
        if ($id) {
            $this->repo->update($id, $nom, $formateurId, $ordre, $nbModules, $sessionVal, $actif);
        } else {
            $id = $this->repo->create($nom, $formateurId, $ordre, $nbModules, $sessionVal, $actif);
        }
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }

    /** @return array{ok:bool,errors:array<string,string>,id:?int,promoted_to:?int} */
    public function saveInscrit(array $in): array
    {
        $errors = [];
        $classeId = (int) ($in['classe_id'] ?? 0);
        $classe = $classeId > 0 ? $this->repo->find($classeId) : null;
        if (!$classe) {
            $errors['classe_id'] = 'Classe introuvable.';
        }
        $userId = (int) ($in['user_id'] ?? 0);
        if ($userId <= 0 || !Query::value('SELECT id FROM users WHERE id = ?', [$userId])) {
            $errors['user_id'] = 'Membre introuvable.';
        }

        $valid = array_keys(EXAM_STATUTS);
        $oral = in_array($in['exam_oral'] ?? '', $valid, true) ? $in['exam_oral'] : 'non_passe';
        $ecrit = in_array($in['exam_ecrit'] ?? '', $valid, true) ? $in['exam_ecrit'] : 'non_passe';

        $noteRaw = trim((string) ($in['exam_note'] ?? ''));
        $note = $noteRaw === '' ? null : (float) str_replace(',', '.', $noteRaw);
        $dateRaw = trim((string) ($in['exam_date'] ?? ''));
        $date = null;
        if ($dateRaw !== '') {
            $ts = strtotime($dateRaw);
            if ($ts === false || date('Y-m-d', $ts) !== $dateRaw) {
                $errors['exam_date'] = 'Date invalide.';
            } else {
                $date = $dateRaw;
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null, 'promoted_to' => null];
        }

        $modules = max(0, min((int) ($in['modules_valides'] ?? 0), (int) $classe['nb_modules']));
        $ordreCourant = (int) $classe['ordre'];

        $result = Query::transaction(function () use ($classeId, $userId, $modules, $oral, $ecrit, $note, $date, $ordreCourant) {
            $inscritId = $this->repo->insertInscrit($classeId, $userId);
            $this->repo->updateInscrit($inscritId, $modules, (string) $oral, (string) $ecrit, $note, $date);

            $promotedTo = null;
            if ($oral === 'reussi' && $ecrit === 'reussi') {
                $this->repo->setInscritStatut($inscritId, 'termine');
                $nextId = $this->repo->nextActiveClassId($ordreCourant);
                if ($nextId !== null) {
                    $this->repo->insertInscrit($nextId, $userId);
                    $promotedTo = $nextId;
                }
            }
            return ['id' => $inscritId, 'promoted_to' => $promotedTo];
        });

        return ['ok' => true, 'errors' => [], 'id' => $result['id'], 'promoted_to' => $result['promoted_to']];
    }
}
```

- [ ] **Step 4: Accessor compat**

`app/Compat/data.php`, avec les autres accessors de services :

```php
function classe_service(): \App\Services\ClasseService { return _repo(\App\Services\ClasseService::class); }
```

- [ ] **Step 5: GREEN + lint + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_service_check.php
php -l app/Services/ClasseService.php && php -l app/Compat/data.php
git add app/Services/ClasseService.php app/Compat/data.php
git commit -m "$(cat <<'EOF'
feat(classes): ClasseService — validation + progression automatique

saveInscrit : upsert de l'inscription (INSERT IGNORE), modules_valides
borné à classe.nb_modules, et quand oral+écrit sont tous deux 'reussi',
statut -> 'termine' + inscription automatique (idempotente) dans la classe
active d'ordre supérieur. Le tout sous Query::transaction.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Contrôleur, routes et vues

**Files:**
- Create: `app/Controllers/ClasseController.php`
- Modify: `Routes/web.php`
- Create: `Views/pages/classes.php`, `Views/pages/classe_detail.php`
- Create: `assets/css/classes.css` + `@import` dans `assets/css/app.css`
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_view_check.php`

**Interfaces:**
- Consumes de Task 4 : `classe_service()`. De Task 2 : `auth_can_manage_classes()`. De Task 1 : `EXAM_STATUTS`.
- Produces :
  - `App\Controllers\ClasseController` (`declare(strict_types=1)`, extends `Controller`) :
    - `index(): void` — GET `?page=classes`. `!current_user()` → `redirect(page=apropos)` ; `!auth_can_manage_classes()` → `redirect(page=accueil)`. `?edit=<id>` → `$edit = classe_service()->find($id)` sinon `null`. Passe à `view('pages/classes', …)` : `classes` (`service->all()`), `edit`, `formateurs` (`service->formateurCandidates()`), `errors` `[]`, `old` `[]`, `csrf`.
    - `detail(): void` — GET `?page=classe&id=<id>`. Mêmes gardes. `$classe = service->find($id)` sinon `redirect(page=classes)`. Passe à `view('pages/classe_detail', …)` : `classe`, `inscrits` (`service->inscrits($id)`), `candidates` (`service->candidates($id)`), `statuts` (`EXAM_STATUTS`), `errors` `[]`, `old` `[]`, `csrf`.
  - Routes `Router::get('classes', ClasseController::class, 'index')`, `Router::get('classe', ClasseController::class, 'detail')` + `use App\Controllers\ClasseController;`.
  - `assets/css/app.css` : `@import url('classes.css');` après `@import url('rapports.css');`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_view_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';

assert(is_file('app/Controllers/ClasseController.php'), 'ClasseController absent');
assert(is_file('Views/pages/classes.php'), 'vue classes absente');
assert(is_file('Views/pages/classe_detail.php'), 'vue classe_detail absente');
assert(is_file('assets/css/classes.css'), 'classes.css absent');

$routes = file_get_contents('Routes/web.php');
assert(str_contains($routes, "'classes'") && str_contains($routes, "'classe'"), 'routes M6 absentes');
assert(str_contains($routes, 'use App\Controllers\ClasseController;'), 'use ClasseController absent');
assert(str_contains(file_get_contents('assets/css/app.css'), "@import url('classes.css')"), 'classes.css non importé');

$grid = view('pages/classes', [
    'classes' => [], 'edit' => null, 'formateurs' => [], 'errors' => [], 'old' => [], 'csrf' => '',
]);
assert(str_contains($grid, 'save_classe'), 'la vue grille ne poste pas save_classe');

$det = view('pages/classe_detail', [
    'classe' => ['id' => 1, 'nom' => 'X', 'nb_modules' => 3, 'ordre' => 1, 'actif' => 1, 'prochaine_session' => null, 'formateur_prenom' => null, 'formateur_nom' => null, 'nb_inscrits' => 0],
    'inscrits' => [], 'candidates' => [['id' => 2, 'prenom' => 'A', 'nom' => 'B']],
    'statuts' => EXAM_STATUTS, 'errors' => [], 'old' => [], 'csrf' => '',
]);
assert(str_contains($det, 'save_classe_inscrit'), 'la vue détail ne poste pas save_classe_inscrit');

echo "OK m6 views\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_view_check.php`
Expected: FAIL — `AssertionError: ClasseController absent`.

- [ ] **Step 3: Créer `app/Controllers/ClasseController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

/**
 * Classes / écoles post-culte : grille + détail d'une classe.
 */
class ClasseController extends Controller
{
    private function guard(): void
    {
        if (!current_user()) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        if (!auth_can_manage_classes()) {
            $this->redirect('index.php', ['page' => 'accueil']);
        }
    }

    public function index(): void
    {
        $this->guard();
        $editId = (int) (Request::get('edit') ?? 0);
        render_page(SECTION_LABELS['classes'], view('pages/classes', [
            'classes'    => classe_service()->all(),
            'edit'       => $editId ? classe_service()->find($editId) : null,
            'formateurs' => classe_service()->formateurCandidates(),
            'errors'     => [],
            'old'        => [],
            'csrf'       => csrf_field(),
        ]));
    }

    public function detail(): void
    {
        $this->guard();
        $id = (int) (Request::get('id') ?? 0);
        $classe = $id ? classe_service()->find($id) : null;
        if (!$classe) {
            $this->redirect('index.php', ['page' => 'classes']);
        }
        render_page($classe['nom'], view('pages/classe_detail', [
            'classe'     => $classe,
            'inscrits'   => classe_service()->inscrits($id),
            'candidates' => classe_service()->candidates($id),
            'statuts'    => EXAM_STATUTS,
            'errors'     => [],
            'old'        => [],
            'csrf'       => csrf_field(),
        ]));
    }
}
```

Vérifier l'alignement sur `CalendrierController` / `RapportController` (M4/M5) : `redirect` `: never`, `Request::get`, `render_page(SECTION_LABELS[...], view(...))`.

- [ ] **Step 4: Routes**

`Routes/web.php` : `use App\Controllers\ClasseController;` + près des autres pages GET :

```php
Router::get('classes', ClasseController::class, 'index');
Router::get('classe', ClasseController::class, 'detail');
```

- [ ] **Step 5: Créer `Views/pages/classes.php`**

```php
<?php /* Grille des classes / écoles + formulaire.
   Variables : $classes, $edit, $formateurs, $errors, $old, $csrf. */
$e = $edit ?? [];
$val = fn($k, $d = '') => h($old[$k] ?? ($e[$k] ?? $d));
?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['classes']) ?></h2><div class="sub">Cursus de discipleship proposés après le culte</div></div>
</div>

<form method="post" action="index.php" class="form-card classe-form">
  <input type="hidden" name="action" value="save_classe">
  <?= $csrf ?>
  <?php if (!empty($e['id'])): ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><?php endif; ?>
  <div class="form-group">
    <label>Nom de la classe</label>
    <input type="text" name="nom" value="<?= $val('nom') ?>" required>
    <?php if (!empty($errors['nom'])): ?><span class="form-error"><?= h($errors['nom']) ?></span><?php endif; ?>
  </div>
  <div class="form-grid">
    <div class="form-group">
      <label>Formateur</label>
      <select name="formateur_id">
        <option value="">—</option>
        <?php foreach ($formateurs as $f): ?>
          <option value="<?= (int) $f['id'] ?>" <?= (int) ($old['formateur_id'] ?? ($e['formateur_id'] ?? 0)) === (int) $f['id'] ? 'selected' : '' ?>><?= h(trim($f['prenom'] . ' ' . $f['nom'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Ordre de progression</label><input type="number" name="ordre" min="0" value="<?= $val('ordre', '0') ?>"></div>
    <div class="form-group"><label>Nombre de modules</label><input type="number" name="nb_modules" min="1" value="<?= $val('nb_modules', '1') ?>">
      <?php if (!empty($errors['nb_modules'])): ?><span class="form-error"><?= h($errors['nb_modules']) ?></span><?php endif; ?>
    </div>
    <div class="form-group"><label>Prochaine session</label><input type="date" name="prochaine_session" value="<?= $val('prochaine_session') ?>">
      <?php if (!empty($errors['prochaine_session'])): ?><span class="form-error"><?= h($errors['prochaine_session']) ?></span><?php endif; ?>
    </div>
    <div class="form-group"><label class="check-label"><input type="checkbox" name="actif" value="1" <?= !empty($old) ? (!empty($old['actif']) ? 'checked' : '') : (!isset($e['actif']) || $e['actif'] ? 'checked' : '') ?>> Classe active</label></div>
  </div>
  <div class="modal-actions">
    <?php if (!empty($e['id'])): ?><a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'classes'])) ?>">Annuler</a><?php endif; ?>
    <button type="submit" class="btn btn-primary"><?= !empty($e['id']) ? 'Enregistrer' : 'Ajouter la classe' ?></button>
  </div>
</form>

<div class="card-grid">
  <?php foreach ($classes as $c): ?>
    <div class="unit-card" onclick="location.href='<?= h(url('index.php', ['page' => 'classe', 'id' => $c['id']])) ?>'">
      <div class="card-actions">
        <a class="icon-btn" title="Modifier" href="<?= h(url('index.php', ['page' => 'classes', 'edit' => $c['id']])) ?>" onclick="event.stopPropagation()"><i class="fa-solid fa-pen"></i></a>
        <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cette classe et toutes ses inscriptions ?" href="<?= h(url('index.php', ['action' => 'delete_classe', 'id' => $c['id']])) ?>" onclick="event.stopPropagation()"><i class="fa-solid fa-trash"></i></a>
      </div>
      <div class="icon-wrap"><i class="fa-solid fa-graduation-cap"></i></div>
      <h3><?= h($c['nom']) ?></h3>
      <p><?= (int) $c['ordre'] ?> · <?= (int) $c['nb_inscrits'] ?> inscrit(s) · <?= (int) $c['nb_modules'] ?> module(s)<?= $c['actif'] ? '' : ' · inactive' ?></p>
    </div>
  <?php endforeach; ?>
  <?php if (!$classes): ?><?= empty_state('fa-graduation-cap', 'Aucune classe pour le moment.') ?><?php endif; ?>
</div>
```

- [ ] **Step 6: Créer `Views/pages/classe_detail.php`** (motif `suivi_week.php` : UN `<form>` englobant la table)

```php
<?php /* Détail d'une classe : infos + inscription + tableau des inscrits/examens.
   Variables : $classe, $inscrits, $candidates, $statuts, $errors, $old, $csrf. */ ?>
<div class="section-toolbar">
  <div><h2><?= h($classe['nom']) ?></h2>
    <div class="sub">
      <?= (int) $classe['nb_modules'] ?> module(s) · ordre <?= (int) $classe['ordre'] ?>
      <?php if ($classe['formateur_prenom'] || $classe['formateur_nom']): ?> · Formateur : <?= h(trim(($classe['formateur_prenom'] ?? '') . ' ' . ($classe['formateur_nom'] ?? ''))) ?><?php endif; ?>
      <?php if (!empty($classe['prochaine_session'])): ?> · Prochaine session : <?= h(date('d/m/Y', strtotime((string) $classe['prochaine_session']))) ?><?php endif; ?>
    </div>
  </div>
  <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'classes'])) ?>"><i class="fa-solid fa-arrow-left"></i> Toutes les classes</a>
</div>

<form method="post" action="index.php" class="inline-add-form">
  <input type="hidden" name="action" value="save_classe_inscrit">
  <?= $csrf ?>
  <input type="hidden" name="classe_id" value="<?= (int) $classe['id'] ?>">
  <select name="user_id" required>
    <option value="">— Inscrire un membre —</option>
    <?php foreach ($candidates as $u): ?>
      <option value="<?= (int) $u['id'] ?>"><?= h(trim($u['prenom'] . ' ' . $u['nom'])) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">+ Inscrire</button>
</form>

<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_classe_inscrits">
  <?= $csrf ?>
  <input type="hidden" name="classe_id" value="<?= (int) $classe['id'] ?>">
  <div class="table-wrap">
    <table class="data-table classe-inscrits">
      <thead><tr><th>Membre</th><th>Modules validés</th><th>Examen oral</th><th>Examen écrit</th><th>Note</th><th>Date</th><th>Statut</th><th></th></tr></thead>
      <tbody>
        <?php if (!$inscrits): ?>
          <tr><td colspan="8"><?= empty_state('fa-users', 'Aucun inscrit pour le moment.') ?></td></tr>
        <?php else: ?>
          <?php foreach ($inscrits as $i): $iid = (int) $i['id']; ?>
            <tr>
              <td><?= h(trim(($i['prenom'] ?? '') . ' ' . ($i['nom'] ?? ''))) ?></td>
              <td><input type="number" name="inscrit[<?= $iid ?>][modules_valides]" min="0" max="<?= (int) $classe['nb_modules'] ?>" value="<?= (int) $i['modules_valides'] ?>"></td>
              <td><select name="inscrit[<?= $iid ?>][exam_oral]"><?php foreach ($statuts as $k => $lbl): ?><option value="<?= h($k) ?>" <?= $i['exam_oral'] === $k ? 'selected' : '' ?>><?= h($lbl) ?></option><?php endforeach; ?></select></td>
              <td><select name="inscrit[<?= $iid ?>][exam_ecrit]"><?php foreach ($statuts as $k => $lbl): ?><option value="<?= h($k) ?>" <?= $i['exam_ecrit'] === $k ? 'selected' : '' ?>><?= h($lbl) ?></option><?php endforeach; ?></select></td>
              <td><input type="text" inputmode="decimal" name="inscrit[<?= $iid ?>][exam_note]" value="<?= h($i['exam_note'] ?? '') ?>"></td>
              <td><input type="date" name="inscrit[<?= $iid ?>][exam_date]" value="<?= h($i['exam_date'] ?? '') ?>"></td>
              <td><span class="badge <?= $i['statut'] === 'termine' ? 'present' : '' ?>"><?= $i['statut'] === 'termine' ? 'Terminé' : 'Inscrit' ?></span></td>
              <td class="row-actions">
                <a class="icon-btn danger" title="Retirer" data-confirm="Retirer cet inscrit ?" href="<?= h(url('index.php', ['action' => 'remove_classe_inscrit', 'id' => $iid])) ?>"><i class="fa-solid fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($inscrits): ?><div class="modal-actions"><button type="submit" class="btn btn-primary">Enregistrer les évaluations</button></div><?php endif; ?>
</form>
```

Notes : (a) le `<a>` « Retirer » est hors du `<form>` visuellement mais reste dans le flux DOM du form — c'est un lien GET, aucun impact sur la soumission POST ; c'est le même motif que les liens `data-confirm` ailleurs. (b) `data-table td input/select` reçoit déjà `width:100%` de `forms.css` — pas de style inline.

- [ ] **Step 7: Créer `assets/css/classes.css` + import**

```css
/* M6 — Classes / Écoles post-culte */

.classe-form {
  margin-bottom: var(--space-6);
}

.classe-inscrits td input[type="text"],
.classe-inscrits td input[type="date"],
.classe-inscrits td input[type="number"],
.classe-inscrits td select {
  width: 100%;
  min-width: 5rem;
}

.classe-inscrits .badge.present {
  background: var(--success-soft);
  color: var(--success);
}
```

Puis `assets/css/app.css` : `@import url('classes.css');` juste après `@import url('rapports.css');`.

- [ ] **Step 8: GREEN + lint + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_view_check.php
php -l app/Controllers/ClasseController.php && php -l Routes/web.php && php -l Views/pages/classes.php && php -l Views/pages/classe_detail.php
git add app/Controllers/ClasseController.php Routes/web.php Views/pages/classes.php Views/pages/classe_detail.php assets/css/classes.css assets/css/app.css
git commit -m "$(cat <<'EOF'
feat(classes): grille des classes + détail avec inscrits et examens

ClasseController (grille CRUD + détail d'une classe : inscription d'un
membre, saisie des modules validés et du statut des examens oral/écrit).
Routes classes / classe, CSS assets/css/classes.css.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Actions POST/GET

**Files:**
- Modify: `app/Controllers/ActionsController.php` (postAction : `save_classe`, `save_classe_inscrit` ; getAction : `delete_classe`, `remove_classe_inscrit`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_action_check.php`

**Interfaces:**
- Consumes de Task 4 : `classe_service()`. De Task 2 : `auth_can_manage_classes()`.
- Requires de Task 4 : une méthode `ClasseService::findInscrit(int $id): ?array` (délégation vers `repo->findInscrit`) — l'ajouter en Task 4 Step 3 si absente, ou l'ajouter ici en modifiant `ClasseService` (le commit de Task 6 inclut déjà `app/Services/ClasseService.php`).
- Produces :
  - POST `save_classe` : `$this->requireUser()` ; `if (!auth_can_manage_classes()) $this->deny();` ; `$res = classe_service()->saveClasse($_POST)` ; sur `!$res['ok']` → re-render `view('pages/classes', [...])` (mêmes clés que `ClasseController::index()` : `classes, edit, formateurs, errors, old, csrf` — `edit` = `$editId ? find($editId) : null`, `errors` = `$res['errors']`, `old` = `$_POST`) puis `return;` ; sinon `redirect(page=classes)`.
  - POST `save_classe_inscrit` (**singulier — ajout d'un inscrit**) : `requireUser` + `auth_can_manage_classes()` else `deny` ; `$classeId = (int)($_POST['classe_id'] ?? 0)` ; `classe_service()->saveInscrit($_POST)` (le service crée via `INSERT IGNORE`) ; `redirect(page=classe, id=$classeId)` (ou `page=classes` si `!$classeId`).
  - POST `save_classe_inscrits` (**pluriel — enregistrement du tableau**) : mêmes gardes ; `$classeId = (int)($_POST['classe_id'] ?? 0)` ; `foreach ((array)($_POST['inscrit'] ?? []) as $inscritId => $fields) { $ins = classe_service()->findInscrit((int) $inscritId); if (!$ins || (int) $ins['classe_id'] !== $classeId) continue; classe_service()->saveInscrit(array_merge((array) $fields, ['classe_id' => $classeId, 'user_id' => (int) $ins['user_id']])); }` ; `redirect(page=classe, id=$classeId)`.
  - GET `delete_classe` : `requireUser` + `auth_can_manage_classes()` else `deny` ; `$id = (int)($_GET['id'] ?? 0)` ; `if ($id) classe_service()->deleteClasse($id)` ; `redirect(page=classes)`.
  - GET `remove_classe_inscrit` : `requireUser` + `auth_can_manage_classes()` else `deny` ; `$id = (int)($_GET['id'] ?? 0)` ; `$ins = $id ? classe_service()->findInscrit($id) : null` ; `$classeId = $ins ? (int) $ins['classe_id'] : 0` ; `if ($id) classe_service()->deleteInscrit($id)` ; `redirect` vers `page=classe&id=$classeId` si connu, sinon `page=classes`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_action_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
$src = file_get_contents('app/Controllers/ActionsController.php');
assert(str_contains($src, "case 'save_classe'"), 'cas save_classe absent');
assert(str_contains($src, "case 'save_classe_inscrit'"), 'cas save_classe_inscrit (singulier) absent');
assert(str_contains($src, "case 'save_classe_inscrits'"), 'cas save_classe_inscrits (pluriel) absent');
assert(str_contains($src, "case 'delete_classe'"), 'cas delete_classe absent');
assert(str_contains($src, "case 'remove_classe_inscrit'"), 'cas remove_classe_inscrit absent');
assert(str_contains($src, 'auth_can_manage_classes()'), 'garde RBAC classes absente');
assert(str_contains(file_get_contents('app/Services/ClasseService.php'), 'function findInscrit'), 'ClasseService::findInscrit absente');

require 'Bootstrap/init.php';
use App\Core\Query;
$svc = classe_service();
$c = $svc->saveClasse(['nom' => 'ZZ M6 ACT', 'ordre' => '70', 'nb_modules' => '2', 'actif' => '1']);
assert($c['ok'] === true, 'saveClasse e2e KO');
$u = (int) Query::value("SELECT id FROM users WHERE role='membre' ORDER BY id LIMIT 1");
$r = $svc->saveInscrit(['classe_id' => $c['id'], 'user_id' => $u, 'exam_oral' => 'non_passe', 'exam_ecrit' => 'non_passe', 'modules_valides' => '1']);
assert($r['ok'] === true, 'saveInscrit e2e KO');
$svc->deleteClasse($c['id']);
echo "OK m6 action\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_action_check.php`
Expected: FAIL — `AssertionError: cas save_classe absent`.

- [ ] **Step 3: Ajouter les cas POST**

`app/Controllers/ActionsController.php`, dans `postAction()`, à côté des cas M5 :

```php
            /* ---------- Classes / Écoles (M6) ---------- */

            case 'save_classe': {
                $this->requireUser();
                if (!auth_can_manage_classes()) {
                    $this->deny();
                }
                $res = classe_service()->saveClasse($_POST);
                if (!$res['ok']) {
                    $editId = (int) ($_POST['id'] ?? 0);
                    render_page(SECTION_LABELS['classes'], view('pages/classes', [
                        'classes'    => classe_service()->all(),
                        'edit'       => $editId ? classe_service()->find($editId) : null,
                        'formateurs' => classe_service()->formateurCandidates(),
                        'errors'     => $res['errors'],
                        'old'        => $_POST,
                        'csrf'       => csrf_field(),
                    ]));
                    return;
                }
                $this->redirect('index.php', ['page' => 'classes']);
                break;
            }

            case 'save_classe_inscrit': {
                $this->requireUser();
                if (!auth_can_manage_classes()) {
                    $this->deny();
                }
                $classeId = (int) ($_POST['classe_id'] ?? 0);
                classe_service()->saveInscrit($_POST);
                $this->redirect('index.php', $classeId ? ['page' => 'classe', 'id' => $classeId] : ['page' => 'classes']);
                break;
            }

            case 'save_classe_inscrits': {
                $this->requireUser();
                if (!auth_can_manage_classes()) {
                    $this->deny();
                }
                $classeId = (int) ($_POST['classe_id'] ?? 0);
                foreach ((array) ($_POST['inscrit'] ?? []) as $inscritId => $fields) {
                    $ins = classe_service()->findInscrit((int) $inscritId);
                    if (!$ins || (int) $ins['classe_id'] !== $classeId) {
                        continue;
                    }
                    classe_service()->saveInscrit(array_merge((array) $fields, [
                        'classe_id' => $classeId,
                        'user_id'   => (int) $ins['user_id'],
                    ]));
                }
                $this->redirect('index.php', $classeId ? ['page' => 'classe', 'id' => $classeId] : ['page' => 'classes']);
                break;
            }
```

Ajouter aussi la délégation dans `app/Services/ClasseService.php` si elle n'existe pas déjà :

```php
    public function findInscrit(int $id): ?array { return $this->repo->findInscrit($id); }
```

- [ ] **Step 4: Ajouter les cas GET**

Dans `getAction()`, à côté des cas M4 (`delete_evenement`) :

```php
            case 'delete_classe': {
                $this->requireUser();
                if (!auth_can_manage_classes()) {
                    $this->deny();
                }
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    classe_service()->deleteClasse($id);
                }
                $this->redirect('index.php', ['page' => 'classes']);
                break;
            }

            case 'remove_classe_inscrit': {
                $this->requireUser();
                if (!auth_can_manage_classes()) {
                    $this->deny();
                }
                $id = (int) ($_GET['id'] ?? 0);
                $ins = $id ? classe_service()->findInscrit($id) : null;
                $classeId = $ins ? (int) $ins['classe_id'] : 0;
                if ($id) {
                    classe_service()->deleteInscrit($id);
                }
                $this->redirect('index.php', $classeId ? ['page' => 'classe', 'id' => $classeId] : ['page' => 'classes']);
                break;
            }
```

- [ ] **Step 5: GREEN + lint + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m6_action_check.php
php -l app/Controllers/ActionsController.php && php -l app/Services/ClasseService.php
git add app/Controllers/ActionsController.php app/Services/ClasseService.php
git commit -m "$(cat <<'EOF'
feat(classes): actions save_classe(_inscrit[s]) + delete_classe / remove_classe_inscrit

requireUser + auth_can_manage_classes sur les 5 cas. save_classe re-rend
la grille avec erreurs + saisie sur échec. save_classe_inscrit ajoute un
inscrit ; save_classe_inscrits enregistre le tableau ligne à ligne (chaque
ligne passe par le service : validation + progression auto). Suppressions
en GET (motif delete_evenement). ClasseService::findInscrit ajouté.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review

**1. Spec coverage (§4 « M6 ») :**

| Exigence spec | Tâche |
|---|---|
| Nouvel onglet « Classes » | Task 5 (route `classes` + entrée de menu Task 2) |
| Lister les 7 cursus distincts (ordre exact, « apologétique » corrigé) | Task 1 (seed migration + `CLASSES_CURSUS`) |
| Nom du formateur (par classe) | Task 1 (`classes.formateur_id`), Task 5 (`<select>` formateurs) |
| Liste des inscrits (liée aux noms des membres) | Task 3 (`inscritsOf` jointe `users`), Task 5 (`classe_detail.php`) |
| Niveau / progression = **structure fixe** (nb de modules + validés) | Task 1 (`nb_modules`, `modules_valides`), Task 4 (borne `[0, nb_modules]`) |
| Date de la prochaine session | Task 1 (`prochaine_session`), Task 5 (champ + affichage) |
| Ajouter / supprimer / éditer une classe | Task 5 (form) + Task 6 (`save_classe`, `delete_classe`) |
| Examens oral / écrit = **statut** (`non_passe`/`reussi`/`echoue`) + note + date facultatives | Task 1 (2 ENUM + `exam_note`/`exam_date`), Task 4, Task 5 |
| Progression **automatique** vers la classe d'ordre supérieur quand les 2 examens sont Réussis | Task 4 (`saveInscrit` sous transaction : `statut='termine'` + `insertInscrit(nextActiveClassId)`) |
| Idempotence de la progression | Task 4 (`INSERT IGNORE` + `UNIQUE(classe_id, user_id)`) ; assertion dédiée `m6_service_check.php` |
| Pas de rétrogradation automatique | Décision #3 ; `updateInscrit` n'écrit jamais `statut` ; le service ne le remet jamais à `inscrit` |
| Qui gère / inscrit : bergers, révérend, pasteurs, ms (désignés) | Task 2 (`auth_can_manage_classes` = admin OU rôle pastoral + responsabilité manager) |
| CSS modulaire | Task 5 (`assets/css/classes.css`) |

**2. Placeholder scan :** chaque step fournit code exact + commande + sortie attendue. `classe_detail.php` (Task 5 Step 6) est désormais un fichier complet suivant le motif `suivi_week.php` (un `<form>` englobant, champs `inscrit[<id>][…]`) — plus d'ambiguïté d'imbrication. Les « vérifier l'alignement sur CalendrierController » sont des ancrages sur des conventions M4/M5 déjà en place, pas des TODO.

**3. Type consistency :**
- `saveClasse()` / `saveInscrit()` renvoient `['ok'=>bool,'errors'=>array<string,string>,'id'=>?int(,'promoted_to'=>?int)]` — consommés par Task 6 et les scripts d'assertion.
- `ClasseRepository::insertInscrit()` renvoie `int` (id existant ou nouveau) — utilisé par `saveInscrit` (`$inscritId`) et l'assertion d'idempotence.
- `nextActiveClassId(int): ?int` — consommé par `saveInscrit` (`$nextId !== null`).
- `EXAM_STATUTS` : clés `non_passe|reussi|echoue` — whitelist dans `saveInscrit`, `<option>` dans `classe_detail.php`.
- `all()`/`find()` renvoient des lignes avec `formateur_prenom`, `formateur_nom`, `nb_inscrits` — lues par `classes.php` / `classe_detail.php`.
- `modules_valides` : `INT`, borné `[0, nb_modules]` service-side ; `<input type="number" min="0" max="nb_modules">` côté vue (défense en profondeur).

**4. Ordre des tâches :** 1 (schéma+constantes+seed) → 2 (RBAC/nav) → 3 (repo, dépend de 1) → 4 (service, dépend de 3+1) → 5 (contrôleur/vues, dépend de 4+2) → 6 (actions, dépend de 4+5). Séquentiel strict.

---

## Execution Handoff

Six tâches séquentielles. Chacune se termine par un livrable testable (script d'assertion contre la base de dev réelle + `php -l` + smoke-render des vues) et un commit. La progression automatique (Task 4) est le point le plus délicat — l'assertion `m6_service_check.php` la couvre explicitement (promotion + idempotence + borne `modules_valides` + `user_id` inexistant).
