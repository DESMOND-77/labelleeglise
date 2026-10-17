<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AdminMiddleware;

/**
 * Administration du site public (identité, contacts, centres publiés).
 * Réservé aux administrateurs (contrôle serveur via AdminMiddleware).
 */
class AdminSiteController extends Controller
{
    /** GET ?page=site_public */
    public function index(): void
    {
        (new AdminMiddleware())->handle();

        $data = public_site_service()->adminData();
        $content = view('pages/admin_site_public', $data + [
            'csrf'    => csrf_field(),
            'errors'  => array_values(array_filter((array) ($_SESSION['site_public_errors'] ?? []), 'is_string')),
            'saved'   => isset($_GET['saved']),
        ]);
        unset($_SESSION['site_public_errors']);
        render_page(SECTION_LABELS['site_public'], $content);
    }
}
