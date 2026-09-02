# M3 — Suivi Hebdo. des Bergers : champs Mixlr / Ushers / Thème + correction « Ashers » — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter au tableau de suivi hebdomadaire des bergers trois colonnes — diffusion Mixlr, nombre d'ushers, thème de la semaine — et corriger l'orthographe du ministère « Ashers » → « Ushers ».

**Architecture:** Le tableau de suivi est entièrement piloté par la constante `SUIVI_FIELDS` (`Config/constants.php`) : les vues (`suivi_week.php`, `suivi_print.php`, `my_profile.php`) itèrent dessus pour rendre les champs, et la persistance (`BergerRepository::saveSuiviWeek`) enregistre sans liste blanche tout `champ` reçu. Ajouter des entrées à la constante suffit donc de bout en bout. Seul `ReportService::weekCompletion` (calcul du % de réalisation) doit être ajusté pour ne pas faire chuter les pourcentages historiques à cause des nouveaux champs. La correction « Ashers » touche `BASONTAS_DEFAULT` (nouvelles installations via le seeder) plus un `UPDATE` idempotent dans la migration (bases déjà en place).

**Tech Stack:** PHP 8 SSR, micro-framework maison, zéro dépendance. MySQL/MariaDB via PDO (`App\Core\Query`). Pas de PHPUnit — vérification = `php -l` + assertions `php -r` + parcours manuel.

**Spec:** `docs/superpowers/specs/2026-09-01-integration-modules-eglise-design.md` (§4 « M3 »)

## Global Constraints

- Zéro dépendance externe : pas de Composer, npm, build, Docker. Ne rien introduire.
- PSR-12, `declare(strict_types=1)`, types sur paramètres et retours.
- Couches strictes : pas de SQL hors Repository, pas de HTML hors View, pas d'accès requête hors Controller.
- Schéma : uniquement des instructions idempotentes dans le fichier de migration unique `Database/Migrations/2024_01_01_000000_create_schema.php`.
- CSS modulaire sous `assets/css/`, variables de `variables.css`, aucun style/script inline dans les vues. (Aucun CSS nécessaire pour M3.)
- Ne jamais casser une URL, l'auth, ni un formulaire existant. On étend `SUIVI_FIELDS`, on ne réordonne pas les entrées existantes.
- `install.php` reste supprimable — aucun code runtime n'en dépend.
- Comptes de démonstration : `admin@labelleeglise.ga` / `LBEGF` (admin), `berger.eric.bongo@labelleeglise.ga` / `BergerEB1` (berger).

## Décisions de cadrage (issues du spec §7 « points ouverts »)

1. **`mixlr` et `ushers` sont marqués `sundayOnly`** : la diffusion Mixlr et le comptage des ushers concernent le culte du dimanche. Cela limite aussi leur impact sur le dénominateur du % (comptés le dimanche uniquement).
2. **`themeSemaine` est marqué `optional`** : nouveau flag ajouté à `SUIVI_FIELDS` + `ReportService::weekCompletion`. Un champ `optional` n'entre ni au numérateur ni au dénominateur du calcul de réalisation — les semaines déjà saisies gardent leur pourcentage.
3. **`nomBerger` (proposé « optionnel » au spec) est abandonné** : le tableau de suivi est déjà entièrement rattaché à un seul berger (nom affiché dans l'en-tête de `suivi_week.php`). YAGNI.

## File Structure

| Fichier | Rôle | Action |
|---|---|---|
| `Config/constants.php` | Définit `SUIVI_FIELDS` (colonnes du tableau de suivi) et `BASONTAS_DEFAULT` (noms de basontas semés) | Modifier : +3 entrées `SUIVI_FIELDS` ; `'Ashers'` → `'Ushers'` dans `BASONTAS_DEFAULT` |
| `app/Services/ReportService.php` | Calcul du % de réalisation d'une semaine / année de suivi | Modifier : `weekCompletion()` ignore les champs `optional` |
| `Database/Migrations/2024_01_01_000000_create_schema.php` | Migration idempotente unique | Modifier : nouveau bloc « 9 » — `UPDATE basontas SET nom = 'Ushers' WHERE nom = 'Ashers'` |

Aucune vue, aucun contrôleur, aucun repository, aucun CSS à toucher.

---

### Task 1: Nouveaux champs de suivi (Mixlr, ushers, thème) + neutralité du % de réalisation

**Files:**
- Modify: `Config/constants.php` (bloc `define('SUIVI_FIELDS', [ ... ]);`, actuellement vers les lignes 177-187)
- Modify: `app/Services/ReportService.php:31-48` (méthode `weekCompletion`)
- Test: `php -r` (assertion sur la constante) + `$CLAUDE_JOB_DIR/tmp/m3_completion_check.php` (assertion sur `ReportService`)

**Interfaces:**
- Consumes: rien (première tâche).
- Produces :
  - `SUIVI_FIELDS` contient trois nouvelles entrées, clés `mixlr`, `ushers`, `themeSemaine`.
  - Nouveau flag conventionnel dans une entrée `SUIVI_FIELDS` : `'optional' => true` — signifie « rendu dans le formulaire mais exclu du calcul de réalisation ».
  - `App\Services\ReportService::weekCompletion(array $week): int` — signature inchangée ; ignore désormais toute entrée `SUIVI_FIELDS` où `!empty($f['optional'])`.

- [ ] **Step 1: Écrire l'assertion qui échoue (constante)**

Créer le fichier `$CLAUDE_JOB_DIR/tmp/m3_fields_check.php` :

```php
<?php
declare(strict_types=1);
require __DIR__ . '/../../../Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise/Config/constants.php';

$byKey = [];
foreach (SUIVI_FIELDS as $f) {
    $byKey[$f['key']] = $f;
}

assert(isset($byKey['mixlr']), 'mixlr manquant');
assert($byKey['mixlr']['type'] === 'text', 'mixlr doit être de type text');
assert(!empty($byKey['mixlr']['sundayOnly']), 'mixlr doit être sundayOnly');

assert(isset($byKey['ushers']), 'ushers manquant');
assert($byKey['ushers']['type'] === 'number', 'ushers doit être de type number');
assert(!empty($byKey['ushers']['sundayOnly']), 'ushers doit être sundayOnly');

assert(isset($byKey['themeSemaine']), 'themeSemaine manquant');
assert(!empty($byKey['themeSemaine']['optional']), 'themeSemaine doit être optional');

assert(!isset($byKey['nomBerger']), 'nomBerger ne doit pas être ajouté (YAGNI)');

echo "OK constante\n";
```

> Note : adapter le chemin `require` au chemin absolu réel du projet si le lancement se fait ailleurs — `Config/constants.php` n'a aucune dépendance (que des `define()`), il se charge seul.

- [ ] **Step 2: Lancer l'assertion, vérifier l'échec**

Run: `php -d zend.assertions=1 -d assert.exception=1 "$CLAUDE_JOB_DIR/tmp/m3_fields_check.php"`
Expected: FAIL — `AssertionError: mixlr manquant`

- [ ] **Step 3: Ajouter les trois entrées à `SUIVI_FIELDS`**

Dans `Config/constants.php`, à l'intérieur de `define('SUIVI_FIELDS', [ ... ]);`, juste après la dernière entrée existante (`invitesApres`, celle avec `'sundayOnly' => true`) et avant le `]);` :

```php
    ['key' => 'mixlr',        'label' => 'Diffusion Mixlr (lien ou statut)', 'type' => 'text', 'sundayOnly' => true],
    ['key' => 'ushers',       'label' => "Nombre d'ushers", 'type' => 'number', 'sundayOnly' => true],
    ['key' => 'themeSemaine', 'label' => 'Thème de la semaine', 'type' => 'text', 'optional' => true],
```

Ne pas modifier ni réordonner les entrées existantes.

- [ ] **Step 4: Relancer l'assertion (constante), vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 "$CLAUDE_JOB_DIR/tmp/m3_fields_check.php"`
Expected: PASS — `OK constante`

- [ ] **Step 5: Écrire l'assertion qui échoue (calcul du %)**

Créer `$CLAUDE_JOB_DIR/tmp/m3_completion_check.php` (remplacer `PROJECT` par le chemin absolu du dépôt) :

```php
<?php
declare(strict_types=1);
const PROJECT = '/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise';
require PROJECT . '/Config/constants.php';
require PROJECT . '/app/Repositories/BergerRepository.php';
require PROJECT . '/app/Services/ReportService.php';

// Construit une semaine où TOUS les champs non-optionnels sont remplis :
// - champs de tous les jours pour les non-sundayOnly
// - champs sundayOnly remplis le dimanche
$week = [];
foreach (WEEK_DAYS as $day) {
    $row = [];
    foreach (SUIVI_FIELDS as $f) {
        if (!empty($f['optional'])) {
            continue; // volontairement laissé vide : ne doit pas compter
        }
        if (!empty($f['sundayOnly']) && $day !== 'Dimanche') {
            continue;
        }
        $row[$f['key']] = ($f['type'] ?? 'text') === 'number' ? '3' : 'x';
    }
    $week[$day] = $row;
}

$svc = new App\Services\ReportService(new App\Repositories\BergerRepository());
$pct = $svc->weekCompletion($week);
assert($pct === 100, "attendu 100, obtenu {$pct} — les champs optionnels comptent encore dans le dénominateur");
echo "OK completion = {$pct}\n";
```

- [ ] **Step 6: Lancer l'assertion (calcul du %), vérifier l'échec**

Run: `php -d zend.assertions=1 -d assert.exception=1 "$CLAUDE_JOB_DIR/tmp/m3_completion_check.php"`
Expected: FAIL — `AssertionError: attendu 100, obtenu 97` (ou valeur < 100 : `themeSemaine` gonfle encore le dénominateur)

- [ ] **Step 7: Exclure les champs `optional` du calcul de réalisation**

Dans `app/Services/ReportService.php`, méthode `weekCompletion`, la boucle interne actuelle :

```php
            foreach (SUIVI_FIELDS as $f) {
                if (!empty($f['sundayOnly']) && $day !== 'Dimanche') {
                    continue;
                }
                $total++;
```

devient :

```php
            foreach (SUIVI_FIELDS as $f) {
                if (!empty($f['optional'])) {
                    continue;
                }
                if (!empty($f['sundayOnly']) && $day !== 'Dimanche') {
                    continue;
                }
                $total++;
```

Ne pas toucher `isFieldFilled`, `yearCompletion`, `weeklySeries`.

- [ ] **Step 8: Relancer l'assertion (calcul du %), vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 "$CLAUDE_JOB_DIR/tmp/m3_completion_check.php"`
Expected: PASS — `OK completion = 100`

- [ ] **Step 9: Lint**

Run: `php -l Config/constants.php && php -l app/Services/ReportService.php`
Expected: `No syntax errors detected` sur les deux.

- [ ] **Step 10: Vérification manuelle du rendu**

```bash
php -S 127.0.0.1:8000
```

Dans un navigateur :
1. Se connecter en admin (`admin@labelleeglise.ga` / `LBEGF`).
2. Ouvrir `http://127.0.0.1:8000/index.php?page=suiviBergers&membre=1` (ou un id de berger existant).
3. Vérifier :
   - Nouvelle colonne **« Diffusion Mixlr (lien ou statut) »** : cellule éditable uniquement sur la ligne **Dimanche**, `—` les autres jours.
   - Nouvelle colonne **« Nombre d'ushers »** : champ `number`, Dimanche uniquement, `—` ailleurs.
   - Nouvelle colonne **« Thème de la semaine »** : champ texte sur **tous** les jours.
4. Remplir toutes les cellules de la ligne **Dimanche** (y compris les 2 nouvelles colonnes sundayOnly), laisser « Thème de la semaine » vide, remplir les autres jours pour tous les champs non-sundayOnly, cliquer **Enregistrer la semaine**.
5. Recharger : les valeurs sont persistées ; le chip **« Réalisation : 100 % »** s'affiche (la colonne « Thème de la semaine » vide n'empêche pas d'atteindre 100 %).
6. Ouvrir `index.php?page=profile` (fiche libre-service) et `index.php?page=suiviPrint&membre=1` : les trois colonnes apparaissent aussi, sans erreur PHP.

Arrêter le serveur (Ctrl+C).

- [ ] **Step 11: Commit**

```bash
git add Config/constants.php app/Services/ReportService.php
git commit -m "$(cat <<'EOF'
feat(suivi-hebdo): colonnes Mixlr, nombre d'ushers et thème de la semaine

Ajoute 3 entrées à SUIVI_FIELDS. mixlr/ushers sont sundayOnly (culte du
dimanche). themeSemaine porte un nouveau flag 'optional' : rendu dans le
formulaire mais exclu du calcul de ReportService::weekCompletion, pour ne
pas faire chuter le % des semaines déjà saisies.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

### Task 2: Correction orthographe du ministère « Ashers » → « Ushers »

**Files:**
- Modify: `Config/constants.php` (`define('BASONTAS_DEFAULT', [...])`, actuellement ligne 48)
- Modify: `Database/Migrations/2024_01_01_000000_create_schema.php` (fin de la fonction `up()`, après le bloc « 8 » des colonnes de profil, avant l'accolade fermante de `up()`)
- Test: `php -r` (assertion sur la constante) + `php install.php` + requête SQL de contrôle

**Interfaces:**
- Consumes: rien de Task 1.
- Produces :
  - `BASONTAS_DEFAULT` ne contient plus `'Ashers'` mais `'Ushers'`.
  - `\Database\Migrations\up()` exécute `UPDATE basontas SET nom = 'Ushers' WHERE nom = 'Ashers'` (idempotent, sans effet sur une base déjà corrigée).

- [ ] **Step 1: Écrire l'assertion qui échoue**

Créer `$CLAUDE_JOB_DIR/tmp/m3_basonta_check.php` (adapter le chemin) :

```php
<?php
declare(strict_types=1);
require '/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise/Config/constants.php';

assert(!in_array('Ashers', BASONTAS_DEFAULT, true), "BASONTAS_DEFAULT contient encore 'Ashers'");
assert(in_array('Ushers', BASONTAS_DEFAULT, true), "BASONTAS_DEFAULT devrait contenir 'Ushers'");
echo "OK basonta\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec**

Run: `php -d zend.assertions=1 -d assert.exception=1 "$CLAUDE_JOB_DIR/tmp/m3_basonta_check.php"`
Expected: FAIL — `AssertionError: BASONTAS_DEFAULT contient encore 'Ashers'`

- [ ] **Step 3: Corriger la constante**

Dans `Config/constants.php` ligne ~48, remplacer :

```php
define('BASONTAS_DEFAULT', ['Chorale', 'Ashers', 'Film Start', 'Perfect Sound', 'Akwaba', 'Singing Start']);
```

par :

```php
define('BASONTAS_DEFAULT', ['Chorale', 'Ushers', 'Film Start', 'Perfect Sound', 'Akwaba', 'Singing Start']);
```

- [ ] **Step 4: Relancer l'assertion, vérifier le succès**

Run: `php -d zend.assertions=1 -d assert.exception=1 "$CLAUDE_JOB_DIR/tmp/m3_basonta_check.php"`
Expected: PASS — `OK basonta`

- [ ] **Step 5: Ajouter le bloc de correction idempotent à la migration**

Dans `Database/Migrations/2024_01_01_000000_create_schema.php`, fonction `up()`, tout à la fin (juste après le bloc `if (!index_exists($pdo, 'users', 'idx_email_change_token')) { ... }` et avant l'accolade fermante de `up()`), insérer :

```php

    /* ---- 9. Correction orthographe basonta « Ashers » → « Ushers » ----
     * Le ministère des placiers s'écrit « ushers ». BASONTAS_DEFAULT corrige
     * les nouvelles installations (via le seeder) ; cette requête répare les
     * bases déjà en place. Idempotente : aucun effet si la ligne n'existe
     * pas ou a déjà été renommée. `basontas.nom` n'est pas UNIQUE — aucune
     * collision de clé possible.
     */
    $pdo->exec("UPDATE basontas SET nom = 'Ushers' WHERE nom = 'Ashers'");
```

- [ ] **Step 6: Lint**

Run: `php -l Config/constants.php && php -l Database/Migrations/2024_01_01_000000_create_schema.php`
Expected: `No syntax errors detected` sur les deux.

- [ ] **Step 7: Vérifier le seed d'une base neuve**

> Prérequis : une base MySQL/MariaDB de dev configurée dans `.env` (cette commande **efface** toutes les données).

```bash
php install.php
```

Expected: se termine sans erreur (`Installation terminée` ou équivalent).

Puis contrôler le nom semé :

```bash
php -r 'require "Bootstrap/init.php"; var_dump(App\Core\Query::all("SELECT nom FROM basontas ORDER BY nom"));'
```

Expected: la liste contient `"Ushers"`, ne contient pas `"Ashers"`.

- [ ] **Step 8: Vérifier l'idempotence de la migration (simulation prod)**

Rejouer `up()` seul sur la base déjà migrée :

```bash
php -r 'require "Bootstrap/init.php"; require "Database/Migrations/2024_01_01_000000_create_schema.php"; \Database\Migrations\up(); echo "up() OK\n";'
```

Expected: `up() OK`, aucune exception, aucun avertissement SQL.

- [ ] **Step 9: Commit**

```bash
git add Config/constants.php Database/Migrations/2024_01_01_000000_create_schema.php
git commit -m "$(cat <<'EOF'
fix(basontas): orthographe du ministère « Ashers » → « Ushers »

Corrige BASONTAS_DEFAULT (nouvelles installations) et ajoute un UPDATE
idempotent dans la migration pour renommer la ligne existante sur les
bases déjà en place.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01CTwErHUeHbWLJdC6RXuf1a
EOF
)"
```

---

## Self-Review

**1. Spec coverage (§4 « M3 ») :**

| Exigence spec | Tâche |
|---|---|
| Ajouter `mixlr` (lien/statut) à `SUIVI_FIELDS` | Task 1, Step 3 |
| Ajouter `ushers` (number) à `SUIVI_FIELDS` | Task 1, Step 3 |
| Ajouter `themeSemaine` (text, optionnel) | Task 1, Step 3 |
| `nomBerger` (optionnel) | Abandonné — justifié §« Décisions de cadrage » n°3 (redondant avec l'en-tête de `suivi_week.php`) |
| « Temps de prière » déjà présent (`priere`) — ne rien faire | Aucune tâche nécessaire (constaté dans le spec) |
| Corriger « ashers » → « ushers » | Task 2 (constante + `UPDATE` migration) |
| Migration : bloc `UPDATE basontas ... WHERE nom = 'Ashers'` idempotent | Task 2, Step 5 |
| Décision §7 : intégration des nouveaux champs au % de `ReportService` | Task 1, Steps 5-8 (`optional` exclu du calcul ; `sundayOnly` limite l'impact des deux autres) |
| Aucun changement de schéma de table | Respecté (seul un `UPDATE` de données est ajouté) |
| Aucune vue à modifier (constant-driven) | Respecté — vérifié en Task 1 Step 10 |

Aucun trou.

**2. Placeholder scan :** aucun « TBD/TODO », aucune consigne vague. Chaque step de code montre le code exact et la commande exacte avec sa sortie attendue.

**3. Type consistency :** `weekCompletion(array $week): int` référencée à l'identique en Task 1 (interface + Steps 7-8). Le flag `optional` est introduit en Task 1 Step 3 (constante) et consommé au même endroit Step 7 (`ReportService`). Clés `mixlr` / `ushers` / `themeSemaine` orthographiées identiquement partout. `BASONTAS_DEFAULT` / valeur `'Ushers'` cohérentes entre Task 2 Steps 3 et 5.

---

## Execution Handoff

Deux tâches, séquentielles (Task 2 ne dépend pas de Task 1 mais touche le même fichier `Config/constants.php` — les faire dans l'ordre). Chaque tâche se termine par un livrable testable et un commit.
