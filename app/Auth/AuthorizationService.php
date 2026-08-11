<?php

namespace App\Auth;

use App\Core\Query;
use App\Services\ResponsibilityService;

/**
 * Point d'entrée central des décisions d'autorisation (spec §18-19, §42).
 *
 * Controller → AuthorizationService → Role + Permission + Responsibility + Scope
 *
 * Ne contient JAMAIS de structure codée en dur dans un rôle
 * ("berger_centre", "responsable_bacenta"…) — voir docs/authorization.md.
 */
class AuthorizationService
{
    private ResponsibilityService $responsibilities;

    public function __construct(?ResponsibilityService $responsibilities = null)
    {
        $this->responsibilities = $responsibilities ?? new ResponsibilityService();
    }

    /* ================= RBAC de base ================= */

    public function hasRole(?array $user, string $role): bool
    {
        return $user !== null && ($user['role'] ?? null) === $role;
    }

    /** true si le rôle possède la permission (indépendamment de toute ressource). */
    public function hasPermission(?array $user, string $permission): bool
    {
        if (!$user) {
            return false;
        }
        $role = $user['role'] ?? null;
        $perms = PERMISSIONS_MATRIX[$role] ?? [];
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    /**
     * Vérification générale : $auth->can($user, 'members.view', $resource).
     * Sans ressource : simple vérification de permission de rôle.
     * Avec ressource ('center'|'bacenta'|'cult' => id) : vérifie en plus le
     * périmètre réel via la table `responsibilities`.
     */
    public function can(?array $user, string $permission, $resource = null): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? null) === 'admin') {
            return true; // accès global (spec §28), jamais limité par un centre/bacenta.
        }
        if (!$this->hasPermission($user, $permission)) {
            return false;
        }
        if ($resource === null) {
            return true;
        }

        $userId = (int) $user['id'];
        return match ($permission) {
            'center.manage_assigned' => $this->isResponsibleForCenter($user, (int) $resource),
            'bacenta.manage_assigned' => $this->canManageBacenta($user, (int) $resource),
            'cult.manage_assigned' => $this->canManageCulte($user, (int) $resource),
            default => true,
        };
    }

    /* ================= Responsabilités ================= */

    public function isResponsibleForCenter(?array $user, int $centerId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $this->responsibilities->isResponsibleForCenter((int) $user['id'], $centerId);
    }

    /** Périmètre hérité inclus (un responsable de centre gère les bacentas de ce centre — spec §17). */
    public function isResponsibleForBacenta(?array $user, int $bacentaId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $this->responsibilities->isResponsibleForBacenta((int) $user['id'], $bacentaId);
    }

    public function isResponsibleForCult(?array $user, int $cultId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $this->responsibilities->isResponsibleForCult((int) $user['id'], $cultId);
    }

    public function isResponsibleForBasonta(?array $user, int $basontaId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $this->responsibilities->isResponsibleForBasonta((int) $user['id'], $basontaId);
    }

    /* ================= CRUD gates (rôle + responsabilité + cible) ================= */

    /**
     * Un utilisateur peut gérer (CRUD) un centre si : admin, OU il détient
     * la permission center.manage_assigned ET en est responsable (direct).
     * Comprend aussi le "leader" historique : n'a jamais eu de droits de
     * centre — reste false, comportement inchangé.
     */
    public function canManageCenter(?array $user, int $centerId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $this->hasPermission($user, 'center.manage_assigned')
            && $this->isResponsibleForCenter($user, $centerId);
    }

    /**
     * Un utilisateur peut gérer une bacenta si : admin, OU responsable
     * (direct ou hérité du centre) avec la permission bacenta.manage_assigned,
     * OU (comportement historique préservé — spec §21/§43) il s'agit d'un
     * leader/pasteur/reverant/berger/ms dont c'est la bacenta d'APPARTENANCE
     * (users.bacenta_id), indépendamment de toute ligne `responsibilities`.
     */
    public function canManageBacenta(?array $user, int $bacentaId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        if ($this->hasPermission($user, 'bacenta.manage_assigned') && $this->isResponsibleForBacenta($user, $bacentaId)) {
            return true;
        }
        if (in_array($user['role'], BERGER_ROLES, true) && (int) ($user['bacenta_id'] ?? 0) === $bacentaId && $bacentaId > 0) {
            return true;
        }
        return false;
    }

    /**
     * Un culte ne peut être géré que par : admin, OU pasteur/reverant qui en
     * est explicitement responsable (spec §24-26 — jamais par simple rôle).
     */
    public function canManageCulte(?array $user, int $cultId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $this->hasPermission($user, 'cult.manage_assigned')
            && $this->isResponsibleForCult($user, $cultId);
    }

    /** Alias orthographe anglaise (voir spec §18, canManageCult). */
    public function canManageCult(?array $user, int $cultId): bool
    {
        return $this->canManageCulte($user, $cultId);
    }

    public function canManageBasonta(?array $user, int $basontaId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $this->hasPermission($user, 'bacenta.manage_assigned')
            && $this->isResponsibleForBasonta($user, $basontaId);
    }

    /* ================= IDOR / vérification de chaîne FK ================= */

    /**
     * Vérifie qu'un membre appartient bien au périmètre autorisé de
     * l'utilisateur (spec §12 : Centre A ne doit jamais pouvoir toucher une
     * ressource du Centre B). Remonte la chaîne réelle : membre → bacenta →
     * centre, ne fait jamais confiance à un id fourni par le client.
     */
    public function canManageMember(?array $user, int $memberId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        $member = Query::one('SELECT bacenta_id FROM users WHERE id = ?', [$memberId]);
        if (!$member) {
            return false;
        }
        if (!empty($member['bacenta_id']) && $this->canManageBacenta($user, (int) $member['bacenta_id'])) {
            return true;
        }
        return false;
    }

    /** Permission réservée à l'admin : affectation/retrait de responsabilités. */
    public function canManageResponsibilities(?array $user): bool
    {
        return $user !== null && $user['role'] === 'admin';
    }

    public function isAdmin(?array $user): bool
    {
        return $user !== null && $user['role'] === 'admin';
    }
}
