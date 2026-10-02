<?php
/**
 * Configuration générale de l'application.
 * -------------------------------------------------------------
 * Les valeurs qui varient selon l'environnement (URL, debug, fuseau…)
 * sont lues depuis les variables d'environnement (`.env` en local) -
 * voir `.env.example`.
 */

declare(strict_types=1);

return [
    'name'          => env_value('APP_NAME', 'La Belle Église Intenationale Franceville'),
    'location'      => env_value('APP_LOCATION', 'Franceville'),
    'url'           => env_value('APP_URL', ''),            // vide = chemins relatifs
    'timezone'      => env_value('APP_TIMEZONE', 'Africa/Libreville'),
    'charset'       => 'UTF-8',
    'debug'         => env_bool('APP_DEBUG', false),
    'session_name'  => env_value('APP_SESSION_NAME', 'LBEGF_SESSID'),
    'upload_dir'    => env_value('APP_UPLOAD_DIR', 'uploads'),     // relatif à la racine web
    'max_upload'    => (int) env_value('APP_MAX_UPLOAD_BYTES', 4 * 1024 * 1024), // 4 Mo
    'font_awesome_css' => env_value('APP_FONT_AWESOME_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'),
    'chartjs_js' => env_value('APP_CHARTJS_JS', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.3.0/chart.umd.min.js')
];
