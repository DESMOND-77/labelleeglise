<?php

namespace App\Services;

use App\Core\Query;
use App\Repositories\BergerRepository;

/**
 * Suivi hebdomadaire des bergers : calculs de complétion.
 */
class ReportService
{
    private BergerRepository $berger;

    public function __construct(?BergerRepository $berger = null)
    {
        $this->berger = $berger ?? new BergerRepository();
    }

    public function isFieldFilled(array $f, $v): bool
    {
        if ($v === null || $v === '') {
            return false;
        }
        if (($f['type'] ?? 'text') === 'number') {
            return (float) $v > 0;
        }
        return true;
    }

    public function weekCompletion(array $week): int
    {
        $filled = 0;
        $total = 0;
        foreach (WEEK_DAYS as $day) {
            $data = $week[$day] ?? [];
            foreach (SUIVI_FIELDS as $f) {
                if (!empty($f['sundayOnly']) && $day !== 'Dimanche') {
                    continue;
                }
                $total++;
                if ($this->isFieldFilled($f, $data[$f['key']] ?? '')) {
                    $filled++;
                }
            }
        }
        return $total ? (int) round(($filled / $total) * 100) : 0;
    }

    public function yearCompletion(int $userId, int $year): int
    {
        $weeks = $this->berger->weeksOfYear($userId, $year);
        if (!$weeks) {
            return 0;
        }
        $total = 0;
        foreach ($weeks as $w) {
            $total += $this->weekCompletion($this->berger->suiviWeek($userId, $w['semaine']));
        }
        return (int) round($total / count($weeks));
    }

    /** Série de complétion par semaine pour un admin. */
    public function weeklySeries(int $userId, int $year): array
    {
        $weeks = $this->berger->weeksOfYear($userId, $year);
        $series = [];
        foreach ($weeks as $w) {
            $series[] = ['week' => $w['semaine'], 'pct' => $this->weekCompletion($this->berger->suiviWeek($userId, $w['semaine']))];
        }
        return $series;
    }
}
