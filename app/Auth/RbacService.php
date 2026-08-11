<?php

namespace App\Auth;

use App\Core\Query;
use App\Repositories\BacentaRepository;
use App\Services\ResponsibilityService;

/**
 * RBAC — périmètre du compte courant, sections autorisées, accès porte d'entrée.
 *
 * Le "scope" retourné ici gouverne uniquement la NAVIGATION / le
 * verrouillage historique (leader/pasteur/reverant/berger/ms limités à leur
 * bacenta d'APPARTENANCE — users.bacenta_id, comportement préservé).
 * Les décisions d'autorisation fines (CRUD sur une ressource précise)
 * passent par AuthorizationService, qui consulte la table
 * `responsibilities` — jamais un simple rôle. Voir docs/authorization.md.
 */
class RbacService
{
    private AuthenticationService $auth;
    private BacentaRepository $bacentas;
    private ResponsibilityService $responsibilities;
    private AuthorizationService $authz;

    public function __construct(
        ?AuthenticationService $auth = null,
        ?BacentaRepository $bacentas = null,
        ?ResponsibilityService $responsibilities = null,
        ?AuthorizationService $authz = null
    ) {
        $this->auth = $auth ?? new AuthenticationService();
        $this->bacentas = $bacentas ?? new BacentaRepository();
        $this->responsibilities = $responsibilities ?? new ResponsibilityService();
        $this->authz = $authz ?? new AuthorizationService($this->responsibilities);
    }

    /** Bacentas dont l'utilisateur est responsable (legacy : colonne responsable_id — conservé pour le kind 'responsable' historique). */
    public function myBacentaIds(int $userId): array
    {
        return $this->bacentas->forResponsible($userId);
    }

    /**
     * Périmètre du compte courant (voir get_user_scope).
     */
    public function scope(): ?array
    {
        $u = $this->auth->currentUser();
        if (!$u) {
            return null;
        }
        if ($u['role'] === 'admin') {
            return null;
        }
        // Rôle hérité 'responsable' : ne devrait plus exister en base après
        // migration (voir Database/Migrations…), conservé par sécurité/rollback.
        if ($u['role'] === 'responsable') {
            return [
                'kind' => 'responsable',
                'bacentas' => $this->myBacentaIds((int) $u['id']),
            ];
        }
        if (in_array($u['role'], BERGER_ROLES, true)) {
            $userId = (int) $u['id'];
            return [
                'kind' => 'berger',
                'user_id' => $userId,
                'bacenta_id' => $u['bacenta_id'] ? (int) $u['bacenta_id'] : null,
                // Responsabilités réelles (table `responsibilities`), en plus
                // du verrouillage historique sur la bacenta d'appartenance.
                'responsible_center_ids'  => $this->responsibilities->centerIdsFor($userId),
                'responsible_bacenta_ids' => $this->responsibilities->allAccessibleBacentaIds($userId),
                'responsible_cult_ids'    => $this->responsibilities->cultIdsFor($userId),
            ];
        }
        return null;
    }

    public function isBergerScopeLocked(): bool
    {
        $scope = $this->scope();
        return (bool) ($scope && $scope['kind'] === 'berger');
    }

    /** Sections autorisées : null = aucune restriction (admin). */
    public function allowedSections(): ?array
    {
        $u = $this->auth->currentUser();
        if (!$u) {
            return null;
        }
        if ($u['role'] === 'admin') {
            return null;
        }
        $scope = $this->scope();
        if (!$scope) {
            return ['apropos', 'centresPresentation'];
        }
        if ($scope['kind'] === 'berger') {
            // §22-23 : Informations Église/Centres toujours visibles ; §20 :
            // fiche + suivi personnels toujours visibles pour ces rôles.
            $sections = ['apropos', 'centresPresentation', 'bergerFiche', 'suiviBergers'];
            // Bacenta d'appartenance (comportement historique préservé).
            if ($scope['bacenta_id']) {
                $sections[] = 'bacentas';
            }
            // §17/§23 : responsabilité réelle (table `responsibilities`) →
            // accès aux sections de gestion correspondantes.
            if (!empty($scope['responsible_bacenta_ids']) && !in_array('bacentas', $sections, true)) {
                $sections[] = 'bacentas';
            }
            if (!empty($scope['responsible_center_ids'])) {
                $sections[] = 'centres';
            }
            if (!empty($scope['responsible_cult_ids'])) {
                $sections[] = 'cultes';
            }
            return $sections;
        }
        return ['apropos', 'centresPresentation', 'bacentas', 'centres', 'cultes', 'basontas'];
    }

    /** true si l'utilisateur peut gérer l'entité demandée (délègue à AuthorizationService — voir docs/authorization.md). */
    public function canManageEntity(string $type, int $id): bool
    {
        $u = $this->auth->currentUser();
        if (!$u) {
            return false;
        }
        if ($u['role'] === 'admin') {
            return true;
        }
        return match ($type) {
            'centre', 'center' => $this->authz->canManageCenter($u, $id),
            'bacenta' => $this->authz->canManageBacenta($u, $id),
            'culte', 'cult' => $this->authz->canManageCulte($u, $id),
            'basonta' => $this->authz->canManageBasonta($u, $id),
            default => false,
        };
    }

    /** Navigation par défaut du compte lié. */
    public function scopeTarget(): ?array
    {
        $u = $this->auth->currentUser();
        if (!$u) {
            return null;
        }
        if ($u['role'] === 'admin') {
            return null;
        }
        $scope = $this->scope();
        if (!$scope) {
            return null;
        }
        if ($scope['kind'] === 'berger') {
            return ['page' => 'bergerFiche', 'membre' => $u['id']];
        }
        $bacentas = $scope['bacentas'];
        if ($bacentas) {
            return ['page' => 'bacentas', 'id' => $bacentas[0]];
        }
        return ['page' => 'accueil'];
    }

    /* ---------- Porte d'accès ---------- */

    public function accessKey(string $section, $id = null): string
    {
        return $section . ':' . ($id ?? '');
    }

    public function hasVerifiedAccess(string $section, $id = null): bool
    {
        $u = $this->auth->currentUser();
        if (!$u) {
            return false;
        }
        if ($u['role'] === 'admin') {
            return true;
        }
        $scope = $this->scope();
        if ($scope && $scope['kind'] === 'berger' && $section === 'bacentas' && $scope['bacenta_id'] === (int) $id) {
            return true;
        }
        if ($scope && in_array($scope['kind'], ['responsable', 'berger'], true)) {
            $entityType = match ($section) {
                'bacentas' => 'bacenta',
                'cultes'   => 'culte',
                'basontas' => 'basonta',
                'centres'  => 'centre',
                default    => null,
            };
            if ($entityType && $this->canManageEntity($entityType, (int) $id)) {
                return true;
            }
        }
        return in_array($this->accessKey($section, $id), $_SESSION['verified'] ?? [], true);
    }

    public function grantAccess(string $section, $id = null): void
    {
        $_SESSION['verified'][] = $this->accessKey($section, $id);
    }
}
