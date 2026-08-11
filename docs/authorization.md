# Autorisation

## Chaîne de responsabilité (spec §42)

```
Controller
   ↓
AuthorizationService   (app/Auth/AuthorizationService.php)
   ↓
Role (Config/permissions.php) + Responsibility (table `responsibilities`,
      via ResponsibilityService) + Scope (héritage centre → bacenta)
```

`RbacService` (app/Auth/RbacService.php) reste la source de la
**navigation** (sections visibles, verrouillage historique
leader/pasteur/reverant/berger/ms sur leur bacenta d'appartenance) ; il
délègue à `AuthorizationService` pour toute décision CRUD fine.

Les wrappers globaux de `app/Auth/compat.php` (`current_user()`,
`get_user_scope()`, `can_manage_entity()`, …) **n'ont pas changé de nom ni
de signature** — utilisés par des dizaines de fichiers `app/Compat/*.php` et
`Views/**`. Leur implémentation interne délègue désormais aux nouveaux
services.

## API

```php
use App\Auth\AuthorizationService;

$auth = new AuthorizationService();

$auth->hasRole($user, 'berger');
$auth->hasPermission($user, 'weekly_followup.manage_own');
$auth->can($user, 'center.manage_assigned', $centerId);   // avec ressource
$auth->can($user, 'view_church_information');              // sans ressource

$auth->isResponsibleForCenter($user, $centerId);
$auth->isResponsibleForBacenta($user, $bacentaId);          // héritage inclus
$auth->isResponsibleForCult($user, $cultId);

$auth->canManageCenter($user, $centerId);
$auth->canManageBacenta($user, $bacentaId);   // + comportement historique leader
$auth->canManageCulte($user, $cultId);        // alias canManageCult()
$auth->canManageMember($user, $memberId);     // remonte membre → bacenta → centre

$auth->canManageResponsibilities($user);      // admin uniquement
$auth->isAdmin($user);
```

Équivalents globaux (utilisés dans les vues/compat) dans
`app/Auth/compat.php` : `auth_can()`, `auth_has_permission()`,
`auth_can_manage_center()`, `auth_can_manage_bacenta()`,
`auth_can_manage_culte()`, `auth_can_manage_basonta()`,
`auth_can_manage_member()`, `auth_can_manage_responsibilities()`,
`auth_is_responsible_for_center()`, `auth_is_responsible_for_bacenta()`,
`auth_is_responsible_for_cult()`.

## Règle non négociable (spec §29)

```php
// INTERDIT — un rôle ne code jamais une structure :
if ($user['role'] === 'responsable_centre') { ... }
$user['role'] = 'berger_centre';

// CORRECT :
$auth->hasRole($user, 'berger') && $auth->isResponsibleForCenter($user, $centerId);
```

## Contrôle serveur uniquement (spec §34, §40)

Aucun bouton masqué côté vue n'est considéré comme une protection. Toute
action d'écriture (`ActionsController::postAction`) et toute suppression
(`ActionsController::getAction`, dispatchée par `index.php` **avant** la
vérification de session — voir front controller) revérifie explicitement :

```
current_user() existe
  → rôle a la permission requise
    → l'utilisateur est effectivement responsable de LA ressource ciblée
      (jamais seulement "de ce type de ressource en général")
```

Exemple représentatif (`ActionsController::postAction`, case `point_culte`) :

```php
$culte = (int) ($_POST['culte'] ?? 0);
if (!$culte || !auth_can_manage_culte($culte)) {
    $this->deny(); // redirige vers apropos — même convention que AdminMiddleware
}
```

### IDOR (spec §40)

`/index.php?page=centres&id=5` : un responsable du Centre 3 ne peut pas
accéder au Centre 5 en changeant l'id dans l'URL — `render_centre_detail()`
appelle `has_verified_access('centres', $centreId)`, qui route vers
`AuthorizationService::canManageCenter()`, qui vérifie la ligne
`responsibilities` réelle (jamais l'id soumis pris pour argent comptant).
Un membre remonté ne fait jamais confiance à son propre `bacenta_id` déclaré
côté client : `canManageMember()` relit `bacenta_id` en base à partir de
l'id du membre, puis vérifie le périmètre sur cette valeur.

### Deux bugs critiques corrigés par ce remaniement

1. **`save_suivi` (fiche hebdomadaire)** — `$membre` était lu directement
   depuis `$_POST` sans aucune vérification : n'importe quel compte
   authentifié pouvait écraser le `suivi_hebdo` d'un autre utilisateur.
   Corrigé : `$membre === current_user()['id']` obligatoire, sauf admin.
2. **`save_responsable` / `assign_responsibility`** — aucune autorisation
   au-delà du CSRF global : n'importe quel compte authentifié pouvait
   réaffecter le responsable d'un bacenta/basonta/culte. Corrigé :
   `auth_can_manage_responsibilities()` (admin uniquement) requis, en plus
   d'une validation métier complète côté `ResponsibilityService::assign()`
   (rôle éligible, cible existante, pas de doublon).

## Périmètre "porte d'accès" (`render_gate` / `verify_access`)

Distinct de l'autorisation ci-dessus : c'est un écran de confidentialité
préexistant (reconfirmer son propre mot de passe avant de consulter une
bacenta/un culte/un basonta partagé sur un poste commun), pas une frontière
de sécurité — n'importe quel compte authentifié peut lever ce verrou en
reconfirmant SES PROPRES identifiants. La véritable frontière
d'autorisation est toujours côté écriture (`ActionsController`), qui ne
dépend jamais de cet écran. Voir la section "à surveiller" du rapport de
livraison pour une recommandation de durcissement future.
