<?php

namespace App\Services;

use App\Core\Upload;
use App\Repositories\UserRepository;

/**
 * Gestion du profil libre-service ("Mon profil") : informations
 * personnelles, photo, mot de passe. Ne touche JAMAIS à l'email (voir
 * EmailChangeService, flux séparé et sensible) ni au rôle (jamais
 * modifiable par l'utilisateur lui-même).
 */
class ProfileService
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    /**
     * Valide et enregistre les champs personnels modifiables.
     *
     * @return array<string,string> erreurs par champ (vide = ok)
     */
    public function validatePersonalInfo(array $input): array
    {
        $errors = [];
        $nom = trim((string) ($input['nom'] ?? ''));
        $prenom = trim((string) ($input['prenom'] ?? ''));
        $telephone = trim((string) ($input['telephone'] ?? ''));
        $dateNaissance = trim((string) ($input['date_naissance'] ?? ''));

        if ($nom === '') {
            $errors['nom'] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($nom) > 50) {
            $errors['nom'] = 'Le nom est trop long (50 caractères maximum).';
        }
        if ($prenom === '') {
            $errors['prenom'] = 'Le prénom est obligatoire.';
        } elseif (mb_strlen($prenom) > 50) {
            $errors['prenom'] = 'Le prénom est trop long (50 caractères maximum).';
        }
        if ($telephone !== '' && !preg_match('/^[0-9+][0-9+\s().-]{5,19}$/', $telephone)) {
            $errors['telephone'] = 'Numéro de téléphone invalide.';
        }
        if ($dateNaissance !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateNaissance)) {
            $errors['date_naissance'] = 'Date de naissance invalide.';
        }

        return $errors;
    }

    public function updatePersonalInfo(int $userId, array $input): void
    {
        $this->users->updateProfileFields($userId, [
            'nom' => trim((string) ($input['nom'] ?? '')),
            'prenom' => trim((string) ($input['prenom'] ?? '')),
            'date_naissance' => trim((string) ($input['date_naissance'] ?? '')) ?: null,
            'quartier' => trim((string) ($input['quartier'] ?? '')) ?: null,
            'telephone' => trim((string) ($input['telephone'] ?? '')) ?: null,
        ]);
    }

    /**
     * Traite l'upload de photo si un fichier a été soumis : validation
     * réelle du contenu (Upload::photo — getimagesize, pas l'extension),
     * suppression de l'ancienne photo, mise à jour en base.
     *
     * @return array{ok:bool, error:?string} error='invalid_image' si un fichier a été soumis mais rejeté
     */
    public function updatePhoto(array $user, string $inputName = 'photo'): array
    {
        if (empty($_FILES[$inputName]['name'])) {
            return ['ok' => true, 'error' => null]; // aucun fichier soumis : pas une erreur
        }
        $newPath = Upload::photo($inputName);
        if (!$newPath) {
            return ['ok' => false, 'error' => 'invalid_image'];
        }
        Upload::deletePhoto($user['photo_de_profil'] ?? null);
        $this->users->updatePhoto((int) $user['id'], $newPath);
        return ['ok' => true, 'error' => null];
    }

    /**
     * Change le mot de passe après vérification du mot de passe actuel.
     *
     * @return array{ok:bool, error:?string} error ∈ current_invalid|too_short|mismatch
     */
    public function changePassword(array $user, string $current, string $new, string $confirm): array
    {
        if (!password_verify($current, $user['password'])) {
            return ['ok' => false, 'error' => 'current_invalid'];
        }
        if (mb_strlen($new) < 8) {
            return ['ok' => false, 'error' => 'too_short'];
        }
        if ($new !== $confirm) {
            return ['ok' => false, 'error' => 'mismatch'];
        }
        $this->users->updatePassword((int) $user['id'], password_hash($new, PASSWORD_DEFAULT));
        return ['ok' => true, 'error' => null];
    }
}
