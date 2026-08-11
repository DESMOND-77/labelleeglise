# Responsabilités

## Modèle

Une seule table polymorphe, `responsibilities` — pas trois tables séparées
(`center_responsibilities`, `bacenta_responsibilities`,
`cult_responsibilities`) comme suggéré à titre d'exemple par la spec §8/§14 :
un modèle unique rend triviale l'extension future (département, province,
activité, événement, groupe — spec §49) sans nouvelle migration de schéma.

```sql
CREATE TABLE responsibilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    responsibility_type VARCHAR(30) NOT NULL DEFAULT 'manager',
    target_type VARCHAR(30) NOT NULL,   -- 'center' | 'bacenta' | 'cult' | 'basonta' | (futur)
    target_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_responsibility (user_id, responsibility_type, target_type, target_id),
    CONSTRAINT fk_resp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

`target_type` est un `VARCHAR`, pas un `ENUM` : ajouter un nouveau type de
cible ne nécessite **jamais** de migration de schéma, seulement une entrée
dans `ResponsibilityService::TARGET_TABLES` + `eligibleRoles()`.

Pas de `FOREIGN KEY` sur `target_id` (relation polymorphe) : l'existence de
la cible est validée au niveau service
(`ResponsibilityService::targetExists()`), jamais par la base.

## Cibles supportées aujourd'hui

| target_type | table | rôles éligibles à recevoir | remarque |
|---|---|---|---|
| `center`  | `centres`  | `berger`, `ms`, `pasteur` | **Nouvelle capacité** : n'existait pas avant ce remaniement. |
| `bacenta` | `bacentas` | `berger`, `ms`, `pasteur` | Hérite aussi d'une responsabilité de centre (voir périmètre ci-dessous). |
| `cult`    | `cultes`   | `pasteur`, `reverant` | Jamais `leader`/`berger`/`ms` (spec §24-25, contrairement à l'ancien formulaire qui l'autorisait à tort). |
| `basonta` | `basontas` | `berger`, `ms`, `pasteur` | Migré depuis l'ancien `responsable_id`, choix (non explicitement spécifié) aligné sur centre/bacenta — voir "déviations" dans le rapport de livraison. |

## Héritage du périmètre (spec §17)

```
Centre A
 ├── Bacenta 1
 ├── Bacenta 2
 └── Bacenta 3
```

Un utilisateur responsable du **Centre A** peut administrer Centre A **et**
toutes ses bacentas (1, 2, 3), sans ligne `responsibilities` directe sur
chacune. Implémenté par
`ResponsibilityService::isResponsibleForBacenta($userId, $bacentaId)` :

```php
public function isResponsibleForBacenta(int $userId, int $bacentaId): bool
{
    if ($this->repo->isResponsibleFor($userId, 'bacenta', $bacentaId)) {
        return true; // responsabilité directe
    }
    $centreId = Query::value('SELECT centre_id FROM bacentas WHERE id = ?', [$bacentaId]);
    return $centreId ? $this->isResponsibleForCenter($userId, (int) $centreId) : false;
}
```

Un responsable de Centre A **ne peut jamais** toucher au Centre B ou à ses
bacentas — vérifié par `AuthorizationService::canManageCenter()` /
`canManageBacenta()`, jamais en faisant confiance à un id d'URL/formulaire
(voir `docs/authorization.md`, section IDOR).

## Multiples responsables, multiples responsabilités (spec §9/§30)

- Une structure peut avoir **plusieurs** responsables (`UNIQUE KEY` porte
  sur `(user_id, responsibility_type, target_type, target_id)`, pas sur
  `(target_type, target_id)` seul).
- Un utilisateur peut avoir **plusieurs** responsabilités simultanées
  (centre + bacenta + culte…), sans limite artificielle.

## Colonne héritée `responsable_id`

`bacentas.responsable_id`, `basontas.responsable_id`, `cultes.responsable_id`
sont **conservées** (spec §41 : ne jamais supprimer brutalement une ancienne
colonne) comme reflet dénormalisé de confort pour les lectures existantes
(affichage "Responsable : X" dans les listes). Elles sont synchronisées
automatiquement par `ResponsibilityService::syncLegacyResponsableId()` à
chaque affectation/révocation (dernier responsable affecté, ou `NULL` si
aucun). **Aucune décision d'autorisation ne lit jamais cette colonne** —
uniquement la table `responsibilities`, via `AuthorizationService`.

## API (`ResponsibilityRepository` / `ResponsibilityService`)

```php
$service = new App\Services\ResponsibilityService();

$service->assign($userId, 'center', $centerId);   // valide rôle + existence + doublon
$service->revoke($userId, 'center', $centerId);
$service->revokeById($responsibilityId);

$service->isResponsibleForCenter($userId, $centerId);
$service->isResponsibleForBacenta($userId, $bacentaId); // avec héritage
$service->listForUser($userId);
$service->listForTarget('center', $centerId);

$service->reconcileForNewRole($userId, $newRole); // spec §31
```

## Intégrité (spec §38)

- `assign()` refuse : utilisateur inexistant, rôle inéligible pour ce
  `target_type`, cible inexistante — retourne
  `['ok' => false, 'error' => '...']` (jamais d'exception silencieuse).
- Doublon empêché par la contrainte `UNIQUE` + un pré-check applicatif.
- Suppression d'une structure (`delete_centre`/`delete_bacenta`/…) nettoie
  explicitement les lignes `responsibilities` associées (pas de FK
  `target_id` possible sur une relation polymorphe) — voir
  `BacentaRepository::delete()`, `CentreRepository::delete()`,
  `CulteRepository::delete()`, `BasontaRepository::delete()`.
- Suppression d'un utilisateur : `ON DELETE CASCADE` sur `user_id`
  (`fk_resp_user`) supprime automatiquement ses responsabilités.
