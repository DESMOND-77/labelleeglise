<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\RapportOccurrenceRepository;
use App\Repositories\RapportReminderDueRepository;
use App\Repositories\RapportReminderRepository;
use App\Repositories\RapportSettingsRepository;
use App\Repositories\RapportAuditRepository;
use App\Repositories\UserRepository;

/**
 * Rappels automatiques (in-app + email) et bilans périodiques.
 *
 * Garanties :
 *  - au plus UN envoi par (occurrence, destinataire, type, canal) — index
 *    unique + écriture de la ligne d'idempotence AVANT l'envoi ;
 *  - le rapport est revérifié juste avant CHAQUE envoi ;
 *  - échecs indépendants par canal, compteur de tentatives borné
 *    (jamais de boucle si le mail est indisponible) ;
 *  - une soumission clôture tous les rappels non envoyés ;
 *  - mode simulation (dry-run) : aucune écriture, aucun envoi, tout est
 *    rapporté dans le résultat.
 */
class RapportReminderService
{
    private RapportOccurrenceRepository $occurrences;
    private RapportReminderRepository $reminders;
    private RapportReminderDueRepository $due;
    private RapportSettingsService $settings;
    private NotificationService $notifications;
    private MailService $mail;
    private UserRepository $users;
    private RapportScopeService $scope;
    private RapportScoringService $scoring;
    private RapportAuditRepository $audit;

    public function __construct(
        ?RapportOccurrenceRepository $occurrences = null,
        ?RapportReminderRepository $reminders = null,
        ?RapportReminderDueRepository $due = null,
        ?RapportSettingsRepository $settingsRepo = null,
        ?NotificationService $notifications = null,
        ?MailService $mail = null,
        ?UserRepository $users = null,
        ?RapportScopeService $scope = null,
        ?RapportScoringService $scoring = null,
        ?RapportAuditRepository $audit = null
    ) {
        $this->occurrences = $occurrences ?? new RapportOccurrenceRepository();
        $this->reminders = $reminders ?? new RapportReminderRepository();
        $this->due = $due ?? new RapportReminderDueRepository();
        $this->settings = new RapportSettingsService($settingsRepo ?? new RapportSettingsRepository());
        $this->notifications = $notifications ?? new NotificationService();
        $this->mail = $mail ?? new MailService();
        $this->users = $users ?? new UserRepository();
        $this->scope = $scope ?? new RapportScopeService();
        $this->scoring = $scoring ?? new RapportScoringService(null, $this->occurrences);
        $this->audit = $audit ?? new RapportAuditRepository();
    }

    /** Verrou MySQL applicatif (une seule exécution à la fois, même en multi-poste). */
    public function acquireLock(int $timeoutSeconds = 0): bool
    {
        $value = \App\Core\Query::value('SELECT GET_LOCK(?, ?)', ['rapport_rappels_lock', $timeoutSeconds]);
        return (int) $value === 1;
    }

    public function releaseLock(): void
    {
        \App\Core\Query::value('SELECT RELEASE_LOCK(?)', ['rapport_rappels_lock']);
    }

    /**
     * Traitement complet : synchronisation des occurrences + envoi des
     * rappels dus + bilans périodiques éventuels.
     *
     * @return array<string,mixed> résumé (publié dans les journaux par le cron)
     */
    public function processAll(bool $dryRun = false): array
    {
        $summary = [
            'dry_run' => $dryRun,
            'started_at' => date('Y-m-d H:i:s'),
            'locked' => false,
            'sync' => null,
            'reminders' => ['checked' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'cancelled_closed' => 0],
            'bilans' => [],
            'details' => [],
        ];

        // Verrou applicatif : un seul traitement à la fois (un déclenchement
        // concurrent se termine immédiatement, sans rien envoyer).
        if (!$this->acquireLock(0)) {
            $summary['details'][] = 'Un autre traitement des rappels est en cours (verrou MySQL) — abandon sans envoi.';
            $summary['finished_at'] = date('Y-m-d H:i:s');
            return $summary;
        }
        $summary['locked'] = true;

        try {
            if (!$dryRun) {
                $occurrenceService = new RapportOccurrenceService();
                $summary['sync'] = $occurrenceService->sync();
            } else {
                $summary['sync'] = ['skipped' => 'dry-run'];
            }

            $summary['reminders'] = $this->processDueReminders($dryRun, $summary['details']);
            $summary['bilans'] = $this->maybeSendBilans($dryRun, $summary['details']);
            $summary['finished_at'] = date('Y-m-d H:i:s');
        } finally {
            $this->releaseLock();
        }

        return $summary;
    }

    /* ==================================================================
     * Rappels
     * ================================================================== */

    /**
     * @param array<int,string> $details lignes de journal lisibles (publiées par le cron / dry-run)
     */
    public function processDueReminders(bool $dryRun, ?array &$details = null): array
    {
        $details = $details ?? [];
        $result = ['checked' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'cancelled_closed' => 0, 'by_type' => []];
        $maxAttempts = $this->settings->maxAttempts();
        $retryMinutes = $this->settings->retryMinutes();
        $preventifHeure = $this->settings->preventifHeure();
        $relanceHeure = $this->settings->relanceHeure();

        $work = [];
        if ($this->settings->preventifEnabled()) {
            foreach ($this->due->dueReminders('preventif', $preventifHeure, $relanceHeure) as $row) {
                $work[] = ['type' => 'preventif', 'row' => $row];
            }
        }
        if ($this->settings->echeanceEnabled()) {
            foreach ($this->due->dueReminders('echeance', $preventifHeure, $relanceHeure) as $row) {
                $work[] = ['type' => 'echeance', 'row' => $row];
            }
        }
        if ($this->settings->relanceEnabled()) {
            foreach ($this->due->dueReminders('relance', $preventifHeure, $relanceHeure) as $row) {
                $work[] = ['type' => 'relance', 'row' => $row];
            }
        }

        foreach ($work as $item) {
            $row = $item['row'];
            $type = $item['type'];
            $occurrenceId = (int) $row['id'];
            $userId = (int) $row['responsible_user_id'];
            $result['checked']++;
            $result['by_type'][$type] = ($result['by_type'][$type] ?? 0) + 1;

            // Revérification JUSTE AVANT l'envoi : le rapport a pu être
            // soumis entre la sélection et maintenant.
            if ($this->occurrences->reportsForScopeDate((string) $row['scope_type'], (int) $row['scope_id'], (string) $row['report_date'])) {
                if (!$dryRun) {
                    $result['cancelled_closed'] += $this->reminders->cancelPendingForOccurrence($occurrenceId);
                }
                $details[] = "[$type] occurrence #$occurrenceId ignorée : rapport enregistré entre-temps";
                $result['skipped']++;
                continue;
            }

            foreach ($this->enabledChannels() as $channel) {
                $idem = $this->idempotencyKey($occurrenceId, $userId, $type, $channel);
                if ($dryRun) {
                    $details[] = "[dry-run][$type][$channel] occurrence #$occurrenceId → utilisateur #$userId ($idem)";
                    $result['sent']++;
                    continue;
                }
                $claim = $this->reminders->claimOrFetch($occurrenceId, $userId, $type, $channel, $idem, $maxAttempts, $retryMinutes);
                if ($claim === null) {
                    continue; // déjà envoyé / déjà planifié / abandonné après max tentatives
                }
                $logId = (int) $claim['id'];
                try {
                    $outcome = $channel === 'email'
                        ? $this->sendEmailReminder($row, $userId, $type)
                        : $this->sendInAppReminder($row, $userId, $type);
                } catch (\Throwable $e) {
                    $outcome = ['ok' => false, 'error' => $e->getMessage()];
                }
                if ($outcome['ok']) {
                    $this->reminders->markSent($logId);
                    $result['sent']++;
                    $details[] = "[$type][$channel] occurrence #$occurrenceId → utilisateur #$userId : envoyé";
                } elseif (!empty($outcome['permanent'])) {
                    // Email invalide / canal non configuré : motif tracé, pas de nouvelle tentative.
                    $this->reminders->markSkipped($logId, (string) ($outcome['error'] ?? 'ignoré'));
                    $result['skipped']++;
                    $details[] = "[$type][$channel] occurrence #$occurrenceId → utilisateur #$userId : ignoré (" . ($outcome['error'] ?? '?') . ')';
                } else {
                    $this->reminders->markFailed($logId, (string) ($outcome['error'] ?? 'échec inconnu'), $maxAttempts);
                    $result['failed']++;
                    $details[] = "[$type][$channel] occurrence #$occurrenceId → utilisateur #$userId : ÉCHEC (" . ($outcome['error'] ?? '?') . ')';
                }
            }
        }

        return $result;
    }

    /** Canaux actifs côté administration (V1 : préférences globales uniquement). */
    private function enabledChannels(): array
    {
        $channels = [];
        if ($this->settings->inappEnabled()) {
            $channels[] = 'inapp';
        }
        if ($this->settings->emailEnabled()) {
            $channels[] = 'email';
        }
        return $channels;
    }

    private function idempotencyKey(int $occurrenceId, int $userId, string $type, string $channel): string
    {
        if ($type === 'manuel') {
            // Une relance manuelle par jour, par occurrence et par canal.
            return 'rapport:' . $occurrenceId . ':' . $userId . ':manuel:' . $channel . ':' . date('Ymd');
        }
        return 'rapport:' . $occurrenceId . ':' . $userId . ':' . $type . ':' . $channel;
    }

    /** Nombre de rappels en échec (indicateur admin). */
    public function failedCount(): int
    {
        return $this->reminders->failedCount();
    }

    /** Rappels en échec récents (monitoring admin). */
    public function failedRecent(int $limit = 20): array
    {
        return $this->reminders->failedRecent($limit);
    }

    /* ==================================================================
     * Relance manuelle (depuis la checklist)
     * ================================================================== */

    /**
     * @return array{ok:bool,message:string,sent:int}
     */
    public function manualRelance(int $occurrenceId, int $actorId): array
    {
        $occ = $this->occurrences->find($occurrenceId);
        if (!$occ) {
            return ['ok' => false, 'message' => 'Occurrence introuvable.', 'sent' => 0];
        }
        if (in_array($occ['status'], ['filled_on_time', 'filled_late', 'cancelled'], true)) {
            return ['ok' => false, 'message' => 'Cette occurrence est déjà clôturée : aucun rappel n’a été envoyé.', 'sent' => 0];
        }
        $responsables = $this->occurrences->responsablesOf($occurrenceId);
        if (!$responsables) {
            return ['ok' => false, 'message' => 'Aucun responsable affecté à cette occurrence.', 'sent' => 0];
        }
        $maxAttempts = $this->settings->maxAttempts();
        $retryMinutes = $this->settings->retryMinutes();
        $sent = 0;
        $details = [];
        foreach ($responsables as $resp) {
            $userId = (int) $resp['user_id'];
            $row = $occ + ['responsible_user_id' => $userId, 'responsible_name' => trim(($resp['prenom'] ?? '') . ' ' . ($resp['nom'] ?? '')), 'responsible_email' => $resp['email'] ?? null];
            foreach ($this->enabledChannels() as $channel) {
                $idem = $this->idempotencyKey($occurrenceId, $userId, 'manuel', $channel);
                $claim = $this->reminders->claimOrFetch($occurrenceId, $userId, 'manuel', $channel, $idem, $maxAttempts, $retryMinutes);
                if ($claim === null) {
                    continue;
                }
                $logId = (int) $claim['id'];
                try {
                    $outcome = $channel === 'email' ? $this->sendEmailReminder($row, $userId, 'manuel') : $this->sendInAppReminder($row, $userId, 'manuel');
                } catch (\Throwable $e) {
                    $outcome = ['ok' => false, 'error' => $e->getMessage()];
                }
                if ($outcome['ok']) {
                    $this->reminders->markSent($logId);
                    $sent++;
                    $details[] = "utilisateur #$userId ($channel)";
                } elseif (!empty($outcome['permanent'])) {
                    $this->reminders->markSkipped($logId, (string) ($outcome['error'] ?? 'ignoré'));
                } else {
                    $this->reminders->markFailed($logId, (string) ($outcome['error'] ?? 'échec'), $maxAttempts);
                }
            }
        }
        $this->audit->log('manual_reminder', 'occurrence', $occurrenceId, implode(', ', $details) ?: 'aucun envoi (déjà rappelé aujourd’hui ou canaux désactivés)', $actorId);
        return ['ok' => true, 'message' => $sent > 0 ? "Relance envoyée ($sent envoi(s))." : 'Aucun envoi : déjà relancé aujourd’hui, canaux désactivés ou occurrence clôturée.', 'sent' => $sent];
    }

    /* ==================================================================
     * Envoi des canaux
     * ================================================================== */

    /** @param array<string,mixed> $occRow */
    private function sendInAppReminder(array $occRow, int $userId, string $type): array
    {
        [$title, $message] = $this->reminderCopy($occRow, $type);
        $link = $this->relativeFormLink($occRow);
        $this->notifications->notify($userId, 'rapport_rappel', $title, $message, $link);
        return ['ok' => true];
    }

    /** @param array<string,mixed> $occRow */
    private function sendEmailReminder(array $occRow, int $userId, string $type): array
    {
        $user = $this->users->find($userId);
        if (!$user) {
            return ['ok' => false, 'error' => 'utilisateur introuvable', 'permanent' => true];
        }
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'adresse email absente ou invalide', 'permanent' => true];
        }
        [$title, $message] = $this->reminderCopy($occRow, $type);
        $absoluteLink = $this->mail->buildUrl('index.php', $this->formParams($occRow));
        $context = [
            'type' => $type,
            'cibleNom' => $this->targetName($occRow),
            'dateReunion' => $occRow['report_date'],
            'dueAt' => $occRow['due_at'] ?? null,
            'message' => $message,
            'title' => $title,
        ];
        $ok = $this->mail->sendRapportReminder($user, $context, $absoluteLink);
        if (!$ok) {
            // MailService journalise déjà la cause ; on ne distingue pas
            // « SMTP absent » d'un rejet temporaire → nouvelle tentative bornée.
            return ['ok' => false, 'error' => 'échec SMTP (voir Storage/logs/mail.log)'];
        }
        return ['ok' => true];
    }

    /** @return array{0:string,1:string} */
    private function reminderCopy(array $occRow, string $type): array
    {
        $cible = $this->targetName($occRow);
        $date = $this->frDate((string) $occRow['report_date']);
        $due = $occRow['due_at'] ? ' Heure limite : ' . $this->frDateTime((string) $occRow['due_at']) . '.' : '';
        switch ($type) {
            case 'preventif':
                return [
                    'Rappel : réunion demain',
                    "Bonjour, la réunion de $cible est prévue le $date. Le rapport du jour sera attendu.$due Merci de penser à le compléter.",
                ];
            case 'echeance':
                return [
                    'Rapport du jour en attente',
                    "Bonjour, l’heure limite pour le rapport de $cible (réunion du $date) est passée.$due Merci de le compléter dès que possible.",
                ];
            case 'relance':
                return [
                    'Relance : rapport non soumis',
                    "Bonjour, le rapport de $cible (réunion du $date) n’a pas encore été enregistré. Merci de le renseigner dès aujourd’hui.",
                ];
            case 'manuel':
                return [
                    'Relance : rapport en attente',
                    "Bonjour, un rappel vous est adressé pour le rapport de $cible (réunion du $date). Merci de le compléter dès que possible.",
                ];
        }
        return ['Rapport du jour', "Bonjour, le rapport de $cible ($date) est attendu.$due"];
    }

    /** @param array<string,mixed> $occRow */
    private function targetName(array $occRow): string
    {
        if (!empty($occRow['cible_nom'])) {
            return (string) $occRow['cible_nom'];
        }
        $row = $this->occurrences->find((int) $occRow['id']);
        // Le nom n'est pas porté par l'occurrence : lookup ciblé.
        if ($row) {
            $name = \App\Core\Query::value(
                $occRow['scope_type'] === 'centre'
                    ? 'SELECT nom FROM centres WHERE id = ?'
                    : 'SELECT nom FROM bacentas WHERE id = ?',
                [(int) $occRow['scope_id']]
            );
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }
        return ($occRow['scope_type'] === 'centre' ? 'le centre #' : 'le bacenta #') . (int) $occRow['scope_id'];
    }

    /** @param array<string,mixed> $occRow */
    private function formParams(array $occRow): array
    {
        $params = ['page' => 'rapport', 'date' => (string) $occRow['report_date']];
        if ($occRow['scope_type'] === 'bacenta') {
            $params['type'] = 'bacenta';
            $params['bacenta'] = (int) $occRow['scope_id'];
            $params['centre'] = (int) $occRow['centre_id'];
        } else {
            $params['type'] = 'centre';
            $params['centre'] = (int) $occRow['scope_id'];
        }
        return $params;
    }

    /** @param array<string,mixed> $occRow */
    private function relativeFormLink(array $occRow): string
    {
        return 'index.php?' . http_build_query($this->formParams($occRow));
    }

    private function frDate(string $iso): string
    {
        $ts = strtotime($iso);
        return $ts === false ? $iso : date('d/m/Y', $ts);
    }

    private function frDateTime(string $sqlDateTime): string
    {
        $ts = strtotime($sqlDateTime);
        return $ts === false ? $sqlDateTime : date('d/m/Y à H\hi', $ts);
    }

    /* ==================================================================
     * Bilans périodiques (hebdomadaire / mensuel) — §16.5
     * ================================================================== */

    /**
     * Envoie les bilans dus (lundi matin pour la semaine ISO écoulée ;
     * le 1er du mois pour le mois civil écoulé). Un seul message par
     * responsable et par période (notified_at). Retourne le résumé.
     *
     * @param array<int,string> $details
     */
    public function maybeSendBilans(bool $dryRun, ?array &$details = null): array
    {
        $details = $details ?? [];
        $tz = new \DateTimeZone('Africa/Libreville');
        $now = new \DateTimeImmutable('now', $tz);
        $today = $now->format('Y-m-d');
        $sent = 0;
        $summaries = [];

        // Bilan mensuel : le 1er du mois, après l'heure configurée.
        if ($this->settings->bilanMensuelEnabled() && $now->format('d') === '01'
            && $now->format('H:i') >= $this->settings->bilanMensuelHeure()) {
            $firstPrev = $now->modify('first day of last month');
            $lastPrev = $now->modify('last day of last month');
            $summaries['month'] = $this->sendBilanPeriod('month', $firstPrev->format('Y-m-d'), $lastPrev->format('Y-m-d'), $dryRun, $details, $sent);
        }

        // Bilan hebdomadaire : le lundi, après l'heure configurée.
        if ($this->settings->bilanHebdoEnabled() && (int) $now->format('N') === 1
            && $now->format('H:i') >= $this->settings->bilanHebdoHeure()) {
            $lastWeekReference = $now->modify('-7 days');
            $n = (int) $lastWeekReference->format('N');
            $monday = $lastWeekReference->modify('-' . ($n - 1) . ' days');
            $sunday = $monday->modify('+6 days');
            $summaries['week'] = $this->sendBilanPeriod('week', $monday->format('Y-m-d'), $sunday->format('Y-m-d'), $dryRun, $details, $sent);
        }

        return ['sent' => $sent, 'periods' => $summaries];
    }

    /** @param array<int,string> $details */
    private function sendBilanPeriod(string $periodType, string $from, string $to, bool $dryRun, array &$details, int &$sent): array
    {
        if ($dryRun) {
            $users = count($this->scoring->usersWithOccurrences($from, $to, null));
            $details[] = "[dry-run] bilan $periodType $from → $to : $users responsable(s) concerné(s)";
            return ['from' => $from, 'to' => $to, 'recipients' => $users];
        }

        $results = $this->scoring->finalizePeriod($periodType, $from, $to, null);
        $months = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
        $ref = new \DateTimeImmutable($from);
        $periodLabel = $periodType === 'week'
            ? 'Semaine ' . $ref->format('o-\WW')
            : (($months[(int) $ref->format('n')] ?? $ref->format('m')) . ' ' . $ref->format('Y'));

        $sentForPeriod = 0;
        foreach ($results as $result) {
            $userId = (int) $result['user_id'];
            $stored = $this->scoring->resultFor($userId, $periodType, $from, $to);
            if ($stored && !empty($stored['notified_at'])) {
                continue; // bilan déjà communiqué pour cette période
            }
            $stats = $result['stats'];
            $levelKey = $result['level_key'];
            $previousLevel = $this->scoring->previousLevelKey($userId, $periodType, $from);
            $levelChanged = $this->settings->felicitationsEnabled() && $levelKey !== null && $levelKey !== $previousLevel;

            $outcome = $this->sendBilanMessage($userId, $periodType, $periodLabel, $from, $to, $stats, $result, $levelChanged);
            if ($outcome['ok']) {
                \App\Core\Query::run(
                    'UPDATE rapport_scoring_results SET notified_at = NOW(), notified_level_key = ? WHERE user_id = ? AND period_type = ? AND period_start = ? AND period_end = ?',
                    [$levelKey, $userId, $periodType, $from, $to]
                );
                $sentForPeriod++;
                $sent++;
                $details[] = "[bilan-$periodType] → utilisateur #$userId : envoyé" . ($levelChanged ? ' (nouveau niveau)' : '');
            } else {
                $details[] = "[bilan-$periodType] → utilisateur #$userId : échec (" . ($outcome['error'] ?? '?') . ')';
            }
        }

        $this->audit->log('bilan_' . $periodType, 'scoring_results', null, "Période $from → $to : $sentForPeriod bilan(s) envoyé(s)", null);
        return ['from' => $from, 'to' => $to, 'sent' => $sentForPeriod];
    }

    /** @param array<string,mixed> $stats */
    private function sendBilanMessage(int $userId, string $periodType, string $periodLabel, string $from, string $to, array $stats, array $result, bool $levelChanged): array
    {
        $user = $this->users->find($userId);
        if (!$user) {
            return ['ok' => false, 'error' => 'utilisateur introuvable'];
        }
        $rate = $stats['completion_rate'];
        $rateLabel = $rate === null ? 'Non évalué (aucune échéance attendue)' : number_format((float) $rate, 1, ',', ' ') . ' %';
        $punct = $stats['punctuality_rate'] === null ? 'Non évalué' : number_format((float) $stats['punctuality_rate'], 1, ',', ' ') . ' %';
        $levelLabel = $result['level']['label'] ?? null;
        $message = $result['message'];

        $title = $periodType === 'week' ? 'Votre bilan hebdomadaire — Rapports du Jour' : 'Votre bilan mensuel — Rapports du Jour';
        $body = "Période : $periodLabel.\n"
            . "Rapports attendus : {$stats['expected_count']}\n"
            . "Rapports soumis : {$stats['submitted_count']} (dont " . ($stats['filled_by_other'] ?? 0) . " soumis par un autre responsable)\n"
            . "Dont rendus à temps : {$stats['on_time_count']}\n"
            . "Taux de réalisation personnel : $rateLabel\n"
            . "Ponctualité : $punct\n"
            . ($levelLabel ? "Niveau : $levelLabel\n" : '')
            . ($levelChanged && $levelLabel ? "🎉 Nouveau niveau atteint : $levelLabel !\n" : '')
            . "\n$message";

        $link = 'index.php?page=rapport_responsable&id=' . $userId;
        $inappOk = false;
        $emailOk = false;
        $errors = [];

        if ($this->settings->inappEnabled()) {
            try {
                $this->notifications->notify($userId, 'rapport_bilan', $title, str_replace("\n", ' ', $body), $link);
                $inappOk = true;
            } catch (\Throwable $e) {
                $errors[] = 'in-app: ' . $e->getMessage();
            }
        }
        if ($this->settings->emailEnabled()) {
            $email = trim((string) ($user['email'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $absoluteLink = $this->mail->buildUrl('index.php', ['page' => 'rapport_responsable', 'id' => $userId]);
                $context = [
                    'periodLabel' => $periodLabel,
                    'periodType' => $periodType,
                    'expected' => $stats['expected_count'],
                    'submitted' => $stats['submitted_count'],
                    'filledByOther' => $stats['filled_by_other'] ?? 0,
                    'onTime' => $stats['on_time_count'],
                    'late' => $stats['late_count'],
                    'completionRate' => $rateLabel,
                    'punctualityRate' => $punct,
                    'levelLabel' => $levelLabel,
                    'levelChanged' => $levelChanged,
                    'message' => $message,
                ];
                if ($this->mail->sendRapportBilan($user, $context, $absoluteLink)) {
                    $emailOk = true;
                } else {
                    $errors[] = 'email: échec SMTP';
                }
            } else {
                $errors[] = 'email: adresse absente ou invalide';
            }
        }

        if (!$this->settings->inappEnabled() && !$this->settings->emailEnabled()) {
            return ['ok' => false, 'error' => 'les deux canaux sont désactivés'];
        }
        // Succès si au moins un canal a abouti (les canaux échouent indépendamment).
        if ($inappOk || $emailOk) {
            return ['ok' => true, 'error' => $errors ? implode(' ; ', $errors) : null];
        }
        return ['ok' => false, 'error' => implode(' ; ', $errors) ?: 'échec'];
    }
}
