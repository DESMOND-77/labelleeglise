<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\UserRepository;

/**
 * Workflow complet : inscription publique → vérification email →
 * notification des administrateurs → activation administrative.
 *
 * Respecte strictement :
 *  - le rôle est TOUJOURS forcé à "membre" côté serveur (jamais lu depuis
 *    la requête) ;
 *  - le mot de passe est haché avec password_hash() ;
 *  - le jeton de vérification est généré avec random_bytes() (jamais
 *    rand()/mt_rand()/time()) et stocké haché (sha256) en base — jamais en
 *    clair — avec une expiration de 24h ;
 *  - une erreur d'envoi d'email ne fait jamais échouer la transaction
 *    utilisateur (elle est journalisée et un avertissement doux est renvoyé).
 */
class RegistrationService
{
    public const TOKEN_TTL_HOURS = 24;

    private UserRepository $users;
    private MailService $mail;
    private NotificationService $notifications;

    public function __construct(
        ?UserRepository $users = null,
        ?MailService $mail = null,
        ?NotificationService $notifications = null
    ) {
        $this->users = $users ?? new UserRepository();
        $this->mail = $mail ?? new MailService();
        $this->notifications = $notifications ?? new NotificationService();
    }

    /**
     * Valide les données du formulaire d'inscription publique.
     * Retourne un tableau d'erreurs par champ (vide = validation OK).
     * Ne fait JAMAIS confiance à une validation JavaScript côté client :
     * cette validation serveur est la seule autorité.
     */
    public function validate(array $input): array
    {
        $errors = [];

        $nom = trim((string) ($input['nom'] ?? ''));
        $prenom = trim((string) ($input['prenom'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $telephone = trim((string) ($input['telephone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirm'] ?? '');

        if ($nom === '') {
            $errors['nom'] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($nom) > 50) {
            $errors['nom'] = 'Le nom est trop long (50 caractères maximum).';
        }

        if ($prenom === '') {
            $errors['prenom'] = 'Le prénom est obligatoire.';
        } elseif (mb_strlen($prenom) > 50) {
            $errors['prenom'] = 'Le prénom est trop long (50 caractères maximum).';
        }

        if ($email === '') {
            $errors['email'] = "L'adresse email est obligatoire.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $errors['email'] = 'Adresse email invalide.';
        } elseif ($this->users->emailTaken($email)) {
            $errors['email'] = 'Cette adresse email est déjà utilisée.';
        }

        if ($telephone === '') {
            $errors['telephone'] = 'Le numéro de téléphone est obligatoire.';
        } elseif (!preg_match('/^[0-9+][0-9+\s().-]{5,19}$/', $telephone)) {
            $errors['telephone'] = 'Numéro de téléphone invalide.';
        }

        if ($password === '') {
            $errors['password'] = 'Le mot de passe est obligatoire.';
        } elseif (mb_strlen($password) < 8) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif (mb_strlen($password) > 200) {
            $errors['password'] = 'Le mot de passe est trop long.';
        }

        if ($passwordConfirm === '') {
            $errors['password_confirm'] = 'La confirmation du mot de passe est obligatoire.';
        } elseif ($password !== '' && $password !== $passwordConfirm) {
            $errors['password_confirm'] = 'La confirmation ne correspond pas au mot de passe.';
        }

        return $errors;
    }

    /**
     * Crée le compte (role = membre, email_verified = false,
     * account_status = pending) et envoie l'email de vérification.
     *
     * @return array{user_id:int, mail_sent:bool}
     */
    public function register(array $input): array
    {
        $fields = [
            'nom'       => trim((string) ($input['nom'] ?? '')),
            'prenom'    => trim((string) ($input['prenom'] ?? '')),
            'email'     => mb_strtolower(trim((string) ($input['email'] ?? ''))),
            'telephone' => trim((string) ($input['telephone'] ?? '')),
        ];

        $hashedPassword = password_hash((string) $input['password'], PASSWORD_DEFAULT);

        [$rawToken, $hashedToken] = $this->generateToken();
        $expiresAt = $this->expiryTimestamp();

        $userId = $this->users->createRegistration($fields, $hashedPassword, $hashedToken, $expiresAt);

        $user = $this->users->find($userId);
        $verifyUrl = $this->mail->buildUrl('index.php', ['page' => 'verify_email', 'token' => $rawToken]);

        $mailSent = false;
        try {
            $mailSent = $this->mail->sendVerificationEmail($user, $verifyUrl, self::TOKEN_TTL_HOURS);
        } catch (\Throwable $e) {
            // Ne jamais laisser un échec d'email invalider l'inscription déjà créée.
            Logger::error('Erreur inattendue lors de l\'envoi de l\'email de vérification', ['error' => $e->getMessage()]);
        }

        return ['user_id' => $userId, 'mail_sent' => $mailSent];
    }

    /**
     * Vérifie le jeton reçu par email et, si valide, marque l'email comme
     * vérifié puis notifie tous les administrateurs (email + notification
     * plateforme). Le jeton est utilisable une seule fois (invalidé après
     * usage) et expire après TOKEN_TTL_HOURS heures.
     *
     * @return array{status:string, user:?array} status ∈ invalid|expired|ok
     */
    public function verifyEmailToken(string $rawToken): array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || !ctype_xdigit($rawToken)) {
            return ['status' => 'invalid', 'user' => null];
        }

        $hashedToken = hash('sha256', $rawToken);
        $user = $this->users->findByVerificationTokenHash($hashedToken);

        if (!$user) {
            // Jeton inconnu : soit invalide, soit déjà utilisé (le jeton est
            // effacé après usage) — message générique, pas d'énumération.
            return ['status' => 'invalid', 'user' => null];
        }

        if ((int) $user['email_verified'] === 1) {
            // Déjà vérifié (jeton non encore effacé pour une raison quelconque).
            return ['status' => 'already_verified', 'user' => $user];
        }

        $expiresAt = $user['verification_expires_at'] ?? null;
        if (!$expiresAt || strtotime((string) $expiresAt) < time()) {
            return ['status' => 'expired', 'user' => $user];
        }

        $this->users->markEmailVerified((int) $user['id']);
        $user = $this->users->find((int) $user['id']);

        $this->notifyAdminsOfVerifiedRegistration($user);

        return ['status' => 'ok', 'user' => $user];
    }

    /** Renvoie un nouvel email de vérification (nouveau jeton, ancien invalidé). */
    public function resendVerification(array $user): bool
    {
        if ((int) $user['email_verified'] === 1) {
            return false;
        }
        [$rawToken, $hashedToken] = $this->generateToken();
        $this->users->setVerificationToken((int) $user['id'], $hashedToken, $this->expiryTimestamp());
        $verifyUrl = $this->mail->buildUrl('index.php', ['page' => 'verify_email', 'token' => $rawToken]);
        try {
            return $this->mail->sendVerificationEmail($user, $verifyUrl, self::TOKEN_TTL_HOURS);
        } catch (\Throwable $e) {
            Logger::error('Erreur renvoi email de vérification', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function notifyAdminsOfVerifiedRegistration(array $user): void
    {
        $reviewUrl = $this->mail->buildUrl('index.php', ['page' => 'admin_inscription', 'id' => $user['id']]);
        $fullName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));

        $admins = $this->users->findAdmins();
        foreach ($admins as $admin) {
            $this->notifications->notify(
                (int) $admin['id'],
                'new_registration',
                'Nouvelle inscription',
                $fullName . ' vient de vérifier son adresse email. Son compte attend votre validation.',
                'index.php?page=admin_inscription&id=' . (int) $user['id']
            );
            try {
                $this->mail->sendNewRegistrationAdminNotification($admin, $user, $reviewUrl);
            } catch (\Throwable $e) {
                Logger::error('Erreur notification email admin', ['admin_id' => $admin['id'], 'error' => $e->getMessage()]);
            }
        }
    }

    /* ================= Administration des inscriptions ================= */

    public function listPendingRegistrations(): array
    {
        return $this->users->findPendingRegistrations();
    }

    public function getRegistration(int $id): ?array
    {
        $user = $this->users->find($id);
        if (!$user || $user['role'] !== 'membre') {
            return null;
        }
        return $user;
    }

    /**
     * Active un compte (validation administrative). L'autorisation
     * (rôle admin) doit être vérifiée par l'appelant (contrôleur/middleware) ;
     * cette méthode ne fait que l'opération métier + notification.
     */
    public function activate(int $userId): ?array
    {
        $user = $this->users->find($userId);
        if (!$user) {
            return null;
        }
        $this->users->activateAccount($userId);
        $user = $this->users->find($userId);

        $loginUrl = $this->mail->buildUrl('index.php');
        try {
            $this->mail->sendAccountActivatedEmail($user, $loginUrl);
        } catch (\Throwable $e) {
            Logger::error('Erreur email activation compte', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }

        return $user;
    }

    /** Refuse une inscription : notifie l'utilisateur puis supprime le compte. */
    public function reject(int $userId): bool
    {
        $user = $this->users->find($userId);
        if (!$user) {
            return false;
        }
        try {
            $this->mail->send(
                $user['email'],
                trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')),
                'Votre inscription — La Belle Église',
                '<p>Bonjour ' . htmlspecialchars($user['prenom'] ?? '', ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>Votre demande d\'inscription n\'a pas pu être validée par l\'administration de La Belle Église.</p>'
                . '<p>Pour toute question, contactez l\'église.</p>'
            );
        } catch (\Throwable $e) {
            Logger::error('Erreur email refus inscription', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
        $this->users->delete($userId);
        return true;
    }

    /* ================= Jeton sécurisé ================= */

    /** @return array{0:string,1:string} [jeton en clair, hash sha256] */
    private function generateToken(): array
    {
        $raw = bin2hex(random_bytes(32));
        return [$raw, hash('sha256', $raw)];
    }

    private function expiryTimestamp(): string
    {
        return date('Y-m-d H:i:s', time() + self::TOKEN_TTL_HOURS * 3600);
    }
}
