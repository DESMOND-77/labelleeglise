<?php

namespace App\Auth;

use App\Core\Query;
use App\Repositories\BacentaRepository;

/**
 * RBAC — périmètre du compte courant, sections autorisées, accès porte d'entrée.
 */
class RbacService
{
    private AuthenticationService $auth;
    private BacentaRepository $bacentas;

    public function __construct(?AuthenticationService $auth = null, ?BacentaRepository $bacentas = null)
    {
        $this->auth = $auth ?? new AuthenticationService();
        $this->bacentas = $bacentas ?? new BacentaRepository();
    }

    /** Bacentas dont l'utilisateur est responsable. */
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
        if ($u['role'] === 'responsable') {
            return [
                'kind' => 'responsable',
                'bacentas' => $this->myBacentaIds((int) $u['id']),
            ];
        }
        if (in_array($u['role'], BERGER_ROLES, true)) {
            return [
                'kind' => 'berger',
                'user_id' => (int) $u['id'],
                'bacenta_id' => $u['bacenta_id'] ? (int) $u['bacenta_id'] : null,
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
            $sections = ['apropos', 'centresPresentation', 'bergerFiche', 'suiviBergers'];
            if ($scope['bacenta_id']) {
                $sections[] = 'bacentas';
            }
            return $sections;
        }
        return ['apropos', 'centresPresentation', 'bacentas', 'centres', 'cultes', 'basontas'];
    }

    /** true si l'utilisateur peut gérer l'entité demandée. */
    public function canManageEntity(string $type, int $id): bool
    {
        $u = $this->auth->currentUser();
        if (!$u || $u['role'] === 'admin') {
            return $u !== null;
        }
        $scope = $this->scope();
        if (!$scope) {
            return false;
        }
        if ($scope['kind'] === 'berger') {
            return $type === 'bacenta' && $scope['bacenta_id'] === $id;
        }
        if ($scope['kind'] === 'responsable') {
            if ($type === 'bacenta') {
                return in_array($id, $scope['bacentas'], true);
            }
            $table = $type . 's';
            $row = Query::one("SELECT id FROM $table WHERE id = ? AND responsable_id = ?", [$id, $u['id']]);
            return (bool) $row;
        }
        return false;
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
        if ($scope && $scope['kind'] === 'responsable') {
            if (in_array($section, ['bacentas', 'cultes', 'basontas'], true) && $this->canManageEntity(($section === 'bacentas') ? 'bacenta' : $section, (int) $id)) {
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
