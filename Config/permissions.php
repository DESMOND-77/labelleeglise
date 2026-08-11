<?php

/**
 * Matrice ROLE → PERMISSIONS.
 * -------------------------------------------------------------
 * Config-driven à dessein (pas de tables RoleRepository/PermissionRepository
 * en base) : voir CONTRIBUTING.md (KISS/YAGNI) et Config/constants.php pour
 * la convention "tableau PHP statique" déjà en place dans ce projet.
 *
 * Un rôle ne code JAMAIS une structure ("berger_centre", "responsable_x"…).
 * Les permissions ci-dessous décrivent uniquement des CAPACITÉS générales.
 * Le périmètre réel (quel centre/bacenta/culte précis) est déterminé par la
 * couche `responsibilities` (table SQL) + AuthorizationService, jamais ici.
 *
 * `*` signifie "toutes les permissions" (admin).
 *
 * Voir docs/roles-and-permissions.md pour la matrice documentée avec
 * exemples et docs/authorization.md pour l'usage de $auth->can(...).
 */

declare(strict_types=1);

return [
    'admin' => ['*'],

    'pasteur' => [
        'view_church_information',
        'view_centers',
        'weekly_followup.manage_own',
        'center.manage_assigned',
        'bacenta.manage_assigned',
        'cult.manage_assigned',
        'responsibilities.receive_center',
        'responsibilities.receive_bacenta',
        'responsibilities.receive_cult',
    ],

    'reverant' => [
        'view_church_information',
        'view_centers',
        'weekly_followup.manage_own',
        'cult.manage_assigned',
        'responsibilities.receive_cult',
    ],

    'berger' => [
        'view_church_information',
        'view_centers',
        'weekly_followup.manage_own',
        'center.manage_assigned',
        'bacenta.manage_assigned',
        'responsibilities.receive_center',
        'responsibilities.receive_bacenta',
    ],

    'ms' => [
        'view_church_information',
        'view_centers',
        'weekly_followup.manage_own',
        'center.manage_assigned',
        'bacenta.manage_assigned',
        'responsibilities.receive_center',
        'responsibilities.receive_bacenta',
    ],

    // Comportement historique préservé (spec §21/§43 : "Leader : conserver
    // ses fonctionnalités actuelles"). Le leader gère SA bacenta
    // d'appartenance (users.bacenta_id), pas via le modèle de
    // responsabilités — voir RbacService::scope() (kind = 'berger').
    'leader' => [
        'view_church_information',
        'view_centers',
        'weekly_followup.manage_own',
        'bacenta.manage_own_membership',
    ],

    'membre' => [
        'view_church_information',
        'view_centers',
    ],

    // Rôle hérité, jamais supprimé de l'ENUM (voir migration). Traité comme
    // équivalent à `membre` : aucune permission supplémentaire.
    'assistant' => [
        'view_church_information',
        'view_centers',
    ],
];
