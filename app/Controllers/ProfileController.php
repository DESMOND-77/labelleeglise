<?php

namespace App\Controllers;

/**
 * Fiche profil d'un membre (donut de présence).
 */
class ProfileController extends Controller
{
    public function index(): void
    {
        render_profile_page();
    }
}
