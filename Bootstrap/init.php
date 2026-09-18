<?php
/**
 * Initialisation de l'application.
 * -------------------------------------------------------------
 * Charge l'autoloader, les chemins, la configuration, la session,
 * le fuseau horaire et la gestion d'erreurs.
 */

declare(strict_types=1);

// 0. Sur certains hébergements durcis (mmap exécutable restreint), la
// compilation JIT de PCRE échoue et émet un warning PHP au premier
// `preg_match()` de la requête ; l'application transforme les warnings en
// exceptions (voir Logger::handleError), ce qui ferait planter tout le
// boot. On désactive donc le JIT PCRE au runtime (aucun accès à php.ini
// requis) avant tout `preg_match()` de l'application.
if (function_exists('ini_set')) {
    @ini_set('pcre.jit', '0');
}

// 1. Autoloader (légers, sans Composer).
require_once __DIR__ . '/autoload.php';

// 1b. Variables d'environnement (.env à la racine, si présent).
require_once __DIR__ . '/env.php';
load_env(BASE_PATH . '/.env');

// Sécurité session/cookie côté runtime (Hostinger derrière Apache / HTTPS).
if (function_exists('ini_set')) {
    $isSecureRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');

    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    @ini_set('session.cookie_secure', $isSecureRequest ? '1' : '0');
}

// 2. Chemins absolus.
require_once BASE_PATH . '/Config/paths.php';

// 3. Configuration.
$appConfig = require APP_CONFIG_PATH . '/app.php';
$dbConfig  = require APP_CONFIG_PATH . '/database.php';

define('APP_DEBUG', (bool) ($appConfig['debug'] ?? false));
if (APP_DEBUG) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}
@ini_set('log_errors', '1');

// Fuseau horaire.
if (!empty($appConfig['timezone'])) {
    date_default_timezone_set($appConfig['timezone']);
}

// 4. Constantes métier (rôles, sections, champs…) + matrice de permissions.
require_once APP_CONFIG_PATH . '/constants.php';
define('PERMISSIONS_MATRIX', require APP_CONFIG_PATH . '/permissions.php');

// 5. Gestion d'erreurs + journalisation.
require_once APP_PATH . '/app/Core/Logger.php';
\App\Core\Logger::init();

// 6. Fonctions utilitaires globales (h, url, nav, dates, uploads…)
//    + moteur de rendu et partiels partagés (view, render_page…).
require_once APP_PATH . '/app/Helpers/helpers.php';
require_once APP_PATH . '/app/Helpers/rendering.php';

// 6b. Wrappers de compatibilité (Auth, données, structure, pages) pour les vues.
require_once APP_PATH . '/app/Auth/compat.php';
require_once APP_PATH . '/app/Compat/data.php';
require_once APP_PATH . '/app/Compat/structure.php';
require_once APP_PATH . '/app/Compat/pages.php';
require_once APP_PATH . '/app/Compat/profile.php';
require_once APP_PATH . '/app/Compat/sections.php';
require_once APP_PATH . '/app/Compat/bergers.php';
require_once APP_PATH . '/app/Compat/finances.php';
require_once APP_PATH . '/app/Compat/parametres.php';
require_once APP_PATH . '/app/Compat/apropos.php';
require_once APP_PATH . '/app/Compat/notifications.php';

// 7. Session.
\App\Core\Session::start($appConfig['session_name'] ?? null);

// 7. Constantes publiques d'application (APP_NAME, APP_URL, UPLOAD_DIR…).
define('APP_NAME', $appConfig['name'] ?? 'La Belle Église');
// APP_URL est concaténée telle quelle devant les chemins relatifs (url(),
// redirect() : `APP_URL . 'index.php' . $query`) : on normalise donc pour
// tolérer une valeur .env incomplète (ex. `192.168.1.102:3000` sans schéma,
// ou sans slash final) plutôt que de produire des redirections cassées.
$appUrl = trim((string) ($appConfig['url'] ?? ''));
if ($appUrl === '' && !empty($_SERVER['HTTP_HOST'])) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');
    $scheme = $isHttps ? 'https' : 'http';
    $appUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/';
}
if ($appUrl !== '') {
    if (!preg_match('#^https?://#i', $appUrl)) {
        $appUrl = 'https://' . $appUrl;
    }
    $appUrl = rtrim($appUrl, '/') . '/';
}
define('APP_URL', $appUrl);
define('UPLOAD_DIR', (isset($appConfig['upload_dir']) && $appConfig['upload_dir'][0] === '/')
    ? $appConfig['upload_dir']
    : APP_PUBLIC_PATH . '/' . ($appConfig['upload_dir'] ?? 'uploads'));
define('MAX_PHOTO_BYTES', $appConfig['max_upload'] ?? 4 * 1024 * 1024);
