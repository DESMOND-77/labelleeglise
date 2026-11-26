<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RapportOccurrenceRepository;
use App\Repositories\RapportSettingsRepository;
use App\Repositories\RapportScoringRepository;

/**
 * Agrégats et statistiques du module de suivi (checklist, tableau de bord,
 * classement, fiche responsable). Les taux sont TOUJOURS présentés avec
 * numérateur et dénominateur ; « Non évalué » remplace 0 % quand aucune
 * échéance n'est attendue. Les occurrences annulées sont exclues partout.
 */
class RapportAnalyticsService
{
    private RapportOccurrenceRepository $occurrences;
    private RapportSettingsRepository $settingsRepo;
    private RapportScoringRepository $scoringRepo;
    private RapportScopeService $scope;

    public function __construct(
        ?RapportOccurrenceRepository $occurrences = null,
        ?RapportSettingsRepository $settingsRepo = null,
        ?RapportScoringRepository $scoringRepo = null,
        ?RapportScopeService $scope = null
    ) {
        $this->occurrences = $occurrences ?? new RapportOccurrenceRepository();
        $this->settingsRepo = $settingsRepo ?? new RapportSettingsRepository();
        $this->scoringRepo = $scoringRepo ?? new RapportScoringRepository();
        $this->scope = $scope ?? new RapportScopeService();
    }

    /* ================= Périodes ================= */

    /**
     * @return array{kind:string,from:string,to:string,label:string,prev_from:string,prev_to:string,prev_label:string}
     */
    public function resolvePeriod(string $kind, ?string $from = null, ?string $to = null, ?string $anchor = null): array
    {
        $tz = new \DateTimeZone('Africa/Libreville');
        $anchor = $anchor && preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor) ? $anchor : (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $base = new \DateTimeImmutable($anchor, $tz);

        if ($kind === 'month') {
            $first = $base->modify('first day of this month');
            $last = $base->modify('last day of this month');
            $prevFirst = $first->modify('-1 month');
            $prevLast = $prevFirst->modify('last day of this month');
            return [
                'kind' => 'month',
                'from' => $first->format('Y-m-d'),
                'to' => $last->format('Y-m-d'),
                'label' => $this->monthLabel($first),
                'prev_from' => $prevFirst->format('Y-m-d'),
                'prev_to' => $prevLast->format('Y-m-d'),
                'prev_label' => $this->monthLabel($prevFirst),
            ];
        }

        if ($kind === 'custom' && $from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $from <= $to) {
            $days = (int) (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days + 1;
            $prevEnd = (new \DateTimeImmutable($from))->modify('-1 day');
            $prevStart = $prevEnd->modify('-' . ($days - 1) . ' days');
            return [
                'kind' => 'custom',
                'from' => $from,
                'to' => $to,
                'label' => 'Du ' . $this->frDate($from) . ' au ' . $this->frDate($to),
                'prev_from' => $prevStart->format('Y-m-d'),
                'prev_to' => $prevEnd->format('Y-m-d'),
                'prev_label' => 'Période précédente de ' . $days . ' jour(s)',
            ];
        }

        // Semaine ISO (lundi → dimanche) par défaut.
        [$monday, $sunday] = $this->isoWeekBounds($anchor);
        $prevMonday = $monday->modify('-7 days');
        $prevSunday = $sunday->modify('-7 days');
        return [
            'kind' => 'week',
            'from' => $monday->format('Y-m-d'),
            'to' => $sunday->format('Y-m-d'),
            'label' => 'Semaine ISO ' . $monday->format('o-\WW') . ' (' . $this->frDate($monday->format('Y-m-d')) . ' → ' . $this->frDate($sunday->format('Y-m-d')) . ')',
            'prev_from' => $prevMonday->format('Y-m-d'),
            'prev_to' => $prevSunday->format('Y-m-d'),
            'prev_label' => 'Semaine ' . $prevMonday->format('o-\WW'),
        ];
    }

    /** @return array{0:\DateTimeImmutable,1:\DateTimeImmutable} */
    public function isoWeekBounds(string $date): array
    {
        $tz = new \DateTimeZone('Africa/Libreville');
        $d = new \DateTimeImmutable($date, $tz);
        $n = (int) $d->format('N');
        $monday = $d->modify('-' . ($n - 1) . ' days');
        $sunday = $monday->modify('+6 days');
        return [$monday, $sunday];
    }

    public function monthBounds(string $date): array
    {
        $tz = new \DateTimeZone('Africa/Libreville');
        $d = new \DateTimeImmutable($date, $tz);
        return [$d->modify('first day of this month')->format('Y-m-d'), $d->modify('last day of this month')->format('Y-m-d')];
    }

    public function monthLabel(\DateTimeImmutable $d): string
    {
        $months = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
        return ($months[(int) $d->format('n')] ?? $d->format('m')) . ' ' . $d->format('Y');
    }

    public function frDate(string $iso): string
    {
        $ts = strtotime($iso);
        return $ts === false ? $iso : date('d/m/Y', $ts);
    }

    /* ================= Checklist ================= */

    /** @return array{rows:array<int,array>,total:int,page:int,per_page:int,pages:int} */
    public function checklist(array $filters, ?array $user, int $page): array
    {
        $perPage = 25;
        $page = max(1, $page);
        $pairs = $this->scope->pairsForUser($user);
        $uid = $user ? (int) $user['id'] : 0;

        // Vue « mes échéances » forcée pour les non-admin sans périmètre.
        if (($filters['vue'] ?? '') === 'mes' || ($pairs !== null && $pairs === [] && ($user['role'] ?? '') !== 'admin')) {
            $filters['vue'] = 'mes';
        }

        $offset = ($page - 1) * $perPage;
        $total = $this->occurrences->checklistCount($filters, $pairs, $uid);
        $rows = $this->occurrences->checklist($filters, $perPage, $offset, $pairs, $uid);
        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /* ================= Tableau de bord ================= */

    public function dashboard(string $periodKind, ?string $from, ?string $to, ?array $user): array
    {
        $tz = new \DateTimeZone('Africa/Libreville');
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $period = $this->resolvePeriod($periodKind, $from, $to, $today);
        $pairs = $this->scope->pairsForUser($user);

        $current = $this->occurrences->aggregate($period['from'], $period['to'], $pairs);
        $previous = $this->occurrences->aggregate($period['prev_from'], $period['prev_to'], $pairs);

        $currentRate = $current['expected'] > 0 ? round($current['submitted'] / $current['expected'] * 100, 1) : null;
        $previousRate = $previous['expected'] > 0 ? round($previous['submitted'] / $previous['expected'] * 100, 1) : null;
        $currentPunct = $current['expected'] > 0 ? round($current['on_time'] / $current['expected'] * 100, 1) : null;
        $previousPunct = $previous['expected'] > 0 ? round($previous['on_time'] / $previous['expected'] * 100, 1) : null;

        $missing = max(0, $current['expected'] - $current['submitted']);

        $daily = $this->occurrences->dailySeries($period['from'], $period['to'], $pairs);
        $byStructure = $this->occurrences->byStructure($period['from'], $period['to'], $pairs, 12);
        $byStatusRows = $this->occurrences->byStatus($period['from'], $period['to'], $pairs);
        $byStatus = ['pending' => 0, 'due' => 0, 'overdue' => 0, 'filled_on_time' => 0, 'filled_late' => 0, 'cancelled' => 0];
        foreach ($byStatusRows as $row) {
            $byStatus[(string) $row['status']] = (int) $row['total'];
        }

        $responsables = $this->withRates($this->occurrences->individualStats($period['from'], $period['to'], $pairs));
        usort($responsables, static function ($a, $b) {
            $ra = $a['completion_rate'] ?? -1;
            $rb = $b['completion_rate'] ?? -1;
            if ($ra === $rb) {
                return (int) $b['credited'] <=> (int) $a['credited'];
            }
            return $rb <=> $ra;
        });

        // Occurrences sans aucun responsable (à traiter par l'admin).
        $sansParams = [$period['from'], $period['to']];
        $sansScope = $this->occurrences->scopeWhere($pairs, $sansParams, 'o');
        $sansResponsable = (int) \App\Core\Query::value(
            "SELECT COUNT(*)
               FROM rapport_occurrences o
              WHERE o.report_date BETWEEN ? AND ? $sansScope
                AND NOT EXISTS (SELECT 1 FROM rapport_occurrence_responsables r WHERE r.occurrence_id = o.id)
                AND o.status <> 'cancelled'",
            $sansParams
        );

        return [
            'period' => $period,
            'pairs' => $pairs,
            'current' => $current + [
                'missing' => $missing,
                'completion_rate' => $currentRate,
                'punctuality_rate' => $currentPunct,
            ],
            'previous' => $previous + [
                'completion_rate' => $previousRate,
                'punctuality_rate' => $previousPunct,
            ],
            'delta_completion' => ($currentRate !== null && $previousRate !== null) ? round($currentRate - $previousRate, 1) : null,
            'delta_punctuality' => ($currentPunct !== null && $previousPunct !== null) ? round($currentPunct - $previousPunct, 1) : null,
            'daily' => $daily,
            'by_structure' => $byStructure,
            'by_status' => $byStatus,
            'responsables' => $responsables,
            'sans_responsable' => $sansResponsable,
        ];
    }

    /* ================= Classement ================= */

    /** @return array<int,array<string,mixed>> lignes classées (déjà triées) */
    public function classement(string $from, string $to, ?array $pairs): array
    {
        $rows = $this->withRates($this->occurrences->individualStats($from, $to, $pairs));
        $scorer = new RapportScoringService();
        foreach ($rows as &$row) {
            $level = $scorer->levelForRate($row['completion_rate']);
            $row['level_key'] = $level['level_key'] ?? null;
            $row['level_label'] = $level['label'] ?? 'Non évalué';
        }
        unset($row);
        usort($rows, static function ($a, $b) {
            $ra = $a['completion_rate'] ?? -1;
            $rb = $b['completion_rate'] ?? -1;
            if ($ra === $rb) {
                $pa = $a['punctuality_rate'] ?? -1;
                $pb = $b['punctuality_rate'] ?? -1;
                if ($pa === $pb) {
                    return (int) $b['credited'] <=> (int) $a['credited'];
                }
                return $pb <=> $pa;
            }
            return $rb <=> $ra;
        });
        $rank = 0;
        $prevKey = null;
        foreach ($rows as $i => &$row) {
            $key = ($row['completion_rate'] ?? 'x') . '|' . ($row['punctuality_rate'] ?? 'x') . '|' . $row['credited'];
            if ($key !== $prevKey) {
                $rank = $i + 1;
                $prevKey = $key;
            }
            $row['rank'] = $rank;
        }
        unset($row);
        return $rows;
    }

    /** Ajoute les taux individuels aux agrégats bruts (dénominateur = crédités + manquants). */
    private function withRates(array $rows): array
    {
        foreach ($rows as &$row) {
            $credited = (int) $row['credited'];
            $missing = (int) $row['missing'];
            $onTime = (int) $row['on_time'];
            $denominator = $credited + $missing;
            $row['expected'] = (int) $row['expected'];
            $row['denominator'] = $denominator;
            $row['completion_rate'] = $denominator > 0 ? round($credited / $denominator * 100, 1) : null;
            $row['punctuality_rate'] = $denominator > 0 ? round($onTime / $denominator * 100, 1) : null;
        }
        unset($row);
        return $rows;
    }

    /* ================= Fiche responsable ================= */

    /**
     * @param ?array<int,array{type:string,id:int}> $scopePairs Périmètre du spectateur :
     *        null = aucune restriction (administrateur / soi-même) ; sinon les
     *        statistiques sont limitées au périmètre (les résultats de bilan
     *        historisés - globaux par construction - ne sont alors PAS exposés).
     * @return array{week:array,month:array,cumul:array,cumul_start:?string,cumul_source:string,history:array}
     */
    public function responsableSheet(int $userId, ?array $scopePairs = null): array
    {
        $tz = new \DateTimeZone('Africa/Libreville');
        $now = new \DateTimeImmutable('now', $tz);
        $today = $now->format('Y-m-d');

        // Hebdomadaire (semaine ISO en cours)
        [$monday, $sunday] = $this->isoWeekBounds($today);
        // Mensuel (mois civil en cours)
        [$monthStart, $monthEnd] = $this->monthBounds($today);
        // Cumul depuis la prise de responsabilité (ou date admin explicite)
        $respStart = $this->scope->responsibilityStartFor($userId);
        $settingDefault = $this->settingsDateDefault();
        $cumulStart = $respStart ?? $settingDefault;
        $cumulSource = $respStart !== null ? 'affectations' : ($settingDefault !== null ? 'parametre_admin' : 'absent');

        $scorer = new RapportScoringService();

        $weekStats = $this->individualStatsArray($monday->format('Y-m-d'), $sunday->format('Y-m-d'), $userId, $scopePairs);
        $monthStats = $this->individualStatsArray($monthStart, $monthEnd, $userId, $scopePairs);
        $cumulStats = $cumulStart !== null
            ? $this->individualStatsArray($cumulStart, $today, $userId, $scopePairs)
            : null;

        $decorate = function (?array $stats) use ($scorer) {
            if ($stats === null) {
                return null;
            }
            $level = $scorer->levelForRate($stats['completion_rate']);
            $stats['level_key'] = $level['level_key'] ?? null;
            $stats['level_label'] = $level['label'] ?? 'Non évalué';
            $stats['level_message'] = $level['message_template'] ?? null;
            return $stats;
        };

        $history = $scopePairs === null ? $this->scoringRepo->historyForUser($userId, 12) : [];

        $weekBlock = $decorate($weekStats);
        $weekBlock['from'] = $monday->format('Y-m-d');
        $weekBlock['to'] = $sunday->format('Y-m-d');
        $monthBlock = $decorate($monthStats);
        $monthBlock['from'] = $monthStart;
        $monthBlock['to'] = $monthEnd;
        return [
            'week' => $weekBlock,
            'month' => $monthBlock,
            'cumul' => $decorate($cumulStats),
            'cumul_start' => $cumulStart,
            'cumul_source' => $cumulSource,
            'history' => $history,
            'scope' => $scopePairs,
        ];
    }

    /** @return array<string,mixed> */
    private function individualStatsArray(string $from, string $to, int $userId, ?array $scopePairs = null): array
    {
        $row = $this->occurrences->individualStatsFrom($from, $to, $userId, $scopePairs);
        $credited = (int) $row['credited'];
        $missing = (int) $row['missing'];
        $onTime = (int) $row['on_time'];
        $denominator = $credited + $missing;
        return [
            'user_id' => $userId,
            'expected' => (int) $row['expected'],
            'denominator' => $denominator,
            'credited' => $credited,
            'filled_by_other' => (int) $row['filled_by_other'],
            'missing' => $missing,
            'on_time' => $onTime,
            'late' => (int) $row['late'],
            'submitted' => $credited,
            'completion_rate' => $denominator > 0 ? round($credited / $denominator * 100, 1) : null,
            'punctuality_rate' => $denominator > 0 ? round($onTime / $denominator * 100, 1) : null,
        ];
    }

    private function settingsDateDefault(): ?string
    {
        $v = trim((string) (new RapportSettingsService($this->settingsRepo))->cumulStartDefault());
        return $v !== '' ? $v : null;
    }
}
