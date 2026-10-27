<?php

namespace App\Controllers;

/**
 * Anciennes pages « Présentation de l'église » / « Présentation des centres ».
 * Pour tout visiteur, elles redirigent (301) vers la vitrine publique. Les
 * administrateurs conservent les écrans de gestion (histoire, équipe,
 * articles de centres) ; leurs formulaires et URL ne changent pas.
 */
class AboutController extends Controller
{
    public function apropos(): void
    {
        $this->legacyRedirect('a-propos');
        render_apropos_page();
    }

    public function centresPresentation(): void
    {
        $this->legacyRedirect('centres');
        // Les articles se configurent désormais dans Paramètres du centre.
        $this->redirect('index.php', ['page' => 'centres']);
    }

    private function legacyRedirect(string $anchor): void
    {
        $user = current_user();
        if ($user && $user['role'] === 'admin') {
            return;
        }
        header('Location: ' . url('index.php', ['page' => 'eglise']) . '#' . $anchor, true, 301);
        exit;
    }
}
