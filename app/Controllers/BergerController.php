<?php

namespace App\Controllers;

/**
 * Pages "Berger" : fiche berger, suivi hebdomadaire, suivi statistiques admin.
 */
class BergerController extends Controller
{
    public function fiche(): void
    {
        render_berger_fiche_page();
    }

    public function suivi(): void
    {
        render_suivi_bergers_page();
    }
}
