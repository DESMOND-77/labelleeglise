<?php

namespace App\Core;

/**
 * Jetons CSRF — protection des formulaires POST.
 */
class Csrf
{
    /** Retourne (ou crée) le jeton CSRF de session. */
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    /** Champ caché CSRF à insérer dans un formulaire. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Vérifie le jeton CSRF reçu (POST). */
    public static function check(string $token): void
    {
        if (!hash_equals(self::token(), (string) $token)) {
            http_response_code(400);
            exit('Jeton de sécurité invalide (page expirée). Revenez en arrière et réessayez.');
        }
    }
}
