# M5 — Rapport du Jour des responsables de Bacenta — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un formulaire « Rapport du Jour » où un responsable de bacenta saisit, pour un **centre** et une **date**, les remontées terrain : responsables (dérivés, non modifiables), assistants, assistance chiffrée (présents / adultes / enfants / anciens / nouveaux / nés de nouveau), offrande, livre et chapitre enseignés. Un rapport unique par (centre, date), modifiable ensuite par son auteur ou un admin. Plus une page liste filtrable par centre et par mois.

**Architecture:** Code neuf en couches strictes : `RapportController` → `RapportJourService` → `RapportJourRepository` → `App\Core\Query`. Une nouvelle table `rapports_jour` (bloc de migration idempotent, `UNIQUE(centre_id, date_rapport)`). Deux routes GET (`rapports` liste, `rapport` formulaire) et une action POST (`save_rapport_jour`, upsert). Le formulaire est piloté par la constante `RAPPORT_JOUR_FIELDS`. Les noms de responsables (`resp_centre_nom`, `resp_bacenta_nom`) sont un **instantané** calculé côté service à partir de la table `responsibilities` — jamais saisis par le client. Nouveau helper RBAC `auth_can_report_for_centre(int $centreId)`. Le lien de menu suit le motif « hoist » introduit par M4 (visible aussi pour les responsables non-admin).

**Tech Stack:** PHP 8 SSR, micro-framework maison, zéro dépendance. MySQL/MariaDB via `App\Core\Query`. Pas de PHPUnit — vérification = `php -l` + scripts d'assertion `php` contre la base de dev + smoke-render des vues.

**Spec:** `docs/superpowers/specs/2026-09-01-integration-modules-eglise-design.md` (§4 « M5 »)

## Global Constraints

- Zéro dépendance externe : pas de Composer, npm, build, Docker.
- PSR-12, `declare(strict_types=1)`, types sur paramètres et retours.
- Couches strictes : SQL uniquement dans un Repository ; HTML uniquement dans une View ; `$_POST`/`$_GET` uniquement dans un Controller / `ActionsController`.
- Schéma : instructions idempotentes uniquement dans `Database/Migrations/2024_01_01_000000_create_schema.php`, gardées (`CREATE TABLE IF NOT EXISTS`, `column_exists`/`index_exists` pour les `ALTER`). Toute nouvelle table est ajoutée à `down()`.
- CSS modulaire sous `assets/css/`, `@import` dans `assets/css/app.css`, variables de `assets/css/variables.css` (`--primary --primary-soft --card --border --text --text-muted --text-soft --success --danger --warning --space-1..12 --radius --radius-md --radius-sm --shadow-sm --shadow-xs`), aucun style/script inline dans une vue (l'attribut `onchange="this.form.submit()"` est le motif projet établi, autorisé).
- Ne jamais casser une URL, l'auth, un formulaire existant. On ajoute une page ; on n'en modifie aucune.
- RBAC sur les données, jamais seulement l'affichage. Un `centre_id` / `id` reçu n'est jamais fait confiance : re-vérifier côté serveur (`auth_can_report_for_centre`, et pour l'édition `auteur_id === current` ou admin).
- `check_csrf()` est déjà appelé une seule fois en tête de `ActionsController::postAction()` — le nouveau cas POST n'en ajoute aucun.
- `install.php` reste supprimable.
- Comptes de démo : `admin@labelleeglise.ga` / `LBEGF` (admin) ; `resp.bacenta.sion@labelleeglise.ga` / `ESKLna` (responsable de bacenta) ; `berger.eric.bongo@labelleeglise.ga` / `BergerEB1` (berger).
- Base de dev joignable : MySQL `127.0.0.1:3306`, `root`, db `la_belle_eglise_db` (`.env` configuré). Si la base est vide, la repeupler avec `Database\Seeders\seed()` (non destructif du schéma) — ne pas committer d'artefact.

## Décisions de cadrage (spec §4 M5 + Q/R — tranchées ici, spec = autorité)

1. **`target_type` du centre dans `responsibilities` = `'center'`** (orthographe US, cf. `ResponsibilityRepository`). Le helper et le service utilisent `'center'`, pas `'centre'`.
2. **`resp_centre_nom`** = nom complet du 1ᵉʳ responsable (`responsibilities` `target_type='center'`, `responsibility_type='manager'`) du centre ; chaîne vide si aucun. **`resp_bacenta_nom`** = nom complet du 1ᵉʳ responsable du `bacenta_id` choisi ; à défaut (ou si aucun bacenta choisi) = nom complet de l'auteur du rapport. Les deux sont des **instantanés** stockés en clair, jamais relus depuis la requête.
3. **`bacenta_id` du rapport** : `<select>` limité aux bacentas que l'auteur gère (`responsibilities` `target_type='bacenta'` **ou** `users.bacenta_id`) **et** dont `centre_id` = le centre choisi. Recalculé serveur à chaque rendu et à la sauvegarde. Facultatif (nullable) : un rapport peut être « au niveau centre » sans bacenta précis.
4. **`auth_can_report_for_centre($centreId)`** = admin **OU** l'auteur gère au moins un bacenta (`responsibilities` `target_type='bacenta'` ou `users.bacenta_id`) dont `centre_id = $centreId`. **`auth_can_report_any()`** = admin OU l'auteur gère au moins un bacenta (quel qu'il soit) — sert au lien de menu et à l'accès à la page liste.
5. **Édition** : un rapport n'est modifiable que par `auteur_id === current_user` **ou** un admin. Un autre responsable du même centre voit le rapport en lecture seule (pas de bouton « modifier »).
6. **Flux du formulaire** : on choisit d'abord centre + date (formulaire GET, `onchange` submit), puis le formulaire du rapport de ce (centre, date) s'affiche — pré-rempli si un rapport existe déjà, vierge sinon. Motif identique à `Views/pages/presence_occurrence.php` (M1) et `Views/pages/bacenta_suivi.php`.
7. **Statistiques ultérieures** : hors périmètre v1. Le stockage structuré (une ligne par (centre, date), colonnes typées) les permettra.
8. **Suppression d'un rapport** : hors périmètre v1 (spec ne la mentionne pas ; un rapport erroné se corrige par édition). Pas de `delete_rapport_jour`.

## Constante `RAPPORT_JOUR_FIELDS`

Dans `Config/constants.php` (près de `PRESENCE_STATUTS`) :

```php
define('RAPPORT_JOUR_FIELDS', [
    ['key' => 'nb_presents',       'label' => 'Nombre de personnes présentes',   'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'nb_adultes',        'label' => "Nombre d'adultes",                'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'nb_enfants',        'label' => "Nombre d'enfants",                'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'nb_anciens',        'label' => "Nombre d'anciens",                'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'nb_nouveaux',       'label' => 'Nombre de nouveaux (1re visite)', 'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'nb_nes_de_nouveau', 'label' => 'Nombre de nés de nouveau',        'type' => 'int',      'group' => 'Assistance'],
    ['key' => 'offrande',          'label' => 'Offrande (montant total)',        'type' => 'decimal',  'group' => 'Finances'],
    ['key' => 'assistants',        'label' => 'Noms des assistants',             'type' => 'textarea', 'group' => 'Équipe'],
    ['key' => 'livre_enseigne',    'label' => 'Nom du livre enseigné',           'type' => 'text',     'group' => 'Enseignement'],
    ['key' => 'chapitre_enseigne', 'label' => 'Chapitre enseigné',               'type' => 'text',     'group' => 'Enseignement'],
]);
```

Règles de validation (service) : `int` → `(int)`, refusé si `< 0` ; `decimal` → `(float)` via `str_replace(',', '.', …)`, refusé si `< 0` ; `text` → `trim`, tronqué à 150, `null` si vide ; `textarea` → `trim`, `null` si vide.

## Schéma `rapports_jour`

```sql
CREATE TABLE IF NOT EXISTS rapports_jour (
    id INT NOT NULL AUTO_INCREMENT,
    centre_id INT NOT NULL,
    date_rapport DATE NOT NULL,
    auteur_id INT NOT NULL,
    bacenta_id INT NULL,
    resp_centre_nom  VARCHAR(150) NULL,
    resp_bacenta_nom VARCHAR(150) NULL,
    assistants TEXT NULL,
    nb_presents       INT NOT NULL DEFAULT 0,
    nb_adultes        INT NOT NULL DEFAULT 0,
    nb_enfants        INT NOT NULL DEFAULT 0,
    nb_anciens        INT NOT NULL DEFAULT 0,
    nb_nouveaux       INT NOT NULL DEFAULT 0,
    nb_nes_de_nouveau INT NOT NULL DEFAULT 0,
    offrande DECIMAL(12,2) NOT NULL DEFAULT 0,
    livre_enseigne VARCHAR(150) NULL,
    chapitre_enseigne VARCHAR(80) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_rapport (centre_id, date_rapport),
    KEY idx_rapport_centre_date (centre_id, date_rapport),
    CONSTRAINT fk_rap_centre  FOREIGN KEY (centre_id)  REFERENCES centres(id)  ON DELETE CASCADE,
    CONSTRAINT fk_rap_auteur  FOREIGN KEY (auteur_id)  REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_rap_bacenta FOREIGN KEY (bacenta_id) REFERENCES bacentas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

## File Structure

| Fichier | Rôle | Action |
|---|---|---|
| `Database/Migrations/2024_01_01_000000_create_schema.php` | Migration unique | Modifier : bloc « 12 » (`rapports_jour`) ; `down()` |
| `Config/constants.php` | Constantes | Modifier : `RAPPORT_JOUR_FIELDS`, `SECTION_LABELS`, `SECTION_ICONS`, `NAV_ORDER` |
| `app/Auth/compat.php` | Wrappers RBAC globaux | Modifier : `auth_can_report_for_centre(int)`, `auth_can_report_any()` |
| `Views/layouts/layout.php` | Sidebar | Modifier : lien `rapports` (motif hoist, comme M4) |
| `app/Repositories/RapportJourRepository.php` | SQL rapports_jour | Créer |
| `app/Services/RapportJourService.php` | Validation + instantané responsables + upsert + liste | Créer |
| `app/Controllers/RapportController.php` | HTTP → vue | Créer : `index()`, `form()` |
| `Routes/web.php` | Routes | Modifier : `rapports`, `rapport` |
| `Views/pages/rapports.php` | Liste filtrable | Créer |
| `Views/pages/rapport_form.php` | Sélecteur (centre+date) + formulaire | Créer |
| `assets/css/rapports.css` | Styles M5 | Créer + `@import` dans `app.css` |
| `app/Controllers/ActionsController.php` | Dispatch POST | Modifier : cas `save_rapport_jour` |
| `app/Compat/data.php` | Wrappers globaux | Modifier : accessor `rapport_jour_service()` |

---

### Task 1: Schéma `rapports_jour` + constante `RAPPORT_JOUR_FIELDS`

**Files:**
- Modify: `Database/Migrations/2024_01_01_000000_create_schema.php` (fin de `up()`, après le bloc « 11 » de M4 ; et `down()`)
- Modify: `Config/constants.php` (`RAPPORT_JOUR_FIELDS` près de `PRESENCE_STATUTS`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_schema_check.php`

**Interfaces:**
- Consumes: rien.
- Produces :
  - Table `rapports_jour` conforme au bloc SQL de la section « Schéma » ci-dessus (colonnes, `UNIQUE(centre_id, date_rapport)`, 3 FK).
  - `down()` : `'rapports_jour'` ajouté à `$tables` (juste après `'anniversaires'`).
  - Constante `RAPPORT_JOUR_FIELDS` (10 entrées `{key,label,type,group}`, `type ∈ int|decimal|text|textarea`).

- [ ] **Step 1: Écrire l'assertion qui échoue**

Créer `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_schema_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;

function col(string $t, string $c): ?array {
    return Query::one('SELECT DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$t, $c]);
}
function idxCols(string $t, string $i): array {
    return array_map(
        static fn($r) => $r['COLUMN_NAME'] ?? $r['column_name'],
        Query::all('SELECT COLUMN_NAME FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? ORDER BY SEQ_IN_INDEX', [$t, $i])
    );
}

$cols = ['centre_id','date_rapport','auteur_id','bacenta_id','resp_centre_nom','resp_bacenta_nom','assistants',
         'nb_presents','nb_adultes','nb_enfants','nb_anciens','nb_nouveaux','nb_nes_de_nouveau',
         'offrande','livre_enseigne','chapitre_enseigne','created_at','updated_at'];
foreach ($cols as $c) {
    assert(col('rapports_jour', $c) !== null, "rapports_jour.$c manquante");
}
assert(col('rapports_jour', 'nb_presents')['IS_NULLABLE'] === 'NO', 'nb_presents doit être NOT NULL');
assert(stripos(col('rapports_jour', 'offrande')['COLUMN_TYPE'], 'decimal(12,2)') === 0, 'offrande DECIMAL(12,2) attendu');
assert(idxCols('rapports_jour', 'uniq_rapport') === ['centre_id','date_rapport'], 'uniq_rapport KO: ' . implode(',', idxCols('rapports_jour','uniq_rapport')));

assert(defined('RAPPORT_JOUR_FIELDS'), 'RAPPORT_JOUR_FIELDS non définie');
$byKey = [];
foreach (RAPPORT_JOUR_FIELDS as $f) { $byKey[$f['key']] = $f; }
assert(isset($byKey['nb_presents'], $byKey['offrande'], $byKey['assistants'], $byKey['livre_enseigne'], $byKey['chapitre_enseigne']), 'clés RAPPORT_JOUR_FIELDS incomplètes');
assert($byKey['offrande']['type'] === 'decimal', 'offrande doit être type decimal');
assert($byKey['assistants']['type'] === 'textarea', 'assistants doit être type textarea');
assert($byKey['nb_enfants']['type'] === 'int', 'nb_enfants doit être type int');

echo "OK m5 schema\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_schema_check.php`
Expected: FAIL — `AssertionError: rapports_jour.centre_id manquante` (ou `RAPPORT_JOUR_FIELDS non définie`).

- [ ] **Step 3: Ajouter la constante**

`Config/constants.php`, après `define('PRESENCE_STATUTS', …);` — coller le bloc `define('RAPPORT_JOUR_FIELDS', [ … ]);` de la section « Constante `RAPPORT_JOUR_FIELDS` » ci-dessus, verbatim.

- [ ] **Step 4: Ajouter le bloc de migration**

`Database/Migrations/2024_01_01_000000_create_schema.php`, dans `up()`, tout à la fin (après le bloc « 11. M4 — Calendriers » et sa reconstruction d'index, avant l'accolade fermante de `up()`) :

```php

    /* ---- 12. M5 — Rapport du Jour des responsables de bacenta -----------
     * Un rapport par (centre, date). resp_centre_nom / resp_bacenta_nom sont
     * un INSTANTANÉ rempli côté service depuis `responsibilities` — jamais
     * saisis par le client. bacenta_id facultatif (rapport au niveau centre).
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS rapports_jour (
            id INT NOT NULL AUTO_INCREMENT,
            centre_id INT NOT NULL,
            date_rapport DATE NOT NULL,
            auteur_id INT NOT NULL,
            bacenta_id INT NULL,
            resp_centre_nom  VARCHAR(150) NULL,
            resp_bacenta_nom VARCHAR(150) NULL,
            assistants TEXT NULL,
            nb_presents       INT NOT NULL DEFAULT 0,
            nb_adultes        INT NOT NULL DEFAULT 0,
            nb_enfants        INT NOT NULL DEFAULT 0,
            nb_anciens        INT NOT NULL DEFAULT 0,
            nb_nouveaux       INT NOT NULL DEFAULT 0,
            nb_nes_de_nouveau INT NOT NULL DEFAULT 0,
            offrande DECIMAL(12,2) NOT NULL DEFAULT 0,
            livre_enseigne VARCHAR(150) NULL,
            chapitre_enseigne VARCHAR(80) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_rapport (centre_id, date_rapport),
            KEY idx_rapport_centre_date (centre_id, date_rapport),
            CONSTRAINT fk_rap_centre  FOREIGN KEY (centre_id)  REFERENCES centres(id)  ON DELETE CASCADE,
            CONSTRAINT fk_rap_auteur  FOREIGN KEY (auteur_id)  REFERENCES users(id)    ON DELETE CASCADE,
            CONSTRAINT fk_rap_bacenta FOREIGN KEY (bacenta_id) REFERENCES bacentas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
```

- [ ] **Step 5: Mettre à jour `down()`**

Dans `down()`, la liste `$tables` — ajouter `'rapports_jour'` juste après `'anniversaires'` :

```php
    $tables = ['responsibilities', 'notifications', 'users_basontas', 'presences', 'evenements', 'anniversaires', 'rapports_jour', 'offrandes', 'visites', 'suivi_hebdo', 'dimes',
               'examens', 'veillees', 'cultes', 'basontas', 'bacentas', 'users',
               'centres_presentation', 'equipe', 'presentation', 'centres'];
```

- [ ] **Step 6: Appliquer + GREEN + idempotence**

```bash
cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise
php -r 'require "Bootstrap/init.php"; require "Database/Migrations/2024_01_01_000000_create_schema.php"; \Database\Migrations\up(); echo "up() OK\n";'
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_schema_check.php
php -r 'require "Bootstrap/init.php"; require "Database/Migrations/2024_01_01_000000_create_schema.php"; \Database\Migrations\up(); echo "re-run OK\n";'
```
Expected: `up() OK` → `OK m5 schema` → `re-run OK` (aucune erreur).

- [ ] **Step 7: Lint + commit**

```bash
php -l Database/Migrations/2024_01_01_000000_create_schema.php && php -l Config/constants.php
git add Database/Migrations/2024_01_01_000000_create_schema.php Config/constants.php
git commit -m "$(cat <<'EOF'
feat(rapports): schéma rapports_jour + constante RAPPORT_JOUR_FIELDS

Bloc de migration 12 : table rapports_jour, un rapport par (centre, date)
via UNIQUE(centre_id, date_rapport) ; FK centres CASCADE, users CASCADE,
bacentas SET NULL. down() mis à jour. RAPPORT_JOUR_FIELDS pilote le
formulaire et la validation.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 2: RBAC + navigation

**Files:**
- Modify: `app/Auth/compat.php` (près de `auth_can_manage_calendar`)
- Modify: `Config/constants.php` (`SECTION_LABELS`, `SECTION_ICONS`, `NAV_ORDER`)
- Modify: `Views/layouts/layout.php` (bloc hoist, comme M4)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_rbac_check.php`

**Interfaces:**
- Consumes: rien.
- Produces :
  - `auth_can_report_any(): bool` — admin OU `EXISTS` d'un bacenta géré par l'utilisateur (`responsibilities` `target_type='bacenta'` OU `users.bacenta_id`).
  - `auth_can_report_for_centre(int $centreId): bool` — admin OU `EXISTS` d'un bacenta géré par l'utilisateur dont `centre_id = $centreId`.
  - `SECTION_LABELS['rapports'] = 'Rapports du Jour'` ; `SECTION_ICONS['rapports'] = '<i class="fa-solid fa-file-lines"></i>'` ; `'rapports'` ajouté à `NAV_ORDER` avant `'parametres'` (après `'anniversaires'`).

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_rbac_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';

assert(function_exists('auth_can_report_any'), 'auth_can_report_any absente');
assert(function_exists('auth_can_report_for_centre'), 'auth_can_report_for_centre absente');
assert(isset(SECTION_LABELS['rapports'], SECTION_ICONS['rapports']), 'SECTION_LABELS/ICONS rapports manquant');
assert(in_array('rapports', NAV_ORDER, true), 'rapports absent de NAV_ORDER');

// Sans session : tout refusé
assert(auth_can_report_any() === false, 'sans session auth_can_report_any doit être false');
assert(auth_can_report_for_centre(1) === false, 'sans session auth_can_report_for_centre doit être false');

echo "OK m5 rbac\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_rbac_check.php`
Expected: FAIL — `AssertionError: auth_can_report_any absente`.

- [ ] **Step 3: Helpers RBAC**

`app/Auth/compat.php`, après `auth_can_edit_evenement()` :

```php
/** L'utilisateur peut-il produire au moins un Rapport du Jour ? (admin ou gère un bacenta) */
function auth_can_report_any(): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (($u['role'] ?? '') === 'admin') {
        return true;
    }
    $uid = (int) $u['id'];
    return (int) \App\Core\Query::value(
        "SELECT EXISTS(
            SELECT 1 FROM bacentas b
             WHERE b.id IN (SELECT target_id FROM responsibilities WHERE user_id = ? AND target_type = 'bacenta')
                OR b.id = (SELECT bacenta_id FROM users WHERE id = ?)
        )",
        [$uid, $uid]
    ) === 1;
}

/** Rapport du Jour pour CE centre : admin ou gère un bacenta rattaché à ce centre. */
function auth_can_report_for_centre(int $centreId): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (($u['role'] ?? '') === 'admin') {
        return true;
    }
    $uid = (int) $u['id'];
    return (int) \App\Core\Query::value(
        "SELECT EXISTS(
            SELECT 1 FROM bacentas b
             WHERE b.centre_id = ?
               AND (
                 b.id IN (SELECT target_id FROM responsibilities WHERE user_id = ? AND target_type = 'bacenta')
                 OR b.id = (SELECT bacenta_id FROM users WHERE id = ?)
               )
        )",
        [$centreId, $uid, $uid]
    ) === 1;
}
```

- [ ] **Step 4: Constantes de navigation**

`Config/constants.php` :
- `SECTION_LABELS` : `'rapports' => 'Rapports du Jour',` (après `'anniversaires'`).
- `SECTION_ICONS` : `'rapports' => '<i class="fa-solid fa-file-lines"></i>',`.
- `NAV_ORDER` : `'rapports',` juste après `'anniversaires'`, avant `'parametres'`.

- [ ] **Step 5: Lien de menu (motif hoist M4)**

`Views/layouts/layout.php` — juste après le bloc `if ($user && !$isAdmin && auth_can_manage_calendar()) { … }` ajouté par M4 :

```php
// Rapport du Jour : lien pour tout responsable de bacenta non-admin
// (l'admin l'a déjà via la boucle NAV_ORDER).
if ($user && !$isAdmin && auth_can_report_any()) {
    $navLis[] = '<li><a class="nav-item' . ($page === 'rapports' ? ' active' : '') . '" href="' . h(url('index.php', ['page' => 'rapports'])) . '"><span class="ico">' . SECTION_ICONS['rapports'] . '</span><span class="label">' . h(SECTION_LABELS['rapports']) . '</span></a></li>';
}
```

- [ ] **Step 6: GREEN + lint + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_rbac_check.php
php -l app/Auth/compat.php && php -l Config/constants.php && php -l Views/layouts/layout.php
git add app/Auth/compat.php Config/constants.php Views/layouts/layout.php
git commit -m "$(cat <<'EOF'
feat(rapports): helpers RBAC + entrée de navigation

auth_can_report_any / auth_can_report_for_centre (admin, ou gère un
bacenta — via responsibilities ou users.bacenta_id — rattaché au centre).
rapports dans SECTION_LABELS/ICONS/NAV_ORDER et dans le menu des
responsables non-admin (motif hoist M4).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 3: `RapportJourRepository`

**Files:**
- Create: `app/Repositories/RapportJourRepository.php`
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_repo_check.php`

**Interfaces:**
- Consumes de Task 1 : table `rapports_jour`.
- Produces : `App\Repositories\RapportJourRepository`
  - `find(int $id): ?array`
  - `findByCentreDate(int $centreId, string $date): ?array`
  - `upsert(array $data): int` — `$data` contient `centre_id, date_rapport, auteur_id, bacenta_id, resp_centre_nom, resp_bacenta_nom, assistants, nb_presents, nb_adultes, nb_enfants, nb_anciens, nb_nouveaux, nb_nes_de_nouveau, offrande, livre_enseigne, chapitre_enseigne`. Si une ligne existe pour `(centre_id, date_rapport)` → `UPDATE` (sans toucher `auteur_id` ni `created_at`) et renvoie son id ; sinon `INSERT` et renvoie le nouvel id. Écriture par colonnes explicites (pas de `SELECT *`-based).
  - `list(?int $centreId, ?string $monthKey): array` — jointures `c.nom AS centre_nom`, `au.prenom/nom` (auteur), `ba.nom AS bacenta_nom` ; filtre optionnel `centre_id = ?` ; filtre optionnel `DATE_FORMAT(date_rapport, '%Y-%m') = ?` ; tri `date_rapport DESC, id DESC`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_repo_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;
use App\Repositories\RapportJourRepository;

$centre = (int) Query::value('SELECT id FROM centres ORDER BY id LIMIT 1');
$auteur = (int) Query::value('SELECT id FROM users ORDER BY id LIMIT 1');
assert($centre > 0 && $auteur > 0, 'besoin d\'un centre et d\'un user');

$repo = new RapportJourRepository();
$base = [
    'centre_id' => $centre, 'date_rapport' => '2029-02-02', 'auteur_id' => $auteur, 'bacenta_id' => null,
    'resp_centre_nom' => 'Resp Centre', 'resp_bacenta_nom' => 'Resp Bacenta', 'assistants' => 'A, B',
    'nb_presents' => 40, 'nb_adultes' => 30, 'nb_enfants' => 10, 'nb_anciens' => 5, 'nb_nouveaux' => 2, 'nb_nes_de_nouveau' => 1,
    'offrande' => 12345.67, 'livre_enseigne' => 'Jean', 'chapitre_enseigne' => '3',
];
$id = $repo->upsert($base);
$row = $repo->find($id);
assert($row['nb_presents'] === 40 || (int) $row['nb_presents'] === 40, 'insert KO');
assert((float) $row['offrande'] === 12345.67, 'offrande KO');
assert($repo->findByCentreDate($centre, '2029-02-02')['id'] == $id, 'findByCentreDate KO');

// upsert même (centre, date) → UPDATE, même id, auteur inchangé
$base['nb_presents'] = 99;
$base['auteur_id'] = 999999; // doit être ignoré par l'UPDATE
$id2 = $repo->upsert($base);
assert($id2 === $id, "upsert doit réutiliser l'id, vu $id2 vs $id");
$row = $repo->find($id);
assert((int) $row['nb_presents'] === 99, 'update KO');
assert((int) $row['auteur_id'] === $auteur, 'auteur_id ne doit pas changer à l\'update');

$rows = $repo->list($centre, '2029-02');
assert(count(array_filter($rows, fn($r) => (int) $r['id'] === $id)) === 1, 'list(centre, mois) KO');
assert(array_key_exists('centre_nom', $rows[0]), 'list doit joindre centre_nom');

Query::run('DELETE FROM rapports_jour WHERE id = ?', [$id]);
echo "OK m5 repo\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_repo_check.php`
Expected: FAIL — `Error: Class "App\Repositories\RapportJourRepository" not found`.

- [ ] **Step 3: Créer `app/Repositories/RapportJourRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Rapports du Jour (un par centre et par date).
 */
class RapportJourRepository
{
    /** Colonnes métier écrites par upsert (dans l'ordre). */
    private const WRITABLE = [
        'centre_id', 'date_rapport', 'auteur_id', 'bacenta_id',
        'resp_centre_nom', 'resp_bacenta_nom', 'assistants',
        'nb_presents', 'nb_adultes', 'nb_enfants', 'nb_anciens', 'nb_nouveaux', 'nb_nes_de_nouveau',
        'offrande', 'livre_enseigne', 'chapitre_enseigne',
    ];

    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM rapports_jour WHERE id = ?', [$id]);
    }

    public function findByCentreDate(int $centreId, string $date): ?array
    {
        return Query::one('SELECT * FROM rapports_jour WHERE centre_id = ? AND date_rapport = ?', [$centreId, $date]);
    }

    /** INSERT si (centre_id, date_rapport) libre, sinon UPDATE (auteur_id/created_at préservés). */
    public function upsert(array $data): int
    {
        $existing = $this->findByCentreDate((int) $data['centre_id'], (string) $data['date_rapport']);

        if ($existing) {
            $cols = array_values(array_diff(self::WRITABLE, ['centre_id', 'date_rapport', 'auteur_id']));
            $set = implode(', ', array_map(static fn($c) => "$c = ?", $cols));
            $params = array_map(static fn($c) => $data[$c] ?? null, $cols);
            $params[] = (int) $existing['id'];
            Query::run("UPDATE rapports_jour SET $set WHERE id = ?", $params);
            return (int) $existing['id'];
        }

        $cols = self::WRITABLE;
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $params = array_map(static fn($c) => $data[$c] ?? null, $cols);
        return Query::run('INSERT INTO rapports_jour (' . implode(', ', $cols) . ") VALUES ($placeholders)", $params);
    }

    /** @return array<int,array<string,mixed>> */
    public function list(?int $centreId, ?string $monthKey): array
    {
        $sql = "SELECT r.*, c.nom AS centre_nom, ba.nom AS bacenta_nom,
                       au.prenom AS auteur_prenom, au.nom AS auteur_nom
                  FROM rapports_jour r
                  JOIN centres c   ON c.id = r.centre_id
                  LEFT JOIN bacentas ba ON ba.id = r.bacenta_id
                  LEFT JOIN users au    ON au.id = r.auteur_id
                 WHERE 1 = 1";
        $params = [];
        if ($centreId !== null) {
            $sql .= ' AND r.centre_id = ?';
            $params[] = $centreId;
        }
        if ($monthKey !== null && $monthKey !== '') {
            $sql .= " AND DATE_FORMAT(r.date_rapport, '%Y-%m') = ?";
            $params[] = $monthKey;
        }
        $sql .= ' ORDER BY r.date_rapport DESC, r.id DESC';
        return Query::all($sql, $params);
    }
}
```

- [ ] **Step 4: GREEN + lint + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_repo_check.php
php -l app/Repositories/RapportJourRepository.php
git add app/Repositories/RapportJourRepository.php
git commit -m "$(cat <<'EOF'
feat(rapports): RapportJourRepository (find, findByCentreDate, upsert, list)

upsert : INSERT si (centre_id, date_rapport) libre, sinon UPDATE en
préservant auteur_id et created_at. list : jointures centre/bacenta/auteur,
filtres centre + mois (DATE_FORMAT %Y-%m).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 4: `RapportJourService`

**Files:**
- Create: `app/Services/RapportJourService.php`
- Modify: `app/Compat/data.php` (accessor `rapport_jour_service()`, forme `_repo(...)`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_service_check.php`

**Interfaces:**
- Consumes de Task 3 : `RapportJourRepository`. De Task 1 : `RAPPORT_JOUR_FIELDS`.
- Produces : `App\Services\RapportJourService`
  - `__construct(?RapportJourRepository $repo = null, ?ResponsibilityRepository $resp = null)`
  - `report(int $id): ?array` / `reportForCentreDate(int $centreId, string $date): ?array`
  - `list(?int $centreId, ?string $monthKey): array`
  - `reportableBacentas(int $userId, int $centreId): array` — `[ ['id'=>int,'nom'=>string], … ]` : bacentas de ce centre que l'utilisateur gère (`responsibilities` `target_type='bacenta'` OU `users.bacenta_id`). Admin → tous les bacentas du centre.
  - `derivedNames(int $centreId, ?int $bacentaId, int $authorId): array` — `['resp_centre_nom' => string, 'resp_bacenta_nom' => string]`. Centre : 1ᵉʳ `ResponsibilityRepository::listForTarget('center', $centreId)` → `prenom nom`, sinon `''`. Bacenta : si `$bacentaId`, 1ᵉʳ `listForTarget('bacenta', $bacentaId)` → `prenom nom`, sinon nom complet de l'auteur (`SELECT prenom, nom FROM users WHERE id = ?`).
  - `save(array $in, int $userId, bool $isAdmin): array` — `['ok'=>bool, 'errors'=>array<string,string>, 'id'=>?int]`.
    - Valide : `centre_id` entier > 0 requis ; `date_rapport` `Y-m-d` valide requise ; `bacenta_id` (facultatif) doit appartenir à `reportableBacentas($userId, $centre_id)` sinon `errors['bacenta_id']` ; chaque champ `RAPPORT_JOUR_FIELDS` : `int` ≥ 0, `decimal` ≥ 0 (accepte virgule décimale), `text`/`textarea` trim + null si vide.
    - Édition : si un rapport existe déjà pour `(centre_id, date_rapport)` et que `!$isAdmin` et que `auteur_id !== $userId` → `['ok'=>false, 'errors'=>['_form'=>'Ce rapport a été créé par une autre personne ; seul son auteur ou un administrateur peut le modifier.'], 'id'=>null]`.
    - Construit `resp_*_nom` via `derivedNames()` (jamais depuis `$in`), fixe `auteur_id = $userId` **uniquement à la création** (l'`upsert` du repo préserve l'`auteur_id` existant), appelle `repo->upsert(...)`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_service_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;
use App\Services\RapportJourService;

$centre = (int) Query::value('SELECT id FROM centres ORDER BY id LIMIT 1');
$u1 = (int) Query::value('SELECT id FROM users ORDER BY id LIMIT 1');
$u2 = (int) Query::value('SELECT id FROM users ORDER BY id LIMIT 1 OFFSET 1');
$svc = new RapportJourService();

// validation : centre manquant + nombre négatif
$bad = $svc->save(['centre_id' => 0, 'date_rapport' => '', 'nb_presents' => '-3'], $u1, false);
assert($bad['ok'] === false && isset($bad['errors']['centre_id'], $bad['errors']['date_rapport'], $bad['errors']['nb_presents']), 'validation KO: ' . json_encode($bad['errors']));

// création OK (admin pour éviter le filtre bacenta)
$ok = $svc->save([
    'centre_id' => $centre, 'date_rapport' => '2029-03-03', 'bacenta_id' => '',
    'nb_presents' => '50', 'nb_adultes' => '35', 'nb_enfants' => '15', 'nb_anciens' => '4',
    'nb_nouveaux' => '3', 'nb_nes_de_nouveau' => '2', 'offrande' => '9 999,50',
    'assistants' => "  Jean, Marie ", 'livre_enseigne' => 'Actes', 'chapitre_enseigne' => '2',
], $u1, true);
assert($ok['ok'] === true && $ok['id'] > 0, 'création KO: ' . json_encode($ok['errors'] ?? []));
$r = $svc->report($ok['id']);
assert((int) $r['nb_presents'] === 50, 'nb_presents KO');
assert((float) $r['offrande'] === 9999.5, 'offrande virgule KO: ' . $r['offrande']);
assert($r['assistants'] === 'Jean, Marie', 'assistants trim KO');
assert((int) $r['auteur_id'] === $u1, 'auteur_id KO');
$respCentreSnapshot = $r['resp_centre_nom'];
assert($respCentreSnapshot !== null, 'resp_centre_nom doit être un instantané (chaîne, pas null)');

// un autre user non-admin ne peut pas modifier ce rapport
$deny = $svc->save(['centre_id' => $centre, 'date_rapport' => '2029-03-03', 'nb_presents' => '1'], $u2, false);
assert($deny['ok'] === false && isset($deny['errors']['_form']), 'un tiers non-admin doit être refusé à l\'édition');

// l'auteur peut modifier
$upd = $svc->save(['centre_id' => $centre, 'date_rapport' => '2029-03-03', 'nb_presents' => '77'], $u1, false);
assert($upd['ok'] === true && $upd['id'] === $ok['id'], 'édition par l\'auteur KO');
assert((int) $svc->report($ok['id'])['nb_presents'] === 77, 'update valeur KO');

Query::run('DELETE FROM rapports_jour WHERE id = ?', [$ok['id']]);
echo "OK m5 service\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_service_check.php`
Expected: FAIL — `Error: Class "App\Services\RapportJourService" not found`.

- [ ] **Step 3: Créer `app/Services/RapportJourService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Query;
use App\Repositories\RapportJourRepository;
use App\Repositories\ResponsibilityRepository;

/**
 * Rapport du Jour : validation, instantané des responsables, upsert.
 */
class RapportJourService
{
    private RapportJourRepository $repo;
    private ResponsibilityRepository $resp;

    public function __construct(?RapportJourRepository $repo = null, ?ResponsibilityRepository $resp = null)
    {
        $this->repo = $repo ?? new RapportJourRepository();
        $this->resp = $resp ?? new ResponsibilityRepository();
    }

    public function report(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function reportForCentreDate(int $centreId, string $date): ?array
    {
        return $this->repo->findByCentreDate($centreId, $date);
    }

    public function list(?int $centreId, ?string $monthKey): array
    {
        return $this->repo->list($centreId, $monthKey);
    }

    /** @return list<array{id:int,nom:string}> */
    public function reportableBacentas(int $userId, int $centreId, bool $isAdmin = false): array
    {
        if ($isAdmin) {
            return array_map(
                static fn($r) => ['id' => (int) $r['id'], 'nom' => (string) $r['nom']],
                Query::all('SELECT id, nom FROM bacentas WHERE centre_id = ? ORDER BY nom', [$centreId])
            );
        }
        return array_map(
            static fn($r) => ['id' => (int) $r['id'], 'nom' => (string) $r['nom']],
            Query::all(
                "SELECT id, nom FROM bacentas
                  WHERE centre_id = ?
                    AND (
                      id IN (SELECT target_id FROM responsibilities WHERE user_id = ? AND target_type = 'bacenta')
                      OR id = (SELECT bacenta_id FROM users WHERE id = ?)
                    )
                  ORDER BY nom",
                [$centreId, $userId, $userId]
            )
        );
    }

    /** @return array{resp_centre_nom:string,resp_bacenta_nom:string} */
    public function derivedNames(int $centreId, ?int $bacentaId, int $authorId): array
    {
        $centreResp = $this->resp->listForTarget('center', $centreId);
        $respCentreNom = $centreResp ? trim(($centreResp[0]['prenom'] ?? '') . ' ' . ($centreResp[0]['nom'] ?? '')) : '';

        $respBacentaNom = '';
        if ($bacentaId) {
            $bacResp = $this->resp->listForTarget('bacenta', $bacentaId);
            if ($bacResp) {
                $respBacentaNom = trim(($bacResp[0]['prenom'] ?? '') . ' ' . ($bacResp[0]['nom'] ?? ''));
            }
        }
        if ($respBacentaNom === '') {
            $a = Query::one('SELECT prenom, nom FROM users WHERE id = ?', [$authorId]);
            $respBacentaNom = $a ? trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')) : '';
        }

        return ['resp_centre_nom' => $respCentreNom, 'resp_bacenta_nom' => $respBacentaNom];
    }

    /**
     * @param array<string,mixed> $in
     * @return array{ok:bool,errors:array<string,string>,id:?int}
     */
    public function save(array $in, int $userId, bool $isAdmin): array
    {
        $errors = [];

        $centreId = (int) ($in['centre_id'] ?? 0);
        if ($centreId <= 0) {
            $errors['centre_id'] = 'Choisissez un centre.';
        }

        $date = trim((string) ($in['date_rapport'] ?? ''));
        $ts = $date !== '' ? strtotime($date) : false;
        if ($ts === false || date('Y-m-d', $ts) !== $date) {
            $errors['date_rapport'] = 'Date invalide (format attendu AAAA-MM-JJ).';
        }

        $bacentaIdRaw = (int) ($in['bacenta_id'] ?? 0) ?: null;
        if ($bacentaIdRaw !== null && $centreId > 0) {
            $allowed = array_column($this->reportableBacentas($userId, $centreId, $isAdmin), 'id');
            if (!in_array($bacentaIdRaw, $allowed, true)) {
                $errors['bacenta_id'] = 'Ce bacenta ne fait pas partie de ceux que vous pouvez rapporter pour ce centre.';
            }
        }

        $clean = [];
        foreach (RAPPORT_JOUR_FIELDS as $f) {
            $raw = $in[$f['key']] ?? null;
            switch ($f['type']) {
                case 'int':
                    $v = (int) $raw;
                    if ($v < 0) {
                        $errors[$f['key']] = 'Valeur négative interdite.';
                    }
                    $clean[$f['key']] = max(0, $v);
                    break;
                case 'decimal':
                    $v = (float) str_replace([' ', ','], ['', '.'], (string) $raw);
                    if ($v < 0) {
                        $errors[$f['key']] = 'Montant négatif interdit.';
                    }
                    $clean[$f['key']] = max(0, $v);
                    break;
                case 'text':
                    $s = trim((string) $raw);
                    $clean[$f['key']] = $s === '' ? null : mb_substr($s, 0, 150);
                    break;
                case 'textarea':
                default:
                    $s = trim((string) $raw);
                    $clean[$f['key']] = $s === '' ? null : $s;
                    break;
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        // Contrôle d'édition : un rapport existant appartient à son auteur (ou admin).
        $existing = $this->repo->findByCentreDate($centreId, $date);
        if ($existing && !$isAdmin && (int) $existing['auteur_id'] !== $userId) {
            return [
                'ok' => false,
                'errors' => ['_form' => "Ce rapport a été créé par une autre personne ; seul son auteur ou un administrateur peut le modifier."],
                'id' => null,
            ];
        }

        $names = $this->derivedNames($centreId, $bacentaIdRaw, $userId);

        $data = array_merge($clean, [
            'centre_id'        => $centreId,
            'date_rapport'     => $date,
            'auteur_id'        => $existing ? (int) $existing['auteur_id'] : $userId,
            'bacenta_id'       => $bacentaIdRaw,
            'resp_centre_nom'  => $names['resp_centre_nom'],
            'resp_bacenta_nom' => $names['resp_bacenta_nom'],
        ]);

        $id = $this->repo->upsert($data);
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }
}
```

- [ ] **Step 4: Accessor compat**

`app/Compat/data.php`, avec les autres accessors de services :

```php
function rapport_jour_service(): \App\Services\RapportJourService { return _repo(\App\Services\RapportJourService::class); }
```

- [ ] **Step 5: GREEN + lint + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_service_check.php
php -l app/Services/RapportJourService.php && php -l app/Compat/data.php
git add app/Services/RapportJourService.php app/Compat/data.php
git commit -m "$(cat <<'EOF'
feat(rapports): RapportJourService (validation, instantané responsables, upsert)

Valide centre/date/bacenta + les 10 champs RAPPORT_JOUR_FIELDS (int/decimal
>= 0, virgule décimale acceptée, texte tronqué). resp_centre_nom /
resp_bacenta_nom dérivés de responsibilities, jamais de la requête.
Un rapport existant n'est modifiable que par son auteur ou un admin.
auteur_id figé à la création.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 5: Contrôleur, routes et vues

**Files:**
- Create: `app/Controllers/RapportController.php`
- Modify: `Routes/web.php`
- Create: `Views/pages/rapports.php` (liste), `Views/pages/rapport_form.php` (sélecteur + formulaire)
- Create: `assets/css/rapports.css` + `@import` dans `assets/css/app.css`
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_view_check.php`

**Interfaces:**
- Consumes de Task 4 : `rapport_jour_service()`. De Task 2 : `auth_can_report_any()`, `auth_can_report_for_centre()`. De Task 1 : `RAPPORT_JOUR_FIELDS`.
- Produces :
  - `App\Controllers\RapportController` (`declare(strict_types=1)`, extends `Controller`) :
    - `index(): void` — GET `?page=rapports`. Guard : `current_user()` sinon `redirect(page=apropos)` ; `auth_can_report_any()` sinon `redirect(page=accueil)`. Filtres `?centre=` (int|null) et `?mois=` (`Y-m`|null). Centres proposés au filtre = admin → `get_centres()` ; sinon ceux où `auth_can_report_for_centre($c['id'])`. Passe à `view('pages/rapports', …)` : `rows` (`service->list($centre, $mois)`), `centres` (filtrés), `filterCentre`, `filterMois`, `isAdmin`, `currentUserId`.
    - `form(): void` — GET `?page=rapport` (`?id=` OU `?centre=&date=`). Guard : `current_user()`. Résout `$centreId`/`$date` : depuis `?id=` (charge le rapport → son `centre_id`/`date_rapport`) sinon depuis `?centre=`/`?date=` (défaut `date = today`). Si `$centreId` fixé : `auth_can_report_for_centre($centreId)` sinon `deny` (redirect `page=rapports`). Passe à `view('pages/rapport_form', …)` : `centres` (reportables), `centreId`, `date`, `report` (rapport existant | null), `bacentas` (`service->reportableBacentas(uid, centreId, isAdmin)` si `$centreId`), `fields` (`RAPPORT_JOUR_FIELDS`), `derived` (`service->derivedNames(centreId, report.bacenta_id ?? null, uid)` si `$centreId`), `canEdit` (pas de report, OU `report.auteur_id === uid`, OU admin), `errors` `[]`, `old` `[]`, `csrf`.
  - Routes `Router::get('rapports', RapportController::class, 'index')`, `Router::get('rapport', RapportController::class, 'form')` + `use App\Controllers\RapportController;`.
  - `assets/css/app.css` : `@import url('rapports.css');` après `@import url('calendrier.css');`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_view_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';

assert(is_file('app/Controllers/RapportController.php'), 'RapportController absent');
assert(is_file('Views/pages/rapports.php'), 'vue rapports absente');
assert(is_file('Views/pages/rapport_form.php'), 'vue rapport_form absente');
assert(is_file('assets/css/rapports.css'), 'rapports.css absent');

$routes = file_get_contents('Routes/web.php');
assert(str_contains($routes, "'rapports'") && str_contains($routes, "'rapport'"), 'routes M5 absentes');
assert(str_contains($routes, 'use App\Controllers\RapportController;'), 'use RapportController absent');
assert(str_contains(file_get_contents('assets/css/app.css'), "@import url('rapports.css')"), 'rapports.css non importé');

// smoke-render liste
$html = view('pages/rapports', [
    'rows' => [], 'centres' => [], 'filterCentre' => null, 'filterMois' => null, 'isAdmin' => true, 'currentUserId' => 1,
]);
assert(str_contains($html, 'Rapports du Jour') || str_contains($html, 'rapport'), 'la vue liste ne rend rien de reconnaissable');

// smoke-render formulaire (sans centre choisi)
$f = view('pages/rapport_form', [
    'centres' => [['id' => 1, 'nom' => 'Mingra']], 'centreId' => null, 'date' => date('Y-m-d'),
    'report' => null, 'bacentas' => [], 'fields' => RAPPORT_JOUR_FIELDS, 'derived' => null,
    'canEdit' => true, 'errors' => [], 'old' => [], 'csrf' => '',
]);
assert(str_contains($f, 'save_rapport_jour') || str_contains($f, 'name="centre"'), 'la vue formulaire ne rend pas le sélecteur/action');

echo "OK m5 views\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_view_check.php`
Expected: FAIL — `AssertionError: RapportController absent`.

- [ ] **Step 3: Créer `app/Controllers/RapportController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

/**
 * Rapport du Jour des responsables de bacenta : liste + formulaire.
 */
class RapportController extends Controller
{
    public function index(): void
    {
        $user = current_user();
        if (!$user) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        if (!auth_can_report_any()) {
            $this->redirect('index.php', ['page' => 'accueil']);
        }
        $isAdmin = ($user['role'] ?? '') === 'admin';

        $centres = array_values(array_filter(
            get_centres(),
            static fn($c) => $isAdmin || auth_can_report_for_centre((int) $c['id'])
        ));

        $filterCentre = (int) (Request::get('centre') ?? 0) ?: null;
        $filterMois = trim((string) (Request::get('mois') ?? '')) ?: null;

        render_page(SECTION_LABELS['rapports'], view('pages/rapports', [
            'rows'          => rapport_jour_service()->list($filterCentre, $filterMois),
            'centres'       => $centres,
            'filterCentre'  => $filterCentre,
            'filterMois'    => $filterMois,
            'isAdmin'       => $isAdmin,
            'currentUserId' => (int) $user['id'],
        ]));
    }

    public function form(): void
    {
        $user = current_user();
        if (!$user) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $uid = (int) $user['id'];
        $svc = rapport_jour_service();

        $id = (int) (Request::get('id') ?? 0);
        $report = $id ? $svc->report($id) : null;

        $centreId = $report ? (int) $report['centre_id'] : ((int) (Request::get('centre') ?? 0) ?: null);
        $date = $report
            ? (string) $report['date_rapport']
            : (trim((string) (Request::get('date') ?? '')) ?: date('Y-m-d'));

        if ($centreId !== null && !auth_can_report_for_centre($centreId)) {
            $this->redirect('index.php', ['page' => 'rapports']);
        }
        // Rapport existant non résolu par (?centre&?date) si l'id n'était pas fourni :
        if ($report === null && $centreId !== null) {
            $report = $svc->reportForCentreDate($centreId, $date);
        }

        $centres = array_values(array_filter(
            get_centres(),
            static fn($c) => $isAdmin || auth_can_report_for_centre((int) $c['id'])
        ));

        $canEdit = $report === null || $isAdmin || (int) $report['auteur_id'] === $uid;

        render_page(SECTION_LABELS['rapports'], view('pages/rapport_form', [
            'centres'   => $centres,
            'centreId'  => $centreId,
            'date'      => $date,
            'report'    => $report,
            'bacentas'  => $centreId !== null ? $svc->reportableBacentas($uid, $centreId, $isAdmin) : [],
            'fields'    => RAPPORT_JOUR_FIELDS,
            'derived'   => $centreId !== null
                ? $svc->derivedNames($centreId, $report['bacenta_id'] ?? null, $uid)
                : null,
            'canEdit'   => $canEdit,
            'errors'    => [],
            'old'       => [],
            'csrf'      => csrf_field(),
        ]));
    }
}
```

Vérifier : classe de base `Controller`, `redirect` protégé, helper `nav()`/`Request::get`, alignement sur `CalendrierController` (livré en M4).

- [ ] **Step 4: Routes**

`Routes/web.php` : `use App\Controllers\RapportController;` + près des autres pages GET :

```php
Router::get('rapports', RapportController::class, 'index');
Router::get('rapport', RapportController::class, 'form');
```

- [ ] **Step 5: Créer `Views/pages/rapports.php`**

```php
<?php /* Liste des Rapports du Jour.
   Variables : $rows, $centres, $filterCentre, $filterMois, $isAdmin, $currentUserId. */ ?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['rapports']) ?></h2><div class="sub">Remontées terrain par centre et par date</div></div>
  <a class="btn btn-primary" href="<?= h(url('index.php', ['page' => 'rapport'])) ?>"><i class="fa-solid fa-plus"></i> Nouveau rapport</a>
</div>

<form method="get" action="index.php" class="rapport-filters">
  <input type="hidden" name="page" value="rapports">
  <label>Centre
    <select name="centre" onchange="this.form.submit()">
      <option value="">Tous</option>
      <?php foreach ($centres as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) $filterCentre === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Mois
    <input type="month" name="mois" value="<?= h($filterMois ?? '') ?>" onchange="this.form.submit()">
  </label>
  <?php if ($filterCentre || $filterMois): ?><a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'rapports'])) ?>">Effacer</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Date</th><th>Centre</th><th>Bacenta</th><th>Présents</th><th>Offrande</th><th>Auteur</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7"><?= empty_state('fa-file-lines', 'Aucun rapport pour ces filtres.') ?></td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h(date('d/m/Y', strtotime((string) $r['date_rapport']))) ?></td>
            <td><?= h($r['centre_nom']) ?></td>
            <td><?= h($r['bacenta_nom'] ?? '—') ?></td>
            <td><?= (int) $r['nb_presents'] ?></td>
            <td><?= h(number_format((float) $r['offrande'], 0, ',', ' ')) ?></td>
            <td><?= h(trim(($r['auteur_prenom'] ?? '') . ' ' . ($r['auteur_nom'] ?? ''))) ?></td>
            <td class="row-actions">
              <a class="icon-btn" title="<?= ($isAdmin || (int) $r['auteur_id'] === (int) $currentUserId) ? 'Modifier' : 'Consulter' ?>" href="<?= h(url('index.php', ['page' => 'rapport', 'id' => $r['id']])) ?>"><i class="fa-solid fa-<?= ($isAdmin || (int) $r['auteur_id'] === (int) $currentUserId) ? 'pen' : 'eye' ?>"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
```

- [ ] **Step 6: Créer `Views/pages/rapport_form.php`**

```php
<?php /* Rapport du Jour : sélecteur (centre + date) puis formulaire.
   Variables : $centres, $centreId, $date, $report, $bacentas, $fields, $derived, $canEdit, $errors, $old, $csrf. */
$val = function (string $k, $default = '') use ($old, $report) {
    if (array_key_exists($k, $old)) {
        return $old[$k];
    }
    return $report[$k] ?? $default;
};
?>
<div class="section-toolbar">
  <div><h2><?= h(SECTION_LABELS['rapports']) ?></h2><div class="sub">Formulaire de saisie</div></div>
  <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'rapports'])) ?>"><i class="fa-solid fa-arrow-left"></i> Retour à la liste</a>
</div>

<form method="get" action="index.php" class="rapport-picker">
  <input type="hidden" name="page" value="rapport">
  <label>Centre
    <select name="centre" onchange="this.form.submit()" <?= $report ? 'disabled' : '' ?>>
      <option value="">— Choisir —</option>
      <?php foreach ($centres as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) $centreId === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Date
    <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()" <?= $report ? 'disabled' : '' ?>>
  </label>
</form>

<?php if ($centreId === null): ?>
  <?= empty_state('fa-hand-pointer', 'Choisissez un centre et une date pour commencer.') ?>
<?php else: ?>

  <?php if (!empty($errors['_form'])): ?><div class="alert alert-danger"><?= h($errors['_form']) ?></div><?php endif; ?>
  <?php if ($report && !$canEdit): ?><div class="alert alert-info">Rapport créé par une autre personne — consultation seule.</div><?php endif; ?>

  <form method="post" action="index.php" class="form-card rapport-form">
    <input type="hidden" name="action" value="save_rapport_jour">
    <?= $csrf ?>
    <input type="hidden" name="centre_id" value="<?= (int) $centreId ?>">
    <input type="hidden" name="date_rapport" value="<?= h($date) ?>">

    <div class="form-grid">
      <div class="form-group"><label>Centre</label><input type="text" value="<?= h($centres[array_search($centreId, array_column($centres, 'id'), true)]['nom'] ?? '') ?>" disabled></div>
      <div class="form-group"><label>Date</label><input type="text" value="<?= h(date('d/m/Y', strtotime($date))) ?>" disabled></div>
    </div>

    <div class="form-grid">
      <div class="form-group"><label>Responsable du centre</label><input type="text" value="<?= h($derived['resp_centre_nom'] ?? '') ?>" disabled></div>
      <div class="form-group">
        <label>Bacenta (facultatif)</label>
        <select name="bacenta_id" <?= $canEdit ? '' : 'disabled' ?>>
          <option value="">—</option>
          <?php foreach ($bacentas as $b): ?>
            <option value="<?= (int) $b['id'] ?>" <?= (int) $val('bacenta_id') === (int) $b['id'] ? 'selected' : '' ?>><?= h($b['nom']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['bacenta_id'])): ?><span class="form-error"><?= h($errors['bacenta_id']) ?></span><?php endif; ?>
      </div>
      <div class="form-group"><label>Responsable du bacenta</label><input type="text" value="<?= h($derived['resp_bacenta_nom'] ?? '') ?>" disabled></div>
    </div>

    <?php
    $groups = [];
    foreach ($fields as $f) { $groups[$f['group']][] = $f; }
    foreach ($groups as $groupName => $groupFields): ?>
      <h3 class="form-section-title"><?= h($groupName) ?></h3>
      <div class="form-grid">
        <?php foreach ($groupFields as $f): ?>
          <div class="form-group">
            <label><?= h($f['label']) ?></label>
            <?php if ($f['type'] === 'textarea'): ?>
              <textarea name="<?= h($f['key']) ?>" <?= $canEdit ? '' : 'disabled' ?>><?= h((string) $val($f['key'])) ?></textarea>
            <?php elseif ($f['type'] === 'int'): ?>
              <input type="number" min="0" step="1" name="<?= h($f['key']) ?>" value="<?= h((string) $val($f['key'], '0')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
            <?php elseif ($f['type'] === 'decimal'): ?>
              <input type="text" inputmode="decimal" name="<?= h($f['key']) ?>" value="<?= h((string) $val($f['key'], '0')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
            <?php else: ?>
              <input type="text" name="<?= h($f['key']) ?>" value="<?= h((string) $val($f['key'])) ?>" <?= $canEdit ? '' : 'disabled' ?>>
            <?php endif; ?>
            <?php if (!empty($errors[$f['key']])): ?><span class="form-error"><?= h($errors[$f['key']]) ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($canEdit): ?>
      <div class="modal-actions"><button type="submit" class="btn btn-primary"><?= $report ? 'Enregistrer les modifications' : 'Enregistrer le rapport' ?></button></div>
    <?php endif; ?>
  </form>
<?php endif; ?>
```

- [ ] **Step 7: Créer `assets/css/rapports.css` + import**

```css
/* M5 — Rapport du Jour */

.rapport-filters,
.rapport-picker {
  display: flex;
  gap: var(--space-4);
  flex-wrap: wrap;
  align-items: flex-end;
  margin-bottom: var(--space-5);
}

.rapport-filters label,
.rapport-picker label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-soft);
}

.rapport-form input:disabled,
.rapport-form select:disabled,
.rapport-form textarea:disabled {
  background: var(--primary-soft);
  color: var(--text);
  opacity: 1;
}
```

Puis `assets/css/app.css` : `@import url('rapports.css');` juste après `@import url('calendrier.css');`.

- [ ] **Step 8: GREEN + lint + smoke-render + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_view_check.php
php -l app/Controllers/RapportController.php && php -l Routes/web.php && php -l Views/pages/rapports.php && php -l Views/pages/rapport_form.php
git add app/Controllers/RapportController.php Routes/web.php Views/pages/rapports.php Views/pages/rapport_form.php assets/css/rapports.css assets/css/app.css
git commit -m "$(cat <<'EOF'
feat(rapports): page liste + formulaire Rapport du Jour

RapportController (liste filtrable centre/mois ; formulaire sélecteur
centre+date puis saisie). Responsables du centre/bacenta affichés en
lecture seule (dérivés). Consultation seule si le rapport a un autre
auteur. Routes rapports / rapport, CSS assets/css/rapports.css.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 6: Action `save_rapport_jour`

**Files:**
- Modify: `app/Controllers/ActionsController.php` (cas `save_rapport_jour` dans `postAction()`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_action_check.php`

**Interfaces:**
- Consumes de Task 4 : `rapport_jour_service()`. De Task 2 : `auth_can_report_for_centre()`.
- Produces : cas POST `save_rapport_jour` :
  - `$user = $this->requireUser();`
  - `$centreId = (int) ($_POST['centre_id'] ?? 0);`
  - `if (!$centreId || !auth_can_report_for_centre($centreId)) { $this->deny(); }`
  - `$isAdmin = ($user['role'] ?? '') === 'admin';`
  - `$res = rapport_jour_service()->save($_POST, (int) $user['id'], $isAdmin);`
  - Sur `!$res['ok']` : re-render `view('pages/rapport_form', [...])` via `render_page(SECTION_LABELS['rapports'], ...)` avec `errors => $res['errors']`, `old => $_POST`, et les **mêmes clés** que `RapportController::form()` calcule (`centres`, `centreId`, `date`, `report` = `reportForCentreDate($centreId, $date)`, `bacentas` = `reportableBacentas`, `fields`, `derived`, `canEdit` = true, `csrf`), puis `return;`.
  - Sur succès : `$this->redirect('index.php', ['page' => 'rapport', 'id' => $res['id']]);`

- [ ] **Step 1: Écrire l'assertion qui échoue**

`/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_action_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');

$src = file_get_contents('app/Controllers/ActionsController.php');
assert(str_contains($src, "case 'save_rapport_jour'"), 'cas save_rapport_jour absent');
assert(str_contains($src, 'auth_can_report_for_centre('), 'garde RBAC centre absente');
assert(str_contains($src, 'rapport_jour_service()->save('), 'appel service absent');
assert(str_contains($src, "'page' => 'rapport'"), 'redirection vers la fiche absente');

// e2e via le service (l'action n'est qu'une fine couche HTTP au-dessus)
require 'Bootstrap/init.php';
use App\Core\Query;
$centre = (int) Query::value('SELECT id FROM centres ORDER BY id LIMIT 1');
$u = (int) Query::value('SELECT id FROM users ORDER BY id LIMIT 1');
$r = rapport_jour_service()->save([
    'centre_id' => $centre, 'date_rapport' => '2029-04-04', 'bacenta_id' => '',
    'nb_presents' => '12', 'offrande' => '100',
], $u, true);
assert($r['ok'] === true, 'save e2e KO: ' . json_encode($r['errors'] ?? []));
assert((int) rapport_jour_service()->report($r['id'])['nb_presents'] === 12, 'valeur KO');
Query::run('DELETE FROM rapports_jour WHERE id = ?', [$r['id']]);
echo "OK m5 action\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_action_check.php`
Expected: FAIL — `AssertionError: cas save_rapport_jour absent`.

- [ ] **Step 3: Ajouter le cas**

`app/Controllers/ActionsController.php`, dans `postAction()`, à côté des cas M4 (`save_evenement`/`save_anniversaire`) :

```php
            /* ---------- Rapport du Jour (M5) ---------- */

            case 'save_rapport_jour': {
                $user = $this->requireUser();
                $centreId = (int) ($_POST['centre_id'] ?? 0);
                if (!$centreId || !auth_can_report_for_centre($centreId)) {
                    $this->deny();
                }
                $isAdmin = ($user['role'] ?? '') === 'admin';
                $uid = (int) $user['id'];
                $res = rapport_jour_service()->save($_POST, $uid, $isAdmin);
                if (!$res['ok']) {
                    $date = trim((string) ($_POST['date_rapport'] ?? '')) ?: date('Y-m-d');
                    $svc = rapport_jour_service();
                    $centres = array_values(array_filter(
                        get_centres(),
                        static fn($c) => $isAdmin || auth_can_report_for_centre((int) $c['id'])
                    ));
                    $existing = $svc->reportForCentreDate($centreId, $date);
                    render_page(SECTION_LABELS['rapports'], view('pages/rapport_form', [
                        'centres'  => $centres,
                        'centreId' => $centreId,
                        'date'     => $date,
                        'report'   => $existing,
                        'bacentas' => $svc->reportableBacentas($uid, $centreId, $isAdmin),
                        'fields'   => RAPPORT_JOUR_FIELDS,
                        'derived'  => $svc->derivedNames($centreId, (int) ($_POST['bacenta_id'] ?? 0) ?: null, $uid),
                        'canEdit'  => true,
                        'errors'   => $res['errors'],
                        'old'      => $_POST,
                        'csrf'     => csrf_field(),
                    ]));
                    return;
                }
                $this->redirect('index.php', ['page' => 'rapport', 'id' => $res['id']]);
                break;
            }
```

- [ ] **Step 4: GREEN + lint + commit**

```bash
php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m5_action_check.php
php -l app/Controllers/ActionsController.php
git add app/Controllers/ActionsController.php
git commit -m "$(cat <<'EOF'
feat(rapports): action save_rapport_jour (upsert par centre + date)

requireUser + auth_can_report_for_centre ; délègue la validation et
l'instantané des responsables au service ; re-render avec erreurs +
saisie sur échec ; redirige vers la fiche du rapport en cas de succès.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

## Self-Review

**1. Spec coverage (§4 « M5 ») :**

| Exigence spec | Tâche |
|---|---|
| Nouvel onglet/fenêtre « Rapport du Jour » | Task 5 (`rapports` liste + `rapport` formulaire, entrée de menu Task 2) |
| Menu déroulant centre = table `centres` (`centre_id` FK) | Task 1 (`fk_rap_centre`), Task 5 (`<select name="centre">` alimenté par `get_centres()` filtrés) |
| 1 rapport = (centre + date), `UNIQUE` | Task 1 (`uniq_rapport`), Task 3 (`upsert` par `findByCentreDate`) |
| Modifiable après enregistrement par l'auteur / l'admin | Task 4 (`save()` refuse un tiers non-admin), Task 5 (`canEdit`), Task 6 |
| Responsable du centre / du bacenta : pré-remplis non modifiables, dérivés | Task 4 (`derivedNames` depuis `responsibilities`, jamais depuis `$in`), Task 5 (`<input disabled>`) |
| Champs : centre, date | Task 5 (hidden `centre_id`/`date_rapport` + affichage lecture seule) |
| Noms des assistants (zone de texte) | `RAPPORT_JOUR_FIELDS` `assistants` type `textarea` (Task 1) |
| nb présents / adultes / enfants / anciens / nouveaux (1re visite) / nés de nouveau | `RAPPORT_JOUR_FIELDS` 6 entrées `int`, colonnes `NOT NULL DEFAULT 0` (Task 1) |
| Offrande (montant total) | `RAPPORT_JOUR_FIELDS` `offrande` type `decimal`, colonne `DECIMAL(12,2)` |
| Nom du livre enseigné / chapitre enseigné | `RAPPORT_JOUR_FIELDS` `livre_enseigne` / `chapitre_enseigne` type `text` |
| Créateur autorisé = admin OU manager d'un bacenta rattaché au centre | Task 2 (`auth_can_report_for_centre`), Task 5/6 (garde) |
| Enregistrer chaque rapport en base pour stats ultérieures | Task 1 (colonnes typées) + Décision #7 (stats hors v1) |
| Nom du responsable du bacenta = l'auteur / son bacenta (Q/R 4b interprétation) | Task 4 `derivedNames` : responsable du `bacenta_id`, sinon nom de l'auteur |

**2. Placeholder scan :** chaque step fournit le code exact et la commande avec sa sortie attendue. Les « vérifier l'alignement sur `CalendrierController` » (Task 5 Step 3) et « accessor forme `_repo(...)` » (Task 4 Step 4) sont des ancrages sur des conventions du dépôt déjà établies par M4, pas des TODO.

**3. Type consistency :**
- `save()` renvoie `['ok'=>bool,'errors'=>array<string,string>,'id'=>?int]` — consommé identiquement par Task 6 et `m5_service_check.php`.
- `derivedNames()` renvoie `['resp_centre_nom'=>string,'resp_bacenta_nom'=>string]` — utilisé par `RapportController::form()`, l'action, et stocké tel quel par `upsert()`.
- `reportableBacentas()` renvoie `list<array{id:int,nom:string}>` — itéré dans `rapport_form.php`, et `array_column(..., 'id')` dans `save()`.
- `RapportJourRepository::upsert(array $data)` attend exactement les 16 clés de `self::WRITABLE` ; `save()` construit ce tableau via `array_merge($clean, [...])` où `$clean` a les 10 clés `RAPPORT_JOUR_FIELDS` et le merge ajoute les 6 clés d'identité/responsables.
- `list()` renvoie des lignes avec `centre_nom`, `bacenta_nom`, `auteur_prenom`, `auteur_nom` — colonnes lues par `rapports.php`.
- `RAPPORT_JOUR_FIELDS` : `type ∈ {int, decimal, text, textarea}` — le `switch` de `save()` et le `if/elseif` de `rapport_form.php` couvrent ces 4 valeurs.

**4. Ordre des tâches :** 1 (schéma+constante) → 2 (RBAC/nav) → 3 (repo, dépend de 1) → 4 (service, dépend de 3+1) → 5 (contrôleur/vues, dépend de 4+2) → 6 (action, dépend de 4+5). Séquentiel strict.

---

## Execution Handoff

Six tâches séquentielles. Chacune se termine par un livrable testable (script d'assertion contre la base de dev réelle + `php -l` + smoke-render des vues) et un commit.
