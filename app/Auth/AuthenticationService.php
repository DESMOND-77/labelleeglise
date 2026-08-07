<?php

namespace App\Auth;

use App\Core\Session;
use App\Repositories\UserRepository;

/**
 * Authentification des comptes applicatifs (email + mot de passe).
 */
class AuthenticationService
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    /** Retourne le compte si l'email + mot de passe sont valides. */
    public function findAccount(string $email, string $password): ?array
    {
        $u = $this->users->findByEmail($email);
        if (!$u || (int) $u['compte_actif'] !== 1) {
            return null;
        }
        if (!\password_verify($password, $u['password'])) {
            return null;
        }
        return [
            'type' => 'app',
            'id'   => (int) $u['id'],
            'name' => trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')),
            'role' => $u['role'],
            'email'=> $u['email'],
        ];
    }

    /** Vérifie des identifiants (email OU nom/prénom + mot de passe) — porte d'accès. */
    public function verifyCredentials(string $name, string $password): bool
    {
        $n = mb_strtolower(trim($name));
        $u = \App\Core\Query::one(
            "SELECT * FROM users WHERE LOWER(email) = ? OR LOWER(CONCAT(prenom, ' ', nom)) = ? OR LOWER(CONCAT(nom, ' ', prenom)) = ?",
            [$n, $n, $n]
        );
        if (!$u || (int) $u['compte_actif'] !== 1) {
            return false;
        }
        return \password_verify($password, $u['password']);
    }

    /** Connecte l'utilisateur (crée la session). */
    public function login(string $email, string $password): bool
    {
        $account = $this->findAccount($email, $password);
        if (!$account) {
            return false;
        }
        Session::regenerate();
        Session::set('user', ['id' => $account['id']]);
        return true;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    /** Utilisateur courant (chargé depuis la base). */
    public function currentUser(): ?array
    {
        static $user = null;
        static $loaded = false;
        if (!$loaded) {
            $loaded = true;
            $id = Session::get('user')['id'] ?? null;
            $user = $id ? $this->users->find((int) $id) : null;
        }
        return $user;
    }
}
