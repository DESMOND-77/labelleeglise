<?php
/**
 * Configuration générale de l'application.
 * -------------------------------------------------------------
 * Les valeurs qui varient selon l'environnement (URL, debug, fuseau…)
 * sont lues depuis les variables d'environnement (`.env` en local) —
 * voir `.env.example`.
 */

declare(strict_types=1);

return [
    'name'          => env_value('APP_NAME', 'La Belle Église'),
    'url'           => env_value('APP_URL', ''),            // vide = chemins relatifs
    'timezone'      => env_value('APP_TIMEZONE', 'Africa/Libreville'),
    'charset'       => 'UTF-8',
    'debug'         => env_bool('APP_DEBUG', true),
    'session_name'  => env_value('APP_SESSION_NAME', 'LBEGF_SESSID'),
    'upload_dir'    => env_value('APP_UPLOAD_DIR', 'uploads'),     // relatif à la racine web
    'max_upload'    => (int) env_value('APP_MAX_UPLOAD_BYTES', 4 * 1024 * 1024), // 4 Mo
];
