<?php

namespace App\Controllers;

use App\Core\Request;
use App\Auth\AuthenticationService;

/**
 * Authentification : formulaire de connexion, déconnexion, porte d'accès.
 */
class AuthController extends Controller
{
    public function loginForm(): void
    {
        echo view('pages/login', ['error' => isset($_GET['error'])]);
    }

    public function login(): void
    {
        $email = (string) (Request::post('email') ?? '');
        $password = (string) (Request::post('password') ?? '');

        $ok = \login($email, $password);
        if ($ok) {
            $target = scope_target();
            $this->redirect('index.php', $target ?: ['page' => 'accueil']);
        }
        $this->redirect('index.php', ['error' => 1]);
    }

    public function verifyAccess(): void
    {
        $ok = verify_credentials(
            (string) (Request::post('name') ?? ''),
            (string) (Request::post('password') ?? '')
        );
        $page = (string) (Request::post('page') ?? '');
        $id = (int) (Request::post('id') ?? 0);

        if ($ok) {
            grant_access($page, $id ?: null);
        }
        $params = ['page' => $page, 'id' => $id ?: null, 'gate' => $ok ? 0 : 1];
        if (!$ok) {
            $params['error'] = 1;
        }
        $this->redirect('index.php', $params);
    }

    public function logout(): void
    {
        logout();
        $this->redirect('index.php');
    }
}
