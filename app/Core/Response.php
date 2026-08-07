<?php

namespace App\Core;

/**
 * Réponses HTTP : redirections, codes, JSON.
 */
class Response
{
    public static function redirect(string $path = '', array $params = []): never
    {
        $query = $params ? ('?' . http_build_query($params)) : '';
        header('Location: ' . APP_URL . $path . $query);
        exit;
    }

    public static function status(int $code): void
    {
        http_response_code($code);
    }

    public static function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        exit($message);
    }
}
