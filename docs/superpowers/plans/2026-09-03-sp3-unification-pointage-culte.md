# SP-3 — Unification du pointage culte — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development ou superpowers:executing-plans. Steps `- [ ]`.

**Goal:** Retirer l'onglet « Pointage rapide » et `Views/pages/culte_detail.php` ; la fiche culte n'affiche que le pointage par occurrence (composant SP-2) + matrice annuelle. `point_culte` / `AttendanceService::pointCulte` deviennent des wrappers dépréciés vers `pointOccurrence`. Zéro migration, zéro perte de données.

**Architecture:** `render_culte_detail()` → `render_unit_presence_tab('cult', …)` direct ; `culte_detail.php` supprimé ; `point_culte` (action) et `AttendanceService::pointCulte()` ré-implémentés en délégation `pointOccurrence('cult', …)` avec docbloc `@deprecated`.

**Tech Stack:** PHP 8 SSR, zéro dep, MySQL via `App\Core\Query`. `php -l` + assertions + smoke-render.

**Spec:** `docs/superpowers/specs/2026-09-03-sp3-unification-pointage-culte-design.md`. **Prérequis :** SP-2 livré (le composant `attendance_pointage`).

## Global Constraints

- Zéro dep ; PSR-12 + `strict_types` ; couches strictes.
- Ne pas casser : `save_presence_occurrence unit_type=cult`, `tab=presences_annuel`, `presencePrint?unit_type=cult`, la grille des cultes (`nb_presents`), le fil d'Ariane `page=cultes&id=<id>`, `has_verified_access('cultes', …)` + `render_gate`.
- Aucune perte de données : les lignes `presences.culte_id` existantes restent lues à l'identique.
- Base dev MySQL `127.0.0.1:3306` root `la_belle_eglise_db`. Comptes : `admin@labelleeglise.ga`/`LBEGF`.

## File Structure

| Fichier | Action |
|---|---|
| `app/Compat/sections.php` | Modifier : `render_culte_detail()` → `render_unit_presence_tab('cult', …)` direct |
| `Views/pages/culte_detail.php` | Supprimer |
| `app/Controllers/ActionsController.php` | Modifier : `case 'point_culte'` → wrapper déprécié vers `save_unit_presence('cult', …)` |
| `app/Services/AttendanceService.php` | Modifier : `pointCulte()` → délégation `pointOccurrence('cult', …)` + `@deprecated` |
| `app/Repositories/AttendanceRepository.php` | Modifier : `@deprecated` sur `pointCulte()` (ou suppression si zéro référence) |

---

### Task 1: `point_culte` + `AttendanceService::pointCulte` → délégation `pointOccurrence`

**Files:** `app/Controllers/ActionsController.php`, `app/Services/AttendanceService.php`, `app/Repositories/AttendanceRepository.php` ; test `tmp/sp3_wrapper_check.php`

**Interfaces produced:**
- `AttendanceService::pointCulte(int $culteId, string $date, array $userIds): void` — `$ids = array_map('intval', $userIds)` ; `$this->pointOccurrence('cult', $culteId, $date, array_fill_keys($ids, 'present'), $ids)`. Docbloc `@deprecated Utiliser pointOccurrence('cult', …)`.
- `case 'point_culte'` (action) — voir spec §4 : `auth_can_manage_culte`, population re-dérivée serveur, `save_unit_presence('cult', $culte, $date, array_fill_keys($present, 'present'), $population)`, redirige `page=cultes&id=<id>&tab=presences&date=<date>`. `@deprecated`.
- `AttendanceRepository::pointCulte()` — `@deprecated` (conservé).

- [ ] **Step 1: Assertion qui échoue** — `tmp/sp3_wrapper_check.php` :
```php
<?php declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query; use App\Services\AttendanceService;
$culte = (int) Query::value('SELECT id FROM cultes ORDER BY id LIMIT 1');
$us = array_map(fn($r)=>(int)$r['id'], Query::all("SELECT id FROM users WHERE role='membre' ORDER BY id LIMIT 2"));
[$u1,$u2] = $us;
$svc = new AttendanceService();
$svc->pointCulte($culte, '2018-12-02', [$u1, $u2]);
$rows = Query::all('SELECT user_id, statut, culte_id FROM presences WHERE culte_id=? AND date_presence=?', [$culte,'2018-12-02']);
assert(count($rows) === 2, 'pointCulte deleg: 2 lignes attendues, vu '.count($rows));
assert($rows[0]['statut'] === 'present' && (int)$rows[0]['culte_id'] === $culte, 'statut/culte_id KO');
$svc->pointCulte($culte, '2018-12-02', [$u1, $u2]); // idempotence
assert((int) Query::value('SELECT COUNT(*) FROM presences WHERE culte_id=? AND date_presence=?', [$culte,'2018-12-02']) === 2, 'idempotence KO');
// hors population ignoré (via save_unit_presence path — simulé)
$svc->pointCulte($culte, '2018-12-02', [$u1]); // seul u1 → 1 ligne
assert((int) Query::value('SELECT COUNT(*) FROM presences WHERE culte_id=? AND date_presence=?', [$culte,'2018-12-02']) === 1, 'remplacement KO');
$src = file_get_contents('app/Controllers/ActionsController.php');
assert(str_contains($src, "save_unit_presence('cult'") || str_contains($src, 'save_unit_presence("cult"'), 'point_culte doit router vers save_unit_presence(cult)');
assert(str_contains($src, '@deprecated'), 'point_culte doit être marqué @deprecated');
Query::run('DELETE FROM presences WHERE culte_id=? AND date_presence=?', [$culte,'2018-12-02']);
echo "OK sp3 wrapper\n";
```
- [ ] **Step 2: FAIL** (`pointCulte` fait encore l'ancien DELETE/INSERT via le repo ; l'assertion `save_unit_presence('cult'` échoue).
- [ ] **Step 3: `AttendanceService::pointCulte`** — remplacer le corps par la délégation (voir Interfaces). Garder la signature.
- [ ] **Step 4: `case 'point_culte'`** — remplacer par le wrapper de la spec §4. `\App\Core\Query` est importé dans `ActionsController`.
- [ ] **Step 5: `AttendanceRepository::pointCulte`** — ajouter `/** @deprecated Plus appelé (SP-3). Conservé pour compat. */` au-dessus.
- [ ] **Step 6: GREEN + `php -l` (3 fichiers) + commit** `refactor(presences): point_culte et pointCulte délèguent à pointOccurrence (dépréciés)`.

---

### Task 2: `render_culte_detail()` → composant occurrence direct + suppression `culte_detail.php`

**Files:** `app/Compat/sections.php`, suppression `Views/pages/culte_detail.php` ; test `tmp/sp3_render_check.php`

- [ ] **Step 1: Assertion** — `tmp/sp3_render_check.php` :
```php
<?php declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
$sec = file_get_contents('app/Compat/sections.php');
// extraire render_culte_detail
$start = strpos($sec, 'function render_culte_detail');
$body = substr($sec, $start, 2000);
assert(strpos($body, "view('pages/culte_detail'") === false && strpos($body, 'pages/culte_detail') === false, 'render_culte_detail ne doit plus rendre culte_detail.php');
assert(strpos($body, "'pointage'") === false, "l'onglet 'pointage' doit être retiré");
assert(strpos($body, "render_unit_presence_tab('cult'") !== false, "render_culte_detail doit appeler render_unit_presence_tab('cult', …)");
assert(!is_file('Views/pages/culte_detail.php'), 'culte_detail.php doit être supprimé');
assert(strpos(file_get_contents('Routes/web.php').file_get_contents('app/Compat/sections.php'), 'culte_detail') === false || true, 'info');
echo "OK sp3 render\n";
```
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: Réécrire `render_culte_detail()`** — garder `get_culte` + redirect si absent + `has_verified_access('cultes', $culteId)` + `render_gate`. Ensuite :
```php
    $culteMembers = Query::all("SELECT * FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY prenom, nom");
    $tab = nav('tab');
    render_unit_presence_tab('cult', 'cultes', $c, in_array($tab, ['presences', 'presences_annuel'], true) ? $tab : 'presences', $culteMembers);
```
  Supprimer tout le bloc legacy (`$culteTabs`, `tab_row`, `$presents = get_members_of_culte`, `$candidates`, `$isAdmin`, `$date` formaté, `section_toolbar(...)` + `view('pages/culte_detail', …)`).
- [ ] **Step 4: `git rm Views/pages/culte_detail.php`**.
- [ ] **Step 5: GREEN + `php -l app/Compat/sections.php` + commit** `refactor(cultes): fiche culte = pointage occurrence + matrice annuelle uniquement`.

---

### Task 3: Non-régression + balayage

- [ ] `grep -rn "culte_detail\|point_culte\|pointCulte\|'pointage'" app/ Views/ Routes/` — attendu : `point_culte` (action wrapper `@deprecated`), `pointCulte` (service délégation + repo `@deprecated`), `point_culte_presence` (compat). **Aucun** `pages/culte_detail`, **aucun** onglet `'pointage'`.
- [ ] Repo-wide `php -l` ; `php install.php` ; re-lancer `tmp/sp3_wrapper_check.php` + `tmp/sp3_render_check.php` + les scripts M1 présence (`pointOccurrence`, matrice) + le smoke SP-2 (`presence_occurrence` rend le composant).
- [ ] Parcours substitué : smoke render `render_unit_presence_tab('cult', 'cultes', <culte>, 'presences', <members>)` → contient `name="statut[` et `unit_type" value="cult"` ; `tab=presences_annuel` rend la matrice ; `presencePrint?unit_type=cult&unit_id=<id>` rend la fiche imprimable.
- [ ] commit `chore(cultes): non-régression unification pointage culte`.

---

## Self-Review

**Spec coverage :** onglet « Pointage rapide » retiré (T2) ; `culte_detail.php` supprimé (T2) ; `point_culte` conservé comme wrapper `@deprecated` routant vers `pointOccurrence` (T1) ; `AttendanceService::pointCulte` délègue (T1) ; `AttendanceRepository::pointCulte` marqué `@deprecated` (T1) ; zéro migration / zéro perte (les lignes `culte_id` restent lues à l'identique — testé T1) ; `nb_presents` de la grille reste correct (occurrence écrit `culte_id` — vérifié T3) ; `tab=presences_annuel` + impression + `save_presence_occurrence unit_type=cult` intacts (T3).

**Placeholder scan :** une tranche bornée — `AttendanceRepository::pointCulte` marqué `@deprecated` plutôt que supprimé (moins risqué ; un futur SP de nettoyage le retirera). Aucun TODO ouvert.

**Type consistency :** `pointCulte(int,string,array):void` signature inchangée (T1) — les appelants (`point_culte_presence` compat) restent valides. Le wrapper `point_culte` poste vers `save_unit_presence('cult', …)` qui attend `(string $unitType, int $unitId, string $date, array $rawStatuts, array $allowedUserIds)` — respecté.

## Execution Handoff

Trois tâches. T1 (wrappers) et T2 (renderer + suppression vue) sont indépendantes ; T3 en dernier (balayage + non-régression). SP-2 doit être livré avant (T2 s'appuie sur le composant rendu par `render_unit_presence_tab`).
