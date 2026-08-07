<?php

namespace App\Middleware;

use App\Core\Response;
use App\Auth\AuthenticationService;

/**
 * Vérifie que l'utilisateur est connecté.
 */
class AuthMiddleware
{
    private AuthenticationService $auth;

    public function __construct(?AuthenticationService $auth = null)
    {
        $this->auth = $auth ?? new AuthenticationService();
    }

    /** Retourne l'utilisateur courant ou redirige vers la connexion. */
    public function handle(): ?array
    {
        $user = $this->auth->currentUser();
        if (!$user) {
            Response::redirect('index.php');
        }
        return $user;
    }
}
