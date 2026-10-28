<?php

namespace App\Services;

use App\Core\Query;
use App\Repositories\AttendanceRepository;
use App\Repositories\CulteRepository;
use App\Repositories\NouveauMembreRepository;
use App\Repositories\UserRepository;

/**
 * Suivi des nouveaux membres, de l'enregistrement initial jusqu'à
 * l'intégration automatique dans la liste générale après 3 présences
 * validées à des cultes du dimanche distincts.
 */
class NouveauMembreService
{
    private NouveauMembreRepository $repo;
    private UserRepository $users;
    private AttendanceRepository $attendance;
    private CulteRepository $cultes;

    public function __construct(
        ?NouveauMembreRepository $repo = null,
        ?UserRepository $users = null,
        ?AttendanceRepository $attendance = null,
        ?CulteRepository $cultes = null
    ) {
        $this->repo = $repo ?? new NouveauMembreRepository();
        $this->users = $users ?? new UserRepository();
        $this->attendance = $attendance ?? new AttendanceRepository();
        $this->cultes = $cultes ?? new CulteRepository();
    }

    /**
     * Enregistre une nouvelle personne. Aucune adresse email n'est demandée :
     * `users.email`/`password` étant NOT NULL UNIQUE (table de connexion
     * partagée), un email-jeton unique et un mot de passe aléatoire sont
     * générés, le compte restant non connectable (account_status='disabled')
     * jusqu'à ce qu'un admin décide un jour de lui donner un accès réel.
     */
    public function register(array $in): array
    {
        $nom = trim((string) ($in['nom'] ?? ''));
        $prenom = trim((string) ($in['prenom'] ?? ''));
        if ($nom === '' || $prenom === '') {
            return ['ok' => false, 'error' => 'champs_requis'];
        }

        $sexe = in_array($in['sexe'] ?? null, ['M', 'F'], true) ? $in['sexe'] : null;
        $dateNaissance = trim((string) ($in['date_naissance'] ?? '')) ?: null;
        $telephone = trim((string) ($in['telephone'] ?? '')) ?: null;
        $quartier = trim((string) ($in['quartier'] ?? '')) ?: null;
        $bacentaId = (int) ($in['bacenta_id'] ?? 0) ?: null;
        $datePremiereVisite = trim((string) ($in['date_premiere_visite'] ?? '')) ?: null;
        $observations = trim((string) ($in['observations'] ?? '')) ?: null;
        // Mêmes colonnes users.invite_par/recu_par que le formulaire membre
        // existant (MemberService::dataFromPost()) - réutilisées telles
        // quelles, sans toucher à ce flux d'origine.
        $invitePar = (int) ($in['invite_par'] ?? 0) ?: null;
        $recuPar = (int) ($in['recu_par'] ?? 0) ?: null;

        $userData = [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => 'nouveau_' . bin2hex(random_bytes(8)) . '@prospects.local',
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'role' => 'membre',
            'sexe' => $sexe,
            'date_naissance' => $dateNaissance,
            'telephone' => $telephone,
            'quartier' => $quartier,
            'bacenta_id' => $bacentaId,
            'invite_par' => $invitePar,
            'recu_par' => $recuPar,
            'compte_actif' => 0,
            'email_verified' => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'account_status' => 'disabled',
        ];

        $nmId = Query::transaction(function () use ($userData, $datePremiereVisite, $observations) {
            $userId = $this->users->insert($userData);
            return $this->repo->create($userId, [
                'statut' => 'nouveau',
                'date_premiere_visite' => $datePremiereVisite,
                'observations' => $observations,
            ]);
        });

        return ['ok' => true, 'id' => $nmId];
    }

    /** Attribution/réattribution admin-only (vérifié côté contrôleur) à un berger/MS/pasteur/révérend. */
    public function assignResponsable(int $nmId, ?int $responsableId, int $actorId): array
    {
        $nm = $this->repo->find($nmId);
        if (!$nm) {
            return ['ok' => false, 'error' => 'introuvable'];
        }
        if ($responsableId !== null) {
            $resp = $this->users->find($responsableId);
            if (!$resp || !in_array($resp['role'], BERGER_ROLES, true)) {
                return ['ok' => false, 'error' => 'responsable_invalide'];
            }
        }
        $this->repo->updateResponsable($nmId, $responsableId);
        $this->repo->logAttribution($nmId, $responsableId, $actorId);
        return ['ok' => true];
    }

    /**
     * Enregistre une présence validée à un culte du dimanche et recalcule
     * automatiquement le compteur. Intègre automatiquement dès 3 dates
     * distinctes validées (voir Context/plan : décision produit confirmée).
     */
    public function recordPresence(int $nmId, string $date, int $actorId): array
    {
        $nm = $this->repo->find($nmId);
        if (!$nm) {
            return ['ok' => false, 'error' => 'introuvable'];
        }
        if ($nm['statut'] === 'integre') {
            return ['ok' => false, 'error' => 'deja_integre'];
        }
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            return ['ok' => false, 'error' => 'date_invalide'];
        }
        $sundayCulteIds = $this->cultes->sundayCulteIds();
        if ($sundayCulteIds === []) {
            return ['ok' => false, 'error' => 'aucun_culte_dimanche'];
        }
        $culteId = $sundayCulteIds[0];
        $userId = (int) $nm['user_id'];

        $result = Query::transaction(function () use ($userId, $date, $culteId, $sundayCulteIds, $nmId, $actorId) {
            if (!$this->attendance->hasPresenceOnDate('culte_id', $userId, $culteId, $date)) {
                $this->attendance->insert($userId, $date, ['statut' => 'present', 'culte_id' => $culteId]);
            }
            $count = $this->attendance->countDistinctPresentDatesForCultes($userId, $sundayCulteIds);
            $integrated = false;
            if ($count >= 3) {
                $this->repo->integrate($nmId, $actorId);
                $integrated = true;
                $statut = 'integre';
            } else {
                $statut = $count === 0 ? 'nouveau' : 'en_suivi';
                $this->repo->updateStatut($nmId, $statut);
            }
            return ['count' => min($count, 3), 'statut' => $statut, 'integrated' => $integrated];
        });

        return array_merge(['ok' => true], $result);
    }

    /**
     * Corrige/annule une présence erronée. Lecture seule une fois le membre
     * intégré - l'historique reste alors consultable mais immuable.
     */
    public function cancelPresence(int $nmId, int $presenceId, int $actorId): array
    {
        $nm = $this->repo->find($nmId);
        if (!$nm) {
            return ['ok' => false, 'error' => 'introuvable'];
        }
        if ($nm['statut'] === 'integre') {
            return ['ok' => false, 'error' => 'readonly_apres_integration'];
        }
        $sundayCulteIds = $this->cultes->sundayCulteIds();
        $userId = (int) $nm['user_id'];

        $result = Query::transaction(function () use ($presenceId, $userId, $sundayCulteIds, $nmId) {
            $this->attendance->deleteById($presenceId, $userId);
            $count = $this->attendance->countDistinctPresentDatesForCultes($userId, $sundayCulteIds);
            $statut = $count === 0 ? 'nouveau' : 'en_suivi';
            $this->repo->updateStatut($nmId, $statut);
            return ['count' => min($count, 3), 'statut' => $statut];
        });

        return array_merge(['ok' => true], $result);
    }

    /**
     * Bascule vers la liste générale tout nouveau membre ayant au moins 3
     * présences à des dates distinctes d'un culte du dimanche, quelle que soit
     * la voie de pointage (pointeur dédié ou pointage de culte générique).
     * $userIds = null pour balayer tous les suivis en cours.
     */
    public function syncIntegration(?array $userIds = null, ?int $actorId = null): int
    {
        $sundayCulteIds = $this->cultes->sundayCulteIds();
        if ($sundayCulteIds === []) {
            return 0;
        }
        $done = 0;
        foreach ($this->repo->pendingByUserIds($userIds) as $nm) {
            $count = $this->attendance->countDistinctPresentDatesForCultes((int) $nm['user_id'], $sundayCulteIds);
            if ($count >= 3) {
                $this->repo->integrate((int) $nm['id'], $actorId);
                $done++;
            } else {
                $this->repo->updateStatut((int) $nm['id'], $count === 0 ? 'nouveau' : 'en_suivi');
            }
        }
        return $done;
    }

    public function hasSundayCulte(): bool
    {
        return $this->cultes->sundayCulteIds() !== [];
    }

    public function listForAdmin(array $filters = []): array
    {
        return $this->enrich($this->repo->list($filters));
    }

    public function listForResponsable(int $responsableId, array $filters = []): array
    {
        $filters['responsable_id'] = $responsableId;
        return $this->enrich($this->repo->list($filters));
    }

    public function candidateResponsables(): array
    {
        return $this->repo->candidateResponsables();
    }

    /**
     * Liste à pointer pour une date donnée (comme le pointage bacenta/culte,
     * mais une case à cocher par personne : seul "présent" a un effet, les
     * personnes non cochées ne sont jamais touchées). $responsableId = null
     * pour un admin (voit tout), sinon restreint à ses propres attributions.
     */
    public function rosterForPointage(?int $responsableId, string $date): array
    {
        $filters = ['exclude_integrated' => true];
        if ($responsableId !== null) {
            $filters['responsable_id'] = $responsableId;
        }
        $rows = $this->enrich($this->repo->list($filters));
        $sundayCulteIds = $this->cultes->sundayCulteIds();
        foreach ($rows as &$row) {
            $already = false;
            foreach ($sundayCulteIds as $culteId) {
                if ($this->attendance->hasPresenceOnDate('culte_id', (int) $row['user_id'], $culteId, $date)) {
                    $already = true;
                    break;
                }
            }
            $row['already_present'] = $already;
        }
        return $rows;
    }

    /**
     * Pointage en masse pour une date : réutilise recordPresence() personne
     * par personne (idempotent, auto-intégration à 3/3 incluse) - jamais de
     * suppression, contrairement au pointage générique bacenta/culte qui
     * réécrit toute l'occurrence (ce qui effacerait les présences des autres
     * membres du même culte/date si on le réutilisait ici).
     * $responsableId = null pour un admin ; sinon chaque id soumis est
     * revalidé contre les attributions réelles du responsable (jamais de
     * confiance dans les ids envoyés par le client).
     */
    public function pointAllForDate(string $date, array $nmIds, int $actorId, ?int $responsableId): array
    {
        $allowedIds = null;
        if ($responsableId !== null) {
            $allowedIds = array_map(
                static fn($r) => (int) $r['id'],
                $this->repo->list(['responsable_id' => $responsableId, 'exclude_integrated' => true])
            );
        }
        $results = [];
        foreach ($nmIds as $nmId) {
            $nmId = (int) $nmId;
            if ($allowedIds !== null && !in_array($nmId, $allowedIds, true)) {
                continue;
            }
            $results[$nmId] = $this->recordPresence($nmId, $date, $actorId);
        }
        return $results;
    }

    public function dossier(int $nmId): ?array
    {
        $nm = $this->repo->find($nmId);
        if (!$nm) {
            return null;
        }
        $sundayCulteIds = $this->cultes->sundayCulteIds();
        $userId = (int) $nm['user_id'];
        $nm['nb_presences'] = $sundayCulteIds === [] ? 0 : min(3, $this->attendance->countDistinctPresentDatesForCultes($userId, $sundayCulteIds));
        $nm['presences'] = $sundayCulteIds === [] ? [] : $this->attendance->presencesForCultes($userId, $sundayCulteIds);
        $nm['attributions'] = $this->repo->attributionHistory($nmId);
        return $nm;
    }

    private function enrich(array $rows): array
    {
        $sundayCulteIds = $this->cultes->sundayCulteIds();
        foreach ($rows as &$row) {
            $row['nb_presences'] = $sundayCulteIds === []
                ? 0
                : min(3, $this->attendance->countDistinctPresentDatesForCultes((int) $row['user_id'], $sundayCulteIds));
        }
        return $rows;
    }
}
