<?php

namespace App\Controllers;

/**
 * Sections structurelles (bacentas, centres, cultes, basontas, listes membres).
 * Les actions de rendu orientées section sont prises en charge par le
 * compat layer (render_section_page) pour préserver l'intégralité de la logique.
 */
class SectionController extends Controller
{
    public function index(): void
    {
        render_section_page((string) nav('page'));
    }
}
