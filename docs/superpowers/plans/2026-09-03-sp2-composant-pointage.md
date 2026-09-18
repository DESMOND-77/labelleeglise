# SP-2 - Composant de pointage unifié & enrichi - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development ou superpowers:executing-plans. Steps `- [ ]`.

**Goal:** Un partial de pointage unique (`attendance_pointage.php`) + `attendance.js` + styles, utilisé par l'onglet Présences (bacenta/basonta/culte) et la fiche événement. Ajoute : 4ᵉ état « Non renseigné » (= pas de ligne), boutons segmentés, recherche, filtres, compteurs live, « Tout présent »/« Réinitialiser » (locaux). Zéro migration, moteur inchangé.

**Architecture:** `AttendanceService::occurrenceSummary()` (nouveau, sans requête supplémentaire) ; `Views/pages/partials/attendance_pointage.php` (nouveau) ; `assets/js/attendance.js` (vanilla, auto-gardé, `defer`) ; styles dans `assets/css/presences.css` ; `presence_occurrence.php` + bloc fiche de `calendrier.php` + `render_unit_presence_tab()` + `CalendrierController::evenementFiche()` adaptés pour inclure le partial. `save_presence_occurrence` / `pointOccurrence` **inchangés**.

**Tech Stack:** PHP 8 SSR, zéro dep, MySQL via `App\Core\Query`, JS vanilla sans build. `php -l` + assertions + smoke-render.

**Spec:** `docs/superpowers/specs/2026-09-03-sp2-composant-pointage-design.md`

## Global Constraints

- Zéro dep ; PSR-12 + `strict_types` ; couches strictes.
- CSS via `@import` (déjà fait pour `presences.css`), variables de `variables.css`, aucun style/script inline (attributs `onchange`/`data-*` ok). JS dans `assets/js/`, chargé `defer`, auto-gardé.
- Ne pas casser : `save_presence_occurrence` (CSRF global, `can_manage_entity` / `auth_can_edit_evenement`, re-dérivation population, upsert transactionnel), `pointOccurrence`, onglet Présences bacenta/basonta, fiche événement + pointage, matrice annuelle.
- Progressif : le form (radios + submit) doit fonctionner sans JS ; recherche/filtres/compteurs/tout-présent = enrichissement JS.
- Base dev MySQL `127.0.0.1:3306` root `la_belle_eglise_db`. Comptes : `admin@labelleeglise.ga`/`LBEGF`, `resp.bacenta.sion@labelleeglise.ga`/`ESKLna`.

## File Structure

| Fichier | Action |
|---|---|
| `app/Services/AttendanceService.php` | Modifier : `occurrenceSummary(...)` ; option : `occurrenceGrid` renvoie aussi le summary (voir T1) |
| `app/Repositories/AttendanceRepository.php` | (lecture seule - `occurrenceStatuts` déjà présent) |
| `Views/pages/partials/attendance_pointage.php` | Créer |
| `assets/js/attendance.js` | Créer |
| `assets/css/presences.css` | Modifier : styles du composant |
| `Views/pages/presence_occurrence.php` | Modifier : inclure le partial |
| `Views/pages/calendrier.php` | Modifier : bloc fiche `$canPointe` → inclure le partial |
| `app/Compat/sections.php` | Modifier : `render_unit_presence_tab()` calcule `$summary`, passe le contrat |
| `app/Controllers/CalendrierController.php` | Modifier : `evenementFiche()` calcule `$summary`, passe le contrat |
| `Views/layouts/layout.php` | Modifier : `<script src="assets/js/attendance.js" defer>` |

---

### Task 1: `AttendanceService::occurrenceSummary` (+ factorisation grid/summary)

**Files:** `app/Services/AttendanceService.php` ; test `tmp/sp2_summary_check.php`

**Interfaces produced:**
- `AttendanceService::occurrenceSummary(string $unitType, int $unitId, string $date, int $totalMembers): array` → `['present'=>int,'absent'=>int,'excuse'=>int,'non_renseigne'=>int,'total'=>int]`. Implémentation : `$s = $this->attendance->occurrenceStatuts($unitType, $unitId, $date)` ; `$present = count(array_filter($s, fn($v)=>$v==='present'))` etc. ; `non_renseigne = max(0, $totalMembers - count($s))`.
- (facultatif) `occurrenceGrid(...)` - laisser inchangé ; le contrôleur/compat appellera `occurrenceStatuts` une seule fois s'il veut éviter la double lecture, mais `occurrenceStatuts` est un `SELECT` léger (index `uniq_presence`) : la double lecture est acceptable. **Décision : ne pas modifier `occurrenceGrid`.**

- [ ] **Step 1: Assertion qui échoue** - `tmp/sp2_summary_check.php` :
```php
<?php declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query; use App\Services\AttendanceService;
$bac = (int) Query::value('SELECT id FROM bacentas ORDER BY id LIMIT 1');
$us = Query::all('SELECT id FROM users ORDER BY id LIMIT 3');
[$u1,$u2,$u3] = array_map(fn($r)=>(int)$r['id'],$us);
$svc = new AttendanceService();
$svc->pointOccurrence('bacenta',$bac,'2019-01-06',[$u1=>'present',$u2=>'present',$u3=>'absent'],[$u1,$u2,$u3]);
$sum = $svc->occurrenceSummary('bacenta',$bac,'2019-01-06',5);
assert($sum === ['present'=>2,'absent'=>1,'excuse'=>0,'non_renseigne'=>3,'total'=>5], 'summary KO: '.json_encode($sum));
$sum0 = $svc->occurrenceSummary('bacenta',$bac,'2018-01-06',5);
assert($sum0 === ['present'=>0,'absent'=>0,'excuse'=>0,'non_renseigne'=>5,'total'=>5], 'summary vide KO');
Query::run("DELETE FROM presences WHERE bacenta_id=? AND date_presence='2019-01-06'",[$bac]);
echo "OK sp2 summary\n";
```
- [ ] **Step 2: FAIL** (`undefined method occurrenceSummary`).
- [ ] **Step 3: Implémenter** `occurrenceSummary` dans `AttendanceService` (voir Interfaces).
- [ ] **Step 4: GREEN + `php -l` + commit** `feat(presences): AttendanceService::occurrenceSummary`.

---

### Task 2: Partial `attendance_pointage.php`

**Files:** `Views/pages/partials/attendance_pointage.php` (créer) ; test `tmp/sp2_partial_check.php`

**Contrat de variables** (spec §4) : `$unitType, $unitId, $unitLabel, $date, $dateBarUrl (array), $grid, $summary, $statuts, $joursHint, $csrf, $canPointe`.

- [ ] **Step 1: Assertion** - smoke render deux fois :
  - `$canPointe=true` : la sortie contient `name="statut[`, 4 `<input type="radio"` par membre dont un `value=""`, `value="save_presence_occurrence"`, les `data-count="present"`…, `class="attendance-toolbar` et `js-only`.
  - `$canPointe=false` : pas de `name="statut[`, pas de `value="save_presence_occurrence"`, présence de `presence_badge`/badge.
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: Créer le partial** - d'après spec §4 « Markup ». Détails :
  - racine `<div class="attendance-pointage" data-total="<?= (int)$summary['total'] ?>">`.
  - barre date : `<form method="get" action="index.php" class="presence-datebar"><?php foreach ($dateBarUrl as $k=>$v): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>"><?php endforeach; ?><label>Date</label><input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()"><?php if ($joursHint): ?><span class="presence-hint">Jours habituels : <?= h($joursHint) ?></span><?php endif; ?></form>`.
  - toolbar `js-only is-hidden` : `<input type="search" class="attendance-search" aria-label="Rechercher un membre" placeholder="Rechercher un membre…">` ; filtres = `<button type="button" class="attendance-filter is-active" data-filter="all">Tous</button>` + `present/absent/excuse/non_renseigne` ; actions = `<button type="button" class="attendance-all-present">✓ Tout présent</button> <button type="button" class="attendance-reset">Réinitialiser</button>`.
  - compteurs : `<div class="attendance-counts"><span><b data-count="total"><?= (int)$summary['total'] ?></b> membres</span> · <span class="c-present"><b data-count="present"><?= (int)$summary['present'] ?></b> présents</span> · … absent, excuse, <span class="c-none"><b data-count="non_renseigne"><?= (int)$summary['non_renseigne'] ?></b> non renseignés</span></div>`.
  - si `$canPointe` : `<form method="post" action="index.php"><input type="hidden" name="action" value="save_presence_occurrence"><?= $csrf ?><input type="hidden" name="unit_type" value="<?= h($unitType) ?>"><input type="hidden" name="unit_id" value="<?= (int)$unitId ?>"><input type="hidden" name="date" value="<?= h($date) ?>">` … liste … `<div class="modal-actions"><button type="submit" class="btn btn-primary" <?= $grid?'':'disabled' ?>>Enregistrer le pointage</button></div></form>`.
  - liste : `<div class="attendance-list">` puis par `$line` : `<div class="attendance-row" data-name="<?= h(mb_strtolower(full_name($line['user']))) ?>" data-status="<?= h($line['statut'] ?: 'non_renseigne') ?>"><span class="attendance-name"><?= h(full_name($line['user'])) ?></span>` + soit `.segmented` (4 `<label><input type="radio" name="statut[<?= (int)$line['user']['id'] ?>]" value="…" <?= checked ?>><span>…</span></label>`, la 4ᵉ `value=""`) si `$canPointe`, soit `<span><?= presence_badge($line['statut']) ?></span>` sinon. `</div>`.
  - empty state si `!$grid`.
- [ ] **Step 4: GREEN + `php -l` + commit** `feat(presences): partial attendance_pointage (boutons segmentés, 4 états, toolbar)`.

---

### Task 3: `assets/js/attendance.js`

**Files:** `assets/js/attendance.js` (créer), `Views/layouts/layout.php` (include) ; test : grep + lint

- [ ] **Step 1: Assertion** - `is_file` ; `str_contains(layout.php,'attendance.js')` ; le JS contient `querySelector('.attendance-pointage')`, `data-count`, `normalize(`, `addEventListener`, et **ne contient pas** `import `, `require(`, `http`.
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: Écrire `attendance.js`** - IIFE + `DOMContentLoaded` :
  - `const root = document.querySelector('.attendance-pointage'); if (!root) return;`
  - `root.querySelectorAll('.js-only').forEach(el => el.classList.remove('is-hidden'));`
  - `const rows = [...root.querySelectorAll('.attendance-row')];`
  - `const norm = s => (s||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase();`
  - `function statusOf(row){ const r = row.querySelector('input[type=radio]:checked'); return r ? (r.value || 'non_renseigne') : 'non_renseigne'; }`
  - `function recount(){ const c={present:0,absent:0,excuse:0,non_renseigne:0}; rows.forEach(r=>{ const s=statusOf(r); r.dataset.status=s; c[s]++; }); root.querySelectorAll('[data-count]').forEach(el=>{ const k=el.dataset.count; el.textContent = k==='total' ? rows.length : c[k]; }); }`
  - recherche : `input` sur `.attendance-search` → `applyFilters()`.
  - filtres : clic `.attendance-filter` → `activeFilter = btn.dataset.filter`, toggle `.is-active`, `applyFilters()`.
  - `applyFilters()` : pour chaque row → visible si `(activeFilter==='all' || row.dataset.status===activeFilter) && norm(row.dataset.name).includes(norm(searchValue))` ; `row.hidden = !visible`.
  - `.attendance-all-present` → chaque row : coche `input[value="present"]` ; `recount()`. `.attendance-reset` → coche `input[value=""]` ; `recount()`.
  - `root.addEventListener('change', e => { if (e.target.matches('input[type=radio]')) recount(); })`.
  - init : `recount()`.
- [ ] **Step 4: Include** - `layout.php` : `<script src="<?= h(url('assets/js/attendance.js')) ?>" defer></script>` près de `app.js` (toujours ; auto-gardé).
- [ ] **Step 5: GREEN + `php -l layout.php` + commit** `feat(presences): attendance.js (recherche, filtres, compteurs, tout présent)`.

---

### Task 4: Styles du composant dans `presences.css`

**Files:** `assets/css/presences.css` ; test : grep

- [ ] **Step 1: Assertion** - `presences.css` contient `.segmented`, `.attendance-toolbar`, `.attendance-counts`, `.attendance-row`, `.is-hidden`, et **aucune couleur hex** (`!preg_match('/#[0-9a-fA-F]{3,6}\b/', $css)`), uniquement `var(--…)`.
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: Ajouter les styles** :
  - `.attendance-toolbar.is-hidden, .attendance-counts.is-hidden { display:none; }` (le `js-only` porte `is-hidden` au rendu ; le JS le retire - **inverser** : la toolbar a `class="attendance-toolbar js-only is-hidden"`, `.is-hidden{display:none}` global ; sans JS elle reste masquée).
  - `.segmented { display:inline-flex; border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }`
  - `.segmented label { position:relative; }` ; `.segmented input { position:absolute; opacity:0; }` ; `.segmented span { display:block; padding:8px 10px; font-size:13px; cursor:pointer; min-height:40px; line-height:24px; }`
  - `.segmented input:checked + span` : `present`→`var(--success-soft)`/`var(--success)`, `absent`→`var(--danger-soft)`/`var(--danger)`, `excuse`→`var(--warning-soft)`/`var(--warning)`, `value=""`→`var(--bg)`/`var(--text-muted)`. (Cibler par ordre : `.segmented label:nth-child(1) input:checked + span { … }` etc., ou ajouter une classe par label.)
  - `.segmented input:focus-visible + span { outline:2px solid var(--primary); outline-offset:-2px; }`
  - `.attendance-row { display:flex; align-items:center; justify-content:space-between; gap:var(--space-3); padding:var(--space-2) 0; border-bottom:1px solid var(--border); flex-wrap:wrap; }`
  - `.attendance-counts { margin:var(--space-3) 0; color:var(--text-soft); font-size:13px; }`
  - `.attendance-filter { … } .attendance-filter.is-active { background:var(--primary-soft); border-color:var(--primary); }`
  - mobile `@media (max-width:640px)` : `.segmented { width:100%; } .segmented label { flex:1; } .segmented span { text-align:center; }`
- [ ] **Step 4: GREEN + commit** `feat(presences): styles du composant de pointage segmenté`.

---

### Task 5: Brancher le partial dans les 2 vues + les 2 points d'appel

**Files:** `Views/pages/presence_occurrence.php`, `Views/pages/calendrier.php`, `app/Compat/sections.php`, `app/Controllers/CalendrierController.php` ; test `tmp/sp2_wire_check.php` + smoke

- [ ] **Step 1: Assertion** - `presence_occurrence.php` et le bloc fiche de `calendrier.php` contiennent `partials/attendance_pointage` (via `view('pages/partials/attendance_pointage', …)` ou `include`) ; `render_unit_presence_tab` dans `sections.php` construit `$summary` (grep `occurrenceSummary`) ; `CalendrierController::evenementFiche` idem. Smoke render de `presence_occurrence.php` avec données minimales → contient `name="statut[` et `data-count`.
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: `render_unit_presence_tab()`** (`sections.php`) - là où il rend `view('pages/presence_occurrence', [...])` : ajouter `'summary' => attendance_service()->occurrenceSummary($unitType, $unitId, $date, count($members))` aux données passées ; laisser `presence_occurrence.php` transmettre au partial (contrat §4). `$dateBarUrl` = `['page'=>$pageKey, 'id'=>$unitId, 'tab'=>'presences']`. `$unitLabel` = `$unit['nom']`. `$canPointe` = `true` (l'accès est déjà gardé en amont par `can_manage_entity`).
- [ ] **Step 4: `presence_occurrence.php`** - remplacer le corps (barre date + tableau `<select>`) par `<?= view('pages/partials/attendance_pointage', ['unitType'=>$unitType,'unitId'=>$unit['id'],'unitLabel'=>$unit['nom'],'date'=>$date,'dateBarUrl'=>['page'=>$pageKey,'id'=>$unit['id'],'tab'=>'presences'],'grid'=>$grid,'summary'=>$summary,'statuts'=>$statuts,'joursHint'=>$joursHint,'csrf'=>$csrf,'canPointe'=>true]) ?>`. Conserver le lien « Matrice annuelle » (`$matrixUrl`) hors partial.
- [ ] **Step 5: `CalendrierController::evenementFiche()`** - calculer `$members` (déjà fait), `$grid` (déjà via `unit_presence_grid('evenement', …)`), ajouter `$summary = attendance_service()->occurrenceSummary('evenement', $id, $date, count($members))` ; passer à la vue.
- [ ] **Step 6: `calendrier.php`** (bloc `mode==='fiche'`, `if (!empty($canPointe))`) - remplacer le tableau `<select>` par `<?= view('pages/partials/attendance_pointage', ['unitType'=>'evenement','unitId'=>$e['id'],'unitLabel'=>$e['nom'],'date'=>$presenceDate,'dateBarUrl'=>['page'=>'calendrier','evt'=>$e['id']],'grid'=>$presenceGrid,'summary'=>$presenceSummary,'statuts'=>$presenceStatuts,'joursHint'=>'','csrf'=>$csrf,'canPointe'=>true]) ?>`.
- [ ] **Step 7: GREEN + repo-wide `php -l` + smoke renders + commit** `feat(presences): les 4 occurrences (bacenta/basonta/culte/événement) utilisent le composant unique`.

*(Note : l'onglet culte passe déjà par `render_unit_presence_tab` avec `unitType='cult'` - il hérite automatiquement du composant. SP-3 traitera la suppression de l'onglet « Pointage rapide » hérité.)*

---

### Task 6: Non-régression + parcours manuel substitué

- [ ] `grep -rn "statut\[\|<select name=\"statut" Views/` - plus aucun `<select name="statut[`.
- [ ] `tmp/sp2_summary_check.php` + tous les scripts M1 présence encore verts (`pointOccurrence` inchangé).
- [ ] Repo-wide `php -l` ; `php install.php`.
- [ ] Parcours substitué (lint + smoke, pas de navigateur) : smoke render `pages/presence_occurrence` (bacenta) + `pages/calendrier` mode fiche + `pages/partials/attendance_pointage` `$canPointe` true/false ; grep confirmant que `save_presence_occurrence` reçoit toujours `unit_type`/`unit_id`/`date`/`statut[]` depuis le partial.
- [ ] commit `chore(presences): non-régression composant unifié`.

---

## Self-Review

**Spec coverage :** composant unique (T2) utilisé par bacenta/basonta/culte/événement (T5) ; 4ᵉ état « non renseigné » = radio `value=""` = pas de ligne (T2 + moteur inchangé, testé T1 non-régression) ; boutons segmentés fonctionnant sans JS (T2/T4) ; recherche + filtres + compteurs + « Tout présent »/« Réinitialiser » en JS progressif (T3) ; compteurs valeur serveur initiale (T1 `occurrenceSummary` + T2) ; sécurité inchangée (aucune modif de `save_presence_occurrence`/`pointOccurrence` - T5 ne touche que les vues et les données passées) ; idempotence/transaction déjà en place (testé T1) ; responsive + a11y (T2 `role="group"`/`aria-label`, T4 CSS mobile).

**Placeholder scan :** deux tranches bornées - T3 « Tout présent » agit sur **toutes** les lignes (pas seulement visibles) ; T4 ciblage CSS des radios cochés (par `nth-child` ou classe - l'implémenteur choisit, contrainte : pas de hex). Aucun TODO ouvert.

**Type consistency :** `occurrenceSummary` → `{present,absent,excuse,non_renseigne,total}` consommé par T2 (compteurs) et testé T1. Contrat du partial (§4) identique dans les 4 appelants (T5). `data-status` du JS ∈ `{present,absent,excuse,non_renseigne}` cohérent avec `PRESENCE_STATUTS` + le 4ᵉ état implicite.

## Execution Handoff

Six tâches. T1 backend isolé ; T2 partial ; T3/T4 JS+CSS (dépendent du markup T2) ; T5 câblage (dépend de T1+T2) ; T6 non-régression. L'onglet culte hérite du composant sans travail dédié (SP-3 finira le nettoyage).
