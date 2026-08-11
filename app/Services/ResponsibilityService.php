<?php

namespace App\Services;

use App\Core\Query;
use App\Repositories\ResponsibilityRepository;
use App\Repositories\UserRepository;

/**
 * Règles métier de la couche "responsabilité" (ROLE ≠ RESPONSABILITÉ ≠
 * PÉRIMÈTRE — voir prompts/REMANIEMENT…md).
 *
 * Cibles supportées aujourd'hui : 'center' (table centres), 'bacenta'
 * (table bacentas), 'cult' (table cultes). Le modèle (table SQL polymorphe)
 * permet d'ajouter facilement de nouveaux target_type sans migration.
 */
class ResponsibilityService
{
    /** target_type => [table SQL, rôles éligibles à RECEVOIR cette responsabilité]. */
    private const TARGET_TABLES = [
        'center'  => 'centres',
        'bacenta' => 'bacentas',
        'cult'    => 'cultes',
        'basonta' => 'basontas',
    ];

    private ResponsibilityRepository $repo;
    private UserRepository $users;

    public function __construct(?ResponsibilityRepository $repo = null, ?UserRepository $users = null)
    {
        $this->repo = $repo ?? new ResponsibilityRepository();
        $this->users = $users ?? new UserRepository();
    }

    /** Rôles autorisés à recevoir une responsabilité pour ce type de cible. */
    public function eligibleRoles(string $targetType): array
    {
        return match ($targetType) {
            'center', 'bacenta', 'basonta' => CENTER_BACENTA_RESPONSIBILITY_ROLES,
            'cult' => CULT_RESPONSIBILITY_ROLES,
            default => [],
        };
    }

    public function isKnownTargetType(string $targetType): bool
    {
        return isset(self::TARGET_TABLES[$targetType]);
    }

    private function targetTable(string $targetType): ?string
    {
        return self::TARGET_TABLES[$targetType] ?? null;
    }

    public function targetExists(string $targetType, int $targetId): bool
    {
        $table = $this->targetTable($targetType);
        if (!$table) {
            return false;
        }
        return (bool) Query::value("SELECT COUNT(*) FROM `$table` WHERE id = ?", [$targetId]);
    }

    /**
     * Affecte une responsabilité après validation complète côté serveur :
     * utilisateur existant, rôle éligible, cible existante, pas de doublon.
     *
     * @return array{ok:bool, error:?string}
     */
    public function assign(int $userId, string $targetType, int $targetId, string $responsibilityType = 'manager'): array
    {
        if (!$this->isKnownTargetType($targetType)) {
            return ['ok' => false, 'error' => 'unknown_target_type'];
        }
        $user = $this->users->find($userId);
        if (!$user) {
            return ['ok' => false, 'error' => 'unknown_user'];
        }
        if (!in_array($user['role'], $this->eligibleRoles($targetType), true)) {
            return ['ok' => false, 'error' => 'ineligible_role'];
        }
        if (!$this->targetExists($targetType, $targetId)) {
            return ['ok' => false, 'error' => 'unknown_target'];
        }

        $this->repo->assign($userId, $targetType, $targetId, $responsibilityType);
        $this->syncLegacyResponsableId($targetType, $targetId);

        return ['ok' => true, 'error' => null];
    }

    public function revoke(int $userId, string $targetType, int $targetId, string $responsibilityType = 'manager'): void
    {
        $this->repo->revoke($userId, $targetType, $targetId, $responsibilityType);
        $this->syncLegacyResponsableId($targetType, $targetId);
    }

    /** Révoque par id de ligne (utilisé par les boutons "retirer" de l'UI). */
    public function revokeById(int $responsibilityId): void
    {
        $row = $this->repo->find($responsibilityId);
        if (!$row) {
            return;
        }
        $this->repo->revokeById($responsibilityId);
        $this->syncLegacyResponsableId($row['target_type'], (int) $row['target_id']);
    }

    /**
     * Maintient bacentas.responsable_id / basontas.responsable_id /
     * cultes.responsable_id synchronisés avec la dernière responsabilité
     * affectée (colonne dénormalisée de confort pour les lectures/affichages
     * existants — spec §41 : ne jamais supprimer brutalement une ancienne
     * colonne). Les décisions d'autorisation, elles, ne lisent JAMAIS cette
     * colonne : uniquement la table `responsibilities`.
     */
    private function syncLegacyResponsableId(string $targetType, int $targetId): void
    {
        $table = match ($targetType) {
            'bacenta' => 'bacentas',
            'basonta' => 'basontas',
            'cult'    => 'cultes',
            default   => null,
        };
        if (!$table) {
            return;
        }
        $latest = Query::one(
            'SELECT user_id FROM responsibilities WHERE target_type = ? AND target_id = ? ORDER BY created_at DESC, id DESC LIMIT 1',
            [$targetType, $targetId]
        );
        Query::run("UPDATE `$table` SET responsable_id = ? WHERE id = ?", [$latest['user_id'] ?? null, $targetId]);
    }

    public function listForUser(int $userId): array
    {
        return $this->repo->listForUser($userId);
    }

    public function listForTarget(string $targetType, int $targetId): array
    {
        return $this->repo->listForTarget($targetType, $targetId);
    }

    public function isResponsibleForCenter(int $userId, int $centerId): bool
    {
        return $this->repo->isResponsibleFor($userId, 'center', $centerId);
    }

    /**
     * Héritage de périmètre (spec §17) : un responsable de centre a
     * implicitement accès à toutes les bacentas de ce centre, en plus
     * d'une éventuelle responsabilité directe de bacenta.
     */
    public function isResponsibleForBacenta(int $userId, int $bacentaId): bool
    {
        if ($this->repo->isResponsibleFor($userId, 'bacenta', $bacentaId)) {
            return true;
        }
        $centreId = Query::value('SELECT centre_id FROM bacentas WHERE id = ?', [$bacentaId]);
        if (!$centreId) {
            return false;
        }
        return $this->isResponsibleForCenter($userId, (int) $centreId);
    }

    public function isResponsibleForCult(int $userId, int $cultId): bool
    {
        return $this->repo->isResponsibleFor($userId, 'cult', $cultId);
    }

    public function isResponsibleForBasonta(int $userId, int $basontaId): bool
    {
        return $this->repo->isResponsibleFor($userId, 'basonta', $basontaId);
    }

    /** IDs de centres/bacentas/cultes/basontas dont l'utilisateur est responsable (accès direct, sans héritage). */
    public function centerIdsFor(int $userId): array
    {
        return $this->repo->targetIdsForUser($userId, 'center');
    }

    public function bacentaIdsFor(int $userId): array
    {
        return $this->repo->targetIdsForUser($userId, 'bacenta');
    }

    public function cultIdsFor(int $userId): array
    {
        return $this->repo->targetIdsForUser($userId, 'cult');
    }

    public function basontaIdsFor(int $userId): array
    {
        return $this->repo->targetIdsForUser($userId, 'basonta');
    }

    /** Bacentas accessibles par héritage + affectation directe (spec §17). */
    public function allAccessibleBacentaIds(int $userId): array
    {
        $direct = $this->bacentaIdsFor($userId);
        $centerIds = $this->centerIdsFor($userId);
        if (!$centerIds) {
            return $direct;
        }
        $placeholders = implode(',', array_fill(0, count($centerIds), '?'));
        $inherited = Query::all("SELECT id FROM bacentas WHERE centre_id IN ($placeholders)", $centerIds);
        $inheritedIds = array_map(fn($r) => (int) $r['id'], $inherited);
        return array_values(array_unique(array_merge($direct, $inheritedIds)));
    }

    /**
     * Changement de rôle (spec §31) : révoque toute responsabilité devenue
     * incohérente avec le nouveau rôle. Choix documenté : auto-révocation +
     * journalisation (plutôt que blocage), pour ne jamais empêcher un admin
     * de changer un rôle — voir docs/roles-and-permissions.md.
     *
     * @return array<string,int> nombre de responsabilités révoquées par target_type
     */
    public function reconcileForNewRole(int $userId, string $newRole): array
    {
        $revoked = [];
        foreach ($this->repo->listForUser($userId) as $row) {
            $targetType = $row['target_type'];
            $eligible = $this->eligibleRoles($targetType);
            if ($eligible && !in_array($newRole, $eligible, true)) {
                $this->repo->revokeById((int) $row['id']);
                $this->syncLegacyResponsableId($targetType, (int) $row['target_id']);
                $revoked[$targetType] = ($revoked[$targetType] ?? 0) + 1;
            }
        }
        return $revoked;
    }
}
