<?php

namespace App\Middleware;

use App\Core\Response;
use App\Auth\AuthenticationService;

/**
 * Restreint l'accès aux administrateurs.
 */
class AdminMiddleware
{
    private AuthenticationService $auth;

    public function __construct(?AuthenticationService $auth = null)
    {
        $this->auth = $auth ?? new AuthenticationService();
    }

    public function handle(): void
    {
        $user = $this->auth->currentUser();
        if (!$user || $user['role'] !== 'admin') {
            Response::redirect('index.php', ['page' => 'apropos']);
        }
    }
}
