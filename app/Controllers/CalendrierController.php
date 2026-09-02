<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Query;
use App\Core\Request;

/**
 * Calendrier événementiel + calendrier d'anniversaires.
 */
class CalendrierController extends Controller
{
    public function evenements(): void
    {
        if (!current_user()) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        $evt = (int) (Request::get('evt') ?? 0);
        if ($evt) {
            $this->evenementFiche($evt);
            return;
        }

        $svc = calendrier_service();
        $canManage = auth_can_manage_calendar();
        $editId = (int) (Request::get('edit') ?? 0);
        $edit = null;
        if ($editId && $canManage) {
            $edit = $svc->event($editId);
            if ($edit && !auth_can_edit_evenement($edit)) {
                $edit = null;
            }
        }

        render_page(SECTION_LABELS['calendrier'], view('pages/calendrier', [
            'events'       => $svc->allEvents(),
            'canManage'    => $canManage,
            'edit'         => $edit,
            'responsables' => Query::all("SELECT id, prenom, nom FROM users WHERE role IN ('berger','ms','pasteur','reverant','admin') ORDER BY prenom, nom"),
            'errors'       => [],
            'old'          => [],
            'csrf'         => csrf_field(),
            'mode'         => 'list',
        ]));
    }

    public function anniversaires(): void
    {
        if (!current_user()) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        render_page(SECTION_LABELS['anniversaires'], view('pages/anniversaires', [
            'birthdays'    => calendrier_service()->birthdays(),
            'canManage'    => auth_can_manage_calendar(),
            'monthsFr'     => MONTHS_FR,
            'currentMonth' => (int) date('n'),
            'errors'       => [],
            'old'          => [],
            'csrf'         => csrf_field(),
        ]));
    }

    private function evenementFiche(int $id): void
    {
        $evt = calendrier_service()->event($id);
        if (!$evt) {
            $this->redirect('index.php', ['page' => 'calendrier']);
        }
        render_page($evt['nom'], view('pages/calendrier', [
            'events'       => [],
            'canManage'    => auth_can_manage_calendar(),
            'edit'         => null,
            'responsables' => [],
            'errors'       => [],
            'old'          => [],
            'csrf'         => csrf_field(),
            'mode'         => 'fiche',
            'fiche'        => $evt,
            'canEditFiche' => auth_can_edit_evenement($evt),
        ]));
    }
}
