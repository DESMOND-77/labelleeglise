<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Response;

/**
 * Contrôleur de base : rendu de vues, redirections.
 */
abstract class Controller
{
    protected function render(string $template, array $vars = []): string
    {
        return View::render($template, $vars);
    }

    protected function page(string $title, string $content, ?array $charts = null): void
    {
        View::page($title, $content, $charts);
    }

    protected function redirect(string $path = '', array $params = []): never
    {
        Response::redirect($path, $params);
    }
}
