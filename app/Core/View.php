<?php

namespace App\Core;

/**
 * Moteur de rendu des vues (templates serveur).
 * Reprend le fonctionnement de l'ancien render.php.
 */
class View
{
    /**
     * Rendu d'un template en chaîne HTML.
     * $name peut être "pages/accueil", "components/x", "layouts/layout".
     */
    public static function render(string $name, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require APP_VIEWS_PATH . '/' . $name . '.php';
        return (string) ob_get_clean();
    }

    /**
     * Page complète (shell : sidebar + topbar + contenu).
     */
    public static function page(string $title, string $content, ?array $charts = null): void
    {
        echo self::render('layouts/layout', [
            'title'   => $title,
            'content' => $content,
            'charts'  => $charts,
        ]);
    }

    /** Première lettre (repli sûr si mbstring absent). */
    public static function firstChar(string $s): string
    {
        return function_exists('mb_substr') ? mb_substr($s, 0, 1) : substr($s, 0, 1);
    }
}
