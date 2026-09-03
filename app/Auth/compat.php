<?php

/**
 * Wrappers de compatibilité RBAC/Auth pour les vues.
 * Exposent les anciennes fonctions globales (current_user, get_user_scope…)
 * en déléguant aux services Auth.
 */

declare(strict_types=1);

use App\Auth\AuthenticationService;
use App\Auth\AuthorizationService;
use App\Auth\RbacService;
use App\Services\ResponsibilityService;

function auth_service(): AuthenticationService
{
    static $svc = null;
    $svc = $svc ?? new AuthenticationService();
    return $svc;
}

function rbac_service(): RbacService
{
    static $svc = null;
    $svc = $svc ?? new RbacService(auth_service());
    return $svc;
}

function authz_service(): AuthorizationService
{
    static $svc = null;
    $svc = $svc ?? new AuthorizationService();
    return $svc;
}

function responsibility_service(): ResponsibilityService
{
    static $svc = null;
    $svc = $svc ?? new ResponsibilityService();
    return $svc;
}

/* ---------- Nouveaux wrappers d'autorisation (couche rôle/responsabilité/périmètre) ---------- */

/** $auth->can($user, 'permission', $resource?) — voir docs/authorization.md. */
function auth_can(string $permission, $resource = null): bool
{
    return authz_service()->can(current_user(), $permission, $resource);
}

function auth_has_permission(string $permission): bool
{
    return authz_service()->hasPermission(current_user(), $permission);
}

function auth_is_responsible_for_center(int $centerId): bool
{
    return authz_service()->isResponsibleForCenter(current_user(), $centerId);
}

function auth_is_responsible_for_bacenta(int $bacentaId): bool
{
    return authz_service()->isResponsibleForBacenta(current_user(), $bacentaId);
}

function auth_is_responsible_for_cult(int $cultId): bool
{
    return authz_service()->isResponsibleForCult(current_user(), $cultId);
}

function auth_can_manage_center(int $centerId): bool
{
    return authz_service()->canManageCenter(current_user(), $centerId);
}

function auth_can_manage_bacenta(int $bacentaId): bool
{
    return authz_service()->canManageBacenta(current_user(), $bacentaId);
}

function auth_can_manage_culte(int $cultId): bool
{
    return authz_service()->canManageCulte(current_user(), $cultId);
}

function auth_can_manage_basonta(int $basontaId): bool
{
    return authz_service()->canManageBasonta(current_user(), $basontaId);
}

function auth_can_manage_member(int $memberId): bool
{
    return authz_service()->canManageMember(current_user(), $memberId);
}

function auth_can_manage_responsibilities(): bool
{
    return authz_service()->canManageResponsibilities(current_user());
}

/** Gestionnaire de calendrier : admin OU détenteur d'≥1 responsabilité `manager`. */
function auth_can_manage_calendar(): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (($u['role'] ?? '') === 'admin') {
        return true;
    }
    return (int) \App\Core\Query::value(
        "SELECT COUNT(*) FROM responsibilities WHERE user_id = ? AND responsibility_type = 'manager'",
        [(int) $u['id']]
    ) > 0;
}

/** Édition/suppression d'UN événement : admin, son créateur, ou son responsable. */
function auth_can_edit_evenement(array $evt): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (($u['role'] ?? '') === 'admin') {
        return true;
    }
    $uid = (int) $u['id'];
    return $uid === (int) ($evt['created_by'] ?? 0) || $uid === (int) ($evt['responsable_id'] ?? 0);
}

/** L'utilisateur peut-il produire au moins un Rapport du Jour ? (admin ou gère un bacenta) */
function auth_can_report_any(): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (($u['role'] ?? '') === 'admin') {
        return true;
    }
    $uid = (int) $u['id'];
    return (int) \App\Core\Query::value(
        "SELECT EXISTS(
            SELECT 1 FROM responsibilities
             WHERE user_id = ? AND target_type = 'bacenta' AND responsibility_type = 'manager'
        )",
        [$uid]
    ) === 1;
}

/** Rapport du Jour pour CE centre : admin ou gère un bacenta rattaché à ce centre. */
function auth_can_report_for_centre(int $centreId): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (($u['role'] ?? '') === 'admin') {
        return true;
    }
    $uid = (int) $u['id'];
    return (int) \App\Core\Query::value(
        "SELECT EXISTS(
            SELECT 1 FROM bacentas b
              JOIN responsibilities r
                ON r.target_id = b.id AND r.target_type = 'bacenta' AND r.responsibility_type = 'manager'
             WHERE b.centre_id = ? AND r.user_id = ?
        )",
        [$centreId, $uid]
    ) === 1;
}

/** Gestion des classes/écoles post-culte : admin OU rôle pastoral désigné (manager). */
function auth_can_manage_classes(): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if (($u['role'] ?? '') === 'admin') {
        return true;
    }
    if (!in_array($u['role'] ?? '', ['berger', 'ms', 'pasteur', 'reverant'], true)) {
        return false;
    }
    return (int) \App\Core\Query::value(
        "SELECT COUNT(*) FROM responsibilities WHERE user_id = ? AND responsibility_type = 'manager'",
        [(int) $u['id']]
    ) > 0;
}

function start_session(): void
{
    \App\Core\Session::start(APP_NAME ? 'LBEGF_SESSID' : 'LBEGF_SESSID');
}

function current_user(): ?array
{
    return auth_service()->currentUser();
}

function my_bacenta_ids(int $userId): array
{
    return rbac_service()->myBacentaIds($userId);
}

function get_user_scope(): ?array
{
    return rbac_service()->scope();
}

function is_berger_scope_locked(): bool
{
    return rbac_service()->isBergerScopeLocked();
}

function get_allowed_sections(): ?array
{
    return rbac_service()->allowedSections();
}

function can_manage_entity(string $type, int $id): bool
{
    return rbac_service()->canManageEntity($type, $id);
}

function scope_target(): ?array
{
    return rbac_service()->scopeTarget();
}

function access_key(string $section, $id = null): string
{
    return rbac_service()->accessKey($section, $id);
}

function has_verified_access(string $section, $id = null): bool
{
    return rbac_service()->hasVerifiedAccess($section, $id);
}

function grant_access(string $section, $id = null): void
{
    rbac_service()->grantAccess($section, $id);
}

function login(string $email, string $password): bool
{
    return auth_service()->login($email, $password);
}

/** @return array{ok:bool, reason:string, account:?array} */
function attempt_login(string $email, string $password): array
{
    return auth_service()->authenticate($email, $password);
}

function logout(): void
{
    auth_service()->logout();
}

function find_account(string $email, string $password): ?array
{
    return auth_service()->findAccount($email, $password);
}

function verify_credentials(string $name, string $password): bool
{
    return auth_service()->verifyCredentials($name, $password);
}
