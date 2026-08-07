<?php

namespace App\Core;

/**
 * Cache sur fichiers (Storage/cache/), sans dépendance externe.
 */
class Cache
{
    /**
     * Retourne une valeur en cache, ou la calcule puis la stocke.
     * $ttl en secondes (0 = jamais).
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        $file = APP_CACHE_PATH . '/' . md5($key) . '.cache';

        if (is_file($file) && $ttl > 0 && (time() - filemtime($file)) < $ttl) {
            $data = @unserialize((string) file_get_contents($file));
            if ($data !== false) {
                return $data;
            }
        }

        $value = $callback();
        self::put($key, $value);
        return $value;
    }

    /** Stocke une valeur dans le cache. */
    public static function put(string $key, $value): void
    {
        if (!is_dir(APP_CACHE_PATH)) {
            @mkdir(APP_CACHE_PATH, 0775, true);
        }
        @file_put_contents(APP_CACHE_PATH . '/' . md5($key) . '.cache', serialize($value));
    }

    /** Retourne une valeur en cache si elle existe. */
    public static function get(string $key, $default = null)
    {
        $file = APP_CACHE_PATH . '/' . md5($key) . '.cache';
        if (!is_file($file)) {
            return $default;
        }
        $data = @unserialize((string) file_get_contents($file));
        return $data === false ? $default : $data;
    }

    /** Force le rechargement d'un cache. */
    public static function forget(string $key): void
    {
        $file = APP_CACHE_PATH . '/' . md5($key) . '.cache';
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
