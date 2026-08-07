<?php

namespace App\Core;

/**
 * Routeur léger inspiré de Laravel.
 * -------------------------------------------------------------
 * Associe une clé de page (paramètre `?page=`) à un contrôleur/méthode.
 * Les URL existantes (`index.php?page=...`) sont ainsi préservées.
 */
class Router
{
    /** @var array<string, array{0:string,1:string}> Routes GET. */
    private static array $get = [];

    /** @var array<string, array{0:string,1:string}> Routes POST. */
    private static array $post = [];

    public static function get(string $key, string $controller, string $method): void
    {
        self::$get[$key] = [$controller, $method];
    }

    public static function post(string $key, string $controller, string $method): void
    {
        self::$post[$key] = [$controller, $method];
    }

    /**
     * Dispatch une requête vers le bon contrôleur.
     * Retourne false si aucune route ne correspond.
     */
    public static function dispatch(string $method, string $page): bool
    {
        $table = strtoupper($method) === 'POST' ? self::$post : self::$get;
        $route = $table[$page] ?? null;

        if (!$route) {
            return false;
        }

        [$controller, $methodName] = $route;
        $instance = new $controller();
        $instance->$methodName();

        return true;
    }

    /** Journale les routes enregistrées (debug). */
    public static function routes(): array
    {
        return ['get' => self::$get, 'post' => self::$post];
    }
}
