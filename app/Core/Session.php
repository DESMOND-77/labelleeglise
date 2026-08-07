<?php

namespace App\Core;

/**
 * Gestion de session (nom personnalisé, démarrage sûr).
 */
class Session
{
    /** Démarre la session si ce n'est pas déjà fait. */
    public static function start(?string $name = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if ($name) {
                session_name($name);
            }
            session_start();
        }
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flush(): void
    {
        $_SESSION = [];
    }

    public static function destroy(): void
    {
        self::flush();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
