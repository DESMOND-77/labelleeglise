<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Site public : landing page (sans authentification) et écran de connexion.
 * Aucune donnée privée n'est lue ici - voir PublicSiteService.
 */
class PublicController extends Controller
{
    /** GET ?page=eglise (et `/` pour un visiteur anonyme). */
    public function index(): void
    {
        $service = public_site_service();
        $data = $service->landing();
        $canonical = str_starts_with(APP_URL, 'http') ? url('index.php', ['page' => 'eglise']) : '';

        echo view('layouts/public', [
            'title'     => $data['nom'],
            'canonical' => $canonical,
            'jsonLd'    => $service->jsonLd($data, $canonical),
            'hasUser'   => (bool) current_user(),
            'content'   => view('pages/public/landing', $data),
            'brand'     => $data['nom'],
        ]);
    }

    /** GET ?page=login - un utilisateur déjà connecté est renvoyé vers son espace. */
    public function login(): void
    {
        if (current_user()) {
            $this->redirect('index.php', scope_target() ?: ['page' => 'accueil']);
        }
        (new AuthController())->loginForm();
    }
}
