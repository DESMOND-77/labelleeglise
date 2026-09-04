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
    /**
     * Agenda unifié : grille mensuelle (rendu serveur) + panneau détail du jour
     * sélectionné (JS, avec repli lien sans JS). Remplace les onglets Calendrier
     * et Anniversaires.
     */
    public function agenda(): void
    {
        if (!current_user()) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }

        $today = date('Y-m-d');

        $ym = (string) (Request::get('ym') ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $ym) || (int) substr($ym, 5, 2) < 1 || (int) substr($ym, 5, 2) > 12) {
            $ym = substr($today, 0, 7);
        }
        $year = (int) substr($ym, 0, 4);
        $monthNo = (int) substr($ym, 5, 2);

        $date = (string) (Request::get('date') ?? '');
        $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && date('Y-m-d', strtotime($date)) === $date
            && substr($date, 0, 7) === $ym;
        if (!$validDate) {
            $date = substr($today, 0, 7) === $ym ? $today : $ym . '-01';
        }

        $svc = calendrier_service();
        $month = $svc->agendaForMonth($year, $monthNo);
        $dayDetail = $svc->agendaForDate($date);

        // Grille 6×7, semaine commençant lundi.
        $firstOfMonth = $ym . '-01';
        $startDow = (int) date('N', strtotime($firstOfMonth)); // 1 = lundi
        $gridStart = date('Y-m-d', strtotime($firstOfMonth . ' -' . ($startDow - 1) . ' days'));
        $weeks = [];
        for ($w = 0; $w < 6; $w++) {
            $row = [];
            for ($d = 0; $d < 7; $d++) {
                $cell = date('Y-m-d', strtotime($gridStart . ' +' . ($w * 7 + $d) . ' days'));
                $row[] = [
                    'date'     => $cell,
                    'day'      => (int) substr($cell, 8, 2),
                    'adjacent' => substr($cell, 0, 7) !== $ym,
                ];
            }
            $weeks[] = $row;
        }

        render_page(SECTION_LABELS['agenda'], view('pages/agenda', [
            'canManage'    => auth_can_manage_calendar(),
            'ym'           => $ym,
            'year'         => $year,
            'monthNo'      => $monthNo,
            'prevYm'       => date('Y-m', strtotime($firstOfMonth . ' -1 month')),
            'nextYm'       => date('Y-m', strtotime($firstOfMonth . ' +1 month')),
            'date'         => $date,
            'today'        => $today,
            'month'        => $month,
            'dayDetail'    => $dayDetail,
            'weeks'        => $weeks,
            'responsables' => Query::all("SELECT id, prenom, nom FROM users WHERE role IN ('berger','ms','pasteur','reverant','admin') ORDER BY prenom, nom"),
            'monthsFr'     => MONTHS_FR,
            'errors'       => [],
            'old'          => [],
            'csrf'         => csrf_field(),
        ]));
    }

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
        $this->redirect('index.php', ['page' => 'agenda']);
    }

    public function anniversaires(): void
    {
        if (!current_user()) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        $this->redirect('index.php', ['page' => 'agenda']);
    }

    private function evenementFiche(int $id): void
    {
        $evt = calendrier_service()->event($id);
        if (!$evt) {
            $this->redirect('index.php', ['page' => 'calendrier']);
        }
        $canPointe = auth_can_manage_calendar() || auth_can_edit_evenement($evt);
        $date = (string) (Request::get('date') ?: date('Y-m-d'));
        $members = $canPointe
            ? Query::all("SELECT * FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY prenom, nom")
            : [];
        render_page($evt['nom'], view('pages/calendrier', [
            'events'          => [],
            'canManage'       => auth_can_manage_calendar(),
            'edit'            => null,
            'responsables'    => [],
            'errors'          => [],
            'old'             => [],
            'csrf'            => csrf_field(),
            'mode'            => 'fiche',
            'fiche'           => $evt,
            'canEditFiche'    => auth_can_edit_evenement($evt),
            'canPointe'       => $canPointe,
            'presenceDate'    => $date,
            'presenceGrid'    => $canPointe ? unit_presence_grid('evenement', (int) $evt['id'], $date, $members) : [],
            'presenceStatuts' => PRESENCE_STATUTS,
        ]));
    }
}
