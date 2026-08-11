<?php

namespace App\Services;

use App\Core\Logger;
use App\Core\View;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Centralise l'envoi des emails applicatifs via PHPMailer (déjà vendorisé
 * dans app/Core/PHPMailer, sans Composer). Toute erreur d'envoi est
 * journalisée dans Storage/logs/mail.log et ne doit JAMAIS remonter comme
 * une exception fatale : une inscription doit réussir même si l'email de
 * vérification échoue à partir (l'utilisateur pourra redemander l'envoi).
 */
class MailService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require APP_CONFIG_PATH . '/mail.php';
    }

    /** URL absolue de base pour les liens dans les emails (fallback : APP_URL). */
    public function baseUrl(): string
    {
        $base = trim((string) ($this->config['app_base_url'] ?? ''));
        if ($base === '') {
            return rtrim((string) APP_URL, '/');
        }
        // Tolère une valeur .env incomplète (sans schéma http(s)://) plutôt
        // que de générer des liens cassés dans les emails envoyés.
        if (!preg_match('#^https?://#i', $base)) {
            $base = 'http://' . $base;
        }
        return rtrim($base, '/');
    }

    public function buildUrl(string $path, array $params = []): string
    {
        $query = $params ? ('?' . http_build_query($params)) : '';
        return $this->baseUrl() . '/' . ltrim($path, '/') . $query;
    }

    /**
     * Envoi générique HTML. Retourne true si l'email a été transmis au
     * serveur SMTP (ou journalisé faute de configuration), false en cas
     * d'erreur d'envoi (déjà journalisée).
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): bool
    {
        $host = trim((string) ($this->config['host'] ?? ''));
        if ($host === '') {
            // Aucune configuration SMTP : on journalise sans faire échouer
            // le flux applicatif (comportement attendu en environnement de
            // développement / avant configuration de production).
            Logger::warning('Email non envoyé (SMTP non configuré)', [
                'to' => $toEmail,
                'subject' => $subject,
            ]);
            $this->logMail('SMTP non configuré — email simulé', $toEmail, $subject);
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) ($this->config['port'] ?? 587);
            $mail->SMTPAuth = (bool) ($this->config['auth'] ?? true);
            if ($mail->SMTPAuth) {
                $mail->Username = (string) ($this->config['username'] ?? '');
                $mail->Password = (string) ($this->config['password'] ?? '');
            }
            $encryption = (string) ($this->config['encryption'] ?? 'tls');
            if ($encryption !== '') {
                $mail->SMTPSecure = $encryption;
            }
            if ((bool) ($this->config['debug'] ?? false)) {
                $mail->SMTPDebug = 2;
                // Ne JAMAIS laisser PHPMailer écrire le transcript SMTP (qui peut
                // contenir des identifiants/entêtes) directement dans la sortie
                // HTTP (comportement par défaut de `Debugoutput` = 'echo') : on le
                // redirige systématiquement vers le journal applicatif.
                $mail->Debugoutput = function (string $str, int $level): void {
                    $this->logDebug($str, $level);
                };
            }

            $mail->setFrom(
                (string) ($this->config['from_address'] ?? 'no-reply@labelleeglise.ga'),
                (string) ($this->config['from_name'] ?? 'La Belle Église')
            );
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody !== '' ? $altBody : trim(strip_tags($htmlBody));

            $mail->send();
            $this->logMail('Envoyé', $toEmail, $subject);
            return true;
        } catch (PHPMailerException $e) {
            Logger::error('Échec envoi email', ['to' => $toEmail, 'subject' => $subject, 'error' => $mail->ErrorInfo ?: $e->getMessage()]);
            $this->logMail('ÉCHEC — ' . ($mail->ErrorInfo ?: $e->getMessage()), $toEmail, $subject);
            return false;
        } catch (\Throwable $e) {
            Logger::error('Échec envoi email (inattendu)', ['to' => $toEmail, 'subject' => $subject, 'error' => $e->getMessage()]);
            $this->logMail('ÉCHEC — ' . $e->getMessage(), $toEmail, $subject);
            return false;
        }
    }

    /** Journal dédié aux emails (Storage/logs/mail.log), indépendant du journal applicatif. */
    private function logMail(string $status, string $to, string $subject): void
    {
        $line = '[' . date('Y-m-d H:i:s') . "] $status | to=$to | subject=" . str_replace(["\r", "\n"], ' ', $subject) . PHP_EOL;
        @file_put_contents(APP_LOG_PATH . '/mail.log', $line, FILE_APPEND);
    }

    /**
     * Transcript SMTP (SMTPDebug) — écrit UNIQUEMENT dans le journal, jamais
     * dans la sortie HTTP. Peut contenir des identifiants/entêtes ; réservé au
     * diagnostic (`SMTP_DEBUG=true`), à ne jamais activer durablement en prod.
     */
    private function logDebug(string $str, int $level): void
    {
        $line = '[' . date('Y-m-d H:i:s') . "] SMTP DEBUG [$level] " . trim($str) . PHP_EOL;
        @file_put_contents(APP_LOG_PATH . '/mail.log', $line, FILE_APPEND);
    }

    /* ================= Emails métier ================= */

    public function sendVerificationEmail(array $user, string $verifyUrl, int $expiresHours = 24): bool
    {
        $html = View::render('emails/verify-email', [
            'user'          => $user,
            'verifyUrl'     => $verifyUrl,
            'expiresHours'  => $expiresHours,
            'appName'       => defined('APP_NAME') ? APP_NAME : 'La Belle Église',
        ]);
        return $this->send(
            $user['email'],
            trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')),
            'Vérifiez votre adresse email — La Belle Église',
            $html
        );
    }

    public function sendNewRegistrationAdminNotification(array $admin, array $user, string $reviewUrl): bool
    {
        $html = View::render('emails/registration-admin', [
            'admin'     => $admin,
            'user'      => $user,
            'reviewUrl' => $reviewUrl,
            'appName'   => defined('APP_NAME') ? APP_NAME : 'La Belle Église',
        ]);
        return $this->send(
            $admin['email'],
            trim(($admin['prenom'] ?? '') . ' ' . ($admin['nom'] ?? '')),
            'Nouvelle inscription à valider — La Belle Église',
            $html
        );
    }

    public function sendAccountActivatedEmail(array $user, string $loginUrl): bool
    {
        $html = View::render('emails/account-activated', [
            'user'     => $user,
            'loginUrl' => $loginUrl,
            'appName'  => defined('APP_NAME') ? APP_NAME : 'La Belle Église',
        ]);
        return $this->send(
            $user['email'],
            trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')),
            'Votre compte La Belle Église est activé',
            $html
        );
    }

    /** Envoyé à la NOUVELLE adresse lors d'une demande de changement d'email (spec §12-13). */
    public function sendEmailChangeVerification(array $user, string $newEmail, string $verifyUrl, int $expiresHours = 24): bool
    {
        $html = View::render('emails/email-change-verification', [
            'user'         => $user,
            'newEmail'     => $newEmail,
            'verifyUrl'    => $verifyUrl,
            'expiresHours' => $expiresHours,
            'appName'      => defined('APP_NAME') ? APP_NAME : 'La Belle Église',
        ]);
        return $this->send(
            $newEmail,
            trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')),
            'Confirmez votre nouvelle adresse email — La Belle Église',
            $html
        );
    }

    /** Avis de sécurité envoyé à l'ANCIENNE adresse après un changement d'email confirmé (spec §43). */
    public function sendEmailChangedSecurityNotice(array $user, string $oldEmail): bool
    {
        $html = View::render('emails/email-changed-notice', [
            'user'     => $user,
            'oldEmail' => $oldEmail,
            'appName'  => defined('APP_NAME') ? APP_NAME : 'La Belle Église',
        ]);
        return $this->send(
            $oldEmail,
            trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')),
            'Votre adresse email a été modifiée — La Belle Église',
            $html
        );
    }
}
