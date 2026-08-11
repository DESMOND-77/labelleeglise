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

    /**
     * Authentifie un compte et distingue précisément la raison d'un refus
     * (email/mot de passe invalide, email non vérifié, compte en attente
     * de validation admin, compte désactivé) — sans créer de session.
     *
     * @return array{ok:bool, reason:string, account:?array}
     *   reason ∈ invalid | not_verified | pending | disabled | ok
     */
    public function authenticate(string $email, string $password): array
    {
        $email = trim($email);
        if ($email === '' || $password === '') {
            return ['ok' => false, 'reason' => 'invalid', 'account' => null];
        }

        $u = $this->users->findByEmail($email);
        if (!$u || !\password_verify($password, $u['password'])) {
            return ['ok' => false, 'reason' => 'invalid', 'account' => null];
        }

        // Compatibilité ascendante : les comptes créés avant l'introduction
        // du workflow d'inscription publique n'ont pas de valeur explicite
        // pour ces colonnes ; on se rabat alors sur compte_actif seul.
        $hasVerificationColumns = array_key_exists('email_verified', $u) && array_key_exists('account_status', $u);

        if ($hasVerificationColumns) {
            if ((int) $u['email_verified'] !== 1) {
                return ['ok' => false, 'reason' => 'not_verified', 'account' => $u];
            }
            $status = (string) ($u['account_status'] ?? 'pending');
            if ($status === 'pending') {
                return ['ok' => false, 'reason' => 'pending', 'account' => $u];
            }
            if ($status === 'disabled') {
                return ['ok' => false, 'reason' => 'disabled', 'account' => $u];
            }
        }

        if ((int) $u['compte_actif'] !== 1) {
            return ['ok' => false, 'reason' => 'disabled', 'account' => $u];
        }

        return ['ok' => true, 'reason' => 'ok', 'account' => $u];
    }

    /** Connecte l'utilisateur (crée la session) si les identifiants et le statut du compte sont valides. */
    public function login(string $email, string $password): bool
    {
        $result = $this->authenticate($email, $password);
        if (!$result['ok']) {
            return false;
        }
        Session::regenerate();
        Session::set('user', ['id' => (int) $result['account']['id']]);
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
