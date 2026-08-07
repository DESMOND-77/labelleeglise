<?php
/**
 * Autoloader léger (PSR-4 inspiré) sans Composer.
 * -------------------------------------------------------------
 * Mappe le préfixe `App\` vers `app/`, `Config\` vers `Config/`,
 * `Routes\` vers `Routes/`, `Database\` vers `Database/`.
 *
 * Compatible hébergement mutualisé : aucune dépendance externe,
 * aucun installateur, aucun CLI requis.
 */

declare(strict_types=1);

/**
 * Enregistre un autoloader PSR-4 pour un préfixe et un dossier.
 *
 * @param string $prefix    Préfixe de namespace (ex. "App\\")
 * @param string $baseDir   Chemin absolu du dossier racine
 */
function register_prefix(string $prefix, string $baseDir): void
{
    spl_autoload_register(static function (string $class) use ($prefix, $baseDir): void {
        // Le préfixe ne correspond pas à la classe demandée.
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        // Chemin relatif du namespace → sous-dossier.
        $relative = substr($class, strlen($prefix));
        $file = $baseDir . DIRECTORY_SEPARATOR
              . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative)
              . '.php';

        if (is_file($file)) {
            require $file;
        }
    });
}

/** Racine du projet. */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Enregistre les préfixes de l'application.
register_prefix('App\\', BASE_PATH . DIRECTORY_SEPARATOR . 'app');
register_prefix('Config\\', BASE_PATH . DIRECTORY_SEPARATOR . 'Config');
register_prefix('Routes\\', BASE_PATH . DIRECTORY_SEPARATOR . 'Routes');
register_prefix('Database\\', BASE_PATH . DIRECTORY_SEPARATOR . 'Database');
