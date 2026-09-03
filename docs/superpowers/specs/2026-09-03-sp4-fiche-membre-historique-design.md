# SP-4 — Fiche membre : consultation seule + Historique + fix N+1 — Design

- **Date** : 2026-09-03
- **Source** : `prompts/AUDIT…AGENDA & PRÉSENCES.md` (Objectif B, §A, §11, §29-30, §49, §66) + Q/R
- **Statut** : à valider
- **Dépend de** : SP-2 (statuts explicites dans les vues). Ne bloque pas SP-5 (mais SP-5 consomme l'historique enrichi).

## 1. Objectif

Retirer tout **pointage de présence depuis la fiche/formulaire membre** et depuis les **listes de membres** (source de N+1 et d'une notion de « présence » trompeuse basée sur « le dernier événement trouvé »). Le pointage se fait exclusivement depuis l'occurrence. La fiche membre gagne un bloc **« Présences récentes »** en lecture seule (dernière présence, total, taux) et un lien vers l'**historique** — la page d'impression des présences existante (`attendancePrint`), enrichie du statut et de filtres Type / Statut.

## 2. État actuel (vérifié)

| Élément | Fichier:ligne |
|---|---|
| Section « Présence (dernier événement de chaque type) » — `<select>` éditables | `Views/pages/forms/member.php:66-79` |
| Alimentation du formulaire | `app/Compat/sections.php:737-740` (`render_member_form` → `$presenceValues[$f] = presence_status($member, $f)`) |
| Écriture | `app/Controllers/ActionsController.php:~515-521` (`case 'save_membre'` : boucle `foreach (PRESENCE_FIELDS …) save_quick_presence($id, $f, $_POST[$f])`) |
| Colonnes présence des listes (N+1) | `app/Compat/sections.php:557-565` (`display_columns` append `PRESENCE_FIELDS`) + `:600-604` (boucle `presence_status($m, $f)` par membre) |
| Colonne « Présence Basonta » du tableau basonta | `app/Compat/sections.php:509` (`presence_status($m, 'presenceBasonta')`) |
| `presence_status` / `save_quick_presence` (compat) | `app/Compat/data.php:104-105` |
| `MemberService::presenceStatus` / `saveQuickPresence` / `presenceCounts` | `app/Services/MemberService.php:57 / 93 / 161` (`presenceCounts` boucle `presenceStatus` — **1 membre seulement**, sur la fiche, pour le doughnut) |
| Stats fiche | `app/Compat/profile.php:113` (`render_profile_page` calcule déjà `$stats = attendance_service()->statsForUser($membreId)`) |
| Historique imprimable | route `attendancePrint` → `render_attendance_print_page()` (`app/Compat/profile.php:165-197`) → `Views/pages/attendance_print.php` (colonnes Date/Semaine/Culte/Centre/Bacenta/**Statut hardcodé « Présent »**) ; filtres actuels : `from`/`to` (ou semaine) |
| Lecture historique | `AttendanceService::historyForUser(userId, from?, to?)` → `AttendanceRepository::historyForUser` : `SELECT p.id, p.date_presence, p.culte_id, p.centre_id, p.bacenta_id, p.basonta_id, cu.nom AS culte_nom, ce.nom AS centre_nom, ba.nom AS bacenta_nom` — **pas de `statut`, pas de jointure `evenements`/`basontas`** |

## 3. Décisions de cadrage

1. **Retrait pur** des colonnes `PRESENCE_FIELDS` des listes membres (`generale`/`nouveaux`/`bergers`) et du tableau des membres d'un basonta. Elles dérivent de « la dernière présence trouvée », jugée trompeuse par l'audit, et causent le N+1 (`members_table` appelle `presence_status()` par membre × par type). Le retrait **résout** le N+1 (aucune requête groupée à écrire). Aucune colonne de remplacement dans les listes.
2. **Fiche membre — bloc « Présences récentes » (lecture seule)** : sur `render_profile_page` (fiche admin `personProfile`) et `render_my_profile_page` (`profile`). Réutilise `$stats` déjà calculé (`statsForUser` : `total`, `last_date`, `rate`). Affiche : « Dernière présence : JJ/MM/AAAA — <activité> », « Total : N présences », « Taux : X % » (si `rate !== null`), + bouton « Voir l'historique » → `?page=attendancePrint&membre=<id>`. Aucun `<select>`, aucune écriture.
3. **Formulaire membre** : la section « Présence … » et ses 4 `<select>` sont **supprimés** de `Views/pages/forms/member.php`. `render_member_form` ne calcule plus `$presenceValues`. Le `case 'save_membre'` ne boucle plus sur `PRESENCE_FIELDS` / `save_quick_presence`.
4. **`save_quick_presence` (compat) et `MemberService::saveQuickPresence`** : conservés, marqués `@deprecated` (plus aucun appelant après §3). `presence_status` / `MemberService::presenceStatus` : **conservés** (toujours utilisés par `presenceCounts` pour le doughnut de la fiche — un seul membre, acceptable). `PRESENCE_FIELDS` : constante **conservée** (`presenceCounts`, `FIELD_LABELS`).
5. **Historique = `attendancePrint` enrichi** (pas de nouvelle route) :
   - `AttendanceRepository::historyForUser` gagne `p.statut`, `p.evenement_id`, `LEFT JOIN evenements ev` (`ev.nom AS evenement_nom`), `LEFT JOIN basontas bo` (`bo.nom AS basonta_nom`), et deux filtres optionnels : `?string $statut` (`present|absent|excuse`), `?string $type` (`culte|evenement|bacenta|basonta|centre`).
   - `AttendanceService::memberActivityHistory(int $userId, array $filters): array` : appelle le repo, calcule par ligne `activity_type` (priorité culte > evenement > bacenta > basonta > centre selon la FK non nulle) et `activity_nom` (le nom joint correspondant), renvoie `[{date_presence, statut, activity_type, activity_nom}]` triées date DESC.
   - `render_attendance_print_page()` : passe aussi `?type=` et `?statut=` (validés) ; la vue `attendance_print.php` affiche la vraie colonne **Statut** (`present`/`absent`/`excuse` → badge) et **Activité** (type + nom), plus une barre de filtres visible à l'écran (Période from/to déjà là + `<select>` Type + `<select>` Statut, GET). L'impression conserve le tableau complet.
6. **RBAC** : `attendancePrint` garde sa garde actuelle (`can_view_member_profile($membreId)` / `deny_profile_access()`). Le bloc « Présences récentes » n'apparaît que sur une fiche déjà autorisée.
7. **Aucune migration.** Les colonnes lues (`statut`, `evenement_id`) existent déjà (M1/M4).

## 4. Architecture

### Backend

- **`AttendanceRepository::historyForUser(int $userId, ?string $fromDate = null, ?string $toDate = null, ?string $statut = null, ?string $type = null): array`** *(étendu)* :
  ```sql
  SELECT p.id, p.date_presence, p.statut, p.culte_id, p.evenement_id, p.centre_id, p.bacenta_id, p.basonta_id,
         cu.nom AS culte_nom, ev.nom AS evenement_nom, ce.nom AS centre_nom, ba.nom AS bacenta_nom, bo.nom AS basonta_nom
    FROM presences p
    LEFT JOIN cultes cu     ON cu.id = p.culte_id
    LEFT JOIN evenements ev  ON ev.id = p.evenement_id
    LEFT JOIN centres ce     ON ce.id = p.centre_id
    LEFT JOIN bacentas ba    ON ba.id = p.bacenta_id
    LEFT JOIN basontas bo    ON bo.id = p.basonta_id
   WHERE p.user_id = ?
     [AND p.date_presence >= ?] [AND p.date_presence <= ?]
     [AND p.statut = ?]
     [AND (p.culte_id IS NOT NULL | p.evenement_id IS NOT NULL | …)  -- selon $type]
   ORDER BY p.date_presence DESC, p.id DESC
  ```
  `$type` mappe sur la colonne FK : `culte→culte_id`, `evenement→evenement_id`, `bacenta→bacenta_id`, `basonta→basonta_id`, `centre→centre_id` (clause `AND <col> IS NOT NULL`).
- **`AttendanceService::historyForUser(...)`** *(signature étendue, passthrough)* + **`memberActivityHistory(int $userId, array $filters): array`** *(nouveau)* — `$filters = ['from'=>?, 'to'=>?, 'statut'=>?, 'type'=>?]` ; renvoie `[['date_presence','statut','activity_type','activity_nom']]`.
- **`AttendanceService::statsForUser`** *(inchangé pour ce SP)* — SP-5 le retravaillera. Ici on affiche juste `total` / `last_date` / `rate` tels quels.

### Vues & compat

- **`Views/pages/forms/member.php`** *(modifié)* — supprimer les lignes 66-80 (`<h3>Présence…</h3>` + `form-grid` + `<p class="form-hint">`).
- **`app/Compat/sections.php`** :
  - `render_member_form()` : supprimer le bloc `$presenceValues` (737-740) et la clé `'presenceValues'` passée à la vue.
  - `display_columns()` : supprimer `foreach (PRESENCE_FIELDS as $p) { $cols[] = $p; }`.
  - `members_table()` : supprimer la branche `if (in_array($f, PRESENCE_FIELDS, true)) { … presence_status … }` (devient morte).
  - `render_basonta_detail()` : retirer la `<th>Présence Basonta</th>`, la `<td>` `presence_badge(presence_status($m,'presenceBasonta'))`, ajuster `colspan` (5→4).
  - `render_profile_page()` / `render_my_profile_page()` : injecter le bloc « Présences récentes » (HTML lecture seule) dans le contenu, à partir de `$stats`.
- **`app/Controllers/ActionsController.php`** — `case 'save_membre'` : supprimer la boucle `save_quick_presence`.
- **`app/Compat/profile.php`** — `render_attendance_print_page()` : lire `?type` / `?statut` (validés contre une whitelist), appeler `memberActivityHistory`, passer `rows` + `type` + `statut` + les options à la vue.
- **`Views/pages/attendance_print.php`** — colonnes : Date · Activité (`activity_type` libellé + `activity_nom`) · Statut (badge). Barre de filtres à l'écran (`no-print`) : from/to (déjà) + `<select name="type">` + `<select name="statut">`, submit GET.
- **`app/Compat/data.php`** — `save_quick_presence` : `/** @deprecated */`. `presence_status` : inchangé.
- **`app/Services/MemberService.php`** — `saveQuickPresence` : `/** @deprecated */`.

### Nav / routes

- Aucun changement. `attendancePrint` reste la route de l'historique (déjà dans `Routes/web.php`).

## 5. Sécurité / perf

- N+1 supprimé par retrait des colonnes (aucune requête groupée à maintenir). `members_table` ne fait plus d'appel présence par ligne.
- `render_attendance_print_page` garde `can_view_member_profile()` ; `?type`/`?statut` validés contre whitelist avant SQL (sinon ignorés).
- `memberActivityHistory` : une seule requête, `WHERE p.user_id = ?` indexé, `ORDER BY date_presence DESC` (index sur `date_presence`… à vérifier ; sinon acceptable, volume par membre faible).
- Aucune écriture nouvelle ; `save_quick_presence` mort → surface d'attaque réduite.

## 6. Tests

- `AttendanceRepository::historyForUser` étendu : renvoie `statut` + `evenement_nom` + `basonta_nom` ; `$statut='present'` filtre ; `$type='evenement'` ne renvoie que les lignes `evenement_id IS NOT NULL`.
- `memberActivityHistory` : une ligne culte → `activity_type='culte'`, `activity_nom=<nom du culte>` ; une ligne événement → `'evenement'` + nom ; priorité respectée si (théoriquement) deux FK.
- `members_table` : `grep` — plus de `presence_status` dans la boucle ; smoke render d'une liste `generale` → aucune colonne « Présence … », pas d'erreur.
- `render_basonta_detail` : en-tête à 4 colonnes, `colspan="4"` sur l'empty state.
- `member.php` : `grep` — plus de `PRESENCE_FIELDS` ni de `name="presenceCulte"` ; `save_membre` : `grep` — plus de `save_quick_presence`.
- Fiche : smoke render `render_profile_page` → contient « Présences récentes » + lien `attendancePrint` ; le doughnut (`member_presence_counts`) fonctionne toujours.
- `attendance_print.php` : smoke render → colonne Statut avec badge, filtres Type/Statut présents (`no-print`).
- Non-régression : édition d'un membre (sans les champs présence) ; `attendancePrint` (from/to) ; `suiviPrint` ; le pointage occurrence (SP-2) inchangé.

## 7. Hors périmètre

- SP-5 (reformulation du taux — ici on affiche `statsForUser` tel quel).
- Une vue historique dédiée hors `attendancePrint` (réutilisation assumée).
- Export CSV de l'historique.

## 8. Livrables

- Spec : ce document. Plan : `docs/superpowers/plans/2026-09-03-sp4-fiche-membre-historique.md`.
