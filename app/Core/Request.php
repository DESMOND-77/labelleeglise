<?php

namespace App\Core;

/**
 * Abstrait la requête HTTP (GET/POST, paramètres, méthode).
 */
class Request
{
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    public static function get(string $key, $default = null)
    {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            return $_GET[$key];
        }
        return $default;
    }

    public static function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    public static function has(string $key, string $source = 'get'): bool
    {
        $arr = $source === 'post' ? $_POST : $_GET;
        return isset($arr[$key]) && $arr[$key] !== '';
    }

    public static function int(string $key, int $default = 0, string $source = 'get'): int
    {
        $arr = $source === 'post' ? $_POST : $_GET;
        return isset($arr[$key]) ? (int) $arr[$key] : $default;
    }

    public static function string(string $key, string $default = '', string $source = 'get'): string
    {
        $arr = $source === 'post' ? $_POST : $_GET;
        return isset($arr[$key]) ? trim((string) $arr[$key]) : $default;
    }

    public static function all(string $source = 'get'): array
    {
        return $source === 'post' ? $_POST : $_GET;
    }
}
