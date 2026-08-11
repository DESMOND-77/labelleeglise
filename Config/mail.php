<?php
/**
 * Configuration de l'envoi d'emails (SMTP via PHPMailer).
 * -------------------------------------------------------------
 * Les identifiants SMTP ne doivent JAMAIS être codés en dur ici en
 * production : ils sont lus depuis les variables d'environnement du
 * serveur (getenv), avec des valeurs par défaut neutres pour le
 * développement local. Adaptez ces variables d'environnement sur
 * l'hébergement (fichier .htaccess `SetEnv`, configuration Apache/PHP-FPM,
 * ou panneau d'hébergement mutualisé).
 *
 * Variables reconnues :
 *   SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_ENCRYPTION
 *   MAIL_FROM_ADDRESS, MAIL_FROM_NAME, APP_BASE_URL
 */

declare(strict_types=1);

if (!function_exists('env_value')) {
    function env_value(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return $value;
    }
}

return [
    // Hôte SMTP (ex. smtp.gmail.com, mail.votre-domaine.com). Vide = envoi désactivé (journalisé).
    'host'         => env_value('SMTP_HOST', ''),
    'port'         => (int) env_value('SMTP_PORT', 587),
    'username'     => env_value('SMTP_USERNAME', ''),
    'password'     => env_value('SMTP_PASSWORD', ''),
    // '', 'tls' ou 'ssl'.
    'encryption'   => env_value('SMTP_ENCRYPTION', 'tls'),
    'auth'         => (bool) env_value('SMTP_AUTH', true),
    'from_address' => env_value('MAIL_FROM_ADDRESS', 'no-reply@labelleeglise.ga'),
    'from_name'    => env_value('MAIL_FROM_NAME', 'La Belle Église'),
    // URL absolue de base pour construire les liens dans les emails
    // (ex. https://gestion.labelleeglise.ga/). Vide = liens relatifs (APP_URL).
    'app_base_url' => env_value('APP_BASE_URL', ''),
    'debug'        => (bool) env_value('SMTP_DEBUG', false),
];
