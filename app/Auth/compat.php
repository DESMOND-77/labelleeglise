<?php

/**
 * Wrappers de compatibilité RBAC/Auth pour les vues.
 * Exposent les anciennes fonctions globales (current_user, get_user_scope…)
 * en déléguant aux services Auth.
 */

declare(strict_types=1);

use App\Auth\AuthenticationService;
use App\Auth\RbacService;

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
