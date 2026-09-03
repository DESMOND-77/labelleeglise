# SP-2 — Composant de pointage unifié & enrichi — Design

- **Date** : 2026-09-03
- **Source** : `prompts/AUDIT…AGENDA & PRÉSENCES.md` (Objectif B, §12-21, §66) + Q/R de cadrage
- **Statut** : à valider
- **Dépend de** : rien (le moteur `pointOccurrence` existe déjà, M1/M4). SP-3, SP-4 dépendent de SP-2.

## 1. Objectif

Un **seul composant de pointage** — un partial de vue + un fichier JS + des styles — utilisé à l'identique pour bacenta, basonta, culte (SP-3) et événement. Il ajoute : 4ᵉ état explicite « Non renseigné », boutons segmentés (au lieu de `<select>`), recherche membre instantanée, filtres par statut, compteurs en temps réel, boutons « Tout présent » / « Réinitialiser » (locaux, confirmés par « Enregistrer »). **Aucune migration.** Le moteur d'écriture (`AttendanceService::pointOccurrence`) et l'action (`save_presence_occurrence`) sont réutilisés tels quels ; seuls l'UI et un helper de synthèse sont ajoutés.

## 2. État actuel (vérifié)

| Élément | Détail |
|---|---|
| Moteur | `AttendanceService::pointOccurrence(string $unitType, int $unitId, string $date, array $rawStatutByUserId, array $allowedUserIds)` — sous `Query::transaction` : `DELETE FROM presences WHERE <col> = ? AND date_presence = ?` puis `INSERT` uniquement pour les `userId ∈ $allowedUserIds` **et** `statut ∈ array_keys(PRESENCE_STATUTS)`. Idempotent, atomique. |
| Lecture | `AttendanceService::occurrenceGrid(unitType, unitId, date, members): [['user'=>row,'statut'=>string|''], …]` ; `AttendanceRepository::occurrenceStatuts(unitType, unitId, date): [userId=>statut]` |
| Action | `save_presence_occurrence` (`ActionsController`) : `unit_type ∈ {bacenta,cult,basonta,evenement}`, `unit_id`, `date`, `statut[<uid>]` ; `check_csrf()` global ; `can_manage_entity($unitType,$unitId)` (ou, pour `evenement`, `auth_can_manage_calendar() || auth_can_edit_evenement($evt)`) ; population `$allowed` **re-dérivée serveur** ; upsert transactionnel. |
| Vue occurrence | `Views/pages/presence_occurrence.php` (barre de date + `<select>` present/absent/excuse par membre) — rendue par `render_unit_presence_tab()` (`app/Compat/sections.php`) pour bacenta/cult/basonta |
| Vue événement | Bloc de pointage inline dans `Views/pages/calendrier.php` (`mode==='fiche'`, `$canPointe`) — mêmes champs, même action `unit_type=evenement` |
| Constante | `PRESENCE_STATUTS = ['present'=>'Présent','absent'=>'Absent','excuse'=>'Excusé']` |
| CSS présences | `assets/css/presences.css` (M1) — `.presence-datebar`, `.presence-table`, `.presence-matrix`, `.presence-cell-*` |
| JS | `assets/js/app.js` uniquement |

## 3. Décisions de cadrage

1. **« Non renseigné » = absence de ligne**, pas de valeur ENUM en base. Dans l'UI : un 4ᵉ bouton `— Non renseigné` dont la valeur postée est la **chaîne vide**. `pointOccurrence` ignore déjà les valeurs hors `PRESENCE_STATUTS` → le membre n'a aucune ligne. **Aucune modification du moteur ni du schéma.**
2. **Boutons segmentés = groupe de `<input type="radio">`** stylés (pas de `<select>`, pas de `<button>` JS). 4 radios par membre : `name="statut[<uid>]"`, valeurs `present` / `absent` / `excuse` / `""`. Fonctionne **sans JavaScript** (form-only) ; le radio coché à l'ouverture reflète le statut stocké (ou « — » si aucune ligne).
3. **Recherche, filtres, compteurs, « Tout présent »/« Réinitialiser » = JavaScript progressif** (`assets/js/attendance.js`). Sans JS, ces contrôles sont soit masqués (`class="js-only"` + CSS `display:none` par défaut, révélé par le JS), soit absents. Les compteurs ont une **valeur serveur initiale** (via `occurrenceSummary`) affichée même sans JS, puis rafraîchie par le JS à chaque changement de radio.
4. **« Tout présent » / « Réinitialiser » n'enregistrent pas** : ils modifient l'état local des radios ; l'enregistrement reste le bouton `Enregistrer` (POST `save_presence_occurrence`).
5. **Un seul partial** : `Views/pages/partials/attendance_pointage.php`, inclus par `presence_occurrence.php` **et** par le bloc événement de `calendrier.php`. Contrat de variables unique (voir §4). L'onglet culte (SP-3) l'utilisera aussi.
6. **Pas de nouvelle route, pas de nouvelle action, pas de nouveau schéma.** SP-2 = 1 partial + 1 JS + du CSS dans `presences.css` + 1 méthode `AttendanceService::occurrenceSummary()` + adaptation de 2 vues et de 2 points d'appel (compat + `CalendrierController`).
7. **Sécurité inchangée** : `check_csrf()` global, `can_manage_entity` / `auth_can_edit_evenement`, re-dérivation serveur de la population, upsert transactionnel idempotent — tout est déjà en place dans `save_presence_occurrence` et `pointOccurrence`. SP-2 ne relâche aucune garde. IDOR : un `statut[<uid>]` pour un `uid` hors population est déjà ignoré par le service.
8. **Responsive / a11y** : segmented control ≥ 44px de hauteur au doigt, `fieldset`/`legend` par membre (ou `aria-label`), `aria-pressed` non utilisé (radios sémantiques), focus visible. Recherche = `<input type="search">` avec `aria-label`.

## 4. Architecture

### Partial `Views/pages/partials/attendance_pointage.php` (nouveau)

**Contrat de variables** (fourni par chaque appelant) :

| Variable | Type | Rôle |
|---|---|---|
| `$unitType` | `'bacenta'\|'cult'\|'basonta'\|'evenement'` | posté en `unit_type` |
| `$unitId` | `int` | posté en `unit_id` |
| `$unitLabel` | `string` | titre affiché (« Culte du dimanche », nom du bacenta…) |
| `$date` | `string` `Y-m-d` | occurrence pointée |
| `$dateBarUrl` | `array` | params GET du sélecteur de date (page/id/tab ou page/evt) |
| `$grid` | `list<{user:array, statut:string\|''}>` | sortie `occurrenceGrid()` |
| `$summary` | `{present:int,absent:int,excuse:int,non_renseigne:int,total:int}` | valeur serveur initiale des compteurs |
| `$statuts` | `PRESENCE_STATUTS` | libellés |
| `$joursHint` | `string` | jours de récurrence (bacenta/basonta) — vide pour culte/événement |
| `$csrf` | `string` | `csrf_field()` |
| `$canPointe` | `bool` | si `false` : rendu **lecture seule** (badges, pas de radios ni de bouton) |

**Markup (résumé)** :
- Barre de date : `<form method="get">` + `<input type="date" name="date" onchange="this.form.submit()">` + hint jours.
- Barre d'outils (`class="attendance-toolbar js-only"`) : `<input type="search" class="attendance-search">`, groupe de filtres `[Tous][Présents][Absents][Excusés][Non renseignés]` (radios ou boutons), `[✓ Tout présent] [Réinitialiser]`.
- Compteurs (`class="attendance-counts"`) : `<span data-count="total">`, `data-count="present"`, `absent`, `excuse`, `non_renseigne` — remplis en PHP depuis `$summary`, mis à jour par le JS.
- `<form method="post" action="index.php">` : hidden `action=save_presence_occurrence`, `unit_type`, `unit_id`, `date`, `csrf`. Puis un tableau/liste `.attendance-list` ; pour chaque `$line` :
  ```
  <div class="attendance-row" data-name="<?= h(mb_strtolower(full_name($u))) ?>" data-status="<?= h($line['statut'] ?: 'non_renseigne') ?>">
    <span class="attendance-name"><?= h(full_name($u)) ?></span>
    <div class="segmented" role="group" aria-label="Statut de <?= h(full_name($u)) ?>">
      <label><input type="radio" name="statut[<?= (int)$u['id'] ?>]" value="present" <?= $line['statut']==='present'?'checked':'' ?>><span>✓ Présent</span></label>
      … absent, excuse …
      <label><input type="radio" name="statut[<?= (int)$u['id'] ?>]" value="" <?= $line['statut']===''?'checked':'' ?>><span>— Non renseigné</span></label>
    </div>
  </div>
  ```
  (En `$canPointe === false` : remplacer le `.segmented` par `presence_badge($line['statut'])`, retirer le `<form>` POST et le bouton.)
- `<button type="submit">Enregistrer le pointage</button>` (si `$canPointe`).
- Lien « Matrice annuelle » conservé (rendu par l'appelant, pas le partial — inchangé pour bacenta/cult/basonta).

### `AttendanceService::occurrenceSummary(string $unitType, int $unitId, string $date, int $totalMembers): array` (nouveau)

`$statuts = $this->attendance->occurrenceStatuts($unitType, $unitId, $date)` ; compte `present/absent/excuse` ; `non_renseigne = $totalMembers - count($statuts)` (borné à ≥ 0) ; `total = $totalMembers`. Pas de requête supplémentaire au-delà de `occurrenceStatuts` (déjà appelé indirectement par `occurrenceGrid` — le plan factorisera pour ne pas doubler la requête : `occurrenceGrid` peut renvoyer aussi le summary, ou le contrôleur appelle `occurrenceStatuts` une fois et construit grid + summary).

### `assets/js/attendance.js` (nouveau, vanilla)

Auto-garde : `const root = document.querySelector('.attendance-pointage'); if (!root) return;`. À `DOMContentLoaded` :
- révèle `.js-only` (retire une classe `is-hidden` ou passe `hidden=false`).
- `recount()` : parcourt les `.attendance-row`, lit le radio coché (`present`/`absent`/`excuse`/`''`→`non_renseigne`), met à jour `data-status` de la ligne et les `[data-count]`.
- `search` : `input` → filtre `.attendance-row` sur `data-name` (`includes` insensible casse/accents via `normalize('NFD').replace(/\p{Diacritic}/gu,'')`).
- `filter` : au clic d'un filtre → montre/masque les lignes selon `data-status` ; réapplique la recherche.
- `Tout présent` : coche le radio `value="present"` de chaque ligne **visible** (ou toutes — trancher au plan : **toutes**), `recount()`. `Réinitialiser` : coche `value=""` partout, `recount()`.
- radio `change` : `recount()`.
- Aucune requête réseau ; aucun framework ; `defer`.

### Points d'appel modifiés

- `Views/pages/presence_occurrence.php` → inclut le partial (passe le contrat).
- `Views/pages/calendrier.php` (bloc fiche `$canPointe`) → inclut le partial.
- `app/Compat/sections.php` `render_unit_presence_tab()` → calcule `$summary` (via `occurrenceStatuts` + `count($members)`), passe le contrat au partial via `presence_occurrence.php`.
- `app/Controllers/CalendrierController.php` `evenementFiche()` → idem pour l'événement.
- `assets/css/presences.css` → styles `.segmented`, `.attendance-toolbar`, `.attendance-counts`, `.attendance-row`, `.is-hidden`.
- `Views/layouts/layout.php` → `<script src="assets/js/attendance.js" defer>` (toujours ; le script s'auto-garde).

## 5. Tests

- `occurrenceSummary` : bacenta avec 5 membres, 2 `present` + 1 `absent` pointés → `{present:2,absent:1,excuse:0,non_renseigne:2,total:5}`.
- Non-régression `pointOccurrence` : un POST `statut[uid]=''` (non renseigné) → aucune ligne pour ce membre ; `statut[uid]='present'` → 1 ligne ; re-POST identique → toujours 1 ligne (idempotence) ; `statut[999999]='present'` (hors population) → ignoré.
- Smoke render `partials/attendance_pointage` : `$canPointe=true` → 4 radios/membre + `save_presence_occurrence` ; `$canPointe=false` → badges, pas de `<form>` POST, pas de radios.
- `attendance.js` : lint statique (pas d'`import`/`require`/CDN) ; contient `querySelector('.attendance-pointage')`, `data-count`, `normalize(`.
- Non-régression : onglet Présences bacenta/basonta, fiche événement + pointage, matrice annuelle — inchangés fonctionnellement ; `check_csrf` + RBAC intacts.
- a11y : chaque `.segmented` a un `role="group"` + `aria-label` ; `<input type="search">` a `aria-label`.

## 6. Hors périmètre

- SP-3 (unification culte), SP-4 (fiche membre), SP-5 (stats), SP-6 (normalisation table).
- Sauvegarde partielle / autosave.
- WebSocket / temps réel multi-utilisateur (la concurrence est traitée par l'upsert « remplacer l'occurrence » : le dernier `Enregistrer` gagne, comportement déjà en place — documenté comme choix, pas de verrou optimiste dans ce SP).

## 7. Livrables

- Spec : ce document. Plan : `docs/superpowers/plans/2026-09-03-sp2-composant-pointage.md`.
