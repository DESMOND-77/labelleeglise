<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\UserRepository;

/**
 * Changement d'adresse email en libre-service — flux sécurisé (spec §12-13).
 *
 * Règle absolue : l'email n'est JAMAIS remplacé immédiatement. Le nouveau
 * jeton suit exactement le même schéma que RegistrationService : jeton brut
 * random_bytes(32) envoyé par email, hash sha256 stocké en base, expiration
 * 24h, usage unique. La déconnexion immédiate de la session en cours (au
 * moment de la DEMANDE, pas de la vérification) est de la responsabilité de
 * l'appelant (contrôleur) — cette classe ne connaît pas la session HTTP.
 */
class EmailChangeService
{
    public const TOKEN_TTL_HOURS = 24;

    private UserRepository $users;
    private MailService $mail;

    public function __construct(?UserRepository $users = null, ?MailService $mail = null)
    {
        $this->users = $users ?? new UserRepository();
        $this->mail = $mail ?? new MailService();
    }

    /**
     * Valide et enregistre une demande de changement d'email, puis envoie
     * l'email de vérification à la NOUVELLE adresse.
     *
     * @return array{ok:bool, error:?string}
     */
    public function requestChange(array $user, string $newEmail): array
    {
        $newEmail = mb_strtolower(trim($newEmail));

        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($newEmail) > 100) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        if (mb_strtolower((string) $user['email']) === $newEmail) {
            return ['ok' => false, 'error' => 'same_email'];
        }
        if ($this->users->emailTaken($newEmail)) {
            return ['ok' => false, 'error' => 'taken'];
        }

        [$rawToken, $hashedToken] = $this->generateToken();
        $expiresAt = $this->expiryTimestamp();

        $this->users->setPendingEmailChange((int) $user['id'], $newEmail, $hashedToken, $expiresAt);

        $verifyUrl = $this->mail->buildUrl('index.php', ['page' => 'verify_email_change', 'token' => $rawToken]);

        try {
            $this->mail->sendEmailChangeVerification($user, $newEmail, $verifyUrl, self::TOKEN_TTL_HOURS);
        } catch (\Throwable $e) {
            // Ne jamais faire échouer la demande déjà enregistrée à cause d'un
            // échec d'envoi — l'utilisateur pourra redemander un lien.
            Logger::error('Erreur envoi email de vérification de changement d\'email', ['error' => $e->getMessage()]);
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Vérifie le jeton reçu sur la NOUVELLE adresse et, si valide, remplace
     * l'email. Envoie ensuite un avis de sécurité (best-effort) à l'ANCIENNE
     * adresse.
     *
     * @return array{status:string, user:?array} status ∈ invalid|expired|ok
     */
    public function verifyChange(string $rawToken): array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || !ctype_xdigit($rawToken)) {
            return ['status' => 'invalid', 'user' => null];
        }

        $hashedToken = hash('sha256', $rawToken);
        $user = $this->users->findByEmailChangeTokenHash($hashedToken);

        if (!$user || empty($user['pending_email'])) {
            // Jeton inconnu : invalide ou déjà utilisé (purgé après usage).
            return ['status' => 'invalid', 'user' => null];
        }

        $expiresAt = $user['email_change_expires_at'] ?? null;
        if (!$expiresAt || strtotime((string) $expiresAt) < time()) {
            return ['status' => 'expired', 'user' => $user];
        }

        $oldEmail = (string) $user['email'];
        $newEmail = (string) $user['pending_email'];

        $this->users->confirmEmailChange((int) $user['id'], $newEmail);
        $user = $this->users->find((int) $user['id']);

        try {
            $this->mail->sendEmailChangedSecurityNotice($user, $oldEmail);
        } catch (\Throwable $e) {
            Logger::error('Erreur envoi avis de sécurité (changement d\'email)', ['error' => $e->getMessage()]);
        }

        return ['status' => 'ok', 'user' => $user];
    }

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
