<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\EmailChangeService;

/**
 * Profil : fiche administrative d'un membre (identité, rôle, présences,
 * suivi hebdo) ET page "Mon profil" en libre-service (toujours l'utilisateur
 * connecté, jamais un id fourni par le client — voir render_my_profile_page).
 */
class ProfileController extends Controller
{
    private EmailChangeService $emailChange;

    public function __construct(?EmailChangeService $emailChange = null)
    {
        $this->emailChange = $emailChange ?? new EmailChangeService();
    }

    /** GET ?page=personProfile&membre=<id> — fiche administrative (soi-même ou périmètre autorisé). */
    public function index(): void
    {
        render_profile_page();
    }

    /** GET ?page=profile — "Mon profil" (toujours l'utilisateur connecté). */
    public function me(): void
    {
        render_my_profile_page();
    }

    /** GET ?page=attendancePrint&membre=<id>&semaine=... — fiche présences imprimable. */
    public function attendancePrint(): void
    {
        render_attendance_print_page();
    }

    /** GET ?page=suiviPrint&membre=<id>&semaine=... — fiche suivi hebdo imprimable. */
    public function suiviPrint(): void
    {
        render_suivi_print_page();
    }

    /** GET ?page=verify_email_change&token=... — confirmation du changement d'email (public). */
    public function verifyEmailChange(): void
    {
        $token = (string) (Request::get('token') ?? '');
        $result = $this->emailChange->verifyChange($token);

        echo view('pages/email_change_verify', [
            'status' => $result['status'],
            'user'   => $result['user'],
        ]);
    }

    /** GET ?page=email_change_pending — "vérifiez votre nouvel email" après déconnexion immédiate. */
    public function emailChangePending(): void
    {
        echo view('pages/email_change_pending', []);
    }
}
