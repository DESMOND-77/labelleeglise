<?php

namespace App\Services;

use App\Repositories\AttendanceRepository;
use App\Repositories\CulteRepository;

/**
 * Pointage de présence (cultes).
 */
class AttendanceService
{
    private AttendanceRepository $attendance;
    private CulteRepository $cultes;

    public function __construct(?AttendanceRepository $attendance = null, ?CulteRepository $cultes = null)
    {
        $this->attendance = $attendance ?? new AttendanceRepository();
        $this->cultes = $cultes ?? new CulteRepository();
    }

    /**
     * @deprecated SP-3 - le pointage culte passe par pointOccurrence('cult', …).
     * Conservé pour compat (signature inchangée) ; délègue désormais à l'occurrence :
     * tous les user_id fournis sont marqués « present », les autres membres n'ont pas de ligne.
     */
    public function pointCulte(int $culteId, string $date, array $userIds): void
    {
        $ids = array_map('intval', $userIds);
        $this->pointOccurrence('cult', $culteId, $date, array_fill_keys($ids, 'present'), $ids);
    }

    /* ================= Historique / consultation (fiche membre) ================= */

    public function historyForUser(
        int $userId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $statut = null,
        ?string $type = null
    ): array {
        return $this->attendance->historyForUser($userId, $fromDate, $toDate, $statut, $type);
    }

    /**
     * Historique d'activité mis en forme pour la fiche membre / l'impression.
     * $filters : clés optionnelles from, to, statut, type.
     *
     * @return list<array{date_presence:string,statut:string,activity_type:string,activity_nom:string}>
     */
    public function memberActivityHistory(int $userId, array $filters): array
    {
        $rows = $this->attendance->historyForUser(
            $userId,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $filters['statut'] ?? null,
            $filters['type'] ?? null
        );

        // Priorité : culte > evenement > bacenta > basonta > centre (première FK non nulle).
        $order = ['culte', 'evenement', 'bacenta', 'basonta', 'centre'];
        $out = [];
        foreach ($rows as $r) {
            $type = '';
            $nom = '';
            foreach ($order as $t) {
                if (($r[$t . '_id'] ?? null) !== null) {
                    $type = $t;
                    $nom = (string) ($r[$t . '_nom'] ?? '');
                    break;
                }
            }
            $out[] = [
                'date_presence' => (string) $r['date_presence'],
                'statut'        => (string) ($r['statut'] ?? ''),
                'activity_type' => $type,
                'activity_nom'  => $nom,
            ];
        }
        return $out;
    }

    /** Présences d'un utilisateur restreintes à une semaine ISO (mêmes clés que suivi hebdo). */
    public function weekForUser(int $userId, string $weekKey): array
    {
        $monday = monday_of_week_key($weekKey);
        $sunday = $monday->modify('+6 days');
        return $this->attendance->historyForUser($userId, iso_date_of($monday), iso_date_of($sunday));
    }

    /**
     * Statistiques de présence d'un membre (spec SP-5).
     *
     * Taux = présences ÷ (présents + absents + excusés). Les occurrences non
     * renseignées (aucune ligne) sont exclues du dénominateur : un oubli de
     * pointage du responsable ne pénalise pas le membre. `total` = nombre de
     * lignes `statut = 'present'` (présences réelles), pas le total de lignes.
     *
     * Limite connue : ne mesure pas l'assiduité sur les occurrences auxquelles
     * le membre était éligible mais non pointé (pas de date d'appartenance
     * fiable). Voir spec SP-5 §3.4 / §6.
     *
     * @return array{total:int,present:int,absent:int,excuse:int,pointed:int,last_date:?string,rate:?int,formula:string,rate_denominator_note:string}
     */
    public function statsForUser(int $userId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $c = $this->attendance->statusCountsForUser($userId, $fromDate, $toDate);
        $present = (int) ($c['present'] ?? 0);
        $absent = (int) ($c['absent'] ?? 0);
        $excuse = (int) ($c['excuse'] ?? 0);
        $pointed = $present + $absent + $excuse;
        $rate = $pointed > 0 ? (int) round($present / $pointed * 100) : null;

        return [
            'total'     => $present, // présences réelles (clé conservée, sens corrigé)
            'present'   => $present,
            'absent'    => $absent,
            'excuse'    => $excuse,
            'pointed'   => $pointed,
            'last_date' => $this->attendance->mostRecentDateForUser($userId),
            'rate'      => $rate, // null si aucune occurrence pointée (pas de /0)
            'formula'   => 'Taux = présences ÷ (présents + absents + excusés). Occurrences non renseignées exclues du dénominateur.',
            'rate_denominator_note' => 'présents ÷ (présents + absents + excusés)',
        ];
    }

    /* ================= M1 - Présences par occurrence ================= */

    /**
     * Enregistre les statuts d'une occurrence (unité, date). Filtre : ne garde
     * que les user_id ∈ $allowedUserIds et les statuts ∈ PRESENCE_STATUTS.
     * Upsert transactionnel (delete de l'(unité, date) puis insert).
     */
    public function pointOccurrence(string $unitType, int $unitId, string $date, array $rawStatutByUserId, array $allowedUserIds): void
    {
        $allowed = array_flip(array_map('intval', $allowedUserIds));
        $valid = array_keys(PRESENCE_STATUTS);
        $clean = [];
        foreach ($rawStatutByUserId as $userId => $statut) {
            $userId = (int) $userId;
            if (isset($allowed[$userId]) && in_array($statut, $valid, true)) {
                $clean[$userId] = $statut;
            }
        }
        \App\Core\Query::transaction(function () use ($unitType, $unitId, $date, $clean) {
            $this->attendance->pointOccurrence($unitType, $unitId, $date, $clean);
        });
    }

    /**
     * Synthèse d'une occurrence pour les compteurs du composant de pointage.
     * « non_renseigne » = membres sans ligne de présence (borné à ≥ 0).
     * Aucune requête au-delà de occurrenceStatuts (SELECT léger, index uniq_presence).
     *
     * @return array{present:int,absent:int,excuse:int,non_renseigne:int,total:int}
     */
    public function occurrenceSummary(string $unitType, int $unitId, string $date, int $totalMembers): array
    {
        $statuts = $this->attendance->occurrenceStatuts($unitType, $unitId, $date);
        $present = count(array_filter($statuts, static fn($v) => $v === 'present'));
        $absent = count(array_filter($statuts, static fn($v) => $v === 'absent'));
        $excuse = count(array_filter($statuts, static fn($v) => $v === 'excuse'));
        return [
            'present'       => $present,
            'absent'        => $absent,
            'excuse'        => $excuse,
            'non_renseigne' => max(0, $totalMembers - count($statuts)),
            'total'         => $totalMembers,
        ];
    }

    /**
     * @param array<int,array> $members lignes users (au moins la clé 'id')
     * @return list<array{user:array,statut:string}>
     */
    public function occurrenceGrid(string $unitType, int $unitId, string $date, array $members): array
    {
        $statuts = $this->attendance->occurrenceStatuts($unitType, $unitId, $date);
        $out = [];
        foreach ($members as $m) {
            $out[] = ['user' => $m, 'statut' => $statuts[(int) $m['id']] ?? ''];
        }
        return $out;
    }

    /**
     * @param array<int,array> $members
     * @return array{dates:list<string>,rows:list<array{user:array,cells:array<string,string>}>}
     */
    public function annualMatrix(string $unitType, int $unitId, int $year, array $members): array
    {
        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);
        $dates = $this->attendance->distinctDatesForUnit($unitType, $unitId, $from, $to);
        $matrix = $this->attendance->matrixForUnit($unitType, $unitId, $from, $to);
        $rows = [];
        foreach ($members as $m) {
            $rows[] = ['user' => $m, 'cells' => $matrix[(int) $m['id']] ?? []];
        }
        return ['dates' => $dates, 'rows' => $rows];
    }
}
