# SP-3 — Unification du pointage culte — Design

- **Date** : 2026-09-03
- **Source** : `prompts/AUDIT…AGENDA & PRÉSENCES.md` (Objectif B, §22, §25, §57, §66) + Q/R
- **Statut** : à valider
- **Dépend de** : SP-2 (le composant unifié). N'a pas de dépendant.

## 1. Objectif

Supprimer le **deuxième mécanisme de pointage** propre au culte. Le culte devient un simple type d'occurrence : sa fiche n'affiche plus que le pointage par occurrence (composant SP-2) et la matrice annuelle. L'ancien chemin (`point_culte` / `AttendanceService::pointCulte` / `Views/pages/culte_detail.php` / onglet « Pointage rapide ») est retiré de l'UI ; l'action `point_culte` **reste** comme wrapper de compatibilité déprécié (liens/bookmarks/éventuels POST externes) et route désormais vers `pointOccurrence`.

## 2. État actuel (vérifié)

| Élément | Détail |
|---|---|
| Action legacy | `point_culte` (`ActionsController`) : `present[<uid>]` (cases), `auth_can_manage_culte($culte)`, `point_culte_presence($culte,$date,$userIds)` → `AttendanceService::pointCulte` → `AttendanceRepository::pointCulte` (`DELETE … WHERE culte_id=? AND date_presence=?` puis `INSERT` present-only, statut défaut `present`). Redirige `page=cultes&id=<id>`. |
| Vue legacy | `Views/pages/culte_detail.php` — grille de cases « Pointer la présence » + liste « Présents (X) ». |
| Renderer | `render_culte_detail()` (`app/Compat/sections.php`, M1) : `$culteTabs = ['pointage'=>'Pointage rapide', 'presences'=>'Présences']` ; onglet par défaut `pointage` = `view('pages/culte_detail', …)` ; `tab=presences` / `presences_annuel` → `render_unit_presence_tab('cult', 'cultes', $c, $tab, $culteMembers)`. |
| Compat | `point_culte_presence(int $culteId, string $date, array $userIds): void` (`app/Compat/data.php`) → `AttendanceService::pointCulte`. |
| Occurrence culte | déjà pleinement fonctionnelle depuis M1 : `pointOccurrence('cult', …)`, `save_presence_occurrence unit_type=cult`, `render_unit_presence_tab` + matrice annuelle + impression. Après SP-2, l'onglet « Présences » du culte utilise le composant segmenté. |
| Stat | `MemberService::presenceStatus()` cas `presenceCulte` : `cultes->latest()` + `hasPresence('culte_id', …)` — **non touché ici** (SP-4 retire l'affichage). |
| Grille cultes | `CulteRepository::all()` calcule `nb_presents` depuis `presences WHERE culte_id` — reste correct (l'occurrence écrit bien `culte_id`). |

## 3. Décisions de cadrage

1. **Onglet « Pointage rapide » supprimé.** `render_culte_detail()` ne rend plus que le pointage par occurrence (composant SP-2) et, via le bouton déjà présent dans ce composant, la matrice annuelle. Plus de barre d'onglets à deux entrées : la fiche culte = `render_unit_presence_tab('cult', 'cultes', $c, $tab ?: 'presences', $culteMembers)` directement (comme un bacenta sans onglet « Membres »).
2. **`Views/pages/culte_detail.php` supprimé** (plus aucun chemin ne le rend).
3. **Action `point_culte` conservée, dépréciée, ré-implémentée en wrapper** : elle convertit `present[<uid>]` cochés en `['uid' => 'present']` et appelle `pointOccurrence('cult', $culteId, $date, $map, $population)` où `$population` = la liste complète des membres du culte re-dérivée serveur. Effet net identique à l'ancien `pointCulte` (supprime les lignes de l'(culte, date), insère les présents). `auth_can_manage_culte()` conservé. Docbloc `@deprecated` + commentaire renvoyant vers `save_presence_occurrence`.
4. **`AttendanceService::pointCulte()` et `AttendanceRepository::pointCulte()` conservés** mais ré-implémentés en délégation : `pointCulte($culteId, $date, $userIds)` → `pointOccurrence('cult', $culteId, $date, array_fill_keys($userIds, 'present'), $userIds)` (service) ; la méthode repo devient inutilisée mais reste (marquée `@deprecated`) pour ne casser aucun appelant éventuel. `point_culte_presence()` (compat) inchangé dans sa signature, route via le service.
5. **Aucune migration, aucune perte de données.** Les lignes `presences` avec `culte_id` écrites par l'ancien système restent lues à l'identique par le nouveau (même colonne, `statut` = `present` par défaut).
6. **Pas de nouvelle route.** `page=cultes&id=<id>` (sans `tab`) affiche désormais directement le pointage occurrence du jour. `page=cultes&id=<id>&tab=presences_annuel` inchangé.

## 4. Architecture

- **`app/Compat/sections.php` — `render_culte_detail(int $culteId)`** *(modifié)* :
  - garde `has_verified_access('cultes', $culteId)` + `render_gate` inchangés.
  - `$culteMembers = Query::all("SELECT * FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY prenom, nom")` (déjà présent).
  - `$tab = nav('tab')` ; `render_unit_presence_tab('cult', 'cultes', $c, in_array($tab, ['presences','presences_annuel'], true) ? $tab : 'presences', $culteMembers)` ; `return`.
  - **Supprimer** : `$culteTabs`, `tab_row($culteTabs, …)`, `$presents`, `$candidates`, `$isAdmin`, `view('pages/culte_detail', …)`, `section_toolbar(...)` legacy.
  - `render_unit_presence_tab` fournit déjà son propre en-tête (`section-toolbar` avec le nom + « Pointage des présences »).
- **`Views/pages/culte_detail.php`** *(supprimé)*.
- **`app/Controllers/ActionsController.php` — `case 'point_culte'`** *(ré-implémenté, déprécié)* :
  ```php
  // @deprecated — conservé pour compat ; le pointage culte passe par
  // save_presence_occurrence (unit_type=cult). Ce wrapper route vers pointOccurrence.
  case 'point_culte': {
      $this->requireUser();
      $culte = (int) ($_POST['culte'] ?? 0);
      if (!$culte || !auth_can_manage_culte($culte)) { $this->deny(); }
      $date = (string) ($_POST['date_presence'] ?? date('Y-m-d'));
      $present = array_map('intval', array_keys($_POST['present'] ?? []));
      $population = array_map(
          static fn($m) => (int) $m['id'],
          \App\Core\Query::all("SELECT id FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant')")
      );
      save_unit_presence('cult', $culte, $date, array_fill_keys($present, 'present'), $population);
      $this->redirect('index.php', ['page' => 'cultes', 'id' => $culte, 'tab' => 'presences', 'date' => $date]);
      break;
  }
  ```
- **`app/Services/AttendanceService.php` — `pointCulte()`** *(délégation)* :
  ```php
  /** @deprecated Utiliser pointOccurrence('cult', …). Conservé pour compat. */
  public function pointCulte(int $culteId, string $date, array $userIds): void
  {
      $ids = array_map('intval', $userIds);
      $this->pointOccurrence('cult', $culteId, $date, array_fill_keys($ids, 'present'), $ids);
  }
  ```
  `AttendanceRepository::pointCulte()` : garder tel quel avec `/** @deprecated */` (plus aucun appelant après ce SP), ou le supprimer si le plan confirme zéro référence.
- **Nav / fil d'Ariane** : inchangés (`page=cultes` existe toujours ; le fil d'Ariane `render_culte_detail` → nom du culte fonctionne via `get_culte`).

## 5. Sécurité / compat

- `point_culte` garde `auth_can_manage_culte()`. `save_unit_presence` → `pointOccurrence` filtre la population et les statuts, sous transaction idempotente.
- Aucun lien interne ne pointe vers `culte_detail.php` autrement que via `render_culte_detail` (à confirmer au plan par grep). Les bookmarks `?page=cultes&id=<id>` continuent de fonctionner (rendent le pointage occurrence).
- Données historiques : intactes (même colonne `presences.culte_id`).

## 6. Tests

- `AttendanceService::pointCulte([$u1,$u2])` sur un culte/date → 2 lignes `statut='present'` avec `culte_id` renseigné ; re-appel identique → toujours 2 (idempotence via `pointOccurrence`).
- Action `point_culte` (simulée via `save_unit_presence`) : `present[$u1]=1` → 1 ligne present ; un `present[$horsPopulation]` → ignoré.
- `render_culte_detail` : grep confirmant plus de `view('pages/culte_detail'` ni de `'pointage'` dans le renderer ; smoke — `render_unit_presence_tab('cult', …)` rend le composant SP-2 (`name="statut[`).
- Grille cultes : `nb_presents` d'un culte pointé via occurrence est correct.
- Non-régression : `save_presence_occurrence unit_type=cult` + `tab=presences_annuel` + impression `presencePrint?unit_type=cult` inchangés ; `MemberService::presenceStatus('presenceCulte')` ne plante pas (même s'il n'est plus affiché après SP-4).
- `grep -rn "culte_detail\|point_culte\|pointCulte" app/ Views/` — seules restent : l'action wrapper `point_culte`, `AttendanceService::pointCulte` (délégation), `point_culte_presence` (compat). Zéro référence à `pages/culte_detail`.

## 7. Hors périmètre

- SP-4 (retrait de l'affichage `presenceCulte` des listes membres), SP-5 (stats).
- Suppression définitive de l'action `point_culte` (reste en dépréciation ; un futur SP de nettoyage la retirera).

## 8. Livrables

- Spec : ce document. Plan : `docs/superpowers/plans/2026-09-03-sp3-unification-pointage-culte.md`.
