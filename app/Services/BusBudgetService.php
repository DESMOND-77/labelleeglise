<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BusBudgetRepository;

/**
 * Budget Bus du dimanche (M2) : validation des mouvements + agrégation
 * annuelle (sous-totaux par centre et par mois, total année).
 */
class BusBudgetService
{
    private BusBudgetRepository $repo;

    public function __construct(?BusBudgetRepository $repo = null)
    {
        $this->repo = $repo ?? new BusBudgetRepository();
    }

    public function entry(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Tableau annuel prêt à afficher.
     *
     * @param ?int        $centreId  filtre d'affichage (un centre) ou null
     * @param int         $year      année civile
     * @param ?array<int> $centreIds périmètre autorisé (null = admin, pas de restriction)
     * @return array{centres:array<int,array{nom:string,rows:array<int,array<string,mixed>>,months:array<string,float>,total:float}>,grandTotal:float,count:int}
     */
    public function annualTable(?int $centreId, int $year, ?array $centreIds): array
    {
        $rows = $this->repo->list($centreId, $year, $centreIds);

        $centres = [];
        foreach ($rows as $r) {
            $cid = (int) $r['centre_id'];
            $mkey = substr((string) $r['date_retrait'], 0, 7);
            $montant = (float) $r['montant'];

            if (!isset($centres[$cid])) {
                $centres[$cid] = ['nom' => (string) $r['centre_nom'], 'rows' => [], 'months' => [], 'total' => 0.0];
            }
            $centres[$cid]['rows'][] = $r;
            $centres[$cid]['months'][$mkey] = ($centres[$cid]['months'][$mkey] ?? 0.0) + $montant;
            $centres[$cid]['total'] += $montant;
        }

        $grandTotal = 0.0;
        foreach ($centres as $c) {
            $grandTotal += $c['total'];
        }

        return ['centres' => $centres, 'grandTotal' => $grandTotal, 'count' => count($rows)];
    }

    /**
     * Crée (id absent) ou met à jour (id présent) un mouvement.
     * Le contrôle de périmètre (centre autorisé) est fait en amont par
     * l'appelant (ActionsController) — ici : validation des valeurs.
     *
     * @param array<string,mixed> $in
     * @return array{ok:bool,errors:array<string,string>,id:?int}
     */
    public function save(array $in, int $userId): array
    {
        $errors = [];
        $id = (int) ($in['id'] ?? 0) ?: null;

        $centreId = (int) ($in['centre_id'] ?? 0);
        if ($centreId <= 0) {
            $errors['centre_id'] = 'Choisissez un centre.';
        }

        $date = trim((string) ($in['date_retrait'] ?? ''));
        $ts = $date !== '' ? strtotime($date) : false;
        if ($ts === false || date('Y-m-d', $ts) !== $date) {
            $errors['date_retrait'] = 'Date invalide (format attendu AAAA-MM-JJ).';
        }

        $montant = (float) str_replace([' ', ','], ['', '.'], (string) ($in['montant'] ?? ''));
        if ($montant < 0) {
            $errors['montant'] = 'Montant négatif interdit.';
        }

        $obs = trim((string) ($in['observations'] ?? ''));
        $obs = $obs === '' ? null : mb_substr($obs, 0, 2000);

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $data = [
            'centre_id'    => $centreId,
            'date_retrait' => $date,
            'montant'      => $montant,
            'observations' => $obs,
        ];

        if ($id !== null) {
            if ($this->repo->find($id) === null) {
                return ['ok' => false, 'errors' => ['_form' => 'Ligne introuvable.'], 'id' => null];
            }
            $this->repo->update($id, $data);
            return ['ok' => true, 'errors' => [], 'id' => $id];
        }

        $data['created_by'] = $userId;
        return ['ok' => true, 'errors' => [], 'id' => $this->repo->create($data)];
    }

    public function deleteEntry(int $id): void
    {
        $this->repo->delete($id);
    }
}
