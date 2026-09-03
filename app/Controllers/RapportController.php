<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

/**
 * Rapport du Jour des responsables de bacenta : liste + formulaire.
 */
class RapportController extends Controller
{
    public function index(): void
    {
        $user = current_user();
        if (!$user) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        if (!auth_can_report_any()) {
            $this->redirect('index.php', ['page' => 'accueil']);
        }
        $isAdmin = ($user['role'] ?? '') === 'admin';

        $centres = array_values(array_filter(
            get_centres(),
            static fn($c) => $isAdmin || auth_can_report_for_centre((int) $c['id'])
        ));

        $filterCentre = (int) (Request::get('centre') ?? 0) ?: null;
        $filterMois = trim((string) (Request::get('mois') ?? '')) ?: null;

        render_page(SECTION_LABELS['rapports'], view('pages/rapports', [
            'rows'          => rapport_jour_service()->list($filterCentre, $filterMois),
            'centres'       => $centres,
            'filterCentre'  => $filterCentre,
            'filterMois'    => $filterMois,
            'isAdmin'       => $isAdmin,
            'currentUserId' => (int) $user['id'],
        ]));
    }

    public function form(): void
    {
        $user = current_user();
        if (!$user) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $uid = (int) $user['id'];
        $svc = rapport_jour_service();

        $id = (int) (Request::get('id') ?? 0);
        $report = $id ? $svc->report($id) : null;

        $centreId = $report ? (int) $report['centre_id'] : ((int) (Request::get('centre') ?? 0) ?: null);
        $date = $report
            ? (string) $report['date_rapport']
            : (trim((string) (Request::get('date') ?? '')) ?: date('Y-m-d'));

        if ($centreId !== null && !auth_can_report_for_centre($centreId)) {
            $this->redirect('index.php', ['page' => 'rapports']);
        }
        // Rapport existant non résolu par (?centre&?date) si l'id n'était pas fourni :
        if ($report === null && $centreId !== null) {
            $report = $svc->reportForCentreDate($centreId, $date);
        }

        $centres = array_values(array_filter(
            get_centres(),
            static fn($c) => $isAdmin || auth_can_report_for_centre((int) $c['id'])
        ));

        $canEdit = $report === null || $isAdmin || (int) $report['auteur_id'] === $uid;

        render_page(SECTION_LABELS['rapports'], view('pages/rapport_form', [
            'centres'   => $centres,
            'centreId'  => $centreId,
            'date'      => $date,
            'report'    => $report,
            'bacentas'  => $centreId !== null ? $svc->reportableBacentas($uid, $centreId, $isAdmin) : [],
            'fields'    => RAPPORT_JOUR_FIELDS,
            'derived'   => $centreId !== null
                ? $svc->derivedNames($centreId, $report['bacenta_id'] ?? null, $uid)
                : null,
            'canEdit'   => $canEdit,
            'errors'    => [],
            'old'       => [],
            'csrf'      => csrf_field(),
        ]));
    }
}
