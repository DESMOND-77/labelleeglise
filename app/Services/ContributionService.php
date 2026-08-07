<?php

namespace App\Services;

use App\Repositories\ContributionRepository;

/**
 * Offrandes & dîmes — calculs et cumuls.
 */
class ContributionService
{
    private ContributionRepository $contributions;

    public function __construct(?ContributionRepository $contributions = null)
    {
        $this->contributions = $contributions ?? new ContributionRepository();
    }

    public function globalYear(int $year): int
    {
        return $this->contributions->sumOffrandesSectionYear('bacenta', $year)
             + $this->contributions->sumOffrandesSectionYear('centre', $year);
    }

    public function saveVisitesOffrandes(int $bacenta, string $mois, array $visites, array $offs, int $visiteurId): void
    {
        // Les visites sont gérées par le BergerRepository via le service dédié.
        // Ici : offrandes d'un bacenta.
        $this->contributions->saveOffrandesMonth('bacenta', $bacenta, $mois, $offs);
    }
}
