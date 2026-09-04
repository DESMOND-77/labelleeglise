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
     * @deprecated SP-3 — le pointage culte passe par pointOccurrence('cult', …).
     * Conservé pour compat (signature inchangée) ; délègue désormais à l'occurrence :
     * tous les user_id fournis sont marqués « present », les autres membres n'ont pas de ligne.
     */
    public function pointCulte(int $culteId, string $date, array $userIds): void
    {
        $ids = array_map('intval', $userIds);
        $this->pointOccurrence('cult', $culteId, $date, array_fill_keys($ids, 'present'), $ids);
    }

    /* ================= Historique / consultation (fiche membre) ================= */

    public function historyForUser(int $userId, ?string $fromDate = null, ?string $toDate = null): array
    {
        return $this->attendance->historyForUser($userId, $fromDate, $toDate);
    }

    /** Présences d'un utilisateur restreintes à une semaine ISO (mêmes clés que suivi hebdo). */
    public function weekForUser(int $userId, string $weekKey): array
    {
        $monday = monday_of_week_key($weekKey);
        $sunday = $monday->modify('+6 days');
        return $this->attendance->historyForUser($userId, iso_date_of($monday), iso_date_of($sunday));
    }

    /**
     * Statistiques honnêtes uniquement (spec §23) : total de présences
     * réellement enregistrées + date de la dernière présence. Un "taux" de
     * présence n'est calculé que si un dénominateur réel existe (nombre de
     * dates de culte distinctes enregistrées sur la même période) ; sinon
     * il est omis plutôt que fabriqué.
     */
    public function statsForUser(int $userId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $total = $this->attendance->countForUser($userId);
        $lastDate = $this->attendance->mostRecentDateForUser($userId);
        // Dénominateur : nombre de dates de culte distinctes enregistrées
        // (toutes présences confondues) sur la période demandée — c'est la
        // seule donnée réellement disponible pour approx. un "taux".
        $denominator = $this->attendance->distinctCulteDatesInRange($fromDate, $toDate);
        $rate = null;
        if ($denominator > 0) {
            $rate = round(min(100, ($total / $denominator) * 100));
        }
        return [
            'total' => $total,
            'last_date' => $lastDate,
            'rate' => $rate, // null si aucun dénominateur honnête n'est disponible
            'rate_denominator_note' => 'présences ÷ dates de culte distinctes enregistrées sur la période',
        ];
    }

    /* ================= M1 — Présences par occurrence ================= */

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
