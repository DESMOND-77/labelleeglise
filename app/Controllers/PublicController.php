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
            'ogImage'   => $data['hero_image'] ?? '',
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

    /**
     * Pages légales publiques (mentions légales, confidentialité).
     * Servies seulement si l'église a fourni le texte : jamais
     * de contenu inventé. Une page vide redirige vers la vitrine.
     */
    public function mentionsLegales(): void
    {
        $this->renderLegal('mentions_legales', 'Mentions légales', 'mentions_legales');
    }

    public function confidentialite(): void
    {
        $this->renderLegal('confidentialite', 'Politique de confidentialité', 'confidentialite');
    }

    private function renderLegal(string $route, string $title, string $key): void
    {
        $data = public_site_service()->landing();
        $text = trim((string) ($data[$key] ?? ''));
        if ($text === '') {
            $this->redirect('index.php', ['page' => 'eglise']);
        }

        echo view('layouts/public', [
            'title'     => $title . ' - ' . $data['nom'],
            'canonical' => str_starts_with(APP_URL, 'http') ? url('index.php', ['page' => $route]) : '',
            'jsonLd'    => null,
            'hasUser'   => (bool) current_user(),
            'content'   => view('pages/public/legal', [
                'title' => $title,
                'text'  => $text,
                'nom'   => $data['nom'],
            ]),
            'brand'     => $data['nom'],
        ]);
    }
}
