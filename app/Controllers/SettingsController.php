<?php

namespace App\Controllers;

/**
 * Page Paramètres : comptes applicatifs + accès responsables.
 */
class SettingsController extends Controller
{
    public function index(): void
    {
        render_parametres_page();
    }
}
