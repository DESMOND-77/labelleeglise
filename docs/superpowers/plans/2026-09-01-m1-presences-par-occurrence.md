# M1 — Présences par occurrence (Bacentas / Basontas / Cultes) + matrice annuelle — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pouvoir définir un ou plusieurs jour(s) de récurrence sur un culte / bacenta / basonta, puis pointer pour une date donnée la présence de chaque membre (Présent / Absent / Excusé), et consulter/imprimer une matrice annuelle des présences par unité.

**Architecture:** La table `presences` existe déjà et porte `user_id, date_presence, culte_id, bacenta_id, basonta_id, centre_id`. On lui ajoute une colonne `statut` et un index d'unicité. Le pointage passe par un upsert transactionnel (delete des lignes de l'(unité, date) puis insert des nouveaux statuts) ajouté à `AttendanceRepository` / `AttendanceService` (pas de nouveau service parallèle). La config de récurrence (`jours_semaine`, `heure_debut`, `heure_fin`) est ajoutée aux repositories `Bacenta`/`Culte`/`Basonta` et à leurs formulaires + actions existants. L'UI de pointage et la matrice sont des **onglets** rendus dans les pages détail existantes (`render_bacenta_detail` / `render_culte_detail` / `render_basonta_detail` du compat layer) — aucune nouvelle entrée de menu. Seule l'impression de la matrice est une route autonome (`presencePrint`).

**Tech Stack:** PHP 8 SSR, micro-framework maison, zéro dépendance. MySQL/MariaDB via `App\Core\Query` (`all/one/value/run/transaction`). Pas de PHPUnit — vérification = `php -l` + scripts d'assertion `php` exécutés contre la base de dev + parcours manuel substitué par lint/grep là où un navigateur est requis.

**Spec:** `docs/superpowers/specs/2026-09-01-integration-modules-eglise-design.md` (§4 « M1 »)

## Global Constraints

- Zéro dépendance externe : pas de Composer, npm, build, Docker.
- PSR-12, `declare(strict_types=1)`, types sur paramètres et retours.
- Couches strictes : SQL uniquement dans un Repository ; HTML uniquement dans une View ; accès `$_POST`/`$_GET` uniquement dans un Controller (ou le dispatch `ActionsController`).
- Schéma : uniquement des instructions idempotentes dans le fichier de migration unique `Database/Migrations/2024_01_01_000000_create_schema.php`, gardées par `column_exists()` / `index_exists()` (déjà fournis). Étendre `down()` si une table est ajoutée (aucune ici).
- CSS modulaire sous `assets/css/`, variables de `assets/css/variables.css`, **aucun** style/script inline dans une vue. Le JavaScript éventuel va dans `assets/js/`.
- Ne jamais casser : URLs existantes, auth, formulaire « ajouter un membre » de la page bacenta, **pointage culte actuel** (`point_culte` + `Views/pages/culte_detail.php`). On ajoute des onglets À CÔTÉ.
- RBAC : le filtrage porte sur les données, pas seulement l'affichage. Ne jamais faire confiance à un `unit_id` / `user_id` reçu : re-vérifier via `can_manage_entity()` et revalider chaque membre côté service.
- `install.php` reste supprimable.
- Comptes de démo : `admin@labelleeglise.ga` / `LBEGF` (admin) ; `berger.eric.bongo@labelleeglise.ga` / `BergerEB1` (berger) ; `resp.bacenta.sion@labelleeglise.ga` / `ESKLna` (responsable).
- Base de dev joignable : MySQL `127.0.0.1:3306`, user `root`, db `la_belle_eglise_db` (via `.env` déjà configuré).

## Décisions de cadrage (spec §7 « points ouverts » — tranchées ici, spec = autorité)

1. **Extension de `AttendanceRepository` / `AttendanceService`**, pas de nouveau `PresenceService`. La classe est déjà « Présences (culte, bacenta, basonta, centre) et pointage » et porte `historyForUser`, `hasPresence`, `insert`, `deleteByColumn` — un service parallèle dupliquerait cette logique.
2. **Index d'unicité incluant `centre_id`** : `(user_id, date_presence, culte_id, bacenta_id, basonta_id, centre_id)`. La spec §4 l'écrit sans `centre_id`, mais des lignes de présence « centre » existent dans la même table (`MemberService::presenceStatus` cas `presenceCentre`) et provoqueraient une fausse collision `(uid, date, NULL, NULL, NULL)`. Un bloc de déduplication précède la création de l'index.
3. **Pointage culte hérité laissé intact** : `point_culte` + `Views/pages/culte_detail.php` ne sont pas touchés. Le nouvel onglet `tab=presences` est ajouté à côté (norme projet « étendre sans toucher à l'UI qui marche »). Les deux écrivent des lignes `presences` sur `(culte_id, date)` ; le nouvel écrit `statut`, l'ancien laisse le défaut `'present'`.
4. **Aucune entrée de navigation ajoutée** : toute l'UI M1 est en onglets dans les pages détail d'unité existantes. Seul `presencePrint` est une route neuve (fenêtre d'impression, pas une destination de menu). C'est une déviation assumée du §D de la spec (« chaque page → entrée de menu ») — M1 n'ajoute pas de page de niveau menu.
5. **« Nom de la personne visitée » (Bacentas) : hors périmètre de ce plan** — spec §4 M1 la laisse explicitement à confirmer avec l'utilisateur ; la table `visites` la couvre déjà.
6. **Restriction du sélecteur de date aux jours configurés : non implémentée en JS.** Le champ `<input type="date">` reste libre ; un texte d'aide rappelle les jours de récurrence. Pas de build JS pour si peu.

## Statuts

Nouvelle constante `PRESENCE_STATUTS` dans `Config/constants.php` :

```php
define('PRESENCE_STATUTS', ['present' => 'Présent', 'absent' => 'Absent', 'excuse' => 'Excusé']);
```

Valeur par défaut = `present`. Toute valeur reçue hors de ces clés est rejetée côté service (ligne ignorée).

## File Structure

| Fichier | Rôle | Action |
|---|---|---|
| `Database/Migrations/2024_01_01_000000_create_schema.php` | Migration idempotente unique | Modifier : bloc « 10 » — colonnes `jours_semaine`/`heure_debut`/`heure_fin`, colonne `presences.statut`, dédup + `CREATE UNIQUE INDEX uniq_presence` |
| `Config/constants.php` | Constantes métier | Modifier : `PRESENCE_STATUTS` |
| `app/Repositories/CulteRepository.php` | SQL cultes | Modifier : `create`/`update` acceptent `?string $jours` |
| `app/Repositories/BacentaRepository.php` | SQL bacentas | Modifier : `create`/`update` acceptent `?string $jours, ?string $debut, ?string $fin` |
| `app/Repositories/BasontaRepository.php` | SQL basontas | Modifier : `create`/`update` acceptent `?string $jours, ?string $debut, ?string $fin` |
| `app/Compat/structure.php` | Wrappers globaux `save_*` | Modifier : `save_bacenta`/`save_culte`/`save_basonta` propagent les nouveaux paramètres |
| `app/Controllers/ActionsController.php` | Dispatch POST | Modifier : cas `save_bacenta`/`save_culte`/`save_basonta` lisent les champs récurrence ; nouveau cas `save_presence_occurrence` |
| `Views/pages/forms/bacenta.php` | Formulaire bacenta | Modifier : champs jours + heures |
| `Views/pages/forms/culte.php` | Formulaire culte | Modifier : champs jours |
| `Views/pages/forms/basonta.php` | **Nouveau** formulaire basonta dédié (remplace `forms/name` pour basonta) | Créer |
| `app/Compat/sections.php` | Renderers détail d'unité | Modifier : `render_basonta_form` pointe vers `forms/basonta` ; dispatch `tab=presences` / `tab=presences_annuel` dans les 3 `render_*_detail` ; colonne « Prénom » dans `render_basonta_detail` |
| `app/Repositories/AttendanceRepository.php` | SQL présences | Modifier : `pointOccurrence`, `occurrenceStatuts`, `annualMatrix`, `distinctDatesForUnit` |
| `app/Services/AttendanceService.php` | Logique pointage | Modifier : `pointOccurrence`, `occurrenceGrid`, `annualMatrix` |
| `app/Compat/data.php` | Wrappers globaux données | Modifier : `unit_presence_grid`, `unit_annual_matrix`, `save_unit_presence` |
| `app/Controllers/PresenceController.php` | **Nouveau** — impression matrice | Créer : `matrixPrint()` |
| `Routes/web.php` | Routes | Modifier : `Router::get('presencePrint', PresenceController::class, 'matrixPrint')` |
| `Views/pages/presence_occurrence.php` | **Nouveau** — pointage d'une date | Créer |
| `Views/pages/presence_matrix.php` | **Nouveau** — matrice annuelle in-app | Créer |
| `Views/pages/presence_matrix_print.php` | **Nouveau** — matrice imprimable autonome | Créer |
| `assets/css/presences.css` | **Nouveau** — styles M1 | Créer + inclure comme les autres CSS |

---

### Task 1: Schéma — récurrence, colonne `statut`, index d'unicité + constante

**Files:**
- Modify: `Database/Migrations/2024_01_01_000000_create_schema.php` (fin de `up()`, après le bloc « 9 » de M3, avant l'accolade fermante)
- Modify: `Config/constants.php` (après `define('PRESENCE_FIELDS', ...)`, vers la ligne 143)
- Test: `php -r` bootstrap + requêtes `information_schema`

**Interfaces:**
- Consumes: rien.
- Produces :
  - `cultes.jours_semaine VARCHAR(60) NULL`
  - `bacentas.jours_semaine VARCHAR(60) NULL`, `bacentas.heure_debut TIME NULL`, `bacentas.heure_fin TIME NULL`
  - `basontas.jours_semaine VARCHAR(60) NULL`, `basontas.heure_debut TIME NULL`, `basontas.heure_fin TIME NULL`
  - `presences.statut ENUM('present','absent','excuse') NOT NULL DEFAULT 'present'`
  - Index unique `uniq_presence` sur `presences (user_id, date_presence, culte_id, bacenta_id, basonta_id, centre_id)`
  - Constante `PRESENCE_STATUTS` (array `clé => label`).

- [ ] **Step 1: Écrire l'assertion qui échoue**

Créer `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_schema_check.php` :

```php
<?php
declare(strict_types=1);
const ROOT = '/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise';
require ROOT . '/Bootstrap/init.php';
use App\Core\Query;

function col(string $t, string $c): ?array {
    return Query::one(
        'SELECT DATA_TYPE, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE
           FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        [$t, $c]
    );
}
function idx(string $t, string $i): int {
    return (int) Query::value(
        'SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
        [$t, $i]
    );
}

assert(col('cultes', 'jours_semaine') !== null, 'cultes.jours_semaine manquante');
assert(col('bacentas', 'jours_semaine') !== null, 'bacentas.jours_semaine manquante');
assert(col('bacentas', 'heure_debut') !== null, 'bacentas.heure_debut manquante');
assert(col('bacentas', 'heure_fin') !== null, 'bacentas.heure_fin manquante');
assert(col('basontas', 'jours_semaine') !== null, 'basontas.jours_semaine manquante');
assert(col('basontas', 'heure_debut') !== null, 'basontas.heure_debut manquante');
assert(col('basontas', 'heure_fin') !== null, 'basontas.heure_fin manquante');

$s = col('presences', 'statut');
assert($s !== null, 'presences.statut manquante');
assert(stripos($s['COLUMN_TYPE'], "enum('present','absent','excuse')") === 0, 'presences.statut ENUM incorrect: ' . $s['COLUMN_TYPE']);
assert($s['IS_NULLABLE'] === 'NO', 'presences.statut doit être NOT NULL');
assert($s['COLUMN_DEFAULT'] === 'present', "presences.statut défaut doit être 'present', vu: " . var_export($s['COLUMN_DEFAULT'], true));

assert(idx('presences', 'uniq_presence') > 0, 'index uniq_presence manquant');

assert(defined('PRESENCE_STATUTS'), 'PRESENCE_STATUTS non définie');
assert(array_keys(PRESENCE_STATUTS) === ['present', 'absent', 'excuse'], 'clés PRESENCE_STATUTS incorrectes');

echo "OK schema\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_schema_check.php`
Expected: FAIL — `AssertionError: cultes.jours_semaine manquante` (ou `PRESENCE_STATUTS non définie` selon l'ordre de chargement).

- [ ] **Step 3: Ajouter la constante**

Dans `Config/constants.php`, juste après la ligne `define('PRESENCE_FIELDS', ['presenceCulte', 'presenceBasonta', 'presenceCentre', 'presenceBacenta']);` :

```php

define('PRESENCE_STATUTS', ['present' => 'Présent', 'absent' => 'Absent', 'excuse' => 'Excusé']);
```

- [ ] **Step 4: Ajouter le bloc de migration**

Dans `Database/Migrations/2024_01_01_000000_create_schema.php`, fonction `up()`, tout à la fin (après le bloc « 9. Correction orthographe basonta » de M3, avant l'accolade fermante de `up()`), insérer :

```php

    /* ---- 10. M1 — Présences par occurrence -------------------------------
     * a) Récurrence hebdomadaire des unités : jour(s) de la semaine (CSV de
     *    libellés WEEK_DAYS, ex. "Vendredi" ou "Lundi,Mercredi") + plage
     *    horaire facultative. `cultes` a déjà heure_debut/heure_fin.
     * b) `presences.statut` : Présent / Absent / Excusé. Défaut 'present' —
     *    une ligne de présence existante signifiait déjà "présent".
     * c) Index d'unicité : une ligne de présence par (personne, date, unité).
     *    centre_id est inclus (des lignes "centre" existent dans la table).
     *    Déduplication préalable (idempotente) pour que la création de
     *    l'index unique ne bute pas sur d'anciens doublons.
     */
    $m1Columns = [
        ['cultes',   'jours_semaine', "ALTER TABLE cultes ADD COLUMN jours_semaine VARCHAR(60) NULL AFTER date_culte"],
        ['bacentas', 'jours_semaine', "ALTER TABLE bacentas ADD COLUMN jours_semaine VARCHAR(60) NULL"],
        ['bacentas', 'heure_debut',   "ALTER TABLE bacentas ADD COLUMN heure_debut TIME NULL"],
        ['bacentas', 'heure_fin',     "ALTER TABLE bacentas ADD COLUMN heure_fin TIME NULL"],
        ['basontas', 'jours_semaine', "ALTER TABLE basontas ADD COLUMN jours_semaine VARCHAR(60) NULL"],
        ['basontas', 'heure_debut',   "ALTER TABLE basontas ADD COLUMN heure_debut TIME NULL"],
        ['basontas', 'heure_fin',     "ALTER TABLE basontas ADD COLUMN heure_fin TIME NULL"],
    ];
    foreach ($m1Columns as [$table, $column, $alterSql]) {
        if (!column_exists($pdo, $table, $column)) {
            $pdo->exec($alterSql);
        }
    }

    if (!column_exists($pdo, 'presences', 'statut')) {
        $pdo->exec(
            "ALTER TABLE presences
                ADD COLUMN statut ENUM('present','absent','excuse') NOT NULL DEFAULT 'present' AFTER date_presence"
        );
    }

    if (!index_exists($pdo, 'presences', 'uniq_presence')) {
        // Déduplication : garder la ligne d'id minimal par clé logique.
        $pdo->exec(
            "DELETE p FROM presences p
               JOIN (
                    SELECT MIN(id) AS keep_id,
                           user_id, date_presence,
                           COALESCE(culte_id,0)   AS c,
                           COALESCE(bacenta_id,0) AS b,
                           COALESCE(basonta_id,0) AS s,
                           COALESCE(centre_id,0)  AS ce
                      FROM presences
                     GROUP BY user_id, date_presence, c, b, s, ce
                    HAVING COUNT(*) > 1
               ) d
                 ON p.user_id = d.user_id
                AND p.date_presence = d.date_presence
                AND COALESCE(p.culte_id,0)   = d.c
                AND COALESCE(p.bacenta_id,0) = d.b
                AND COALESCE(p.basonta_id,0) = d.s
                AND COALESCE(p.centre_id,0)  = d.ce
                AND p.id <> d.keep_id"
        );
        $pdo->exec(
            "CREATE UNIQUE INDEX uniq_presence
                ON presences (user_id, date_presence, culte_id, bacenta_id, basonta_id, centre_id)"
        );
    }
```

- [ ] **Step 5: Appliquer la migration**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -r 'require "Bootstrap/init.php"; require "Database/Migrations/2024_01_01_000000_create_schema.php"; \Database\Migrations\up(); echo "up() OK\n";'`
Expected: `up() OK`, aucune exception.

- [ ] **Step 6: Relancer l'assertion, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_schema_check.php`
Expected: PASS — `OK schema`

- [ ] **Step 7: Vérifier l'idempotence**

Run: `php -r 'require "Bootstrap/init.php"; require "Database/Migrations/2024_01_01_000000_create_schema.php"; \Database\Migrations\up(); echo "re-run OK\n";'`
Expected: `re-run OK`, aucune erreur (les gardes `column_exists`/`index_exists` court-circuitent tout).

- [ ] **Step 8: Lint**

Run: `php -l Database/Migrations/2024_01_01_000000_create_schema.php && php -l Config/constants.php`
Expected: `No syntax errors detected` sur les deux.

- [ ] **Step 9: Commit**

```bash
git add Database/Migrations/2024_01_01_000000_create_schema.php Config/constants.php
git commit -m "$(cat <<'EOF'
feat(presences): schéma récurrence des unités + statut + index d'unicité

Bloc de migration 10 : jours_semaine (+ heure_debut/fin pour bacentas et
basontas), presences.statut ENUM(present,absent,excuse) défaut present,
déduplication idempotente puis index unique uniq_presence sur
(user_id, date_presence, culte_id, bacenta_id, basonta_id, centre_id).
Constante PRESENCE_STATUTS.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 2: Configuration de récurrence dans les formulaires + actions de sauvegarde

**Files:**
- Modify: `app/Repositories/CulteRepository.php:34-44` (`create`, `update`)
- Modify: `app/Repositories/BacentaRepository.php:50-60` (`create`, `update`)
- Modify: `app/Repositories/BasontaRepository.php:30-38` (`create`, `update`)
- Modify: `app/Compat/structure.php:32-64` (`save_bacenta`, `save_culte`, `save_basonta`)
- Modify: `app/Controllers/ActionsController.php` (cas `save_bacenta`, `save_culte`, `save_basonta` — vers lignes 284-338)
- Modify: `Views/pages/forms/bacenta.php`, `Views/pages/forms/culte.php`
- Create: `Views/pages/forms/basonta.php`
- Modify: `app/Compat/sections.php` (`render_basonta_form` — vers ligne 712)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_schedule_check.php`

**Interfaces:**
- Consumes de Task 1 : colonnes `jours_semaine`/`heure_debut`/`heure_fin`.
- Produces :
  - `CulteRepository::create(string $nom, ?string $date, ?string $debut, ?string $fin, ?int $resp = null, ?string $jours = null): int`
  - `CulteRepository::update(int $id, string $nom, ?string $date, ?string $debut, ?string $fin, ?int $resp = null, ?string $jours = null): void`
  - `BacentaRepository::create(string $nom, ?int $centreId, ?int $respId = null, ?string $jours = null, ?string $debut = null, ?string $fin = null): int`
  - `BacentaRepository::update(int $id, string $nom, ?int $centreId, ?int $respId = null, ?string $jours = null, ?string $debut = null, ?string $fin = null): void`
  - `BasontaRepository::create(string $nom, ?int $respId = null, ?string $jours = null, ?string $debut = null, ?string $fin = null): int`
  - `BasontaRepository::update(int $id, string $nom, ?int $respId = null, ?string $jours = null, ?string $debut = null, ?string $fin = null): void`
  - `save_bacenta(?int $id, string $nom, ?int $centreId, ?int $respId, ?string $jours = null, ?string $debut = null, ?string $fin = null): void`
  - `save_culte(?int $id, string $nom, ?string $date, ?string $debut, ?string $fin, ?int $resp, ?string $jours = null): void`
  - `save_basonta(?int $id, string $nom, ?int $resp, ?string $jours = null, ?string $debut = null, ?string $fin = null): void`
  - Champs de formulaire POST : `jours_semaine[]` (cases, valeurs = libellés `WEEK_DAYS`), `heure_debut`, `heure_fin`. Les actions convertissent `jours_semaine[]` filtré sur `WEEK_DAYS` en CSV via `implode(',', ...)`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

Créer `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_schedule_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;
use App\Repositories\BacentaRepository;

$repo = new BacentaRepository();
$id = $repo->create('ZZ_M1_TEST_' . uniqid(), null, null, 'Lundi,Mercredi', '18:00:00', '20:00:00');
$row = Query::one('SELECT jours_semaine, heure_debut, heure_fin FROM bacentas WHERE id = ?', [$id]);
assert($row['jours_semaine'] === 'Lundi,Mercredi', 'jours_semaine non persisté: ' . var_export($row['jours_semaine'], true));
assert(substr((string)$row['heure_debut'], 0, 5) === '18:00', 'heure_debut non persistée');

$repo->update($id, 'ZZ_M1_TEST_upd', null, null, 'Vendredi', null, null);
$row = Query::one('SELECT nom, jours_semaine, heure_debut FROM bacentas WHERE id = ?', [$id]);
assert($row['jours_semaine'] === 'Vendredi', 'update jours_semaine KO');
assert($row['heure_debut'] === null, 'update heure_debut devait repasser à NULL');

Query::run('DELETE FROM bacentas WHERE id = ?', [$id]); // nettoyage
echo "OK schedule persistence\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_schedule_check.php`
Expected: FAIL — `ArgumentCountError` ou `AssertionError: jours_semaine non persisté` (la signature actuelle de `create` n'accepte pas les jours).

- [ ] **Step 3: Étendre `CulteRepository`**

`app/Repositories/CulteRepository.php` — `create` et `update` :

```php
    public function create(string $nom, ?string $date, ?string $debut, ?string $fin, ?int $resp = null, ?string $jours = null): int
    {
        return Query::run(
            'INSERT INTO cultes (nom, date_culte, jours_semaine, heure_debut, heure_fin) VALUES (?, ?, ?, ?, ?)',
            [$nom, $date, $jours, $debut, $fin]
        );
    }

    public function update(int $id, string $nom, ?string $date, ?string $debut, ?string $fin, ?int $resp = null, ?string $jours = null): void
    {
        Query::run(
            'UPDATE cultes SET nom = ?, date_culte = ?, jours_semaine = ?, heure_debut = ?, heure_fin = ? WHERE id = ?',
            [$nom, $date, $jours, $debut, $fin, $id]
        );
    }
```

(Adapter aux `[...]` de paramètres réellement présents lignes 36-44 — seul l'ajout de `jours_semaine` dans les colonnes/valeurs et du paramètre `$jours` est requis. `$resp` reste ignoré comme aujourd'hui.)

- [ ] **Step 4: Étendre `BacentaRepository`**

`app/Repositories/BacentaRepository.php` :

```php
    public function create(string $nom, ?int $centreId, ?int $respId = null, ?string $jours = null, ?string $debut = null, ?string $fin = null): int
    {
        return Query::run(
            'INSERT INTO bacentas (nom, centre_id, jours_semaine, heure_debut, heure_fin) VALUES (?, ?, ?, ?, ?)',
            [$nom, $centreId, $jours, $debut, $fin]
        );
    }

    public function update(int $id, string $nom, ?int $centreId, ?int $respId = null, ?string $jours = null, ?string $debut = null, ?string $fin = null): void
    {
        Query::run(
            'UPDATE bacentas SET nom = ?, centre_id = ?, jours_semaine = ?, heure_debut = ?, heure_fin = ? WHERE id = ?',
            [$nom, $centreId, $jours, $debut, $fin, $id]
        );
    }
```

- [ ] **Step 5: Étendre `BasontaRepository`**

`app/Repositories/BasontaRepository.php` :

```php
    public function create(string $nom, ?int $respId = null, ?string $jours = null, ?string $debut = null, ?string $fin = null): int
    {
        return Query::run(
            'INSERT INTO basontas (nom, jours_semaine, heure_debut, heure_fin) VALUES (?, ?, ?, ?)',
            [$nom, $jours, $debut, $fin]
        );
    }

    public function update(int $id, string $nom, ?int $respId = null, ?string $jours = null, ?string $debut = null, ?string $fin = null): void
    {
        Query::run(
            'UPDATE basontas SET nom = ?, jours_semaine = ?, heure_debut = ?, heure_fin = ? WHERE id = ?',
            [$nom, $jours, $debut, $fin, $id]
        );
    }
```

- [ ] **Step 6: Étendre les wrappers `app/Compat/structure.php`**

```php
function save_bacenta(?int $id, string $nom, ?int $centreId, ?int $respId, ?string $jours = null, ?string $debut = null, ?string $fin = null): void
{
    if ($id) {
        _repo(BacentaRepository::class)->update($id, $nom, $centreId, $respId, $jours, $debut, $fin);
    } else {
        _repo(BacentaRepository::class)->create($nom, $centreId, $respId, $jours, $debut, $fin);
    }
}

function save_culte(?int $id, string $nom, ?string $date, ?string $debut, ?string $fin, ?int $resp, ?string $jours = null): void
{
    if ($id) {
        _repo(CulteRepository::class)->update($id, $nom, $date, $debut, $fin, $resp, $jours);
    } else {
        _repo(CulteRepository::class)->create($nom, $date, $debut, $fin, $resp, $jours);
    }
}

function save_basonta(?int $id, string $nom, ?int $resp, ?string $jours = null, ?string $debut = null, ?string $fin = null): void
{
    if ($id) {
        _repo(BasontaRepository::class)->update($id, $nom, $resp, $jours, $debut, $fin);
    } else {
        _repo(BasontaRepository::class)->create($nom, $resp, $jours, $debut, $fin);
    }
}
```

- [ ] **Step 7: Relancer l'assertion de persistance, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_schedule_check.php`
Expected: PASS — `OK schedule persistence`

- [ ] **Step 8: Lire les champs récurrence dans les actions**

`app/Controllers/ActionsController.php`. Ajouter un helper privé dans la classe (près des autres helpers) :

```php
    /** Jours de récurrence soumis (cases) → CSV filtré sur WEEK_DAYS, ou null. */
    private function scheduleDaysFromPost(): ?string
    {
        $days = array_values(array_intersect(WEEK_DAYS, (array) ($_POST['jours_semaine'] ?? [])));
        return $days ? implode(',', $days) : null;
    }
```

Cas `save_bacenta` — après la vérification d'autorisation, remplacer l'appel `save_bacenta(...)` par :

```php
                if ($nom !== '') {
                    $jours = $this->scheduleDaysFromPost();
                    $debut = trim((string) ($_POST['heure_debut'] ?? '')) ?: null;
                    $fin   = trim((string) ($_POST['heure_fin'] ?? '')) ?: null;
                    save_bacenta($id ?: null, $nom, $centreId, null, $jours, $debut, $fin);
                }
```

Cas `save_culte` — le corps lit déjà `$debut`/`$fin` ; remplacer l'appel par :

```php
                if ($nom !== '') {
                    save_culte($id ?: null, $nom, $date, $debut, $fin, null, $this->scheduleDaysFromPost());
                }
```

Cas `save_basonta` — remplacer l'appel par :

```php
                if ($nom !== '') {
                    $jours = $this->scheduleDaysFromPost();
                    $debut = trim((string) ($_POST['heure_debut'] ?? '')) ?: null;
                    $fin   = trim((string) ($_POST['heure_fin'] ?? '')) ?: null;
                    save_basonta($id ?: null, $nom, null, $jours, $debut, $fin);
                }
```

- [ ] **Step 9: Champs dans `Views/pages/forms/bacenta.php`**

Après le `form-group` du centre, avant le bloc `respUrl`, insérer :

```php
    <div class="form-group">
      <label>Jour(s) de rassemblement (récurrence hebdomadaire)</label>
      <div class="checkbox-row">
        <?php $bJours = explode(',', (string) ($bacenta['jours_semaine'] ?? '')); ?>
        <?php foreach (WEEK_DAYS as $d): ?>
          <label class="check-label"><input type="checkbox" name="jours_semaine[]" value="<?= h($d) ?>" <?= in_array($d, $bJours, true) ? 'checked' : '' ?>> <?= h($d) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Heure début</label><input type="time" name="heure_debut" value="<?= h($bacenta['heure_debut'] ?? '') ?>"></div>
      <div class="form-group"><label>Heure fin</label><input type="time" name="heure_fin" value="<?= h($bacenta['heure_fin'] ?? '') ?>"></div>
    </div>
```

- [ ] **Step 10: Champs dans `Views/pages/forms/culte.php`**

Après le `form-grid` existant (date / heure début / heure fin), insérer :

```php
    <div class="form-group">
      <label>Jour(s) de rassemblement (récurrence hebdomadaire)</label>
      <div class="checkbox-row">
        <?php $cJours = explode(',', (string) ($culte['jours_semaine'] ?? '')); ?>
        <?php foreach (WEEK_DAYS as $d): ?>
          <label class="check-label"><input type="checkbox" name="jours_semaine[]" value="<?= h($d) ?>" <?= in_array($d, $cJours, true) ? 'checked' : '' ?>> <?= h($d) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
```

- [ ] **Step 11: Créer `Views/pages/forms/basonta.php`**

```php
<?php /* Formulaire basonta. Variables : $basonta, $cancelUrl, $csrf. */
$basonta = $basonta ?? null;
$jours = explode(',', (string) ($basonta['jours_semaine'] ?? ''));
?>
<?= section_toolbar(h($basonta ? 'Modifier le basonta' : 'Ajouter un basonta')) ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card">
    <input type="hidden" name="action" value="save_basonta">
    <?= $csrf ?>
    <?php if ($basonta): ?><input type="hidden" name="id" value="<?= h($basonta['id']) ?>"><?php endif; ?>
    <div class="form-group"><label>Nom du basonta</label><input type="text" name="nom" value="<?= h($basonta['nom'] ?? '') ?>" required autofocus></div>
    <div class="form-group">
      <label>Jour(s) de rassemblement (récurrence hebdomadaire)</label>
      <div class="checkbox-row">
        <?php foreach (WEEK_DAYS as $d): ?>
          <label class="check-label"><input type="checkbox" name="jours_semaine[]" value="<?= h($d) ?>" <?= in_array($d, $jours, true) ? 'checked' : '' ?>> <?= h($d) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Heure début</label><input type="time" name="heure_debut" value="<?= h($basonta['heure_debut'] ?? '') ?>"></div>
      <div class="form-group"><label>Heure fin</label><input type="time" name="heure_fin" value="<?= h($basonta['heure_fin'] ?? '') ?>"></div>
    </div>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
```

- [ ] **Step 12: Pointer `render_basonta_form` vers la nouvelle vue**

`app/Compat/sections.php`, fonction `render_basonta_form` — remplacer le `view('pages/forms/name', [...])` par :

```php
    $content = view('pages/forms/basonta', [
        'basonta'   => $b,
        'cancelUrl' => url('index.php', ['page' => 'basontas']),
        'csrf'      => csrf_field(),
    ]);
```

- [ ] **Step 13: Lint**

Run: `php -l app/Repositories/CulteRepository.php && php -l app/Repositories/BacentaRepository.php && php -l app/Repositories/BasontaRepository.php && php -l app/Compat/structure.php && php -l app/Controllers/ActionsController.php && php -l Views/pages/forms/bacenta.php && php -l Views/pages/forms/culte.php && php -l Views/pages/forms/basonta.php && php -l app/Compat/sections.php`
Expected: `No syntax errors detected` partout.

- [ ] **Step 14: Vérification statique du câblage formulaire↔action**

Run: `grep -n "jours_semaine\|heure_debut\|heure_fin" Views/pages/forms/bacenta.php Views/pages/forms/culte.php Views/pages/forms/basonta.php app/Controllers/ActionsController.php`
Expected : chaque formulaire émet `jours_semaine[]` ; les 3 cas d'action lisent `scheduleDaysFromPost()` ; `save_bacenta`/`save_basonta` lisent aussi `heure_debut`/`heure_fin`.

- [ ] **Step 15: Commit**

```bash
git add app/Repositories/CulteRepository.php app/Repositories/BacentaRepository.php app/Repositories/BasontaRepository.php app/Compat/structure.php app/Controllers/ActionsController.php Views/pages/forms/bacenta.php Views/pages/forms/culte.php Views/pages/forms/basonta.php app/Compat/sections.php
git commit -m "$(cat <<'EOF'
feat(presences): configuration de récurrence sur cultes / bacentas / basontas

jours_semaine (cases WEEK_DAYS → CSV) + heure_debut/heure_fin ajoutés aux
formulaires et propagés via save_bacenta/save_culte/save_basonta jusqu'aux
repositories. Nouveau formulaire dédié forms/basonta.php (remplace le
formulaire générique forms/name pour le basonta).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 3: Moteur de présence par occurrence (repository + service + wrappers)

**Files:**
- Modify: `app/Repositories/AttendanceRepository.php` (ajouts de méthodes)
- Modify: `app/Services/AttendanceService.php` (ajouts de méthodes)
- Modify: `app/Compat/data.php` (wrappers, près des lignes 104-106)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_engine_check.php`

**Interfaces:**
- Consumes de Task 1 : colonne `presences.statut`, index `uniq_presence`.
- Produces :
  - `AttendanceRepository::UNIT_COLUMNS` — `['bacenta' => 'bacenta_id', 'cult' => 'culte_id', 'basonta' => 'basonta_id']` (const de classe privée ; `'cult'` = même clé que `RbacService::canManageEntity`).
  - `AttendanceRepository::pointOccurrence(string $unitType, int $unitId, string $date, array $statutByUserId): void` — dans une transaction : `DELETE FROM presences WHERE <col> = ? AND date_presence = ?` puis une insertion par entrée `[userId => statut]`. Lève `\InvalidArgumentException` si `$unitType` inconnu.
  - `AttendanceRepository::occurrenceStatuts(string $unitType, int $unitId, string $date): array` — `[userId => statut]` pour cette occurrence.
  - `AttendanceRepository::distinctDatesForUnit(string $unitType, int $unitId, string $from, string $to): array` — liste triée de `date_presence` (chaînes `Y-m-d`) sur l'intervalle.
  - `AttendanceRepository::matrixForUnit(string $unitType, int $unitId, string $from, string $to): array` — `[userId => [date => statut]]`.
  - `AttendanceService::pointOccurrence(string $unitType, int $unitId, string $date, array $rawStatutByUserId, array $allowedUserIds): void` — filtre : ne garde que les `userId ∈ $allowedUserIds` et les statuts ∈ `array_keys(PRESENCE_STATUTS)` ; délègue au repo dans `Query::transaction()`.
  - `AttendanceService::occurrenceGrid(string $unitType, int $unitId, string $date, array $members): array` — `[ ['user' => <row>, 'statut' => <statut|''> ], ... ]` dans l'ordre de `$members`.
  - `AttendanceService::annualMatrix(string $unitType, int $unitId, int $year, array $members): array` — `['dates' => string[], 'rows' => [ ['user' => <row>, 'cells' => [date => statut] ], ... ]]`.
  - Wrappers `app/Compat/data.php` :
    - `save_unit_presence(string $unitType, int $unitId, string $date, array $rawStatuts, array $allowedUserIds): void`
    - `unit_presence_grid(string $unitType, int $unitId, string $date, array $members): array`
    - `unit_annual_matrix(string $unitType, int $unitId, int $year, array $members): array`

- [ ] **Step 1: Écrire l'assertion qui échoue**

Créer `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_engine_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;
use App\Services\AttendanceService;

// Deux membres réels quelconques
$users = Query::all("SELECT id FROM users ORDER BY id LIMIT 2");
assert(count($users) === 2, 'besoin de 2 users');
[$u1, $u2] = [(int) $users[0]['id'], (int) $users[1]['id']];
$bac = (int) Query::value("SELECT id FROM bacentas ORDER BY id LIMIT 1");
assert($bac > 0, 'besoin d\'un bacenta');
$date = '2020-01-06'; // date de test isolée

$svc = new AttendanceService();

// 1er pointage : u1 présent, u2 excusé, + un id NON autorisé ignoré
$svc->pointOccurrence('bacenta', $bac, $date, [$u1 => 'present', $u2 => 'excuse', 999999 => 'present'], [$u1, $u2]);
$g = $svc->occurrenceGrid('bacenta', $bac, $date, [['id' => $u1], ['id' => $u2]]);
assert($g[0]['statut'] === 'present', 'u1 devrait être present');
assert($g[1]['statut'] === 'excuse', 'u2 devrait être excuse');
$cnt = (int) Query::value('SELECT COUNT(*) FROM presences WHERE bacenta_id = ? AND date_presence = ?', [$bac, $date]);
assert($cnt === 2, "999999 non autorisé aurait dû être ignoré, vu $cnt lignes");

// 2e pointage même occurrence : remplacement propre (u1 absent, u2 retiré)
$svc->pointOccurrence('bacenta', $bac, $date, [$u1 => 'absent'], [$u1, $u2]);
$cnt = (int) Query::value('SELECT COUNT(*) FROM presences WHERE bacenta_id = ? AND date_presence = ?', [$bac, $date]);
assert($cnt === 1, "remplacement attendu = 1 ligne, vu $cnt");
$st = Query::value('SELECT statut FROM presences WHERE bacenta_id = ? AND date_presence = ? AND user_id = ?', [$bac, $date, $u1]);
assert($st === 'absent', "u1 devrait être absent, vu " . var_export($st, true));

// statut invalide ignoré
$svc->pointOccurrence('bacenta', $bac, $date, [$u1 => 'n_importe_quoi'], [$u1]);
$cnt = (int) Query::value('SELECT COUNT(*) FROM presences WHERE bacenta_id = ? AND date_presence = ?', [$bac, $date]);
assert($cnt === 0, "statut invalide => 0 ligne, vu $cnt");

// matrice annuelle
$svc->pointOccurrence('bacenta', $bac, '2021-03-01', [$u1 => 'present'], [$u1]);
$svc->pointOccurrence('bacenta', $bac, '2021-03-08', [$u1 => 'excuse'], [$u1]);
$m = $svc->annualMatrix('bacenta', $bac, 2021, [['id' => $u1]]);
assert($m['dates'] === ['2021-03-01', '2021-03-08'], 'dates matrice KO: ' . implode(',', $m['dates']));
assert($m['rows'][0]['cells']['2021-03-01'] === 'present', 'cellule matrice KO');

// nettoyage
Query::run("DELETE FROM presences WHERE bacenta_id = ? AND date_presence IN ('2020-01-06','2021-03-01','2021-03-08')", [$bac]);
echo "OK engine\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_engine_check.php`
Expected: FAIL — `Error: Call to undefined method App\Services\AttendanceService::pointOccurrence()`.

- [ ] **Step 3: Ajouter les méthodes au repository**

Dans `app/Repositories/AttendanceRepository.php`, dans la classe :

```php
    private const UNIT_COLUMNS = ['bacenta' => 'bacenta_id', 'cult' => 'culte_id', 'basonta' => 'basonta_id'];

    private function unitColumn(string $unitType): string
    {
        if (!isset(self::UNIT_COLUMNS[$unitType])) {
            throw new \InvalidArgumentException("Type d'unité inconnu: {$unitType}");
        }
        return self::UNIT_COLUMNS[$unitType];
    }

    /** Upsert des statuts d'une occurrence (unité, date). Appelé sous transaction. */
    public function pointOccurrence(string $unitType, int $unitId, string $date, array $statutByUserId): void
    {
        $col = $this->unitColumn($unitType);
        Query::run("DELETE FROM presences WHERE $col = ? AND date_presence = ?", [$unitId, $date]);
        foreach ($statutByUserId as $userId => $statut) {
            Query::run(
                "INSERT INTO presences (user_id, date_presence, statut, $col) VALUES (?, ?, ?, ?)",
                [(int) $userId, $date, $statut, $unitId]
            );
        }
    }

    /** @return array<int,string> [userId => statut] */
    public function occurrenceStatuts(string $unitType, int $unitId, string $date): array
    {
        $col = $this->unitColumn($unitType);
        $out = [];
        foreach (Query::all("SELECT user_id, statut FROM presences WHERE $col = ? AND date_presence = ?", [$unitId, $date]) as $r) {
            $out[(int) $r['user_id']] = (string) $r['statut'];
        }
        return $out;
    }

    /** @return list<string> dates Y-m-d triées */
    public function distinctDatesForUnit(string $unitType, int $unitId, string $from, string $to): array
    {
        $col = $this->unitColumn($unitType);
        return array_map(
            static fn($r) => (string) $r['date_presence'],
            Query::all(
                "SELECT DISTINCT date_presence FROM presences
                  WHERE $col = ? AND date_presence BETWEEN ? AND ? ORDER BY date_presence",
                [$unitId, $from, $to]
            )
        );
    }

    /** @return array<int,array<string,string>> [userId => [date => statut]] */
    public function matrixForUnit(string $unitType, int $unitId, string $from, string $to): array
    {
        $col = $this->unitColumn($unitType);
        $out = [];
        foreach (Query::all(
            "SELECT user_id, date_presence, statut FROM presences
              WHERE $col = ? AND date_presence BETWEEN ? AND ?",
            [$unitId, $from, $to]
        ) as $r) {
            $out[(int) $r['user_id']][(string) $r['date_presence']] = (string) $r['statut'];
        }
        return $out;
    }
```

- [ ] **Step 4: Ajouter les méthodes au service**

Dans `app/Services/AttendanceService.php`, dans la classe :

```php
    public function pointOccurrence(string $unitType, int $unitId, string $date, array $rawStatutByUserId, array $allowedUserIds): void
    {
        $allowed = array_flip(array_map('intval', $allowedUserIds));
        $valid = array_keys(PRESENCE_STATUTS);
        $clean = [];
        foreach ($rawStatutByUserId as $userId => $statut) {
            $userId = (int) $userId;
            if (isset($allowed[$userId]) && in_array($statut, $valid, true)) {
                $clean[$userId] = $statut;
            }
        }
        \App\Core\Query::transaction(function () use ($unitType, $unitId, $date, $clean) {
            $this->attendance->pointOccurrence($unitType, $unitId, $date, $clean);
        });
    }

    /** @param array<int,array> $members lignes users (au moins la clé 'id') */
    public function occurrenceGrid(string $unitType, int $unitId, string $date, array $members): array
    {
        $statuts = $this->attendance->occurrenceStatuts($unitType, $unitId, $date);
        $out = [];
        foreach ($members as $m) {
            $out[] = ['user' => $m, 'statut' => $statuts[(int) $m['id']] ?? ''];
        }
        return $out;
    }

    public function annualMatrix(string $unitType, int $unitId, int $year, array $members): array
    {
        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);
        $dates = $this->attendance->distinctDatesForUnit($unitType, $unitId, $from, $to);
        $matrix = $this->attendance->matrixForUnit($unitType, $unitId, $from, $to);
        $rows = [];
        foreach ($members as $m) {
            $rows[] = ['user' => $m, 'cells' => $matrix[(int) $m['id']] ?? []];
        }
        return ['dates' => $dates, 'rows' => $rows];
    }
```

Ajouter `use App\Core\Query;` en tête si absent (sinon garder le `\App\Core\Query::transaction` pleinement qualifié comme ci-dessus).

- [ ] **Step 5: Ajouter les wrappers `app/Compat/data.php`**

Près de la ligne `function point_culte_presence(...)` :

```php
function save_unit_presence(string $unitType, int $unitId, string $date, array $rawStatuts, array $allowedUserIds): void
{
    _repo(\App\Services\AttendanceService::class)->pointOccurrence($unitType, $unitId, $date, $rawStatuts, $allowedUserIds);
}
function unit_presence_grid(string $unitType, int $unitId, string $date, array $members): array
{
    return _repo(\App\Services\AttendanceService::class)->occurrenceGrid($unitType, $unitId, $date, $members);
}
function unit_annual_matrix(string $unitType, int $unitId, int $year, array $members): array
{
    return _repo(\App\Services\AttendanceService::class)->annualMatrix($unitType, $unitId, $year, $members);
}
```

- [ ] **Step 6: Relancer l'assertion, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_engine_check.php`
Expected: PASS — `OK engine`

- [ ] **Step 7: Lint**

Run: `php -l app/Repositories/AttendanceRepository.php && php -l app/Services/AttendanceService.php && php -l app/Compat/data.php`
Expected: `No syntax errors detected` partout.

- [ ] **Step 8: Non-régression du pointage culte hérité**

Run: `grep -n "function pointCulte\|function point_culte_presence\|pointCulte(" app/Repositories/AttendanceRepository.php app/Services/AttendanceService.php app/Compat/data.php`
Expected : `pointCulte` (repo + service) et `point_culte_presence` (compat) sont toujours présents et inchangés — les ajouts sont purement additifs.

- [ ] **Step 9: Commit**

```bash
git add app/Repositories/AttendanceRepository.php app/Services/AttendanceService.php app/Compat/data.php
git commit -m "$(cat <<'EOF'
feat(presences): moteur de pointage par occurrence (unité, date, statut)

AttendanceRepository/Service : pointOccurrence (upsert transactionnel
delete+insert par (unité, date)), occurrenceStatuts, matrixForUnit,
distinctDatesForUnit, annualMatrix. Le service filtre les user_id au
périmètre autorisé et les statuts à PRESENCE_STATUTS. Wrappers compat
save_unit_presence / unit_presence_grid / unit_annual_matrix. Le pointage
culte hérité (pointCulte) reste intact.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 4: Onglet de pointage d'une date + action `save_presence_occurrence`

**Files:**
- Create: `Views/pages/presence_occurrence.php`
- Create: `assets/css/presences.css`
- Modify: le point d'inclusion CSS (même mécanisme que les CSS existants — voir Step 5)
- Modify: `app/Compat/sections.php` (`render_bacenta_detail`, `render_culte_detail`, `render_basonta_detail` — ajout onglet `presences`)
- Modify: `app/Controllers/ActionsController.php` (nouveau cas `save_presence_occurrence`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_action_check.php` + lint + grep

**Interfaces:**
- Consumes de Task 3 : `unit_presence_grid`, `save_unit_presence`.
- Consumes de Task 1 : `PRESENCE_STATUTS`.
- Produces :
  - Onglet `?page=<bacentas|cultes|basontas>&id=<id>&tab=presences&date=<Y-m-d>` rendu par les renderers détail.
  - Action POST `save_presence_occurrence` : champs `unit_type` (`bacenta|cult|basonta`), `unit_id`, `date` (`Y-m-d`), `statut[<userId>]` (`present|absent|excuse`). Garde : `check_csrf()` + `can_manage_entity($unit_type, $unit_id)`. Redirige vers l'onglet avec la date.
  - Vue `Views/pages/presence_occurrence.php` — variables : `$unitType`, `$unit`, `$pageKey` (`bacentas|cultes|basontas`), `$date`, `$grid` (sortie `unit_presence_grid`), `$statuts` (`PRESENCE_STATUTS`), `$joursHint` (string), `$csrf`, `$matrixUrl`.

- [ ] **Step 1: Écrire l'assertion qui échoue (action enregistrée)**

Créer `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_action_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
$src = file_get_contents('app/Controllers/ActionsController.php');
assert(str_contains($src, "case 'save_presence_occurrence'"), "cas save_presence_occurrence absent");
assert(str_contains($src, 'can_manage_entity'), "garde RBAC absente du contrôleur");

$view = 'Views/pages/presence_occurrence.php';
assert(is_file($view), "vue $view absente");
$v = file_get_contents($view);
assert(str_contains($v, 'save_presence_occurrence'), "la vue ne poste pas la bonne action");
assert(str_contains($v, 'name="statut['), "la vue n'émet pas statut[<userId>]");

$css = file_get_contents('assets/css/presences.css');
assert(!str_contains($css, '#') || preg_match('/var\(--/', $css) === 1, "presences.css devrait utiliser les variables CSS");

// Onglet câblé dans les renderers
$sec = file_get_contents('app/Compat/sections.php');
assert(substr_count($sec, "'presences'") >= 3, "onglet 'presences' pas câblé dans les 3 renderers");
echo "OK wiring\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_action_check.php`
Expected: FAIL — `AssertionError: cas save_presence_occurrence absent`.

- [ ] **Step 3: Créer `Views/pages/presence_occurrence.php`**

```php
<?php /* Pointage de présence d'une occurrence.
   Variables : $unitType, $unit, $pageKey, $date, $grid, $statuts, $joursHint, $csrf, $matrixUrl. */ ?>
<div class="section-toolbar">
  <div>
    <h2><?= h($unit['nom']) ?></h2>
    <div class="sub">Pointage des présences — une date</div>
  </div>
  <a class="btn btn-outline" href="<?= h($matrixUrl) ?>"><i class="fa-solid fa-table-cells"></i> Matrice annuelle</a>
</div>

<form method="get" action="index.php" class="presence-datebar">
  <input type="hidden" name="page" value="<?= h($pageKey) ?>">
  <input type="hidden" name="id" value="<?= h($unit['id']) ?>">
  <input type="hidden" name="tab" value="presences">
  <label>Date du rassemblement</label>
  <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
  <?php if ($joursHint !== ''): ?><span class="presence-hint">Jours habituels : <?= h($joursHint) ?></span><?php endif; ?>
</form>

<form method="post" action="index.php">
  <input type="hidden" name="action" value="save_presence_occurrence">
  <?= $csrf ?>
  <input type="hidden" name="unit_type" value="<?= h($unitType) ?>">
  <input type="hidden" name="unit_id" value="<?= h($unit['id']) ?>">
  <input type="hidden" name="date" value="<?= h($date) ?>">

  <div class="table-wrap">
    <table class="data-table presence-table">
      <thead><tr><th>Membre</th><th>Statut</th></tr></thead>
      <tbody>
        <?php if (!$grid): ?>
          <tr><td colspan="2"><?= empty_state('fa-users', 'Aucun membre à pointer pour cette unité.') ?></td></tr>
        <?php else: ?>
          <?php foreach ($grid as $line): $u = $line['user']; ?>
            <tr>
              <td><?= h(full_name($u)) ?></td>
              <td>
                <select name="statut[<?= (int) $u['id'] ?>]">
                  <option value="">—</option>
                  <?php foreach ($statuts as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $line['statut'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="modal-actions">
    <button type="submit" class="btn btn-primary" <?= $grid ? '' : 'disabled' ?>>Enregistrer les présences</button>
  </div>
</form>
```

- [ ] **Step 4: Créer `assets/css/presences.css`**

```css
/* M1 — Présences par occurrence & matrice annuelle */
.presence-datebar {
  display: flex;
  align-items: center;
  gap: var(--space-3, 12px);
  margin-bottom: var(--space-4, 16px);
  flex-wrap: wrap;
}
.presence-hint {
  color: var(--color-text-muted, #6b7280);
  font-size: var(--font-size-sm, 0.875rem);
}
.presence-table td:last-child { width: 12rem; }
.presence-matrix { border-collapse: collapse; width: 100%; }
.presence-matrix th,
.presence-matrix td {
  border: 1px solid var(--color-border, #e5e7eb);
  padding: var(--space-2, 8px);
  text-align: center;
  white-space: nowrap;
}
.presence-matrix th:first-child,
.presence-matrix td:first-child { text-align: left; position: sticky; left: 0; background: var(--color-surface, #fff); }
.presence-cell-present { color: var(--color-success, #16a34a); font-weight: 600; }
.presence-cell-absent  { color: var(--color-danger, #dc2626); font-weight: 600; }
.presence-cell-excuse  { color: var(--color-warning, #d97706); font-weight: 600; }
```

(Adapter les noms de variables à ceux réellement définis dans `assets/css/variables.css` — voir Step 5.)

- [ ] **Step 5: Inclure le CSS**

Run d'abord: `grep -rn "presences\|attendance\|app.css\|suivi.css\|assets/css/" Views/layouts/layout.php assets/css/app.css` pour repérer le mécanisme (import `@import` dans `app.css`, ou `<link>` dans `layout.php`).
Puis ajouter `presences.css` par le même mécanisme que les autres feuilles modulaires (probablement une ligne `@import "presences.css";` dans `assets/css/app.css`, ou un `<link rel="stylesheet">` supplémentaire dans `Views/layouts/layout.php`). Reproduire exactement le style des lignes voisines. Vérifier aussi les vraies variables disponibles dans `assets/css/variables.css` et corriger `presences.css` en conséquence (pas de couleur en dur si une variable existe).

- [ ] **Step 6: Câbler l'onglet dans `render_bacenta_detail`**

`app/Compat/sections.php`, `render_bacenta_detail`. Le corps gère déjà `$tab === 'suivi'`. Ajouter, après le bloc `suivi` et avant le rendu `membres` par défaut :

```php
    if ($tab === 'presences' || $tab === 'presences_annuel') {
        if (!has_verified_access('bacentas', $bacentaId)) {
            render_gate('bacentas', $bacentaId, $b['nom']);
            return;
        }
        render_unit_presence_tab('bacenta', 'bacentas', $b, $tab, get_members_of_bacenta($bacentaId));
        return;
    }
```

Ajouter les 2 onglets à `$tabs` (la barre existante) :

```php
        'presences' => ['label' => '<i class="fa-solid fa-clipboard-check"></i> Présences', 'url' => url('index.php', ['page' => 'bacentas', 'id' => $bacentaId, 'tab' => 'presences'])],
```

- [ ] **Step 7: Câbler l'onglet dans `render_culte_detail` et `render_basonta_detail`**

Même logique. Pour le culte, `$unitType = 'cult'`, `$pageKey = 'cultes'`, population = `Query::all("SELECT id, prenom, nom FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY prenom, nom")` (identique à la liste `$candidates` déjà utilisée dans `render_culte_detail`). Pour le basonta, `$unitType = 'basonta'`, `$pageKey = 'basontas'`, population = `get_members_of_basonta($basontaId)`.

`render_culte_detail` n'a pas de barre d'onglets aujourd'hui (il rend directement `culte_detail`). Ajouter une `tab_row` en tête avec deux entrées : `pointage` (le contenu actuel `culte_detail`, onglet par défaut, **inchangé**) et `presences` (nouvel onglet occurrence). Ne pas retirer le pointeur inline existant.

- [ ] **Step 8: Écrire le helper de rendu partagé `render_unit_presence_tab`**

Dans `app/Compat/sections.php` (près des autres helpers de section) :

```php
/**
 * Onglet présences d'une unité : pointage d'une date (tab=presences) ou
 * matrice annuelle (tab=presences_annuel). $unitType ∈ bacenta|cult|basonta,
 * $pageKey ∈ bacentas|cultes|basontas.
 */
function render_unit_presence_tab(string $unitType, string $pageKey, array $unit, string $tab, array $members): void
{
    $unitId = (int) $unit['id'];
    if (!can_manage_entity($unitType, $unitId)) {
        deny();
    }
    $joursHint = trim(str_replace(',', ', ', (string) ($unit['jours_semaine'] ?? '')));

    if ($tab === 'presences_annuel') {
        $year = (int) (nav('year') ?: date('Y'));
        $matrix = unit_annual_matrix($unitType, $unitId, $year, $members);
        render_page($unit['nom'], view('pages/presence_matrix', [
            'unit'       => $unit,
            'pageKey'    => $pageKey,
            'unitType'   => $unitType,
            'year'       => $year,
            'matrix'     => $matrix,
            'statuts'    => PRESENCE_STATUTS,
            'printUrl'   => url('index.php', ['page' => 'presencePrint', 'unit_type' => $unitType, 'unit_id' => $unitId, 'year' => $year]),
            'occUrl'     => url('index.php', ['page' => $pageKey, 'id' => $unitId, 'tab' => 'presences']),
        ]));
        return;
    }

    $date = (string) (nav('date') ?: date('Y-m-d'));
    $grid = unit_presence_grid($unitType, $unitId, $date, $members);
    render_page($unit['nom'], view('pages/presence_occurrence', [
        'unitType'  => $unitType,
        'unit'      => $unit,
        'pageKey'   => $pageKey,
        'date'      => $date,
        'grid'      => $grid,
        'statuts'   => PRESENCE_STATUTS,
        'joursHint' => $joursHint,
        'csrf'      => csrf_field(),
        'matrixUrl' => url('index.php', ['page' => $pageKey, 'id' => $unitId, 'tab' => 'presences_annuel']),
    ]));
}
```

`deny()` : vérifier le nom exact du helper d'accès refusé utilisé ailleurs dans `sections.php` (`render_gate` / `deny` / `redirect`) et reproduire.

- [ ] **Step 9: Ajouter le cas `save_presence_occurrence`**

`app/Controllers/ActionsController.php`, dans `postAction()`, à côté de `point_culte` :

```php
            case 'save_presence_occurrence': {
                $this->requireUser();
                $unitType = (string) ($_POST['unit_type'] ?? '');
                $unitId = (int) ($_POST['unit_id'] ?? 0);
                if (!in_array($unitType, ['bacenta', 'cult', 'basonta'], true) || !$unitId || !can_manage_entity($unitType, $unitId)) {
                    $this->deny();
                }
                $date = (string) ($_POST['date'] ?? date('Y-m-d'));
                // Population autorisée revalidée serveur selon le type d'unité.
                $allowed = match ($unitType) {
                    'bacenta' => array_map(static fn($m) => (int) $m['id'], get_members_of_bacenta($unitId)),
                    'basonta' => array_map(static fn($m) => (int) $m['id'], get_members_of_basonta($unitId)),
                    'cult'    => array_map(static fn($m) => (int) $m['id'], \App\Core\Query::all("SELECT id FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant')")),
                };
                $raw = [];
                foreach ((array) ($_POST['statut'] ?? []) as $uid => $st) {
                    $raw[(int) $uid] = (string) $st;
                }
                save_unit_presence($unitType, $unitId, $date, $raw, $allowed);
                $pageKey = ['bacenta' => 'bacentas', 'cult' => 'cultes', 'basonta' => 'basontas'][$unitType];
                $this->redirect('index.php', ['page' => $pageKey, 'id' => $unitId, 'tab' => 'presences', 'date' => $date]);
                break;
            }
```

- [ ] **Step 10: Relancer l'assertion, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_action_check.php`
Expected: PASS — `OK wiring`

- [ ] **Step 11: Lint**

Run: `php -l Views/pages/presence_occurrence.php && php -l app/Compat/sections.php && php -l app/Controllers/ActionsController.php`
Expected: `No syntax errors detected` partout.

- [ ] **Step 12: Vérification fonctionnelle bout-en-bout (script)**

Créer et lancer `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_e2e_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;

$bac = (int) Query::value("SELECT id FROM bacentas ORDER BY id LIMIT 1");
$members = get_members_of_bacenta($bac);
if (count($members) < 1) { echo "SKIP: bacenta sans membre\n"; exit; }
$uid = (int) $members[0]['id'];
$allowed = array_map(static fn($m) => (int) $m['id'], $members);

save_unit_presence('bacenta', $bac, '2019-06-02', [$uid => 'excuse'], $allowed);
$grid = unit_presence_grid('bacenta', $bac, '2019-06-02', $members);
assert($grid[0]['statut'] === 'excuse', 'grid KO');
$mx = unit_annual_matrix('bacenta', $bac, 2019, $members);
assert($mx['dates'] === ['2019-06-02'], 'matrix dates KO');
Query::run("DELETE FROM presences WHERE bacenta_id = ? AND date_presence = '2019-06-02'", [$bac]);
echo "OK e2e\n";
```

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_e2e_check.php`
Expected: `OK e2e` (ou `SKIP: ...` si le bacenta n'a pas de membre — dans ce cas noter dans le rapport).

- [ ] **Step 13: Commit**

```bash
git add Views/pages/presence_occurrence.php assets/css/presences.css app/Compat/sections.php app/Controllers/ActionsController.php Views/layouts/layout.php assets/css/app.css
git commit -m "$(cat <<'EOF'
feat(presences): onglet de pointage d'une date + action save_presence_occurrence

Onglet "Présences" ajouté aux pages détail bacenta/culte/basonta (à côté
de l'UI existante, pointage culte hérité intact). Sélecteur de date +
menu Présent/Absent/Excusé par membre. Action save_presence_occurrence :
CSRF, can_manage_entity, revalidation serveur de la population, upsert
transactionnel. Nouvelle feuille assets/css/presences.css.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

*(Ajuster `git add` aux fichiers réellement modifiés au Step 5.)*

---

### Task 5: Matrice annuelle in-app + impression

**Files:**
- Create: `Views/pages/presence_matrix.php`
- Create: `Views/pages/presence_matrix_print.php`
- Create: `app/Controllers/PresenceController.php`
- Modify: `Routes/web.php` (route `presencePrint`)
- Test: `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_print_check.php` + lint

**Interfaces:**
- Consumes de Task 3 : `unit_annual_matrix`. De Task 4 : `render_unit_presence_tab` (branche `presences_annuel` déjà écrite — cette tâche ne fait que fournir les 2 vues + la route d'impression).
- Produces :
  - `Views/pages/presence_matrix.php` — variables : `$unit`, `$pageKey`, `$unitType`, `$year`, `$matrix` (`['dates'=>[], 'rows'=>[]]`), `$statuts`, `$printUrl`, `$occUrl`.
  - `Views/pages/presence_matrix_print.php` — page autonome (pattern `attendance_print.php`) — variables : `$unit`, `$year`, `$matrix`, `$statuts`, `$printedAt`.
  - `App\Controllers\PresenceController::matrixPrint(): void` — lit `unit_type`, `unit_id`, `year` (GET) ; `can_manage_entity` ; charge l'unité + ses membres ; `echo view('pages/presence_matrix_print', ...)`.
  - Route `Router::get('presencePrint', PresenceController::class, 'matrixPrint')`.

- [ ] **Step 1: Écrire l'assertion qui échoue**

Créer `/home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_print_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
assert(is_file('app/Controllers/PresenceController.php'), 'PresenceController absent');
assert(is_file('Views/pages/presence_matrix.php'), 'presence_matrix.php absent');
assert(is_file('Views/pages/presence_matrix_print.php'), 'presence_matrix_print.php absent');
$routes = file_get_contents('Routes/web.php');
assert(str_contains($routes, "'presencePrint'"), 'route presencePrint absente');
$ctrl = file_get_contents('app/Controllers/PresenceController.php');
assert(str_contains($ctrl, 'can_manage_entity'), 'garde RBAC absente de PresenceController');
$print = file_get_contents('Views/pages/presence_matrix_print.php');
assert(str_contains($print, 'window.print()'), 'bouton imprimer absent');
assert(str_contains($print, '<!DOCTYPE html>'), 'page print non autonome');
echo "OK print wiring\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `cd /home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise && php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_print_check.php`
Expected: FAIL — `AssertionError: PresenceController absent`.

- [ ] **Step 3: Créer `Views/pages/presence_matrix.php`**

```php
<?php /* Matrice annuelle des présences d'une unité (in-app, lecture seule).
   Variables : $unit, $pageKey, $unitType, $year, $matrix, $statuts, $printUrl, $occUrl. */
$cls = ['present' => 'presence-cell-present', 'absent' => 'presence-cell-absent', 'excuse' => 'presence-cell-excuse'];
?>
<div class="section-toolbar">
  <div>
    <h2><?= h($unit['nom']) ?></h2>
    <div class="sub">Présences <?= (int) $year ?> — matrice annuelle</div>
  </div>
  <div class="toolbar-actions">
    <form method="get" action="index.php" class="inline-form">
      <input type="hidden" name="page" value="<?= h($pageKey) ?>">
      <input type="hidden" name="id" value="<?= h($unit['id']) ?>">
      <input type="hidden" name="tab" value="presences_annuel">
      <label>Année <input type="number" name="year" value="<?= (int) $year ?>" min="2000" max="2100" onchange="this.form.submit()"></label>
    </form>
    <a class="btn btn-outline" href="<?= h($occUrl) ?>"><i class="fa-solid fa-clipboard-check"></i> Pointer une date</a>
    <a class="btn btn-primary" href="<?= h($printUrl) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Imprimer</a>
  </div>
</div>

<?php if (!$matrix['dates']): ?>
  <?= empty_state('fa-table-cells', "Aucune présence enregistrée pour cette unité en {$year}.") ?>
<?php else: ?>
<div class="table-wrap table-scroll">
  <table class="presence-matrix">
    <thead>
      <tr><th>Membre</th>
        <?php foreach ($matrix['dates'] as $d): ?><th><?= h(date('d/m', strtotime($d))) ?></th><?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($matrix['rows'] as $row): ?>
        <tr>
          <td><?= h(full_name($row['user'])) ?></td>
          <?php foreach ($matrix['dates'] as $d): $s = $row['cells'][$d] ?? ''; ?>
            <td class="<?= h($cls[$s] ?? '') ?>"><?= $s ? h(mb_substr($statuts[$s], 0, 1)) : '—' ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="presence-hint">P = Présent · A = Absent · E = Excusé</p>
</div>
<?php endif; ?>
```

- [ ] **Step 4: Créer `Views/pages/presence_matrix_print.php`**

Reproduire la structure autonome de `Views/pages/attendance_print.php` (mêmes `<link>` `app.css` / `print.css` / font-awesome, `print-toolbar no-print` avec `window.print()` / `window.close()`, `print-header`, `print-title`). Corps :

```php
<?php
/* Matrice annuelle imprimable — page autonome.
 * Variables : $unit, $year, $matrix, $statuts, $printedAt. */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Présences <?= (int) $year ?> — <?= h($unit['nom']) ?> — <?= h(APP_NAME) ?></title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="assets/css/print.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <div class="print-page">
    <div class="print-toolbar no-print">
      <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer</button>
      <button class="btn btn-outline" onclick="window.close()">Fermer</button>
    </div>
    <div class="print-header">
      <div class="brand">⛪ <?= h(APP_NAME) ?></div>
      <div class="meta">Édité le <?= h($printedAt) ?></div>
    </div>
    <h1 class="print-title">Présences <?= (int) $year ?></h1>
    <p class="print-sub"><?= h($unit['nom']) ?></p>
    <?php if (!$matrix['dates']): ?>
      <p>Aucune présence enregistrée pour cette unité en <?= (int) $year ?>.</p>
    <?php else: ?>
    <table class="print-table">
      <thead><tr><th>Membre</th>
        <?php foreach ($matrix['dates'] as $d): ?><th><?= h(date('d/m', strtotime($d))) ?></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
        <?php foreach ($matrix['rows'] as $row): ?>
          <tr><td><?= h(full_name($row['user'])) ?></td>
            <?php foreach ($matrix['dates'] as $d): $s = $row['cells'][$d] ?? ''; ?>
              <td><?= $s ? h(mb_substr($statuts[$s], 0, 1)) : '—' ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p>P = Présent · A = Absent · E = Excusé</p>
    <?php endif; ?>
    <div class="print-footer"><?= h(APP_NAME) ?> — Fiche générée automatiquement, à usage administratif.</div>
  </div>
</body>
</html>
```

- [ ] **Step 5: Créer `app/Controllers/PresenceController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Query;

/**
 * Impression de la matrice annuelle des présences d'une unité.
 */
class PresenceController extends Controller
{
    public function matrixPrint(): void
    {
        $this->requireUser();
        $unitType = (string) (nav('unit_type') ?? '');
        $unitId = (int) (nav('unit_id') ?? 0);
        if (!in_array($unitType, ['bacenta', 'cult', 'basonta'], true) || !$unitId || !can_manage_entity($unitType, $unitId)) {
            $this->deny();
        }
        $year = (int) (nav('year') ?: date('Y'));

        [$unit, $members] = match ($unitType) {
            'bacenta' => [get_bacenta($unitId), get_members_of_bacenta($unitId)],
            'basonta' => [get_basonta($unitId), get_members_of_basonta($unitId)],
            'cult'    => [get_culte($unitId), Query::all("SELECT * FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY prenom, nom")],
        };
        if (!$unit) {
            $this->redirect('index.php', ['page' => 'accueil']);
        }

        echo view('pages/presence_matrix_print', [
            'unit'      => $unit,
            'year'      => $year,
            'matrix'    => unit_annual_matrix($unitType, $unitId, $year, $members),
            'statuts'   => PRESENCE_STATUTS,
            'printedAt' => date('d/m/Y à H:i'),
        ]);
    }
}
```

Vérifier les vrais noms : classe de base (`Controller`), helpers `requireUser`/`deny`/`redirect`, helper `nav()`. S'aligner sur `ProfileController`.

- [ ] **Step 6: Enregistrer la route**

`Routes/web.php` — ajouter avec les autres `Router::get`, et le `use App\Controllers\PresenceController;` en tête :

```php
Router::get('presencePrint', PresenceController::class, 'matrixPrint');
```

- [ ] **Step 7: Relancer l'assertion, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 /home/foxtrot/.claude/jobs/e2c26e8c/tmp/m1_print_check.php`
Expected: PASS — `OK print wiring`

- [ ] **Step 8: Lint**

Run: `php -l Views/pages/presence_matrix.php && php -l Views/pages/presence_matrix_print.php && php -l app/Controllers/PresenceController.php && php -l Routes/web.php`
Expected: `No syntax errors detected` partout.

- [ ] **Step 9: Rendu de la vue matrice hors HTTP (fumée)**

Run: `php -r '
chdir("/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise");
require "Bootstrap/init.php";
$html = view("pages/presence_matrix_print", [
  "unit" => ["id" => 1, "nom" => "Test"], "year" => 2021,
  "matrix" => ["dates" => ["2021-03-01"], "rows" => [["user" => ["prenom" => "A", "nom" => "B"], "cells" => ["2021-03-01" => "present"]]]],
  "statuts" => PRESENCE_STATUTS, "printedAt" => "x",
]);
echo (str_contains($html, "<!DOCTYPE html>") && str_contains($html, "Présences 2021")) ? "render OK\n" : "render KO\n";
'`
Expected: `render OK` (aucune notice PHP dans la sortie).

- [ ] **Step 10: Commit**

```bash
git add Views/pages/presence_matrix.php Views/pages/presence_matrix_print.php app/Controllers/PresenceController.php Routes/web.php
git commit -m "$(cat <<'EOF'
feat(presences): matrice annuelle in-app + fiche imprimable

Onglet "Présences annuelles" (membres × dates pointées de l'année, badges
de statut) et page autonome imprimable ?page=presencePrint (pattern
attendance_print). Nouveau PresenceController::matrixPrint gardé par
can_manage_entity.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 6: Colonne « Prénom » dans le tableau des membres du basonta

**Files:**
- Modify: `app/Compat/sections.php` (`render_basonta_detail` — vers lignes 401-418)
- Test: lint + grep

**Interfaces:**
- Consumes: rien des tâches précédentes.
- Produces : le tableau de `render_basonta_detail` a une colonne « Prénom » (valeur `h($m['prenom'] ?? '')`) juste après « Nom », sans casser la colonne « Nom » (qui reste `full_name($m)` ou, au choix, `h($m['nom'])` — voir Step 1) ni les colonnes suivantes.

- [ ] **Step 1: Décider du contenu de la colonne « Nom »**

Lire `render_basonta_detail` dans `app/Compat/sections.php`. Aujourd'hui la 1ʳᵉ cellule est `full_name($m)` (prénom + nom). Pour éviter la redondance, avec l'ajout d'une colonne « Prénom » dédiée : mettre la colonne « Nom » à `h($m['nom'] ?? '')` et la nouvelle colonne « Prénom » à `h($m['prenom'] ?? '')`. En-têtes : `<th>Nom</th><th>Prénom</th><th>Téléphone</th><th>Présence Basonta</th><th>Actions</th>`.

- [ ] **Step 2: Appliquer la modification**

Dans `render_basonta_detail`, la génération des `$rows` :

```php
    foreach ($members as $m) {
        $rows .= '<tr><td>' . h($m['nom'] ?? '') . '</td><td>' . h($m['prenom'] ?? '') . '</td><td>' . h($m['telephone'] ?? '') . '</td>'
            . '<td>' . presence_badge(presence_status($m, 'presenceBasonta')) . '</td>'
            . '<td class="row-actions"><a class="icon-btn danger" title="Retirer" data-confirm="Retirer ce membre du basonta ?" href="' . h(url('index.php', ['page' => 'basontas', 'action' => 'basonta_remove_member', 'basonta' => $basontaId, 'membre' => $m['id']])) . '"><i class="fa-solid fa-trash"></i></a></td></tr>';
    }
    $rows = $rows ?: '<tr><td colspan="5">' . empty_state('fa-inbox', 'Aucun membre dans ce basonta.') . '</td></tr>';
```

Et l'en-tête `<thead>` : passer de 4 à 5 colonnes comme indiqué au Step 1. Mettre à jour le `colspan` de la ligne vide (4 → 5).

- [ ] **Step 3: Lint**

Run: `php -l app/Compat/sections.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Vérification statique**

Run: `grep -n "Prénom\|colspan=\"5\"\|\$m\['prenom'\]" app/Compat/sections.php`
Expected : l'en-tête « Prénom » et la cellule `$m['prenom']` sont présents dans `render_basonta_detail` ; le `colspan` de la ligne vide est passé à 5.

- [ ] **Step 5: Commit**

```bash
git add app/Compat/sections.php
git commit -m "$(cat <<'EOF'
feat(basontas): colonne Prénom dans le tableau des membres du basonta

Sépare Nom / Prénom dans render_basonta_detail (spec M1). En-tête et
colspan de la ligne vide mis à jour.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

## Self-Review

**1. Spec coverage (§4 « M1 ») :**

| Exigence spec | Tâche |
|---|---|
| Config récurrence (jour(s) + plage horaire) à la création/édition culte/bacenta/basonta | Task 2 (repos + wrappers + actions + 3 formulaires, dont `forms/basonta.php` neuf) |
| `cultes.jours_semaine` | Task 1, Step 4 |
| `bacentas`/`basontas` : `jours_semaine`, `heure_debut`, `heure_fin` | Task 1, Step 4 |
| `presences.statut ENUM('present','absent','excuse') DEFAULT 'present'` | Task 1, Step 4 |
| Index d'unicité (unité, date, personne) + dédup préalable | Task 1, Step 4 (centre_id inclus — voir Décision #2) |
| Onglet pointage `tab=presences&date=` sur bacenta/culte/basonta | Task 4, Steps 6-8 |
| Menu déroulant Présent/Absent/Excusé par personne | Task 4, Step 3 (`presence_occurrence.php`) |
| Bacenta → membres du bacenta ; Basonta → membres du basonta ; Culte → tous les membres | Task 4, Step 7 + Task 4, Step 9 (revalidation serveur `$allowed`) |
| Upsert transactionnel (delete (unité,date) + insert) | Task 3, Step 3-4 (`pointOccurrence` sous `Query::transaction`) |
| Onglet matrice annuelle `tab=presences_annuel`, lecture seule + impression | Task 4 Step 8 (branche) + Task 5 (2 vues + route `presencePrint` + `PresenceController`) |
| Réutiliser le patron `attendancePrint` | Task 5, Step 4 (structure autonome de `attendance_print.php`) |
| Permissions = périmètre RBAC existant (`can_manage_entity`) | Task 4 Steps 8-9, Task 5 Step 5 |
| Action `save_presence_occurrence` (CSRF, RBAC, revalidation, transaction) | Task 4, Step 9 |
| Config récurrence intégrée aux `save_bacenta/save_culte/save_basonta` existants (pas de nouvelle action) | Task 2, Step 8 |
| Colonne « Prénom » (Basontas) | Task 6 |
| CSS modulaire `assets/css/presences.css` | Task 4, Steps 4-5 |
| « Nom de la personne visitée » (Bacentas) | Hors périmètre — Décision #5 (spec le laisse à confirmer) |
| Pointage culte hérité non cassé | Task 3 Step 8 + Task 4 Step 7 (onglet `pointage` = contenu actuel inchangé) |
| Aucune nouvelle entrée de menu (déviation §D assumée) | Décision #4 |

**2. Placeholder scan :** chaque step de code fournit le code exact et la commande exacte avec sa sortie attendue. Les rares « adapter aux noms réels » (Task 4 Step 5/8, Task 5 Step 5) sont des vérifications ciblées de noms d'API du framework, pas des TODO de logique — chacune nomme précisément quoi vérifier et où (`ProfileController`, `assets/css/variables.css`, helpers de `sections.php`).

**3. Type consistency :**
- `$unitType` prend les valeurs `bacenta` | `cult` | `basonta` partout (repo `UNIT_COLUMNS`, service, action `save_presence_occurrence`, `PresenceController`, `render_unit_presence_tab`) — aligné sur `RbacService::canManageEntity` qui accepte `'cult'`.
- `$pageKey` (`bacentas` | `cultes` | `basontas`) est distinct de `$unitType` et converti explicitement (map inline dans l'action et le helper).
- `unit_annual_matrix()` renvoie `['dates' => string[], 'rows' => [['user'=>..,'cells'=>[date=>statut]]]]` — consommé identiquement par `presence_matrix.php`, `presence_matrix_print.php`, `m1_engine_check.php`, `m1_e2e_check.php`.
- `unit_presence_grid()` renvoie `[['user'=>row,'statut'=>string]]` — consommé par `presence_occurrence.php` et les scripts d'assertion.
- Signatures des repos étendues avec des paramètres **à défaut `null` en fin de liste** → les appelants existants (`save_*` compat, seeders) restent valides sans modification.
- `PRESENCE_STATUTS` : clés `present|absent|excuse`, utilisées comme whitelist dans `AttendanceService::pointOccurrence` et comme `<option>` dans les vues.

**4. Ordre des tâches :** 1 (schéma) → 2 (config récurrence, indépendante du moteur) → 3 (moteur, dépend de 1) → 4 (UI pointage, dépend de 3 et 1) → 5 (matrice/impression, dépend de 3 et de la branche `presences_annuel` écrite en 4) → 6 (colonne Prénom, indépendante). Task 6 peut être faite à tout moment après le début ; placée en dernier car cosmétique.

---

## Execution Handoff

Six tâches. 1→5 sont séquentielles (dépendances de données/schéma) ; 6 est indépendante. Chaque tâche finit par un livrable testable (script d'assertion contre la base de dev réelle + `php -l`) et un commit.
