<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Query;
use App\Repositories\RapportAuditRepository;
use App\Repositories\RapportOccurrenceRepository;
use App\Repositories\RapportReminderRepository;
use App\Repositories\RapportSettingsRepository;

/**
 * Génération et synchronisation des occurrences attendues de rapport.
 * Idempotente : une réunion (cible + date) ne produit qu'une occurrence.
 */
class RapportOccurrenceService
{
    public const PAST_DAYS = 45;
    public const FUTURE_DAYS = 14;

    private RapportOccurrenceRepository $occurrences;
    private RapportSettingsRepository $settingsRepo;
    private RapportReminderRepository $reminders;
    private RapportAuditRepository $audit;
    private ?RapportSettingsService $settingsCache = null;

    public function __construct(
        ?RapportOccurrenceRepository $occurrences = null,
        ?RapportSettingsRepository $settingsRepo = null,
        ?RapportReminderRepository $reminders = null,
        ?RapportAuditRepository $audit = null
    ) {
        $this->occurrences = $occurrences ?? new RapportOccurrenceRepository();
        $this->settingsRepo = $settingsRepo ?? new RapportSettingsRepository();
        $this->reminders = $reminders ?? new RapportReminderRepository();
        $this->audit = $audit ?? new RapportAuditRepository();
    }

    /**
     * Synchronise la fenêtre [aujourd'hui - PAST_DAYS ; aujourd'hui + FUTURE_DAYS] :
     *  1. clôture des statuts (due / overdue) des occurrences existantes ;
     *  2. génération des nouvelles occurrences depuis les calendriers ;
     *  3. (ré)association des responsables effectifs à chaque occurrence ;
     *  4. rattachement idempotent des rapports déjà saisis (backfill) ;
     *  5. clôture finale des statuts.
     *
     * @return array<string,mixed> résumé exploitable (logs / recette)
     */
    public function sync(?string $from = null, ?string $to = null): array
    {
        $tz = new \DateTimeZone('Africa/Libreville');
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $from = $from ?? (new \DateTimeImmutable($today, $tz))->modify('-' . self::PAST_DAYS . ' days')->format('Y-m-d');
        $to = $to ?? (new \DateTimeImmutable($today, $tz))->modify('+' . self::FUTURE_DAYS . ' days')->format('Y-m-d');
        $grace = $this->settings()->graceMinutes();

        $created = 0;
        $missingDue = [];

        // 1. Statuts existants → due / overdue.
        $this->occurrences->refreshStatuses($grace);

        // 2. Génération depuis les calendriers (centres puis bacentas).
        //    L'ensemble des occurrences existantes est chargé une seule fois.
        $existing = [];
        foreach ($this->occurrences->window($from, $to) as $row) {
            $existing[$row['scope_type'] . '#' . $row['scope_id'] . '#' . $row['report_date']] = true;
        }
        foreach ($this->occurrences->schedulableCentres() as $target) {
            $created += $this->generateForTarget('centre', (int) $target['id'], (int) $target['id'], $target, $from, $to, $missingDue, $existing);
        }
        foreach ($this->occurrences->schedulableBacentas() as $target) {
            $centreId = (int) ($target['centre_id'] ?? 0);
            $created += $this->generateForTarget('bacenta', (int) $target['id'], $centreId, $target, $from, $to, $missingDue, $existing);
        }

        // 3. Association des responsables effectifs (avant le backfill,
        //    pour que recordSubmission attribue le crédit correctement).
        $this->syncResponsables($from, $to, $today);

        // 4. Rattachement des rapports existants (backfill idempotent).
        $backfilled = $this->occurrences->backfillFromReports($from, $to);

        // 5. Statuts finaux.
        $this->occurrences->refreshStatuses($grace);

        return [
            'from' => $from,
            'to' => $to,
            'created' => $created,
            'backfilled' => $backfilled,
            'missing_due_at' => array_values(array_unique($missingDue)),
        ];
    }

    /**
     * @param array<string,mixed> $target  Ligne centres/bacentas (jours_semaine, heures, heure_limite)
     * @param array<int,string>  $missingDue
     */
    private function generateForTarget(string $scopeType, int $scopeId, int $centreId, array $target, string $from, string $to, array &$missingDue, array &$existing): int
    {
        $days = $this->parseDays((string) ($target['jours_semaine'] ?? ''));
        if (!$days) {
            return 0;
        }
        $globalDue = $this->settings()->heureLimiteDefaut();
        $targetDue = $this->normalizeTime($target['heure_limite'] ?? null);
        $dueTime = $targetDue ?? $globalDue;
        if ($dueTime === null) {
            $missingDue[] = ($scopeType === 'centre' ? 'Centre' : 'Bacenta') . ' « ' . ($target['nom'] ?? $scopeId) . ' »';
        }

        $created = 0;
        $tz = new \DateTimeZone('Africa/Libreville');
        $cursor = new \DateTimeImmutable($from, $tz);
        $end = new \DateTimeImmutable($to, $tz);
        while ($cursor <= $end) {
            $isoN = (int) $cursor->format('N'); // 1 = lundi … 7 = dimanche
            if (in_array($isoN, $days, true)) {
                $date = $cursor->format('Y-m-d');
                $key = $scopeType . '#' . $scopeId . '#' . $date;
                if (!isset($existing[$key])) {
                    $dueAt = $dueTime !== null ? $date . ' ' . $dueTime . ':00' : null;
                    $this->occurrences->insertOccurrence($scopeType, $scopeId, $centreId, $date, $dueAt);
                    $existing[$key] = true;
                    $created++;
                }
            }
            $cursor = $cursor->modify('+1 day');
        }
        return $created;
    }

    /** Associe/désassigne les responsables effectifs sur la fenêtre (jamais retroactivement pour un nouveau nommé). */
    private function syncResponsables(string $from, string $to, string $today): void
    {
        foreach ($this->occurrences->window($from, $to) as $occ) {
            if ($occ['status'] === 'cancelled') {
                continue;
            }
            $targetType = $occ['scope_type'] === 'centre' ? 'center' : 'bacenta';
            $desired = $this->occurrences->managersForTarget($targetType, (int) $occ['scope_id'], (string) $occ['report_date']);
            $this->occurrences->addResponsables((int) $occ['id'], $desired);
            if ((string) $occ['report_date'] >= $today && $occ['submitted_report_id'] === null) {
                $this->occurrences->removeStaleLinks((int) $occ['id'], $desired, (string) $occ['report_date'], $today);
            }
            $this->occurrences->reconcileIndividualStatuses((int) $occ['id']);
        }
    }

    /**
     * Enregistrement atomique d'un dépôt de rapport (appelé DANS la
     * transaction de RapportJourService::save()). Ne lève jamais : en cas
     * d'échec, le rapport reste enregistré et la synchronisation rattache
     * l'occurrence au prochain passage.
     */
    public function recordSubmission(string $scopeType, int $scopeId, string $date, int $authorId, int $reportId, ?string $submittedAt = null): void
    {
        try {
            // Assure les liaisons responsables (règle « created_at ≤ date »)
            // avant l'attribution - indispensable si la sync n'a pas encore
            // vu cette occurrence.
            $occ = $this->occurrences->findByScopeDate($scopeType, $scopeId, $date);
            if ($occ && $occ['status'] !== 'cancelled') {
                $targetType = $scopeType === 'centre' ? 'center' : 'bacenta';
                $desired = $this->occurrences->managersForTarget($targetType, $scopeId, $date);
                $this->occurrences->addResponsables((int) $occ['id'], $desired);
            }
            $this->occurrences->recordSubmission($scopeType, $scopeId, $date, $authorId, $reportId, $submittedAt);
        } catch (\Throwable $e) {
            Logger::error('Suivi des rapports : attribution de la soumission impossible', [
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Annulation explicite par un administrateur (motif obligatoire, journalisée). */
    public function cancelOccurrence(int $occurrenceId, int $actorId, string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '' || $occurrenceId <= 0) {
            return false;
        }
        $occ = $this->occurrences->find($occurrenceId);
        if (!$occ) {
            return false;
        }
        $this->occurrences->cancel($occurrenceId, $actorId, $reason);
        $this->audit->log('occurrence_cancelled', 'occurrence', $occurrenceId, $reason, $actorId);
        return true;
    }

    private function settings(): RapportSettingsService
    {
        if ($this->settingsCache === null) {
            $this->settingsCache = new RapportSettingsService($this->settingsRepo);
        }
        return $this->settingsCache;
    }

    /** @return list<int> Jours ISO (1-7) depuis le CSV de libellés WEEK_DAYS. */
    public function parseDays(string $csv): array
    {
        $map = ['Lundi' => 1, 'Mardi' => 2, 'Mercredi' => 3, 'Jeudi' => 4, 'Vendredi' => 5, 'Samedi' => 6, 'Dimanche' => 7];
        $days = [];
        foreach (preg_split('/[,;|]/', $csv) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (isset($map[$part])) {
                $days[] = $map[$part];
                continue;
            }
            // Tolère les variantes numériques ("1".."7", "lundi" minuscule…)
            $lower = mb_strtolower($part);
            foreach ($map as $label => $iso) {
                if (mb_strtolower($label) === $lower) {
                    $days[] = $iso;
                    break;
                }
            }
            if (ctype_digit($part) && (int) $part >= 1 && (int) $part <= 7) {
                $days[] = (int) $part;
            }
        }
        return array_values(array_unique($days));
    }

    private function normalizeTime($value): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            return null;
        }
        // TIME MySQL → "HH:MM:SS" : on tronque aux minutes.
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v, $m)) {
            return $m[1] . ':' . $m[2];
        }
        return null;
    }

    /** Les liens vers le formulaire pré-rempli (dans l'application, authentifié). */
    public function formLinkFor(array $occurrence): string
    {
        $params = ['page' => 'rapport', 'date' => (string) $occurrence['report_date']];
        if ($occurrence['scope_type'] === 'bacenta') {
            $params['type'] = 'bacenta';
            $params['bacenta'] = (int) $occurrence['scope_id'];
            // Centre parent : dénormalisé sur l'occurrence (nécessaire pour le formulaire).
            $params['centre'] = (int) $occurrence['centre_id'];
        } else {
            $params['type'] = 'centre';
            $params['centre'] = (int) $occurrence['scope_id'];
        }
        return 'index.php?' . http_build_query($params);
    }
}
