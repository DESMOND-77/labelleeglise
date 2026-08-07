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

    public function pointCulte(int $culteId, string $date, array $userIds): void
    {
        $culte = $this->cultes->find($culteId);
        if (!$culte) {
            return;
        }
        $this->attendance->pointCulte($culteId, $date, $userIds);
    }
}
