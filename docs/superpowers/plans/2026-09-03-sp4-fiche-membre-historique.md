# SP-4 — Fiche membre : consultation seule + Historique + fix N+1 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development ou superpowers:executing-plans. Steps `- [ ]`.

**Goal:** Retirer le pointage éditable de `forms/member.php` et des listes membres (fix N+1 par retrait), ajouter un bloc « Présences récentes » lecture seule sur la fiche, enrichir `attendancePrint` en historique complet (statut + Activité + filtres Type/Statut). Zéro migration.

**Architecture:** `AttendanceRepository::historyForUser` étendu (`statut`, `evenement_id`, jointures `evenements`/`basontas`, filtres `statut`/`type`) ; `AttendanceService::memberActivityHistory()` (mise en forme) ; `render_attendance_print_page()` + `attendance_print.php` enrichis ; `forms/member.php` / `render_member_form` / `save_membre` / `display_columns` / `members_table` / `render_basonta_detail` / `render_profile_page` modifiés ; `save_quick_presence` & co marqués `@deprecated`.

**Tech Stack:** PHP 8 SSR, zéro dep, MySQL via `App\Core\Query`. `php -l` + assertions + smoke-render.

**Spec:** `docs/superpowers/specs/2026-09-03-sp4-fiche-membre-historique-design.md`. **Prérequis :** SP-2 (statuts explicites).

## Global Constraints

- Zéro dep ; PSR-12 + `strict_types` ; couches strictes.
- Ne pas casser : édition membre (hors champs présence), `attendancePrint` (from/to), `suiviPrint`, le doughnut `member_presence_counts` de la fiche, le pointage occurrence SP-2, `can_view_member_profile()` / `deny_profile_access()`.
- `PRESENCE_FIELDS` reste (utilisé par `presenceCounts`, `FIELD_LABELS`). `presence_status` reste. `save_quick_presence` / `MemberService::saveQuickPresence` : conservés `@deprecated` (plus d'appelant).
- Base dev MySQL `127.0.0.1:3306` root `la_belle_eglise_db`. Comptes : `admin@labelleeglise.ga`/`LBEGF`, `user@labelleeglise.ga`/`user1111`.

## File Structure

| Fichier | Action |
|---|---|
| `app/Repositories/AttendanceRepository.php` | Modifier : `historyForUser` étendu (statut/evenement/basonta + filtres) |
| `app/Services/AttendanceService.php` | Modifier : `historyForUser` passthrough ; `memberActivityHistory()` (neuf) |
| `Views/pages/forms/member.php` | Modifier : supprimer la section « Présence … » |
| `app/Compat/sections.php` | Modifier : `render_member_form`, `display_columns`, `members_table`, `render_basonta_detail`, `render_profile_page` + `render_my_profile_page` |
| `app/Controllers/ActionsController.php` | Modifier : `case 'save_membre'` — retirer la boucle `save_quick_presence` |
| `app/Compat/profile.php` | Modifier : `render_attendance_print_page()` (filtres type/statut, `memberActivityHistory`) |
| `Views/pages/attendance_print.php` | Modifier : colonnes Activité + Statut (badge) + filtres écran |
| `app/Compat/data.php` | Modifier : `@deprecated` sur `save_quick_presence` |
| `app/Services/MemberService.php` | Modifier : `@deprecated` sur `saveQuickPresence` |

---

### Task 1: `historyForUser` étendu + `memberActivityHistory`

**Files:** `app/Repositories/AttendanceRepository.php`, `app/Services/AttendanceService.php` ; test `tmp/sp4_history_check.php`

**Interfaces produced:**
- `AttendanceRepository::historyForUser(int $userId, ?string $fromDate = null, ?string $toDate = null, ?string $statut = null, ?string $type = null): array` — SQL de la spec §4. `$type` ∈ `culte|evenement|bacenta|basonta|centre` → `AND <col>_id IS NOT NULL` (col : `culte|evenement|bacenta|basonta|centre` + `_id`) ; toute autre valeur → filtre ignoré. Retourne les colonnes `id, date_presence, statut, culte_id, evenement_id, centre_id, bacenta_id, basonta_id, culte_nom, evenement_nom, centre_nom, bacenta_nom, basonta_nom`.
- `AttendanceService::historyForUser(int $userId, ?string $from = null, ?string $to = null, ?string $statut = null, ?string $type = null): array` — passthrough (signature étendue, rétro-compatible).
- `AttendanceService::memberActivityHistory(int $userId, array $filters): array` — `$filters` clés optionnelles `from,to,statut,type`. Renvoie `list<{date_presence:string, statut:string, activity_type:string, activity_nom:string}>`. `activity_type` : première FK non nulle dans l'ordre `culte, evenement, bacenta, basonta, centre` ; `activity_nom` : le `*_nom` correspondant (ou `''`).

- [ ] **Step 1: Assertion qui échoue** — `tmp/sp4_history_check.php` :
```php
<?php declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query; use App\Services\AttendanceService; use App\Repositories\EvenementRepository;
$u = (int) Query::value("SELECT id FROM users WHERE role='membre' ORDER BY id LIMIT 1");
$culte = (int) Query::value('SELECT id FROM cultes ORDER BY id LIMIT 1');
$er = new EvenementRepository();
$ev = $er->create('SP4 hist evt', '2017-05-05 10:00:00', null, null, null, null);
$svc = new AttendanceService();
$svc->pointOccurrence('cult', $culte, '2017-05-06', [$u=>'present'], [$u]);
$svc->pointOccurrence('evenement', $ev, '2017-05-05', [$u=>'excuse'], [$u]);

$h = $svc->memberActivityHistory($u, []);
$byDate = [];
foreach ($h as $r) { $byDate[$r['date_presence']] = $r; }
assert(isset($byDate['2017-05-06']) && $byDate['2017-05-06']['activity_type'] === 'culte', 'culte type KO');
assert($byDate['2017-05-06']['statut'] === 'present', 'culte statut KO');
assert(isset($byDate['2017-05-05']) && $byDate['2017-05-05']['activity_type'] === 'evenement', 'evenement type KO');
assert($byDate['2017-05-05']['activity_nom'] === 'SP4 hist evt', 'evenement nom KO');

$hp = $svc->memberActivityHistory($u, ['statut' => 'present']);
assert(count(array_filter($hp, fn($r)=>$r['statut'] !== 'present')) === 0, 'filtre statut KO');
$ht = $svc->memberActivityHistory($u, ['type' => 'evenement']);
assert(count(array_filter($ht, fn($r)=>$r['activity_type'] !== 'evenement')) === 0, 'filtre type KO');

Query::run("DELETE FROM presences WHERE user_id=? AND date_presence IN ('2017-05-05','2017-05-06')", [$u]);
Query::run('DELETE FROM evenements WHERE id=?', [$ev]);
echo "OK sp4 history\n";
```
- [ ] **Step 2: FAIL** (`memberActivityHistory` absente ; `historyForUser` n'a pas `statut`).
- [ ] **Step 3: Étendre `AttendanceRepository::historyForUser`** — SQL spec §4, construction dynamique des clauses `AND`. Garder l'ordre des params : `[$userId, (from), (to), (statut), ...]`.
- [ ] **Step 4: `AttendanceService::historyForUser` (signature) + `memberActivityHistory`** — voir Interfaces. `activity_type`/`activity_nom` calculés en PHP par une petite fonction locale.
- [ ] **Step 5: GREEN + `php -l` (2 fichiers) + commit** `feat(presences): historyForUser enrichi (statut, événement, basonta) + memberActivityHistory`.

---

### Task 2: Retirer le pointage du formulaire membre + `save_membre`

**Files:** `Views/pages/forms/member.php`, `app/Compat/sections.php` (`render_member_form`), `app/Controllers/ActionsController.php` (`save_membre`), `app/Compat/data.php`, `app/Services/MemberService.php` ; test `tmp/sp4_memberform_check.php`

- [ ] **Step 1: Assertion** — `member.php` : `!str_contains(…, 'PRESENCE_FIELDS')` et `!str_contains(…, 'name="presenceCulte"')` et `!str_contains(…, 'Présence (dernier événement')`. `sections.php` `render_member_form` : `!str_contains(<corps>, 'presenceValues')`. `ActionsController` `save_membre` : `!str_contains(<corps du case>, 'save_quick_presence')`. `data.php` : `str_contains(…, '@deprecated')` près de `save_quick_presence`.
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: `member.php`** — supprimer le `<h3 class="form-section-title">…Présence…</h3>`, le `<div class="form-grid">` qui suit avec la boucle `PRESENCE_FIELDS`, et le `<p class="form-hint">« Présent » enregistre…</p>`.
- [ ] **Step 4: `render_member_form()`** (`sections.php`) — supprimer `$presenceValues = []; foreach (…) { $presenceValues[$f] = presence_status($member, $f); }` et la clé `'presenceValues' => $presenceValues` du tableau `view(...)`.
- [ ] **Step 5: `case 'save_membre'`** (`ActionsController` ~515-521) — supprimer la boucle `foreach (PRESENCE_FIELDS as $f) { if (isset($_POST[$f])) save_quick_presence($id, $f, (string) $_POST[$f]); }` (vérifier les lignes exactes).
- [ ] **Step 6: `@deprecated`** — `data.php` au-dessus de `function save_quick_presence` ; `MemberService.php` au-dessus de `saveQuickPresence`.
- [ ] **Step 7: GREEN + `php -l` (5 fichiers) + smoke render `render_member_form` (via une entrée valide) + commit** `refactor(membre): retrait du pointage éditable du formulaire membre`.

---

### Task 3: Retirer les colonnes présence des listes + fix N+1

**Files:** `app/Compat/sections.php` (`display_columns`, `members_table`, `render_basonta_detail`) ; test `tmp/sp4_lists_check.php`

- [ ] **Step 1: Assertion** — `display_columns` : `!str_contains(<corps>, 'PRESENCE_FIELDS')`. `members_table` : `!str_contains(<corps>, 'presence_status')`. `render_basonta_detail` : `!str_contains(<corps>, "presence_status(\$m, 'presenceBasonta')")` et l'en-tête ne contient plus `Présence Basonta` et `colspan="4"` sur l'empty state. Smoke : `grep -c "presence_status" app/Compat/sections.php` → **1** (il reste `render_member_form`? non, retiré en T2 → 0) — attendu **0** dans `sections.php`.
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: `display_columns()`** — supprimer `foreach (PRESENCE_FIELDS as $p) { $cols[] = $p; }`.
- [ ] **Step 4: `members_table()`** — supprimer la branche `if (in_array($f, PRESENCE_FIELDS, true)) { $cells .= '<td>' . presence_badge(presence_status($m, $f)) . '</td>'; continue; }` (devenue morte).
- [ ] **Step 5: `render_basonta_detail()`** — retirer la cellule `<td>' . presence_badge(presence_status($m, 'presenceBasonta')) . '</td>'` de la génération des `$rows`, retirer `<th>Présence Basonta</th>` de l'en-tête, passer `colspan="5"` → `colspan="4"` sur la ligne vide.
- [ ] **Step 6: GREEN + `php -l` + smoke render** (`members_table('generale', null, 'Liste', 0, [<2 membres>])` → pas d'erreur, aucune colonne présence ; `render_basonta_detail` en-tête à 4 colonnes) + **commit** `perf(membre): retrait des colonnes présence des listes (fix N+1)`.

---

### Task 4: Bloc « Présences récentes » sur la fiche membre

**Files:** `app/Compat/sections.php` (`render_profile_page` + `render_my_profile_page`) ; test `tmp/sp4_fiche_check.php`

- [ ] **Step 1: Assertion** — smoke render `render_profile_page` (ou la fonction qui construit le HTML) : la sortie contient « Présences récentes » et un lien `href="…page=attendancePrint&membre=…"`. `render_my_profile_page` idem pour l'utilisateur courant.
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: Ajouter le bloc** — dans `render_profile_page` / `render_my_profile_page`, après le bloc identité et avant/après le suivi hebdo, insérer un fragment HTML (helper local `member_recent_presence_html(array $stats, int $memberId): string`) :
  ```php
  function member_recent_presence_html(array $stats, int $memberId): string {
      $last = $stats['last_date'] ? date('d/m/Y', strtotime((string) $stats['last_date'])) : '—';
      $rate = $stats['rate'] !== null ? (int) $stats['rate'] . ' %' : 'n/d';
      return '<div class="dash-section-title"><h2><i class="fa-solid fa-clipboard-check"></i> Présences récentes</h2></div>'
          . '<div class="stat-row">'
          . stat_card('Dernière présence', h($last), 'var(--primary)')
          . stat_card('Total de présences', (string) (int) $stats['total'], 'var(--success)')
          . stat_card('Taux', h($rate), 'var(--warning)', $stats['rate_denominator_note'] ?? '')
          . '</div><a class="btn btn-outline" href="' . h(url('index.php', ['page' => 'attendancePrint', 'membre' => $memberId])) . '"><i class="fa-solid fa-list"></i> Voir l\'historique</a>';
  }
  ```
  (adapter aux helpers réels : `stat_card`, `dash-section-title`, `stat-row` — vérifier leur existence dans `Views/pages/suivi_admin_stats.php` / `rendering.php` ; sinon markup simple `<ul>`.) `$stats` provient de `attendance_service()->statsForUser($membreId)` — déjà calculé dans `render_profile_page` (`profile.php:113`) ; pour `render_my_profile_page`, ajouter l'appel.
- [ ] **Step 4: GREEN + `php -l` + commit** `feat(membre): bloc "Présences récentes" en lecture seule sur la fiche`.

---

### Task 5: `attendancePrint` = historique complet (Activité + Statut + filtres)

**Files:** `app/Compat/profile.php` (`render_attendance_print_page`), `Views/pages/attendance_print.php` ; test `tmp/sp4_print_check.php`

- [ ] **Step 1: Assertion** — `render_attendance_print_page` : `str_contains(…, 'memberActivityHistory')` et lit `$_GET['type']` / `$_GET['statut']` avec whitelist. `attendance_print.php` : `str_contains(…, 'presence_badge')` (ou rendu du statut) et `name="type"` + `name="statut"` dans un bloc `no-print`. Smoke render `attendance_print.php` avec `rows=[{date_presence, statut:'absent', activity_type:'bacenta', activity_nom:'Sion'}]` → contient `Sion` et un rendu « Absent ».
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: `render_attendance_print_page()`** — après la résolution `$membreId` + garde : `$type = in_array($_GET['type'] ?? '', ['culte','evenement','bacenta','basonta','centre'], true) ? $_GET['type'] : null;` `$statut = in_array($_GET['statut'] ?? '', ['present','absent','excuse'], true) ? $_GET['statut'] : null;` `$rows = attendance_service()->memberActivityHistory((int) $membreId, ['from'=>$from ?: null,'to'=>$to ?: null,'statut'=>$statut,'type'=>$type]);` (remplace l'appel `historyForUser` brut). Passer `rows`, `type`, `statut`, `from`, `to` à la vue.
- [ ] **Step 4: `attendance_print.php`** — colonnes du `<table class="print-table">` : `Date · Activité · Statut`. `Date` = `date('d/m/Y', strtotime($r['date_presence']))` ; `Activité` = `<libellé type> — <?= h($r['activity_nom']) ?>` (libellés : culte→« Culte », evenement→« Événement », bacenta→« Bacenta », basonta→« Basonta », centre→« Centre ») ; `Statut` = `presence_badge($r['statut'])` (ou `PRESENCE_STATUTS[$r['statut']] ?? $r['statut']`). Ajouter, avant le tableau, un `<form method="get" class="print-toolbar no-print">` : hidden `page=attendancePrint`, `membre` ; `<input type="date" name="from">` / `to` (repris de l'existant) ; `<select name="type">` (— Tous — + 5 options) ; `<select name="statut">` (— Tous — + Présent/Absent/Excusé) ; bouton « Filtrer ».
- [ ] **Step 5: GREEN + `php -l` (2 fichiers) + smoke render + commit** `feat(membre): historique des présences complet (activité + statut + filtres Type/Statut)`.

---

### Task 6: Non-régression + balayage N+1

- [ ] `grep -rn "presence_status\|save_quick_presence\|saveQuickPresence" app/ Views/` — attendu : `presence_status`/`saveQuickPresence` uniquement dans `MemberService::presenceCounts` (doughnut, 1 membre) + les définitions compat `@deprecated`. **Zéro** appel dans une boucle de liste.
- [ ] `grep -rn "PRESENCE_FIELDS" app/ Views/` — attendu : `MemberService` (`presenceCounts`), `FIELD_LABELS`/`constants.php`. Plus dans `member.php` ni `display_columns` ni `members_table`.
- [ ] Repo-wide `php -l` ; `php install.php` ; re-lancer `tmp/sp4_*` + les scripts M1 présence + smoke SP-2.
- [ ] Parcours substitué (lint + smoke) : `render_member_form` (édition sans champs présence) ; `members_table('generale'…)` / `members_table('bergers'…)` / `render_basonta_detail` (colonnes réduites) ; `render_profile_page` (bloc « Présences récentes » + lien) ; `attendance_print.php` (Activité/Statut/filtres) ; `attendancePrint?membre=<id>&type=culte&statut=present` filtre bien.
- [ ] commit `chore(membre): non-régression fiche membre lecture seule + N+1`.

---

## Self-Review

**Spec coverage :** pointage retiré du formulaire membre (T2) et de `save_membre` (T2) ; colonnes présence retirées des listes + tableau basonta → N+1 supprimé par retrait (T3) ; bloc « Présences récentes » lecture seule sur la fiche `personProfile` + `profile` (T4) ; historique = `attendancePrint` enrichi statut + activité (incl. événement/basonta) + filtres Période/Type/Statut (T1 + T5) ; `save_quick_presence` & `saveQuickPresence` `@deprecated` conservés (T2) ; `presence_status`/`PRESENCE_FIELDS`/doughnut conservés (T3/T6) ; aucune migration ; RBAC `attendancePrint` inchangé (T5) ; `?type`/`?statut` whitelistés avant SQL (T5).

**Placeholder scan :** une tranche bornée — T4 Step 3 : réutiliser `stat_card`/`dash-section-title` si présents, sinon markup `<ul>` simple (l'implémenteur vérifie). Aucun TODO ouvert.

**Type consistency :** `historyForUser(userId, from?, to?, statut?, type?)` rétro-compatible (params ajoutés en fin, `null` par défaut) — les appelants existants (`profile.php:184`, `weekForUser`) restent valides. `memberActivityHistory` → `{date_presence, statut, activity_type, activity_nom}` consommé par `attendance_print.php` (T5) et testé (T1). `statsForUser` (`{total,last_date,rate,rate_denominator_note}`) consommé tel quel par T4.

## Execution Handoff

Six tâches. T1 backend isolé ; T2/T3 (retraits) indépendantes ; T4 (fiche) dépend de `statsForUser` (existant) ; T5 dépend de T1 ; T6 en dernier. SP-2 doit être livré (badges de statut dans les vues).
