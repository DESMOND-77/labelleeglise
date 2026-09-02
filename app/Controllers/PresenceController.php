<?php

namespace App\Controllers;

/**
 * Présences par occurrence : impression de la matrice annuelle d'une unité.
 * (Le pointage et la consultation in-app vivent dans les onglets des pages
 * détail d'unité — voir render_unit_presence_tab dans app/Compat/sections.php.)
 */
class PresenceController extends Controller
{
    /** GET ?page=presencePrint&unit_type=&unit_id=&year= — matrice annuelle imprimable. */
    public function matrixPrint(): void
    {
        render_presence_matrix_print_page();
    }
}
