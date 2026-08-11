<?php

namespace App\Services;

use App\Auth\RbacService;
use App\Core\Query;
use App\Repositories\BacentaRepository;
use App\Repositories\UserRepository;

/**
 * Affectation de membres actifs/vérifiés à un bacenta, par son responsable
 * (ou un administrateur). Le bacenta cible est toujours re-dérivé/vérifié
 * côté serveur via le RBAC existant — jamais accepté tel quel depuis le
 * formulaire — et chaque identifiant de membre soumis est revalidé
 * individuellement avant affectation, dans une transaction SQL unique.
 */
class BacentaMembershipService
{
    private UserRepository $users;
    private BacentaRepository $bacentas;
    private RbacService $rbac;

    public function __construct(
        ?UserRepository $users = null,
        ?BacentaRepository $bacentas = null,
        ?RbacService $rbac = null
    ) {
        $this->users = $users ?? new UserRepository();
        $this->bacentas = $bacentas ?? new BacentaRepository();
        $this->rbac = $rbac ?? new RbacService();
    }

    /** Membres actifs/vérifiés/rôle "membre" et sans bacenta, avec recherche optionnelle. */
    public function searchUnassigned(string $search = ''): array
    {
        return $this->users->findUnassignedActiveMembers($search);
    }

    /**
     * Vérifie que l'utilisateur courant (admin ou responsable) est bien
     * autorisé à gérer le bacenta demandé. Retourne l'id de bacenta
     * effectivement autorisé, ou null si l'accès est refusé.
     */
    public function authorizedBacentaId(array $currentUser, int $submittedBacentaId): ?int
    {
        if ($submittedBacentaId <= 0) {
            return null;
        }
        if (($currentUser['role'] ?? null) === 'admin') {
            $bacenta = $this->bacentas->find($submittedBacentaId);
            return $bacenta ? $submittedBacentaId : null;
        }
        $myBacentas = $this->rbac->myBacentaIds((int) $currentUser['id']);
        return in_array($submittedBacentaId, $myBacentas, true) ? $submittedBacentaId : null;
    }

    /**
     * Affecte plusieurs membres (ids soumis par le client — jamais fiables
     * tels quels) au bacenta autorisé, après revalidation individuelle de
     * chaque candidat. Toute l'opération est exécutée dans une transaction
     * SQL : soit tous les membres éligibles sont affectés, soit rien ne
     * l'est en cas d'erreur inattendue.
     *
     * @param int[] $submittedUserIds
     * @return array{assigned:int[], skipped:int[]}
     */
    public function assignMembers(int $authorizedBacentaId, array $submittedUserIds): array
    {
        $assigned = [];
        $skipped = [];

        Query::transaction(function () use ($authorizedBacentaId, $submittedUserIds, &$assigned, &$skipped) {
            foreach ($submittedUserIds as $rawId) {
                $id = (int) $rawId;
                if ($id <= 0) {
                    continue;
                }
                // Revalidation individuelle et fraîche de chaque candidat :
                // existe, actif, email vérifié, rôle membre, sans bacenta.
                $candidate = $this->users->findEligibleUnassignedMember($id);
                if (!$candidate) {
                    $skipped[] = $id;
                    continue;
                }
                $this->bacentas->assignMember($id, $authorizedBacentaId);
                $assigned[] = $id;
            }
        });

        return ['assigned' => $assigned, 'skipped' => $skipped];
    }
}
