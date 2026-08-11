<?php

namespace App\Controllers;

use App\Core\Request;
use App\Middleware\AdminMiddleware;
use App\Services\RegistrationService;

/**
 * Administration des inscriptions publiques : liste des demandes en attente
 * + fiche détaillée. Accès strictement réservé aux administrateurs — le
 * contrôle est fait ici côté serveur (AdminMiddleware), pas seulement en
 * masquant les liens côté vue.
 */
class AdminRegistrationController extends Controller
{
    private RegistrationService $registrations;

    public function __construct(?RegistrationService $registrations = null)
    {
        $this->registrations = $registrations ?? new RegistrationService();
    }

    /** GET ?page=admin_inscriptions — liste des inscriptions en attente. */
    public function index(): void
    {
        (new AdminMiddleware())->handle();

        $pending = $this->registrations->listPendingRegistrations();

        $content = view('pages/admin_inscriptions', ['registrations' => $pending]);
        render_page(SECTION_LABELS['admin_inscriptions'], $content);
    }

    /** GET ?page=admin_inscription&id=... — fiche détaillée d'une demande. */
    public function show(): void
    {
        (new AdminMiddleware())->handle();

        $id = (int) (Request::get('id') ?? 0);
        $registration = $id ? $this->registrations->getRegistration($id) : null;

        if (!$registration) {
            $this->redirect('index.php', ['page' => 'admin_inscriptions']);
        }

        $content = view('pages/admin_inscription_detail', [
            'registration' => $registration,
            'csrf'         => csrf_field(),
        ]);
        render_page(SECTION_LABELS['admin_inscription'], $content);
    }
}
