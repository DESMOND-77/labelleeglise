<?php

namespace App\Controllers;

/**
 * Page Finances & Offrandes.
 */
class FinanceController extends Controller
{
    public function index(): void
    {
        render_finances_page();
    }
}
