# SP-1 — Agenda unifié — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Remplacer les onglets `Calendrier` et `Anniversaires` par un onglet unique `Agenda` : grille mensuelle (rendu serveur), sélection de jour en JS, panneau détail événements + anniversaires, 2 requêtes par mois, CRUD et fiche événement conservés.

**Architecture:** Backend neuf minimal (`EvenementRepository::overlapping`, `CalendrierService::agendaForMonth`/`agendaForDate`) ; `CalendrierController::agenda()` + route ; `Views/pages/agenda.php` + `assets/js/agenda.js` (vanilla) + `assets/css/agenda.css` ; nav retargetée ; anciennes routes redirigées (sauf `calendrier&evt=`).

**Tech Stack:** PHP 8 SSR, zéro dépendance, MySQL via `App\Core\Query`, JS vanilla, pas de build. Pas de PHPUnit — `php -l` + scripts d'assertion contre la base de dev + smoke-render.

**Spec:** `docs/superpowers/specs/2026-09-03-sp1-agenda-unifie-design.md`

## Global Constraints

- Zéro dépendance externe ; PSR-12 + `declare(strict_types=1)` ; couches strictes (SQL en repo, HTML en vue, `$_GET`/`$_POST` en contrôleur).
- CSS via `@import` dans `assets/css/app.css`, variables de `variables.css`, aucun style/script inline dans les vues (attribut `onchange`/`data-*` acceptés — motif projet). JS dans `assets/js/`.
- Ne rien casser : CRUD événements, fiche événement (`page=calendrier&evt=<id>`), pointage présence événement (`save_presence_occurrence unit_type=evenement`), anniversaires manuels + dérivés (`users.date_naissance`), `auth_can_manage_calendar()` / `auth_can_edit_evenement()`, `check_csrf()` global.
- `install.php` supprimable. Base de dev : MySQL `127.0.0.1:3306` root `la_belle_eglise_db`.
- Comptes démo : `admin@labelleeglise.ga`/`LBEGF`, `berger.eric.bongo@labelleeglise.ga`/`BergerEB1`.

## File Structure

| Fichier | Action |
|---|---|
| `app/Repositories/EvenementRepository.php` | Modifier : `overlapping(string $from, string $to): array` |
| `app/Services/CalendrierService.php` | Modifier : `agendaForMonth(int,int)`, `agendaForDate(string)` |
| `app/Controllers/CalendrierController.php` | Modifier : `agenda()` (neuf) ; `evenements()`/`anniversaires()` → redirections |
| `Routes/web.php` | Modifier : `Router::get('agenda', …, 'agenda')` |
| `Config/constants.php` | Modifier : `SECTION_LABELS/ICONS` `agenda` ; `NAV_ORDER` (−calendrier −anniversaires +agenda) |
| `Views/layouts/layout.php` | Modifier : bloc hoist → `['agenda']` ; include `agenda.js` si `$page==='agenda'` |
| `app/Controllers/ActionsController.php` | Modifier : redirections `save_evenement`/`delete_evenement`/`save_anniversaire`/`delete_anniversaire` → `page=agenda` |
| `Views/pages/agenda.php` | Créer |
| `assets/js/agenda.js` | Créer |
| `assets/css/agenda.css` | Créer + `@import` dans `app.css` |
| `Views/pages/anniversaires.php` | Supprimer (plus aucun chemin ne la rend) |

---

### Task 1: Backend — `overlapping` + `agendaForMonth` + `agendaForDate`

**Files:** `app/Repositories/EvenementRepository.php`, `app/Services/CalendrierService.php` ; test `tmp/sp1_backend_check.php`

**Interfaces produced:**
- `EvenementRepository::overlapping(string $from, string $to): array` — événements avec `date_debut <= :to AND (date_fin >= :from OR date_fin IS NULL)`, colonnes jointes `resp_prenom`/`resp_nom`, `ORDER BY date_debut ASC`, params `[$to, $from]`.
- `CalendrierService::agendaForMonth(int $year, int $month): array` → `['ym' => 'YYYY-MM', 'byDate' => [Y-m-d => ['events' => [...], 'birthdays' => [...]]], 'counts' => [Y-m-d => ['events' => int, 'birthdays' => int]]]`. Événement normalisé : `['id','nom','lieu','resp' (string|null),'heure_debut' (?'HH:MM'),'heure_fin' (?'HH:MM'),'date_debut','date_fin','is_multi_day' (bool)]`. Multi-jours : indexé sur chaque `Y-m-d` de `max(DATE(date_debut), $first)` à `min(DATE(date_fin ?? date_debut), $last)`.
- `CalendrierService::agendaForDate(string $date): array` → `['events' => [...], 'birthdays' => [...]]` (même normalisation ; via `overlapping("$date 00:00:00", "$date 23:59:59")` + `birthdays()` filtré `mois == (int) substr($date,5,2) && jour == (int) substr($date,8,2)`).

- [ ] **Step 1: Écrire l'assertion qui échoue** — `tmp/sp1_backend_check.php` :

```php
<?php
declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query;
use App\Repositories\EvenementRepository;
use App\Services\CalendrierService;

$er = new EvenementRepository();
$idMulti = $er->create('SP1 multi', '2029-06-10 09:00:00', '2029-06-12 17:00:00', 'X', null, null);
$idBefore = $er->create('SP1 chevauche', '2029-05-28 10:00:00', '2029-06-03 10:00:00', null, null, null);
$idAfter = $er->create('SP1 apres', '2029-07-01 10:00:00', '2029-07-02 10:00:00', null, null, null);

$ov = $er->overlapping('2029-06-30 23:59:59', '2029-06-01 00:00:00');
$ids = array_column($ov, 'id');
assert(in_array($idMulti, $ids) && in_array($idBefore, $ids), 'overlapping doit inclure multi + chevauchant');
assert(!in_array($idAfter, $ids), 'overlapping ne doit pas inclure juillet');

$svc = new CalendrierService();
$m = $svc->agendaForMonth(2029, 6);
foreach (['2029-06-10','2029-06-11','2029-06-12'] as $d) {
    assert(count(array_filter($m['byDate'][$d]['events'] ?? [], fn($e) => (int)$e['id'] === $idMulti)) === 1, "multi absent le $d");
    assert(($m['counts'][$d]['events'] ?? 0) >= 1, "counts events le $d");
}
assert(!isset($m['byDate']['2029-06-13']) || count(array_filter($m['byDate']['2029-06-13']['events'], fn($e)=>(int)$e['id']===$idMulti)) === 0, 'multi ne doit pas déborder au 13');

$d1 = $svc->agendaForDate('2029-06-11');
assert(count(array_filter($d1['events'], fn($e)=>(int)$e['id']===$idMulti)) === 1, 'agendaForDate KO');
assert(array_key_exists('birthdays', $d1) && array_key_exists('events', $d1), 'agendaForDate structure KO');

// février
assert(count($svc->agendaForMonth(2027, 2)['byDate']) >= 0, 'fev ok'); // pas de crash
Query::run('DELETE FROM evenements WHERE id IN (?,?,?)', [$idMulti,$idBefore,$idAfter]);
echo "OK sp1 backend\n";
```

- [ ] **Step 2: Lancer, vérifier l'échec** — `php -d zend.assertions=1 -d assert.exception=1 tmp/sp1_backend_check.php` → `Error: Call to undefined method …::overlapping()`.
- [ ] **Step 3: `EvenementRepository::overlapping`** — ajouter la méthode (voir Interfaces). Réutiliser la constante `self::SELECT` si présente, sinon la clause jointure de `all()`.
- [ ] **Step 4: `CalendrierService::agendaForMonth` + `agendaForDate`** — voir Interfaces. `agendaForMonth` : `$first = sprintf('%04d-%02d-01', $y, $m)`, `$last = date('Y-m-t', strtotime($first))`, `overlapping("$last 23:59:59", "$first 00:00:00")`, boucle d'indexation multi-jours ; anniversaires via `$this->birthdays()` filtré `mois === $m`, indexé `sprintf('%04d-%02d-%02d', $y, $m, $b['jour'])`. `heure_debut` = `date('H:i', strtotime($e['date_debut']))` si l'heure ≠ `00:00`, sinon `null` (idem `date_fin`).
- [ ] **Step 5: GREEN** — relancer l'assertion → `OK sp1 backend`.
- [ ] **Step 6: Lint + commit** — `php -l` sur les 2 fichiers ; commit `feat(agenda): overlapping + agendaForMonth/agendaForDate`.

---

### Task 2: Contrôleur `agenda()` + route + redirections

**Files:** `app/Controllers/CalendrierController.php`, `Routes/web.php` ; test `tmp/sp1_ctrl_check.php`

**Interfaces produced:**
- `CalendrierController::agenda(): void` — guard `current_user()` ; `$ym` (`^\d{4}-\d{2}$`, défaut mois courant) ; `$date` (`Y-m-d` dans `$ym`, défaut aujourd'hui∈mois sinon 1er) ; construit `$weeks` = tableau de 6 semaines × 7 jours `['date'=>'Y-m-d','day'=>int,'adjacent'=>bool]` (semaine lundi) ; `render_page(SECTION_LABELS['agenda'], view('pages/agenda', [... voir spec §4]))`.
- `evenements()` : `?evt=` → `evenementFiche()` sinon `redirect(index.php, ['page'=>'agenda'])`.
- `anniversaires()` : `redirect(index.php, ['page'=>'agenda'])`.
- Route `Router::get('agenda', CalendrierController::class, 'agenda');`.

- [ ] **Step 1: Assertion qui échoue** — `tmp/sp1_ctrl_check.php` : `is_file` du contrôleur inchangé ; `str_contains(file_get_contents('app/Controllers/CalendrierController.php'), 'function agenda(')` ; `str_contains(file_get_contents('Routes/web.php'), "'agenda'")` ; `str_contains(..., "redirect('index.php', ['page' => 'agenda'])")` pour `anniversaires()`. + smoke : `require Bootstrap/init.php` puis appeler `(new ReflectionMethod(App\Controllers\CalendrierController::class,'agenda'))` existe.
- [ ] **Step 2: Lancer → FAIL** (`function agenda(` absent).
- [ ] **Step 3: Implémenter `agenda()`** — modeler sur `RapportController::form()` (M5) pour la structure guard + `Request::get` + `render_page(SECTION_LABELS[...], view(...))`. Calcul `$weeks` : `$firstOfMonth = "$ym-01"` ; `$startDow = (int) date('N', strtotime($firstOfMonth))` (1=lundi) ; la grille commence à `$firstOfMonth - ($startDow-1) jours` ; 42 cases ; `adjacent = substr($d,0,7) !== $ym`.
- [ ] **Step 4: Redirections `evenements()` / `anniversaires()`** — voir Interfaces.
- [ ] **Step 5: Route** — ajouter la ligne `agenda` près des routes de page GET.
- [ ] **Step 6: GREEN + lint + commit** — `feat(agenda): route + controller agenda(), redirections des anciennes routes`.

---

### Task 3: Vue `agenda.php` + CSS

**Files:** `Views/pages/agenda.php` (créer), `assets/css/agenda.css` (créer) + `@import` dans `assets/css/app.css` ; test `tmp/sp1_view_check.php`

- [ ] **Step 1: Assertion qui échoue** — fichiers existent ; `@import url('agenda.css')` dans `app.css` ; smoke `view('pages/agenda', [<données minimales>])` contient : `id="agenda-data"`, `data-date=`, la légende, et (si `canManage`) `value="save_evenement"` + `value="save_anniversaire"`.
- [ ] **Step 2: Lancer → FAIL**.
- [ ] **Step 3: Créer `agenda.php`** — d'après spec §4 « Vue ». Points clés :
  - Barre : `<form method="get">` hidden `page=agenda`, `<input type="date" name="date">` `onchange` submit ; le contrôleur recalculera `ym` depuis `date`. Bouton `Aujourd'hui` = `<a href="?page=agenda">`.
  - En-tête : `‹` = `<a aria-label="Mois précédent" href="?page=agenda&ym=<prev>">`, titre `<?= h($monthsFr[$monthNo-1]) ?> <?= $year ?>`, `›` idem.
  - Grille : `<div class="agenda-grid" role="grid">` ; 7 `<div class="agenda-dow">Lun…</div>` ; 42 cellules `<button type="button" class="agenda-day <?= $isToday?'is-today':'' ?> <?= $isSel?'is-selected':'' ?> <?= $adj?'is-adjacent':'' ?>" data-date="<?= $d ?>" aria-label="<?= h(...) ?>"><span class="agenda-num"><?= $dayNum ?></span><span class="agenda-dots"><?php if ($cE): ?><span class="dot dot-event"></span><?php endif; if ($cB): ?><span class="dot dot-birthday"></span><?php endif; ?></span></button>`. En no-JS, envelopper d'un `<a href="?page=agenda&ym=<?= h($ym) ?>&date=<?= $d ?>">` ou faire du bouton un `<button formaction>` — **choix retenu** : `<a>` stylé comme la cellule (le JS fait `e.preventDefault()`), plus simple et accessible.
  - Panneau `#agenda-day` : rendu serveur du `$date` courant depuis `$dayDetail` (fallback), remplacé par le JS au clic. Sections **ÉVÉNEMENTS** / **ANNIVERSAIRES** avec états vides séparés + global.
  - `<script type="application/json" id="agenda-data"><?= json_encode($month['byDate'], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>`.
  - Formulaires `canManage` : copier les blocs `save_evenement` / `save_anniversaire` de `Views/pages/calendrier.php` / `Views/pages/anniversaires.php` (mêmes champs, mêmes actions, mêmes clés `errors`/`old`). Le contrôleur passe `responsables`, `monthsFr`, `csrf`, `errors=[]`, `old=[]`.
- [ ] **Step 4: Créer `agenda.css`** — grille responsive (`display:grid;grid-template-columns:repeat(7,1fr)`), styles `.is-today` (cercle : `border-radius:50%` sur `.agenda-num`), `.is-selected` (fond `var(--primary-soft)`), `.is-adjacent` (`opacity:.4`), `.dot` (6px, `dot-event`→`var(--primary)`, `dot-birthday`→`var(--warning)`). Mobile : cellules `min-height:44px`. `@import` après la dernière ligne des composants dans `app.css`.
- [ ] **Step 5: GREEN + lint + smoke render** (`php -r` `view('pages/agenda', …)`). Commit `feat(agenda): vue agenda.php + agenda.css`.

---

### Task 4: `assets/js/agenda.js` (vanilla)

**Files:** `assets/js/agenda.js` (créer), `Views/layouts/layout.php` (include conditionnel) ; test : lint statique + grep

- [ ] **Step 1: Assertion** — `is_file('assets/js/agenda.js')` ; `str_contains(layout.php, 'agenda.js')` ; le JS contient `JSON.parse`, `getElementById('agenda-data')`, `sessionStorage`, `history.replaceState`, aucun `import ` / `require(` / URL CDN.
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: Écrire `agenda.js`** — IIFE, `DOMContentLoaded` :
  - `const data = JSON.parse(document.getElementById('agenda-data').textContent || '{}')`.
  - Restauration scroll : `const s = sessionStorage.getItem('agenda:scroll'); if (s) { window.scrollTo(0, parseInt(s,10)||0); sessionStorage.removeItem('agenda:scroll'); }`. `window.addEventListener('beforeunload', () => sessionStorage.setItem('agenda:scroll', String(window.scrollY)))`.
  - Délégation clic sur `.agenda-grid a[data-date]` (ou `.agenda-day`) : `e.preventDefault()`, `selectDay(a.dataset.date)`.
  - `selectDay(date)` : retire `.is-selected` de l'ancien, l'ajoute au nouveau, `renderDay(date)`, `history.replaceState(null, '', '?page=agenda&ym=' + date.slice(0,7) + '&date=' + date)`.
  - `renderDay(date)` : construit le HTML du panneau `#agenda-day` depuis `data[date]` (events : `heure_debut ? heure_debut+' — '+nom : 'Événement — '+nom`, + lieu, + lien `?page=calendrier&evt=ID` ; birthdays : `🎂 nom (+ ' — '+age+' ans' si age)`), avec les états vides par section + global. Échappement : petite fonction `esc(s)` (`&<>"'`).
- [ ] **Step 4: Include** — `layout.php` : `<?php if ($page === 'agenda'): ?><script src="<?= h(url('assets/js/agenda.js')) ?>" defer></script><?php endif; ?>` près de `app.js`.
- [ ] **Step 5: GREEN + lint (`php -l layout.php`) + commit** `feat(agenda): agenda.js (sélection de jour, restauration scroll)`.

---

### Task 5: Nav + retarget des redirections d'actions

**Files:** `Config/constants.php`, `Views/layouts/layout.php`, `app/Controllers/ActionsController.php` ; test `tmp/sp1_nav_check.php`

- [ ] **Step 1: Assertion** — `SECTION_LABELS['agenda']==='Agenda'` ; `in_array('agenda', NAV_ORDER, true)` ; `!in_array('calendrier', NAV_ORDER, true) && !in_array('anniversaires', NAV_ORDER, true)` ; `str_contains(layout.php, "['agenda']")` dans le bloc hoist ; les 4 cas `save_evenement`/`delete_evenement`/`save_anniversaire`/`delete_anniversaire` de `ActionsController` redirigent vers `['page' => 'agenda'` (grep : 4 occurrences `'page' => 'agenda'` dans le fichier).
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: Constantes** — `SECTION_LABELS`/`SECTION_ICONS` : ajouter `agenda`, garder `calendrier`/`anniversaires` (fil d'Ariane). `NAV_ORDER` : remplacer les 2 clés par `'agenda'` (même position).
- [ ] **Step 4: Hoist** — `layout.php` : `foreach (['calendrier','anniversaires'] …)` → `foreach (['agenda'] …)` ; garde `auth_can_manage_calendar()` inchangée.
- [ ] **Step 5: Redirections d'actions** — dans `ActionsController` : `save_evenement` (succès + re-render sur erreur : le re-render garde `pages/calendrier` en `mode='list'` — **le remplacer** par un `redirect(page=agenda)` sur erreur aussi, OU re-render `pages/agenda` avec `errors`/`old` — trancher : re-render `pages/agenda` pour préserver la saisie, en fournissant les mêmes clés que `CalendrierController::agenda()` — mois courant). `delete_evenement` → `redirect(page=agenda)`. `save_anniversaire` (idem re-render → `pages/agenda`). `delete_anniversaire` → `redirect(page=agenda)`. `save_presence_occurrence` avec `unit_type=evenement` : sa redirection `page=calendrier&evt=` reste valable (la fiche événement vit toujours là) — **ne pas toucher**.
- [ ] **Step 6: GREEN + lint + commit** `feat(agenda): navigation Agenda unique + redirections des actions calendrier/anniversaires`.

---

### Task 6: Nettoyage + non-régression

**Files:** supprimer `Views/pages/anniversaires.php` ; balayage

- [ ] **Step 1:** `grep -rn "pages/anniversaires\|'anniversaires'" app/ Views/ Routes/` — confirmer qu'aucun `view('pages/anniversaires'…)` ne subsiste (seule la route redirige). Si propre → `git rm Views/pages/anniversaires.php`.
- [ ] **Step 2:** `grep -rn "page=calendrier\|'calendrier'" Views/ app/` — vérifier que tous les liens `page=calendrier` restants portent `&evt=` (fiche) ; sinon les repointer vers `page=agenda`.
- [ ] **Step 3:** Repo-wide `php -l` ; `php install.php` (re-seed) ; parcours manuel substitué : `php -S 127.0.0.1:8000`, se connecter admin, ouvrir `?page=agenda` → mois courant, aujourd'hui entouré ; cliquer un jour avec un événement de démo → panneau détail ; `‹`/`›` changent de mois ; `?page=calendrier` redirige ; `?page=calendrier&evt=<id>` ouvre la fiche + le bloc pointage ; créer/éditer/supprimer un événement et un anniversaire manuel depuis l'Agenda.
- [ ] **Step 4: commit** `chore(agenda): suppression de la vue anniversaires devenue morte + non-régression`.

---

## Self-Review

**Spec coverage :** grille mensuelle 6×7 lundi→dimanche (T2/T3) ; aujourd'hui/sélection/indicateurs 3 styles distincts (T3 CSS) ; sélection JS sans reload + fallback lien (T3/T4) ; multi-jours indexés sur chaque date (T1) ; 2 requêtes/mois (T1) ; date-picker « aller à une date » (T3) ; nav ‹/›/Aujourd'hui (T2/T3) ; panneau détail sections séparées + états vides (T3/T4) ; anniversaires membres + manuels via `birthdays()` (T1) ; âge respecté (T1, `birthdays()` inchangé) ; clic événement → `page=calendrier&evt=` (T3/T4) ; CRUD + permissions conservés (T5, actions inchangées sauf redirection) ; redirections anciennes routes (T2/T5) ; responsive (T3 CSS) ; scroll smooth + sessionStorage (T3/T4) ; sécurité `json_encode` durci (T3). Fév/bissextile/changement d'année couverts par les assertions T1 + la construction `$weeks` T2.

**Placeholder scan :** deux points de tranche explicites et bornés — T3 Step 3 (cellule `<a>` vs `<button>` → `<a>` retenu) et T5 Step 5 (re-render d'erreur → `pages/agenda` retenu). Aucun TODO ouvert.

**Type consistency :** `agendaForMonth` → `{ym, byDate, counts}` consommé par le contrôleur (T2), la vue (T3, `#agenda-data` = `byDate`), le JS (T4). `agendaForDate` → `{events, birthdays}` consommé par le rendu serveur du panneau (T3) et testé (T1). Événement normalisé `{id,nom,lieu,resp,heure_debut,heure_fin,date_debut,date_fin,is_multi_day}` identique repo→service→vue→JS.

## Execution Handoff

Six tâches séquentielles (T1→T6). T1 pur backend testable en isolation ; T4 (JS) dépend du markup de T3 ; T6 en dernier (nettoyage + parcours manuel substitué).
