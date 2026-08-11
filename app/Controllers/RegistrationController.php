<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\RegistrationService;

/**
 * Inscription publique + vérification d'email. Pages accessibles sans
 * connexion (voir index.php : liste des pages publiques autorisées avant
 * la porte d'authentification).
 */
class RegistrationController extends Controller
{
    private RegistrationService $registrations;

    public function __construct(?RegistrationService $registrations = null)
    {
        $this->registrations = $registrations ?? new RegistrationService();
    }

    /** GET ?page=register — formulaire d'inscription publique. */
    public function form(): void
    {
        echo view('pages/register', [
            'errors' => [],
            'old'    => [],
            'sent'   => isset($_GET['sent']),
        ]);
    }

    /** GET ?page=verify_email&token=... — vérification du lien reçu par email. */
    public function verify(): void
    {
        $token = (string) (Request::get('token') ?? '');
        $result = $this->registrations->verifyEmailToken($token);

        echo view('pages/verify_email', [
            'status' => $result['status'],
            'user'   => $result['user'],
        ]);
    }

    /**
     * POST action=register — traitement du formulaire d'inscription publique.
     * Validation entièrement côté serveur (ne fait jamais confiance au JS).
     * Le rôle n'est JAMAIS lu depuis la requête : il est imposé par
     * RegistrationService/UserRepository::createRegistration().
     */
    public function submit(): void
    {
        check_csrf();

        $input = [
            'nom'              => (string) (Request::post('nom') ?? ''),
            'prenom'           => (string) (Request::post('prenom') ?? ''),
            'email'            => (string) (Request::post('email') ?? ''),
            'telephone'        => (string) (Request::post('telephone') ?? ''),
            'password'         => (string) (Request::post('password') ?? ''),
            'password_confirm' => (string) (Request::post('password_confirm') ?? ''),
        ];

        $errors = $this->registrations->validate($input);

        if ($errors) {
            $old = $input;
            unset($old['password'], $old['password_confirm']);
            echo view('pages/register', [
                'errors' => $errors,
                'old'    => $old,
                'sent'   => false,
            ]);
            return;
        }

        $this->registrations->register($input);

        $this->redirect('index.php', ['page' => 'register', 'sent' => 1]);
    }
}
