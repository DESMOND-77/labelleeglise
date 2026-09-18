<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

/**
 * Budget Bus du dimanche par centre (M2) : tableau annuel de saisie
 * avec sous-totaux par centre et par mois, total année.
 * Périmètre : admin (tous les centres) ou responsable réel d'un centre.
 */
class BusController extends Controller
{
    public function index(): void
    {
        $user = current_user();
        if (!$user) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        if (!auth_can_manage_any_centre()) {
            $this->redirect('index.php', ['page' => 'accueil']);
        }
        $isAdmin = ($user['role'] ?? '') === 'admin';

        $centres = array_values(array_filter(
            get_centres(),
            static fn($c) => $isAdmin || auth_can_manage_center((int) $c['id'])
        ));
        $permittedIds = $isAdmin ? null : array_map(static fn($c) => (int) $c['id'], $centres);

        $year = (int) (Request::get('annee') ?? 0) ?: (int) date('Y');

        $filterCentre = (int) (Request::get('centre') ?? 0) ?: null;
        // Le filtre d'affichage ne peut jamais élargir le périmètre autorisé.
        if ($filterCentre !== null && $permittedIds !== null && !in_array($filterCentre, $permittedIds, true)) {
            $filterCentre = null;
        }

        $editId = (int) (Request::get('edit') ?? 0);
        $edit = $editId ? bus_budget_service()->entry($editId) : null;
        if ($edit && !$isAdmin && !auth_can_manage_center((int) $edit['centre_id'])) {
            $edit = null;
        }

        render_page(SECTION_LABELS['budgetBus'], view('pages/budget_bus', [
            'table'        => bus_budget_service()->annualTable($filterCentre, $year, $permittedIds),
            'centres'      => $centres,
            'year'         => $year,
            'filterCentre' => $filterCentre,
            'edit'         => $edit,
            'errors'       => [],
            'old'          => [],
            'csrf'         => csrf_field(),
            'balances'     => array_reduce($centres, static function (array $out, array $centre) use ($editId): array {
                $out[(int) $centre['id']] = bus_budget_service()->availableBalance((int) $centre['id'], $editId ?: null);
                return $out;
            }, []),
        ]));
    }
}
