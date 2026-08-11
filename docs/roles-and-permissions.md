# Rôles et permissions

> Voir aussi `docs/responsibilities.md` (couche responsabilité/périmètre)
> et `docs/authorization.md` (API `AuthorizationService`).

## Principe fondamental

```
ROLE            → capacités générales de l'utilisateur (ce qu'il PEUT faire, en général)
RESPONSABILITÉ  → quelle structure précise il administre (table `responsibilities`)
PÉRIMÈTRE       → jusqu'où s'étendent ses droits sur cette structure (héritage centre → bacenta)
```

Un rôle **n'encode jamais** une structure. Ces valeurs sont **interdites** :
`berger_centre`, `responsable_bacenta`, `responsable_culte`, etc. — voir
`prompts/REMANIEMENT_COMPLET_DU_SYSTÈME_DE_RÔLES_RESPONSABILITÉS_ET_PÉRIMÈTRES.md` §2/§29/§49.

## Rôles actifs (`users.role`)

| Rôle       | Libellé (ROLE_LABELS) | Remarque |
|------------|------------------------|----------|
| `admin`    | Administrateur | Accès global, jamais limité par un périmètre (spec §28). |
| `pasteur`  | Pasteur | Peut recevoir responsabilité centre/bacenta/culte. |
| `reverant` | Révérend | **Orthographe volontairement conservée** (déjà utilisée en base/BERGER_ROLES/Config avant ce remaniement) — ne jamais introduire `reverend` en parallèle. Peut recevoir uniquement une responsabilité de culte. |
| `berger`   | Berger | **Nouveau rôle** (ce remaniement). Peut recevoir responsabilité centre/bacenta. |
| `ms`       | MS | **Nouveau rôle** (ce remaniement), traité à l'identique de `berger` pour les responsabilités. |
| `leader`   | Leader | Comportement historique préservé : gère sa bacenta d'APPARTENANCE (`users.bacenta_id`), pas via le modèle de responsabilités. |
| `membre`   | Membre | Aucune capacité de gestion. |
| `assistant`| Assistant | Rôle hérité, jamais supprimé de l'ENUM ; traité comme `membre` (aucune permission supplémentaire) — vérifié inutilisé en pratique (0 lignes en base au moment du remaniement) mais conservé par prudence. |

`responsable` reste présent dans l'ENUM SQL (`users.role`) **uniquement pour
compatibilité de rollback** — une suppression de valeur ENUM est destructrice
si une ligne y fait encore référence. Il n'est **plus** un rôle actif :
il a été retiré de `ROLE_LABELS` (donc plus sélectionnable dans l'admin) et
toutes les lignes existantes ont été migrées vers `berger` (voir migration).

## Trois regroupements de rôles (jamais conflatés — `Config/constants.php`)

Le projet distinguait auparavant un seul groupe `BERGER_ROLES`. Ce
remaniement le préserve pour compatibilité mais introduit **deux nouveaux
groupes distincts**, car ils ne se recouvrent plus depuis l'ajout de
`berger`/`ms` :

```php
// Fiche berger + suivi hebdo PERSONNELS + bacenta d'appartenance verrouillée
// (comportement historique préservé pour leader/pasteur/reverant).
BERGER_ROLES = ['leader', 'pasteur', 'reverant', 'berger', 'ms']

// Éligibles à RECEVOIR une responsabilité de centre ou de bacenta.
CENTER_BACENTA_RESPONSIBILITY_ROLES = ['berger', 'ms', 'pasteur']

// Éligibles à RECEVOIR une responsabilité de culte.
CULT_RESPONSIBILITY_ROLES = ['pasteur', 'reverant']

// Permission weekly_followup.manage_own (admin inclus : lecture globale).
WEEKLY_FOLLOWUP_ROLES = ['admin', 'pasteur', 'reverant', 'berger', 'ms', 'leader']
```

## Permissions (`Config/permissions.php`)

Config-driven (tableau PHP statique), pas de tables `roles`/`permissions` en
base — voir `CONTRIBUTING.md` (KISS/YAGNI) et `Config/constants.php` pour la
convention déjà en place dans ce projet. `admin` détient `['*']` (toutes les
permissions, sans exception).

| Permission | admin | pasteur | reverant | berger | ms | leader | membre |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `view_church_information` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `view_centers` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `weekly_followup.manage_own` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – |
| `center.manage_assigned` | ✓ | ✓ | – | ✓ | ✓ | – | – |
| `bacenta.manage_assigned` | ✓ | ✓ | – | ✓ | ✓ | – | – |
| `cult.manage_assigned` | ✓ | ✓ | ✓ | – | – | – | – |
| `bacenta.manage_own_membership` (legacy leader) | ✓ | – | – | – | – | ✓ | – |
| `responsibilities.manage` | ✓ | – | – | – | – | – | – |
| `users.manage` | ✓ | – | – | – | – | – | – |

`*` : "manage_assigned" ne suffit jamais seul — il faut EN PLUS détenir la
responsabilité réelle sur la cible précise (voir `docs/responsibilities.md`).

## Exemple concret

```
Jean
 ├── role = berger
 ├── responsable du centre : Centre Franceville   (table responsibilities)
 └── responsable de la bacenta : Bacenta A        (table responsibilities)
```

Jean conserve le rôle `berger`. Être responsable du Centre Franceville ne
crée JAMAIS un rôle `berger_centre_franceville`.

## Vue applicative (`Views/pages/forms/user.php`)

La fiche utilisateur sépare visuellement (spec §32) :

```
Rôle
────────────────
[ Berger ▼ ]              ← <select name="role"> (ROLE_LABELS)

Responsabilités            ← panneau séparé (user_responsibilities_panel())
────────────────
Centres    [ Centre Franceville ] [retirer]
Bacentas   [ Bacenta A ]          [retirer]
Cultes     Aucune
+ formulaires d'ajout filtrés selon le rôle actuel
```

## Changement de rôle (spec §31)

Quand l'admin change le rôle d'un utilisateur (`save_user`), le système
**révoque automatiquement** (jamais silencieusement laissées incohérentes)
toute responsabilité devenue incompatible avec le nouveau rôle — ex. un
pasteur responsable d'un culte devient `berger` → la responsabilité de
culte est retirée (car `berger` n'est pas dans `CULT_RESPONSIBILITY_ROLES`).
Le nombre de responsabilités révoquées est journalisé
(`App\Core\Logger::info`, voir `ActionsController::postAction` case
`save_user`). Choix documenté : **auto-révocation + journalisation**, plutôt
que blocage de la sauvegarde — pour ne jamais empêcher un admin de changer
un rôle en urgence, au prix d'une trace explicite dans les logs.
