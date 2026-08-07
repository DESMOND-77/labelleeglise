<?php

namespace App\Controllers;

/**
 * Tableau de bord (accueil) + recherche globale.
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        render_accueil_page();
    }

    public function search(): void
    {
        render_recherche_page();
    }
}
