<?php

namespace App\Middleware;

use App\Core\Csrf;

/**
 * Vérifie le jeton CSRF sur les requêtes POST.
 */
class CsrfMiddleware
{
    public function handle(): void
    {
        Csrf::check($_POST['csrf'] ?? '');
    }
}
