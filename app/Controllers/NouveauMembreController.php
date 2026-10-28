<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Query;
use App\Core\Request;

/**
 * Nouveaux membres : liste de suivi, dossier individuel, formulaire d'ajout.
 */
class NouveauMembreController extends Controller
{
    public function index(): void
    {
        $user = current_user();
        if (!$user) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        if (!auth_can_manage_nouveaux_membres()) {
            $this->redirect('index.php', ['page' => 'accueil']);
        }
        $isAdmin = ($user['role'] ?? '') === 'admin';
        nouveau_membre_service()->syncIntegration(null, (int) $user['id']);

        // ?pointage=1 : pointeur de présence (liste à cocher), comme le
        // pointage bacenta/culte mais pour la population des nouveaux
        // membres actifs uniquement.
        if ((int) (Request::get('pointage') ?? 0) === 1) {
            $date = trim((string) (Request::get('date') ?? '')) ?: date('Y-m-d');
            $svc = nouveau_membre_service();
            $roster = $svc->rosterForPointage($isAdmin ? null : (int) $user['id'], $date);
            render_page('Pointage - Nouveaux membres', view('pages/nouveau_membre_pointage', [
                'roster' => $roster,
                'date'   => $date,
                'error'  => trim((string) (Request::get('error') ?? '')),
                'saved'  => Request::get('saved') !== null ? (int) Request::get('saved') : null,
                'noSundayCulte' => !$svc->hasSundayCulte(),
                'csrf'   => csrf_field(),
            ]));
            return;
        }

        $filters = [
            'q'              => trim((string) (Request::get('q') ?? '')),
            'responsable_id' => (int) (Request::get('responsable_id') ?? 0) ?: null,
            'bacenta_id'     => (int) (Request::get('bacenta_id') ?? 0) ?: null,
            'statut'         => trim((string) (Request::get('statut') ?? '')) ?: null,
            'unassigned'     => (bool) (Request::get('unassigned') ?? false),
        ];
        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '' && $v !== false);

        // Une personne intégrée (3/3) quitte la liste et rejoint la liste générale
        // des membres ; on ne la revoit qu'en filtrant explicitement sur ce statut.
        $queryFilters = $filters;
        if (empty($filters['statut'])) {
            $queryFilters['exclude_integrated'] = true;
        }

        $svc = nouveau_membre_service();
        $rows = $isAdmin
            ? $svc->listForAdmin($queryFilters)
            : $svc->listForResponsable((int) $user['id'], $queryFilters);

        render_page(SECTION_LABELS['nouveaux_membres'], view('pages/nouveaux_membres', [
            'rows'         => $rows,
            'isAdmin'      => $isAdmin,
            'responsables' => $svc->candidateResponsables(),
            'bacentas'     => get_bacentas(),
            'filters'      => $filters,
            'csrf'         => csrf_field(),
        ]));
    }

    public function dossier(): void
    {
        $user = current_user();
        if (!$user) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        if (!auth_can_manage_nouveaux_membres()) {
            $this->redirect('index.php', ['page' => 'accueil']);
        }
        $isAdmin = ($user['role'] ?? '') === 'admin';

        // ?form=1 : formulaire d'ajout (pas de dossier existant).
        if ((int) (Request::get('form') ?? 0) === 1) {
            // Mêmes options "Invité par" / "Reçu par" que le formulaire membre
            // existant (render_member_form(), app/Compat/sections.php) - voir
            // users.invite_par/users.recu_par, colonnes déjà existantes,
            // réutilisées ici sans toucher au formulaire/flux membre d'origine.
            $allUsers = Query::all('SELECT id, prenom, nom FROM users ORDER BY prenom, nom');
            $userOptions = '';
            $akwabaOptions = '';
            foreach ($allUsers as $u) {
                $userOptions .= '<option value="' . $u['id'] . '">' . h(full_name($u)) . '</option>';
                $akwabaOptions .= '<option value="' . $u['id'] . '">' . h(full_name($u)) . '</option>';
            }
            render_page('Ajouter un nouveau membre', view('pages/forms/nouveau_membre', [
                'bacentas'      => get_bacentas(),
                'userOptions'   => $userOptions,
                'akwabaOptions' => $akwabaOptions,
                'csrf'          => csrf_field(),
            ]));
            return;
        }

        $id = (int) (Request::get('id') ?? 0);
        $svc = nouveau_membre_service();
        $nm = $id ? $svc->dossier($id) : null;
        if (!$nm) {
            $this->redirect('index.php', ['page' => 'nouveaux_membres']);
        }
        if (!$isAdmin && (int) ($nm['responsable_id'] ?? 0) !== (int) $user['id']) {
            $this->redirect('index.php', ['page' => 'nouveaux_membres']);
        }

        render_page('Dossier - ' . trim($nm['prenom'] . ' ' . $nm['nom']), view('pages/nouveau_membre', [
            'nm'           => $nm,
            'isAdmin'      => $isAdmin,
            'responsables' => $svc->candidateResponsables(),
            'csrf'         => csrf_field(),
        ]));
    }
}
