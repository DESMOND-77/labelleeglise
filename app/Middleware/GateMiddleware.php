<?php

namespace App\Middleware;

use App\Auth\RbacService;

/**
 * Vérifie l'accès porte d'entrée pour une section/entité.
 */
class GateMiddleware
{
    private RbacService $rbac;

    public function __construct(?RbacService $rbac = null)
    {
        $this->rbac = $rbac ?? new RbacService();
    }

    public function hasAccess(string $section, $id = null): bool
    {
        return $this->rbac->hasVerifiedAccess($section, $id);
    }

    public function grant(string $section, $id = null): void
    {
        $this->rbac->grantAccess($section, $id);
    }
}
