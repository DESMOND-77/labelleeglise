# SP-1 — Agenda unifié (fusion Calendrier événementiel + Anniversaires) — Design

- **Date** : 2026-09-03
- **Source** : `prompts/AUDIT, REFACTORING ET IMPLÉMENTATION DU SYSTÈME AGENDA & PRÉSENCES.md` (Objectif A, §34-46, §65) + Q/R de cadrage
- **Statut** : à valider
- **Dépend de** : rien (autonome). Les SP-2..SP-5 ne dépendent pas de SP-1.

## 1. Objectif

Remplacer les deux onglets `Calendrier` et `Anniversaires` par un **onglet unique `Agenda`** : une grille mensuelle qui montre, en un écran, quel mois on est, quel jour on est, quels jours ont une activité, et le détail (événements + anniversaires) du jour sélectionné. Aucune fonctionnalité métier existante n'est supprimée : CRUD événements, fiche événement (avec pointage), anniversaires manuels et anniversaires dérivés de `users.date_naissance` restent.

## 2. État actuel (vérifié dans le code)

| Élément | Fichier |
|---|---|
| Contrôleur | `app/Controllers/CalendrierController.php` — `evenements()` (liste ou fiche si `?evt=`), `anniversaires()`, `evenementFiche()` privé |
| Service | `app/Services/CalendrierService.php` — `allEvents()`, `event(int)`, `saveEvent()`, `deleteEvent()`, `birthdays()`, `saveBirthday()`, `deleteBirthday()` |
| Repos | `EvenementRepository` (`all(?fromDate)`, `find`, `create`, `update`, `delete`), `AnniversaireRepository` (`all`, `find`, `create`, `delete`) |
| Vues | `Views/pages/calendrier.php` (liste + formulaire + fiche `mode==='fiche'`), `Views/pages/anniversaires.php` |
| Routes | `Router::get('calendrier', …, 'evenements')`, `Router::get('anniversaires', …, 'anniversaires')` |
| Actions | `save_evenement`, `delete_evenement`, `save_anniversaire`, `delete_anniversaire` (dans `ActionsController`) |
| Nav | `SECTION_LABELS/ICONS/NAV_ORDER` clés `calendrier` + `anniversaires` ; bloc hoist dans `layout.php` gardé par `auth_can_manage_calendar()` |
| RBAC | `auth_can_manage_calendar()`, `auth_can_edit_evenement(array)` — **conservés tels quels** |
| `birthdays()` | renvoie `list<{nom, jour:int, mois:int, annee:?int, source:'membre'|'manuel', id:int, age:?int, is_current_month:bool}>`, trié `(mois, jour)` |
| Présence événement | `presences.evenement_id` + `save_presence_occurrence` `unit_type='evenement'` — **ne pas casser** |

## 3. Décisions de cadrage

1. **Navigation entre mois = rendu serveur** : chaque `‹` / `›` / `Aujourd'hui` recharge la page (`?page=agenda&ym=YYYY-MM`). Aucun endpoint JSON, aucune SPA (le projet est SSR pur, seul `assets/js/app.js` existe).
2. **Sélection d'un jour = JavaScript sans rechargement** : les données du mois sont injectées en JSON dans la page ; cliquer un jour met à jour le panneau détail et l'URL (`history.replaceState`, `?date=…`) côté client. Sans JS, chaque cellule de jour est un lien `?page=agenda&ym=…&date=…` qui recharge — dégradation propre.
3. **Confort de transition** malgré le rechargement mensuel : `scroll-behavior: smooth` sur le conteneur, et mémorisation/restauration de `window.scrollY` via `sessionStorage` (clé `agenda:scroll`) autour du rechargement.
4. **Anciennes routes** : `page=calendrier&evt=<id>` continue de rendre la **fiche événement** (inchangé). `page=calendrier` sans `evt` → redirection 302 vers `page=agenda`. `page=anniversaires` → redirection 302 vers `page=agenda`. Aucun lien interne cassé.
5. **Navigation (menu)** : une seule entrée `Agenda` remplace `Calendrier` **et** `Anniversaires` dans `NAV_ORDER`, `SECTION_LABELS`, `SECTION_ICONS`, et dans le bloc hoist de `layout.php`. Les clés `calendrier`/`anniversaires` sont retirées de `NAV_ORDER` (gardées dans `SECTION_LABELS`/`SECTION_ICONS` si un fil d'Ariane les référence — à vérifier).
6. **Performance** : la vue mensuelle fait **exactement 2 requêtes** — une pour les événements chevauchant `[1er du mois 00:00 .. dernier du mois 23:59]`, une pour les anniversaires (réutilise `birthdays()`, filtré en PHP sur `mois`). Indexation `byDate` en PHP. Aucune requête par cellule.
7. **Semaine commençant lundi.** Cellules des mois adjacents atténuées. Aujourd'hui = cercle ; jour sélectionné = fond/bordure distincts ; jour avec contenu = points indicateurs — trois styles distincts.
8. **Formulaires CRUD** : le formulaire d'ajout/édition d'événement et le formulaire d'anniversaire manuel restent accessibles depuis l'Agenda (section repliable ou zone dédiée sous le calendrier), gardés par `auth_can_manage_calendar()` / `auth_can_edit_evenement()`. Rien de nouveau côté validation (`CalendrierService::saveEvent`/`saveBirthday` inchangés).
9. **Suppression `Views/pages/calendrier.php` / `anniversaires.php`** : `calendrier.php` est **conservé** (il rend encore la fiche événement en `mode==='fiche'` via `evenementFiche()`). Seuls ses `mode==='list'` ne sont plus atteints. `anniversaires.php` peut être supprimée (plus aucun chemin ne la rend) — ou conservée inerte ; le plan tranchera pour la suppression (rien ne la référence).

## 4. Architecture

### Backend (couches strictes, code neuf minimal)

- **`EvenementRepository::overlapping(string $from, string $to): array`** *(nouveau)* — `SELECT e.*, ru.prenom AS resp_prenom, ru.nom AS resp_nom FROM evenements e LEFT JOIN users ru ON ru.id = e.responsable_id WHERE e.date_debut <= ? AND (e.date_fin >= ? OR e.date_fin IS NULL) ORDER BY e.date_debut ASC`. Paramètres `[$to, $from]`. Couvre les événements commençant avant le mois et finissant pendant/après.
- **`CalendrierService::agendaForMonth(int $year, int $month): array`** *(nouveau)* — renvoie :
  ```php
  [
    'ym'      => 'YYYY-MM',
    'byDate'  => [ 'YYYY-MM-DD' => ['events' => [ <ev normalisé> ], 'birthdays' => [ <anniv> ]], … ],
    'counts'  => [ 'YYYY-MM-DD' => ['events' => int, 'birthdays' => int], … ], // pour les indicateurs
  ]
  ```
  Construit `byDate` en itérant, pour chaque événement, chaque `Y-m-d` de `max(DATE(date_debut), 1er du mois)` à `min(DATE(date_fin ?? date_debut), dernier du mois)`. Chaque `<ev normalisé>` : `{id, nom, lieu, resp, heure_debut:?string 'HH:MM', heure_fin:?string, date_debut, date_fin, is_multi_day:bool}`. Les anniversaires : `birthdays()` filtré `mois === $month`, indexé par `sprintf('%04d-%02d-%02d', $year, $mois, $jour)`.
- **`CalendrierService::agendaForDate(string $date): array`** *(nouveau)* — `['events' => [...], 'birthdays' => [...]]` pour un `Y-m-d`. Réutilisé par le rendu serveur du panneau (fallback sans JS) et testable isolément. Implémenté via `overlapping($date.' 00:00:00', $date.' 23:59:59')` + `birthdays()` filtré.

### Contrôleur

- **`CalendrierController::agenda(): void`** *(nouveau)* :
  - `!current_user()` → `redirect(page=apropos)`.
  - `$ym` = `Request::get('ym')` validé `^\d{4}-\d{2}$`, défaut = mois courant.
  - `$date` = `Request::get('date')` validé `Y-m-d` et dans le mois `$ym`, défaut = aujourd'hui si dans `$ym`, sinon le 1er du mois.
  - `$month = agendaForMonth($year, $monthNo)` ; `$dayDetail = agendaForDate($date)`.
  - `render_page(SECTION_LABELS['agenda'], view('pages/agenda', [... 'canManage' => auth_can_manage_calendar(), 'ym', 'date', 'today', 'month', 'dayDetail', 'weeks' => <grille 6×7 pré-calculée>, 'responsables', 'monthsFr' => MONTHS_FR, 'csrf', 'errors' => [], 'old' => [] ]))`.
- **`CalendrierController::evenements()`** *(modifié)* : si `?evt=` → `evenementFiche()` (inchangé) ; sinon `redirect(page=agenda)`.
- **`CalendrierController::anniversaires()`** *(modifié)* : `redirect(page=agenda)`.

### Vue

- **`Views/pages/agenda.php`** *(nouveau)* :
  1. Barre supérieure : `<input type="date" name="date">` « Aller à une date » (form GET → recharge avec `ym` recalculé) + bouton `Aujourd'hui`.
  2. En-tête calendrier : `‹` (lien `?ym=<mois-1>`), `Septembre 2026`, `›` (lien `?ym=<mois+1>`). `aria-label` sur chaque bouton.
  3. Grille : `<table>` ou CSS grid 7 colonnes, en-tête `Lun…Dim`. Chaque cellule = `<button type="button" data-date="YYYY-MM-DD" aria-label="15 septembre 2026">` (ou `<a href>` en no-JS) portant : classe `is-today`, `is-selected`, `is-adjacent` ; sous le numéro, jusqu'à 2 points `<span class="dot dot-event">` / `dot-birthday` selon `counts`.
  4. Panneau détail (`#agenda-day`) sous la grille : titre « Mardi 15 septembre 2026 », section **ÉVÉNEMENTS** (heure — nom — lieu, lien vers `?page=calendrier&evt=<id>`), section **ANNIVERSAIRES** (`🎂 Nom — 35 ans` si `age`), état vide par section (`Aucun événement.` / `Aucun anniversaire.`) et global (`Aucune activité programmée pour cette journée.`).
  5. Légende discrète : `● Événement`  `● Anniversaire`.
  6. Si `$canManage` : section « Nouvel événement » / « Nouvel anniversaire » (mêmes champs que les formulaires actuels de `calendrier.php`/`anniversaires.php`, mêmes actions `save_evenement`/`save_anniversaire`).
  7. `<script type="application/json" id="agenda-data"><?= json_encode($month['byDate'], JSON_UNESCAPED_UNICODE) ?></script>` — données du mois pour le JS.
- **`assets/js/agenda.js`** *(nouveau, vanilla)* : au clic sur une cellule → lit `#agenda-data`, remplace le contenu de `#agenda-day`, met à jour `is-selected`, `history.replaceState` avec `?date=`. Restaure `sessionStorage['agenda:scroll']` au chargement, l'écrit avant `beforeunload`. Aucune dépendance. Chargé via `<script src="assets/js/agenda.js" defer>` (ajouté dans `layout.php` conditionnellement à `$page === 'agenda'`, ou inclus toujours — trancher au plan ; préférence : conditionnel).
- **`assets/css/agenda.css`** *(nouveau)* + `@import` dans `app.css`. Variables de `variables.css` uniquement. Responsive : desktop grille confortable ; mobile cellules ≥ 44px, pas de débordement horizontal, panneau détail pleine largeur.

### Nav & routes

- `Config/constants.php` : `SECTION_LABELS['agenda'] = 'Agenda'`, `SECTION_ICONS['agenda'] = '<i class="fa-solid fa-calendar-days"></i>'`. Retirer `'calendrier'` et `'anniversaires'` de `NAV_ORDER`, ajouter `'agenda'` (même position). Laisser les libellés/icônes `calendrier`/`anniversaires` en place (fil d'Ariane, `SECTION_LABELS[$page]`).
- `Routes/web.php` : `Router::get('agenda', CalendrierController::class, 'agenda');` ajouté. `calendrier` / `anniversaires` conservées (redirection / fiche).
- `Views/layouts/layout.php` : le bloc hoist `if ($user && !$isAdmin && auth_can_manage_calendar()) { foreach (['calendrier','anniversaires'] …) }` devient `foreach (['agenda'] …)`.

## 5. Sécurité

- Toutes les écritures passent par les actions existantes (`check_csrf()` global + `auth_can_manage_calendar()` / `auth_can_edit_evenement()` déjà en place). SP-1 ne touche pas à ces gardes.
- Le nouvel écran `agenda` exige une session. `agendaForMonth`/`agendaForDate` sont en lecture seule.
- `json_encode` du `byDate` : les chaînes (noms d'événements, d'anniversaires) sont des données de la BDD ; `json_encode` échappe correctement pour un `<script type="application/json">` — mais ajouter `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` par prudence, et lire via `JSON.parse(document.getElementById('agenda-data').textContent)`.

## 6. Tests (pas de test runner — `php -l` + scripts d'assertion + smoke render)

- `EvenementRepository::overlapping` : un événement `10→12 sept` remonte pour une fenêtre `01→30 sept` ; un événement `25 août → 03 sept` remonte pour `01→30 sept` ; un événement `01→05 oct` ne remonte pas.
- `agendaForMonth(2026, 9)` : `byDate['2026-09-10']`, `['2026-09-11']`, `['2026-09-12']` contiennent tous l'événement multi-jours ; `counts` cohérents ; un anniversaire membre (`users.date_naissance` au 15/09) apparaît dans `byDate['2026-09-15']['birthdays']`.
- Février non bissextile : `agendaForMonth(2027, 2)` → 28 jours ; bissextile : `agendaForMonth(2028, 2)` → 29.
- Fin/début d'année : `?ym=2026-12` `›` → `2027-01` ; `?ym=2026-01` `‹` → `2025-12` (URL construite côté contrôleur/vue).
- `agendaForDate('2026-09-15')` renvoie `events` + `birthdays` séparés ; jour vide → deux listes vides.
- `CalendrierController::agenda` : sans `?ym` → mois courant + aujourd'hui sélectionné ; `?date` hors du mois → 1er du mois sélectionné.
- Redirections : `?page=calendrier` (sans `evt`) → 302 `agenda` ; `?page=calendrier&evt=<id>` → fiche ; `?page=anniversaires` → 302 `agenda`.
- Smoke render `pages/agenda` avec données minimales : `save_evenement` / `save_anniversaire` présents si `canManage`, `#agenda-data` bien formé.
- Non-régression : `save_evenement`/`delete_evenement`/`save_anniversaire`/`delete_anniversaire` fonctionnent, redirigent désormais vers `page=agenda` ; fiche événement + pointage présence événement intacts ; `birthdays()` inchangé.

## 7. Hors périmètre

- Vue « semaine » ou « jour » plein écran.
- Récurrence d'événements.
- Notifications / rappels d'anniversaire.
- Export iCal.
- Toute modification du système de présences (SP-2..SP-5).

## 8. Livrables

- Spec : ce document.
- Plan : `docs/superpowers/plans/2026-09-03-sp1-agenda-unifie.md`.
