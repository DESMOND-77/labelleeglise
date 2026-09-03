# SP-5 — Statistiques de présence honnêtes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development ou superpowers:executing-plans. Steps `- [ ]`.

**Goal:** Réécrire `AttendanceService::statsForUser` : taux = `présences ÷ (présents + absents + excusés)` (documenté), `total` = présences réelles, nouvelles clés `present/absent/excuse/pointed`, 1 requête groupée. Marquer `@deprecated` les helpers devenus inutiles. Ligne de ventilation sur la fiche.

**Architecture:** `AttendanceRepository::statusCountsForUser()` (nouveau, 1 `GROUP BY statut`) ; `statsForUser` réécrit (signature inchangée) ; `distinctCulteDatesInRange`/`countForUser` marqués `@deprecated` ; fiche membre affiche la ventilation + la formule.

**Tech Stack:** PHP 8 SSR, zéro dep, MySQL via `App\Core\Query`. `php -l` + assertions + smoke-render.

**Spec:** `docs/superpowers/specs/2026-09-03-sp5-statistiques-honnetes-design.md`. **Prérequis :** SP-2 (statuts saisis) ; consomme SP-4 (bloc « Présences récentes ») si livré.

## Global Constraints

- Zéro dep ; PSR-12 + `strict_types` ; couches strictes.
- Ne pas casser : appelant `profile.php:113` (`$stats['total']`, `['rate']`, `['rate_denominator_note']`, `['last_date']`), `StatisticsService::countMembers` (`countDistinctForCultes`).
- Aucune migration. Signature `statsForUser(int, ?string, ?string)` inchangée.
- Base dev MySQL `127.0.0.1:3306` root `la_belle_eglise_db`. Comptes : `admin@labelleeglise.ga`/`LBEGF`.

## File Structure

| Fichier | Action |
|---|---|
| `app/Repositories/AttendanceRepository.php` | Modifier : `statusCountsForUser()` (neuf) ; `@deprecated` sur `distinctCulteDatesInRange` + `countForUser` |
| `app/Services/AttendanceService.php` | Modifier : `statsForUser()` réécrit + docbloc formule |
| `app/Compat/sections.php` | Modifier : ventilation `present/absent/excuse` + `formula` dans le bloc « Présences récentes » (SP-4) |

---

### Task 1: `statusCountsForUser` + réécriture de `statsForUser`

**Files:** `app/Repositories/AttendanceRepository.php`, `app/Services/AttendanceService.php` ; test `tmp/sp5_stats_check.php`

**Interfaces produced:**
- `AttendanceRepository::statusCountsForUser(int $userId, ?string $from = null, ?string $to = null): array` → `['present'=>int,'absent'=>int,'excuse'=>int]` (clés manquantes → 0). SQL : `SELECT statut, COUNT(*) c FROM presences WHERE user_id = ? [AND date_presence >= ?] [AND date_presence <= ?] GROUP BY statut`.
- `AttendanceService::statsForUser(int $userId, ?string $from = null, ?string $to = null): array` → `['total'=>int(=present), 'present'=>int, 'absent'=>int, 'excuse'=>int, 'pointed'=>int, 'last_date'=>?string, 'rate'=>?int, 'formula'=>string, 'rate_denominator_note'=>string]`. `rate = pointed > 0 ? (int) round(present/pointed*100) : null`.

- [ ] **Step 1: Assertion qui échoue** — `tmp/sp5_stats_check.php` :
```php
<?php declare(strict_types=1);
chdir('/home/foxtrot/Téléchargements/workspace-019fc4e4-dfa8-7cdb-aaa9-3d01f70a55a6/labelleeglise');
require 'Bootstrap/init.php';
use App\Core\Query; use App\Services\AttendanceService;
$u = (int) Query::value("SELECT id FROM users WHERE role='membre' ORDER BY id LIMIT 1");
$bac = (int) Query::value('SELECT id FROM bacentas ORDER BY id LIMIT 1');
Query::run("DELETE FROM presences WHERE user_id=? AND date_presence LIKE '2016-%'", [$u]);
$ins = fn($d,$s) => Query::run('INSERT INTO presences (user_id, date_presence, statut, bacenta_id) VALUES (?,?,?,?)', [$u,$d,$s,$bac]);
$ins('2016-01-01','present'); $ins('2016-01-08','present'); $ins('2016-01-15','present'); $ins('2016-01-22','present');
$ins('2016-01-29','absent'); $ins('2016-02-05','excuse');

$svc = new AttendanceService();
$c = (new ReflectionClass($svc)); // accès repo via méthode publique
$counts = $svc->statsForUser($u, '2016-01-01', '2016-12-31');
assert($counts['present'] === 4 && $counts['absent'] === 1 && $counts['excuse'] === 1, 'ventilation KO: '.json_encode($counts));
assert($counts['total'] === 4, "'total' doit valoir le nb de présences réelles (4), vu ".$counts['total']);
assert($counts['pointed'] === 6, 'pointed KO');
assert($counts['rate'] === 67, "rate attendu round(4/6*100)=67, vu ".var_export($counts['rate'], true));
assert(is_string($counts['formula']) && $counts['formula'] !== '', 'formula manquante');
assert(array_key_exists('rate_denominator_note', $counts), 'rate_denominator_note (rétro-compat) manquante');
assert(array_key_exists('last_date', $counts), 'last_date manquante');

$empty = $svc->statsForUser($u, '1999-01-01', '1999-12-31');
assert($empty['rate'] === null && $empty['total'] === 0 && $empty['pointed'] === 0, 'cas vide KO');

Query::run("DELETE FROM presences WHERE user_id=? AND date_presence LIKE '2016-%'", [$u]);
echo "OK sp5 stats\n";
```
- [ ] **Step 2: FAIL** (`present`/`pointed`/`formula` absents ; `total` = COUNT(*)).
- [ ] **Step 3: `AttendanceRepository::statusCountsForUser`** — voir Interfaces. `$out = ['present'=>0,'absent'=>0,'excuse'=>0]; foreach (Query::all($sql, $params) as $r) { $out[$r['statut']] = (int) $r['c']; } return $out;`.
- [ ] **Step 4: Réécrire `AttendanceService::statsForUser`** — corps de la spec §4. Docbloc au-dessus :
  ```php
  /**
   * Statistiques de présence d'un membre.
   * Taux = présences ÷ (présents + absents + excusés). Les occurrences non
   * renseignées (aucune ligne) sont exclues du dénominateur : un oubli de
   * pointage ne pénalise pas le membre. Limite connue : ne mesure pas
   * l'assiduité sur les occurrences auxquelles le membre était éligible mais
   * non pointé (pas de date d'appartenance fiable). Voir SP-5.
   */
  ```
- [ ] **Step 5: `@deprecated`** — `distinctCulteDatesInRange` + `countForUser` dans `AttendanceRepository` : `/** @deprecated Plus utilisé par statsForUser (SP-5). */`.
- [ ] **Step 6: GREEN + `php -l` (2 fichiers) + commit** `feat(stats): taux de présence = présences / (présents + absents + excusés), documenté`.

---

### Task 2: Ventilation + formule sur la fiche membre

**Files:** `app/Compat/sections.php` ; test `tmp/sp5_fiche_check.php`

- [ ] **Step 1: Assertion** — le fragment « Présences récentes » (helper `member_recent_presence_html` de SP-4, ou le bloc dans `render_profile_page`) contient `présents · ` et `absents · ` et `excusés`, et affiche la chaîne `formula` (ou `rate_denominator_note`). Smoke render `render_profile_page` d'un membre avec des présences → la ligne de ventilation apparaît.
- [ ] **Step 2: FAIL**.
- [ ] **Step 3: Ajouter la ligne** — dans le fragment, après les `stat_card` (ou la liste) :
  ```php
  . '<div class="stat-label">' . (int) $stats['present'] . ' présents · ' . (int) $stats['absent'] . ' absents · ' . (int) $stats['excuse'] . ' excusés</div>'
  . '<p class="form-hint">' . h($stats['formula'] ?? $stats['rate_denominator_note'] ?? '') . '</p>'
  ```
  (Si SP-4 n'est pas encore livré : ce Step crée directement le bloc complet « Présences récentes » avec ventilation ; sinon il complète le bloc existant.)
- [ ] **Step 4: GREEN + `php -l` + commit** `feat(stats): ventilation présents/absents/excusés + formule sur la fiche membre`.

---

### Task 3: Non-régression

- [ ] `grep -rn "statsForUser\|distinctCulteDatesInRange\|countForUser" app/` — `distinctCulteDatesInRange` / `countForUser` : uniquement définitions `@deprecated`, zéro appel. `statsForUser` : appelant `profile.php:113` + les 2 Task ci-dessus.
- [ ] `grep -rn "countDistinctForCultes" app/` — inchangé (`StatisticsService`).
- [ ] Repo-wide `php -l` ; `php install.php` ; `tmp/sp5_stats_check.php` vert ; smoke render fiche membre (`render_profile_page`) sans erreur, `$stats['total']`/`['rate']` toujours lus.
- [ ] commit `chore(stats): non-régression taux honnête`.

---

## Self-Review

**Spec coverage :** formule `présences / (présents + absents + excusés)` implémentée + documentée (`formula` + docbloc) (T1) ; `total` = présences réelles (T1) ; clés `present/absent/excuse/pointed` ajoutées, `rate_denominator_note` conservée en alias (T1) ; 1 requête groupée `statusCountsForUser` (T1) ; `null` si aucune occurrence pointée, pas de /0 (T1, testé) ; `distinctCulteDatesInRange`/`countForUser` `@deprecated` (T1) ; ventilation + formule affichées sur la fiche (T2) ; limite « occurrences éligibles » documentée (T1 docbloc + spec §3.4/§6) ; aucune migration ; `StatisticsService` intact (T3).

**Placeholder scan :** une tranche bornée — T2 : compléter le bloc SP-4 s'il existe, sinon le créer entier. Aucun TODO ouvert.

**Type consistency :** `statusCountsForUser` → `{present,absent,excuse}` (int) consommé uniquement par `statsForUser` (T1). `statsForUser` → tableau à 9 clés ; les 4 clés historiques (`total,last_date,rate,rate_denominator_note`) conservées pour `profile.php` ; les 5 nouvelles (`present,absent,excuse,pointed,formula`) consommées par T2. Signature `statsForUser(int, ?string, ?string)` inchangée → appelant `profile.php:113` valide.

## Execution Handoff

Trois petites tâches séquentielles. T1 (backend) est l'essentiel ; T2 dépend de T1 (et se coordonne avec le bloc SP-4) ; T3 non-régression.
