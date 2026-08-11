<?php
/**
 * Configuration de l'envoi d'emails (SMTP via PHPMailer).
 * -------------------------------------------------------------
 * Les identifiants SMTP ne doivent JAMAIS être codés en dur ici en
 * production : ils sont lus depuis les variables d'environnement
 * (`env_value()`, défini dans `Bootstrap/env.php` et chargé depuis le
 * fichier `.env` en local — voir `.env.example`), avec des valeurs par
 * défaut neutres pour le développement local. Sur l'hébergement, définissez
 * ces variables via `.env` (non versionné) ou les vraies variables
 * d'environnement du serveur (Apache/PHP-FPM, panneau d'hébergement).
 *
 * Variables reconnues :
 *   SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_ENCRYPTION
 *   MAIL_FROM_ADDRESS, MAIL_FROM_NAME, APP_BASE_URL
 */

declare(strict_types=1);

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
