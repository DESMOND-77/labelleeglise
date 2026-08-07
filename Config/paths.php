<?php
/**
 * Chemins absolus de l'application.
 * Centralisés pour éviter tout hardcodage réparti dans le code.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
define('APP_PATH', BASE_PATH);
define('APP_PUBLIC_PATH', BASE_PATH);          // dossier web (racine = dossier projet)
define('APP_ASSETS_PATH', APP_PUBLIC_PATH . '/assets');
define('APP_UPLOADS_PATH', APP_PUBLIC_PATH . '/uploads');
define('APP_STORAGE_PATH', BASE_PATH . '/Storage');
define('APP_LOG_PATH', APP_STORAGE_PATH . '/logs');
define('APP_CACHE_PATH', APP_STORAGE_PATH . '/cache');
define('APP_SESSION_PATH', APP_STORAGE_PATH . '/sessions');
define('APP_CONFIG_PATH', BASE_PATH . '/Config');
define('APP_ROUTES_PATH', BASE_PATH . '/Routes');
define('APP_VIEWS_PATH', BASE_PATH . '/Views');
define('APP_DATABASE_PATH', BASE_PATH . '/Database');
