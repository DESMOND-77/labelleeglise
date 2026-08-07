<?php
/**
 * Configuration de l'authentification & des rôles.
 */

declare(strict_types=1);

return [
    'roles' => [
        'admin'       => 'Administrateur',
        'leader'      => 'Leader',
        'assistant'   => 'Assistant',
        'pasteur'     => 'Pasteur',
        'reverant'    => 'Révérend',
        'membre'      => 'Membre',
        'responsable' => 'Responsable',
    ],
    // Les "bergers" (fiche berger + suivi hebdomadaire) = ces rôles.
    'bergerRoles' => ['leader', 'pasteur', 'reverant'],
];
