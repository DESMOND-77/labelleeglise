<?php

namespace App\Controllers;

/**
 * Pages "À propos" (présentation de l'église) + présentation des centres.
 */
class AboutController extends Controller
{
    public function apropos(): void
    {
        render_apropos_page();
    }

    public function centresPresentation(): void
    {
        render_centres_presentation_page();
    }
}
