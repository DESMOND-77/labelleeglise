<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Query;

/**
 * Occurrences attendues de rapport (centre et bacenta), liaisons
 * responsables et attribution atomique du crédit au premier dépôt valide.
 *
 * Périmètre (« scope ») : tableau de paires ['type' => 'centre'|'bacenta',
 * 'id' => int]. `null` = accès global (administrateur) ; `[]` = aucun accès.
 */
class RapportOccurrenceRepository
{
    /* ================= Génération / synchronisation ================= */

    /** Centres configurés pour générer des occurrences (calendrier explicite). */
    public function schedulableCentres(): array
    {
        return Query::all(
            "SELECT id, nom, jours_semaine, heure_debut, heure_fin, heure_limite
               FROM centres
              WHERE jours_semaine IS NOT NULL AND jours_semaine <> ''"
        );
    }

    /** Bacentas configurés pour générer des occurrences. */
    public function schedulableBacentas(): array
    {
        return Query::all(
            "SELECT b.id, b.nom, b.centre_id, b.jours_semaine, b.heure_debut, b.heure_fin, b.heure_limite,
                    c.nom AS centre_nom
               FROM bacentas b LEFT JOIN centres c ON c.id = b.centre_id
              WHERE b.jours_semaine IS NOT NULL AND b.jours_semaine <> ''"
        );
    }

    public function findByScopeDate(string $scopeType, int $scopeId, string $date): ?array
    {
        return Query::one(
            'SELECT * FROM rapport_occurrences WHERE scope_type = ? AND scope_id = ? AND report_date = ?',
            [$scopeType, $scopeId, $date]
        );
    }

    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM rapport_occurrences WHERE id = ?', [$id]);
    }

    /** INSERT IGNORE : idempotent par (scope_type, scope_id, report_date). */
    public function insertOccurrence(string $scopeType, int $scopeId, int $centreId, string $reportDate, ?string $dueAt): void
    {
        Query::run(
            'INSERT IGNORE INTO rapport_occurrences (scope_type, scope_id, centre_id, report_date, due_at, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$scopeType, $scopeId, $centreId, $reportDate, $dueAt, 'pending']
        );
    }

    /** Occurrences de la fenêtre (pour sync + backfill rapports existants). */
    public function window(string $from, string $to): array
    {
        return Query::all(
            'SELECT * FROM rapport_occurrences WHERE report_date BETWEEN ? AND ? ORDER BY report_date, id',
            [$from, $to]
        );
    }

    /** Rapports existants de la fenêtre (backfill de l'attribution). */
    public function reportsForWindow(string $from, string $to): array
    {
        return Query::all(
            "SELECT id, scope_type, scope_id, date_rapport, auteur_id, created_at
               FROM rapports_jour
              WHERE date_rapport BETWEEN ? AND ?",
            [$from, $to]
        );
    }

    public function reportsForScopeDate(string $scopeType, int $scopeId, string $date): ?array
    {
        return Query::one(
            'SELECT id, scope_type, scope_id, date_rapport, auteur_id, created_at
               FROM rapports_jour
              WHERE scope_type = ? AND scope_id = ? AND date_rapport = ?',
            [$scopeType, $scopeId, $date]
        );
    }

    /** Responsables « manager » effectifs à une date (created_at ≤ date). */
    public function managersForTarget(string $targetType, int $targetId, string $asOfDate): array
    {
        $rows = Query::all(
            "SELECT user_id, MIN(created_at) AS assigned_at
               FROM responsibilities
              WHERE responsibility_type = 'manager'
                AND target_type = ? AND target_id = ?
                AND created_at <= ?
              GROUP BY user_id",
            [$targetType, $targetId, $asOfDate . ' 23:59:59']
        );
        return array_map(static fn($r) => (int) $r['user_id'], $rows);
    }

    /** Toutes les occurrences liées à un utilisateur (pour la vue « mes échéances »). */
    public function occurrenceIdsForUser(int $userId): array
    {
        $rows = Query::all(
            'SELECT occurrence_id FROM rapport_occurrence_responsables WHERE user_id = ?',
            [$userId]
        );
        return array_map(static fn($r) => (int) $r['occurrence_id'], $rows);
    }

    public function responsablesOf(int $occurrenceId): array
    {
        return Query::all(
            'SELECT r.*, u.prenom, u.nom, u.email
               FROM rapport_occurrence_responsables r
               JOIN users u ON u.id = r.user_id
              WHERE r.occurrence_id = ?
              ORDER BY u.prenom, u.nom',
            [$occurrenceId]
        );
    }

    public function addResponsables(int $occurrenceId, array $userIds): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO rapport_occurrence_responsables (occurrence_id, user_id) VALUES (?, ?)'
        );
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0) {
                $stmt->execute([$occurrenceId, $uid]);
            }
        }
    }

    /**
     * Recalcule individual_status des liaisons depuis l'état de
     * l'occurrence (utilisé après (ré)association : un responsable ajouté
     * APRÈS remplissage ne doit pas apparaître « manquant »).
     */
    public function reconcileIndividualStatuses(int $occurrenceId): void
    {
        $occ = $this->find($occurrenceId);
        if (!$occ) {
            return;
        }
        $status = (string) $occ['status'];
        if ($status === 'cancelled') {
            Query::run("UPDATE rapport_occurrence_responsables SET individual_status = 'cancelled' WHERE occurrence_id = ?", [$occurrenceId]);
            return;
        }
        if (in_array($status, ['filled_on_time', 'filled_late'], true)) {
            $credited = $occ['credited_user_id'] !== null ? (int) $occ['credited_user_id'] : null;
            if ($credited !== null) {
                Query::run(
                    "UPDATE rapport_occurrence_responsables
                        SET individual_status = IF(user_id = ?, 'credited', 'filled_by_other')
                      WHERE occurrence_id = ?",
                    [$credited, $occurrenceId]
                );
            } else {
                Query::run(
                    "UPDATE rapport_occurrence_responsables SET individual_status = 'filled_by_other' WHERE occurrence_id = ?",
                    [$occurrenceId]
                );
            }
        }
    }

    /** Retire les liaisons des utilisateurs qui ne sont plus responsables (occurrences FUTURES/DU JOUR uniquement — n'altère jamais l'historique passé). */
    public function removeStaleLinks(int $occurrenceId, array $keepUserIds, string $reportDate, string $today): void
    {
        if ($reportDate < $today) {
            return;
        }
        $keep = array_values(array_filter(array_map('intval', $keepUserIds)));
        if ($keep === []) {
            Query::run('DELETE FROM rapport_occurrence_responsables WHERE occurrence_id = ?', [$occurrenceId]);
            return;
        }
        $in = implode(',', array_fill(0, count($keep), '?'));
        Query::run(
            "DELETE FROM rapport_occurrence_responsables WHERE occurrence_id = ? AND user_id NOT IN ($in)",
            array_merge([$occurrenceId], $keep)
        );
    }

    /* ================= Attribution atomique du dépôt ================= */

    /**
     * Enregistre un dépôt de rapport sur une occurrence existante.
     * Atomique (transaction appelante + WHERE credited_user_id IS NULL) :
     * le premier responsable dont la soumission valide est enregistrée
     * reçoit le crédit ; les autres restent « filled_by_other ».
     * Un tiers (non responsable) enregistre le rapport sans recevoir de
     * crédit (règle explicite « pour le compte de », §16.2).
     *
     * @return ?array Occurrence après mise à jour (null si aucune occurrence).
     */
    public function recordSubmission(string $scopeType, int $scopeId, string $date, int $authorId, int $reportId, ?string $submittedAt = null): ?array
    {
        $pdo = Database::connection();
        $occ = $this->findByScopeDate($scopeType, $scopeId, $date);
        if (!$occ) {
            return null;
        }
        if (($occ['status'] ?? '') === 'cancelled') {
            return $occ;
        }
        $occId = (int) $occ['id'];
        $submittedAt = $submittedAt ?? date('Y-m-d H:i:s');
        $dueAt = $occ['due_at'] ?? null;
        $onTime = $dueAt === null || $submittedAt <= (string) $dueAt;
        $status = $onTime ? 'filled_on_time' : 'filled_late';

        // Étape 1 : renseigner le dépôt + accorder le crédit au premier
        // responsable (le premier déposant gagne ; soumis_at et
        // submitted_by restent ceux du PREMIER dépôt valide — les éditions
        // ultérieures du rapport ne modifient ni l'horodatage ni l'auteur
        // initial ; seul le crédit peut encore être attribué à un
        // responsable si personne n'a encore été crédité).
        $st = $pdo->prepare(
            "UPDATE rapport_occurrences o
                LEFT JOIN rapport_occurrence_responsables r
                  ON r.occurrence_id = o.id AND r.user_id = :author
                SET o.submitted_report_id  = :rid,
                    o.submitted_by_user_id = COALESCE(o.submitted_by_user_id, :author),
                    o.submitted_at         = COALESCE(o.submitted_at, :submitted_at),
                    o.status               = IF(o.submitted_at IS NULL, :status, o.status),
                    o.credited_user_id     = CASE
                        WHEN o.credited_user_id IS NULL AND r.user_id IS NOT NULL THEN :author
                        ELSE o.credited_user_id
                    END
              WHERE o.id = :id AND o.status <> 'cancelled'"
        );
        $st->execute([
            ':rid'          => $reportId,
            ':author'       => $authorId,
            ':submitted_at' => $submittedAt,
            ':status'       => $status,
            ':id'           => $occId,
        ]);

        // Étape 2 : statuts individuels (credited / filled_by_other).
        $updated = $this->find($occId);
        $credited = $updated && $updated['credited_user_id'] !== null ? (int) $updated['credited_user_id'] : null;
        if ($credited !== null) {
            Query::run(
                "UPDATE rapport_occurrence_responsables
                    SET individual_status = IF(user_id = ?, 'credited', 'filled_by_other')
                  WHERE occurrence_id = ?",
                [$credited, $occId]
            );
        } else {
            Query::run(
                "UPDATE rapport_occurrence_responsables SET individual_status = 'filled_by_other' WHERE occurrence_id = ?",
                [$occId]
            );
        }

        // Étape 3 : le rapport enregistré clôt tous les rappels non envoyés.
        Query::run(
            "UPDATE rapport_reminder_logs SET status = 'cancelled' WHERE occurrence_id = ? AND status = 'pending'",
            [$occId]
        );

        return $this->find($occId);
    }

    /** Marque les occurrences « remplies » à partir des rapports existants (backfill idempotent). */
    public function backfillFromReports(string $from, string $to): int
    {
        $count = 0;
        $reports = $this->reportsForWindow($from, $to);
        foreach ($reports as $report) {
            $occ = $this->findByScopeDate((string) $report['scope_type'], (int) $report['scope_id'], (string) $report['date_rapport']);
            if (!$occ || $occ['submitted_report_id'] !== null || $occ['status'] === 'cancelled') {
                continue;
            }
            $this->recordSubmission(
                (string) $report['scope_type'],
                (int) $report['scope_id'],
                (string) $report['date_rapport'],
                (int) $report['auteur_id'],
                (int) $report['id'],
                (string) $report['created_at']
            );
            $count++;
        }
        return $count;
    }

    /* ================= Annulation ================= */

    public function cancel(int $occurrenceId, int $actorId, string $reason): void
    {
        Query::run(
            "UPDATE rapport_occurrences
                SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, cancellation_reason = ?
              WHERE id = ? AND status <> 'cancelled'",
            [$actorId, mb_substr($reason, 0, 255), $occurrenceId]
        );
        Query::run(
            "UPDATE rapport_occurrence_responsables SET individual_status = 'cancelled' WHERE occurrence_id = ?",
            [$occurrenceId]
        );
        Query::run(
            "UPDATE rapport_reminder_logs SET status = 'cancelled' WHERE occurrence_id = ? AND status = 'pending'",
            [$occurrenceId]
        );
    }

    /* ================= Rafraîchissement des statuts ================= */

    /** pending → due (échéance atteinte) → overdue (grâce dépassée). filled_* / cancelled inchangés. */
    public function refreshStatuses(int $graceMinutes): int
    {
        $graceMinutes = max(0, $graceMinutes);
        $graceExpr = "DATE_ADD(due_at, INTERVAL " . $graceMinutes . " MINUTE)";
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "UPDATE rapport_occurrences
                SET status = 'due'
              WHERE status = 'pending'
                AND ( (due_at IS NOT NULL AND due_at <= NOW())
                   OR (due_at IS NULL     AND report_date <= CURDATE()) )"
        );
        $st->execute();
        $due = $st->rowCount();
        $st = $pdo->prepare(
            "UPDATE rapport_occurrences
                SET status = 'overdue'
              WHERE status IN ('pending', 'due')
                AND ( (due_at IS NOT NULL AND $graceExpr <= NOW())
                   OR (due_at IS NULL     AND report_date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)) )"
        );
        $st->execute();
        $overdue = $st->rowCount();
        return $due + $overdue;
    }

    /* ================= Fragment de périmètre ================= */

    /**
     * Construit un fragment WHERE de périmètre sur l'alias d'occurrence.
     * @param ?array<int,array{type:string,id:int}> $pairs null = aucune restriction ; [] = 1=0.
     */
    public function scopeWhere(?array $pairs, array &$params, string $alias = 'o'): string
    {
        if ($pairs === null) {
            return '';
        }
        $centreIds = [];
        $bacentaIds = [];
        foreach ($pairs as $pair) {
            $type = (string) ($pair['type'] ?? '');
            $id = (int) ($pair['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($type === 'centre') {
                $centreIds[] = $id;
            } elseif ($type === 'bacenta') {
                $bacentaIds[] = $id;
            }
        }
        $groups = [];
        if ($centreIds) {
            $centreIds = array_values(array_unique($centreIds));
            $groups[] = "($alias.scope_type = 'centre' AND $alias.scope_id IN (" . implode(',', array_fill(0, count($centreIds), '?')) . '))';
            foreach ($centreIds as $id) {
                $params[] = $id;
            }
        }
        if ($bacentaIds) {
            $bacentaIds = array_values(array_unique($bacentaIds));
            $groups[] = "($alias.scope_type = 'bacenta' AND $alias.scope_id IN (" . implode(',', array_fill(0, count($bacentaIds), '?')) . '))';
            foreach ($bacentaIds as $id) {
                $params[] = $id;
            }
        }
        if (!$groups) {
            return ' AND 1 = 0';
        }
        return ' AND (' . implode(' OR ', $groups) . ')';
    }

    /* ================= Checklist ================= */

    /**
     * @param array<string,mixed> $f Filtres : from, to, centre_id, bacenta_id, user_id, scope_type, status, vue ('mes'|'all'), q (recherche cible)
     * @param ?array $scopePairs périmètre autorisé (null = global)
     * @return array<int,array<string,mixed>>
     */
    public function checklist(array $f, int $limit, int $offset, ?array $scopePairs, int $currentUserId): array
    {
        [$where, $params] = $this->checklistWhere($f, $scopePairs, $currentUserId);
        $sql = "SELECT o.*,
                       COALESCE(c.nom, b.nom) AS cible_nom,
                       COALESCE(c.nom, pa.nom) AS centre_nom,
                       (SELECT GROUP_CONCAT(CONCAT(u.prenom, ' ', u.nom) ORDER BY u.prenom SEPARATOR ', ')
                          FROM rapport_occurrence_responsables r JOIN users u ON u.id = r.user_id
                         WHERE r.occurrence_id = o.id) AS responsables_noms,
                       (SELECT CONCAT(lm.reminder_type, ' · ', lm.channel, ' · ', DATE_FORMAT(lm.created_at, '%d/%m %H:%i'))
                          FROM rapport_reminder_logs lm
                         WHERE lm.occurrence_id = o.id
                         ORDER BY lm.created_at DESC, lm.id DESC
                         LIMIT 1) AS dernier_rappel,
                       (SELECT CONCAT(au.prenom, ' ', au.nom) FROM users au WHERE au.id = o.submitted_by_user_id) AS auteur_nom
                  FROM rapport_occurrences o
                  LEFT JOIN centres c  ON c.id = o.scope_id AND o.scope_type = 'centre'
                  LEFT JOIN bacentas b ON b.id = o.scope_id AND o.scope_type = 'bacenta'
                  LEFT JOIN centres pa ON pa.id = b.centre_id
                $where
                ORDER BY o.report_date DESC, o.id DESC
                LIMIT $limit OFFSET $offset";
        return Query::all($sql, $params);
    }

    public function checklistCount(array $f, ?array $scopePairs, int $currentUserId): int
    {
        [$where, $params] = $this->checklistWhere($f, $scopePairs, $currentUserId);
        $sql = "SELECT COUNT(*)
                  FROM rapport_occurrences o
                  LEFT JOIN centres c  ON c.id = o.scope_id AND o.scope_type = 'centre'
                  LEFT JOIN bacentas b ON b.id = o.scope_id AND o.scope_type = 'bacenta'
                $where";
        return (int) Query::value($sql, $params);
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function checklistWhere(array $f, ?array $scopePairs, int $currentUserId): array
    {
        $where = 'WHERE 1 = 1';
        $params = [];

        $from = trim((string) ($f['from'] ?? ''));
        $to = trim((string) ($f['to'] ?? ''));
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where .= ' AND o.report_date >= ?';
            $params[] = $from;
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where .= ' AND o.report_date <= ?';
            $params[] = $to;
        }

        $centreId = (int) ($f['centre_id'] ?? 0);
        if ($centreId > 0) {
            $where .= ' AND o.centre_id = ?';
            $params[] = $centreId;
        }
        $bacentaId = (int) ($f['bacenta_id'] ?? 0);
        if ($bacentaId > 0) {
            $where .= " AND o.scope_type = 'bacenta' AND o.scope_id = ?";
            $params[] = $bacentaId;
        }
        $scopeType = (string) ($f['scope_type'] ?? '');
        if (in_array($scopeType, ['centre', 'bacenta'], true)) {
            $where .= ' AND o.scope_type = ?';
            $params[] = $scopeType;
        }
        $status = (string) ($f['status'] ?? '');
        $statuses = ['pending', 'due', 'overdue', 'filled_on_time', 'filled_late', 'cancelled'];
        if (in_array($status, $statuses, true)) {
            $where .= ' AND o.status = ?';
            $params[] = $status;
        }
        $userId = (int) ($f['user_id'] ?? 0);
        if ($userId > 0) {
            $where .= ' AND EXISTS (SELECT 1 FROM rapport_occurrence_responsables r WHERE r.occurrence_id = o.id AND r.user_id = ?)';
            $params[] = $userId;
        }
        if (($f['vue'] ?? '') === 'mes') {
            $where .= ' AND EXISTS (SELECT 1 FROM rapport_occurrence_responsables r WHERE r.occurrence_id = o.id AND r.user_id = ?)';
            $params[] = $currentUserId;
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (c.nom LIKE ? OR b.nom LIKE ?)';
            $like = '%' . mb_substr($q, 0, 80) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where .= $this->scopeWhere($scopePairs, $params, 'o');

        return [$where, $params];
    }

    /* ================= Agrégats (tableau de bord) ================= */

    public function aggregate(string $from, string $to, ?array $scopePairs): array
    {
        $params = [$from, $to];
        $scope = $this->scopeWhere($scopePairs, $params, 'o');
        $row = Query::one(
            "SELECT COUNT(*) AS expected,
                    SUM(CASE WHEN o.submitted_report_id IS NOT NULL THEN 1 ELSE 0 END) AS submitted,
                    SUM(CASE WHEN o.status = 'filled_on_time' THEN 1 ELSE 0 END) AS on_time,
                    SUM(CASE WHEN o.status = 'filled_late' THEN 1 ELSE 0 END) AS late,
                    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
               FROM rapport_occurrences o
              WHERE o.report_date BETWEEN ? AND ? $scope AND o.status <> 'cancelled'",
            $params
        );
        return [
            'expected'  => (int) ($row['expected'] ?? 0),
            'submitted' => (int) ($row['submitted'] ?? 0),
            'on_time'   => (int) ($row['on_time'] ?? 0),
            'late'      => (int) ($row['late'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
        ];
    }

    public function dailySeries(string $from, string $to, ?array $scopePairs): array
    {
        $params = [$from, $to];
        $scope = $this->scopeWhere($scopePairs, $params, 'o');
        return Query::all(
            "SELECT o.report_date AS day,
                    COUNT(*) AS expected,
                    SUM(CASE WHEN o.submitted_report_id IS NOT NULL THEN 1 ELSE 0 END) AS submitted
               FROM rapport_occurrences o
              WHERE o.report_date BETWEEN ? AND ? $scope AND o.status <> 'cancelled'
              GROUP BY o.report_date
              ORDER BY o.report_date",
            $params
        );
    }

    public function byStructure(string $from, string $to, ?array $scopePairs, int $limit = 12): array
    {
        $params = [$from, $to];
        $scope = $this->scopeWhere($scopePairs, $params, 'o');
        $limit = max(1, min(50, $limit));
        return Query::all(
            "SELECT COALESCE(c.nom, b.nom) AS cible_nom,
                    o.scope_type,
                    COUNT(*) AS expected,
                    SUM(CASE WHEN o.submitted_report_id IS NOT NULL THEN 1 ELSE 0 END) AS submitted
               FROM rapport_occurrences o
               LEFT JOIN centres c  ON c.id = o.scope_id AND o.scope_type = 'centre'
               LEFT JOIN bacentas b ON b.id = o.scope_id AND o.scope_type = 'bacenta'
              WHERE o.report_date BETWEEN ? AND ? $scope AND o.status <> 'cancelled'
              GROUP BY o.scope_type, o.scope_id, cible_nom
              ORDER BY expected DESC, cible_nom
              LIMIT $limit",
            $params
        );
    }

    public function byStatus(string $from, string $to, ?array $scopePairs): array
    {
        $params = [$from, $to];
        $scope = $this->scopeWhere($scopePairs, $params, 'o');
        return Query::all(
            "SELECT o.status, COUNT(*) AS total
               FROM rapport_occurrences o
              WHERE o.report_date BETWEEN ? AND ? $scope
              GROUP BY o.status",
            $params
        );
    }

    /**
     * Statistiques individuelles par responsable sur une période.
     * Le crédit est strictement celui accordé par recordSubmission() :
     *  - credited          : occurrence remplie grâce à CE responsable (crédit)
     *  - filled_by_other   : occurrence remplie par un autre responsable (ni crédit ni échec individuel)
     *  - missing           : occurrence non remplie
     * Le taux individuel = credited / (credited + missing) ; les « filled_by_other »
     * sont affichées mais exclus du dénominateur (jamais comptées comme un échec).
     */
    public function individualStats(string $from, string $to, ?array $scopePairs, ?int $onlyUserId = null): array
    {
        $params = [$from, $to];
        $scope = $this->scopeWhere($scopePairs, $params, 'o');
        $userClause = '';
        if ($onlyUserId !== null && $onlyUserId > 0) {
            $userClause = ' AND r.user_id = ?';
            $params[] = $onlyUserId;
        }
        return Query::all(
            "SELECT r.user_id,
                    u.prenom, u.nom, u.email,
                    COUNT(*) AS expected,
                    SUM(CASE WHEN r.individual_status = 'credited' THEN 1 ELSE 0 END) AS credited,
                    SUM(CASE WHEN r.individual_status = 'filled_by_other' THEN 1 ELSE 0 END) AS filled_by_other,
                    SUM(CASE WHEN r.individual_status = 'missing' THEN 1 ELSE 0 END) AS missing,
                    SUM(CASE WHEN r.individual_status = 'credited' AND o.status = 'filled_on_time' THEN 1 ELSE 0 END) AS on_time,
                    SUM(CASE WHEN r.individual_status = 'credited' AND o.status = 'filled_late' THEN 1 ELSE 0 END) AS late
               FROM rapport_occurrence_responsables r
               JOIN rapport_occurrences o ON o.id = r.occurrence_id
               JOIN users u ON u.id = r.user_id
              WHERE o.report_date BETWEEN ? AND ? $scope
                AND o.status <> 'cancelled'
                AND r.individual_status <> 'cancelled'
                $userClause
              GROUP BY r.user_id, u.prenom, u.nom, u.email
              ORDER BY credited DESC, expected DESC, u.nom, u.prenom",
            $params
        );
    }

    /** Résultat individuel détaillé (fiche responsable : cumul depuis une date). */
    public function individualStatsFrom(string $from, string $to, int $userId, ?array $scopePairs): array
    {
        $rows = $this->individualStats($from, $to, $scopePairs, $userId);
        if ($rows) {
            return $rows[0];
        }
        return ['user_id' => $userId, 'prenom' => '', 'nom' => '', 'email' => '', 'expected' => 0, 'credited' => 0, 'filled_by_other' => 0, 'missing' => 0, 'on_time' => 0, 'late' => 0];
    }

    /* ================= Rattachement occurrences ↔ user ================= */

    public function syncIndividualMissing(): int
    {
        // Toute liaison d'occurrence ouverte sans responsable restant à NULL
        // n'existe pas par construction ; rien à faire ici. Conservé pour
        // symétrie d'API et extensions futures.
        return 0;
    }

    /* ================= Calendriers (administration) ================= */

    public function allCentresCalendar(): array
    {
        return Query::all('SELECT id, nom, jours_semaine, heure_debut, heure_fin, heure_limite FROM centres ORDER BY nom');
    }

    public function allBacentasCalendar(): array
    {
        return Query::all(
            'SELECT b.id, b.nom, b.centre_id, b.jours_semaine, b.heure_debut, b.heure_fin, b.heure_limite, c.nom AS centre_nom
               FROM bacentas b LEFT JOIN centres c ON c.id = b.centre_id
              ORDER BY c.nom, b.nom'
        );
    }

    public function updateCentreCalendar(int $centreId, ?string $jours, ?string $debut, ?string $fin, ?string $limite): void
    {
        Query::run(
            'UPDATE centres SET jours_semaine = ?, heure_debut = ?, heure_fin = ?, heure_limite = ? WHERE id = ?',
            [$jours, $debut, $fin, $limite, $centreId]
        );
    }

    public function updateBacentaCalendar(int $bacentaId, ?string $jours, ?string $debut, ?string $fin, ?string $limite): void
    {
        Query::run(
            'UPDATE bacentas SET jours_semaine = ?, heure_debut = ?, heure_fin = ?, heure_limite = ? WHERE id = ?',
            [$jours, $debut, $fin, $limite, $bacentaId]
        );
    }

    /** Responsables ayant au moins une occurrence sur la période (listes de filtre). */
    public function responsablesForPeriod(string $from, string $to, ?array $scopePairs): array
    {
        $params = [$from, $to];
        $scope = $this->scopeWhere($scopePairs, $params, 'o');
        return Query::all(
            "SELECT DISTINCT u.id, CONCAT(u.prenom, ' ', u.nom) AS nom
               FROM users u
               JOIN rapport_occurrence_responsables r ON r.user_id = u.id
               JOIN rapport_occurrences o ON o.id = r.occurrence_id
              WHERE o.report_date BETWEEN ? AND ? $scope AND o.status <> 'cancelled'
              ORDER BY nom",
            $params
        );
    }

    /** Occurrence ouverte d'une cible pour une date donnée (utilisée par le formulaire). */
    public function openForScopeDate(string $scopeType, int $scopeId, string $date): ?array
    {
        return Query::one(
            "SELECT * FROM rapport_occurrences
              WHERE scope_type = ? AND scope_id = ? AND report_date = ? AND status NOT IN ('cancelled')",
            [$scopeType, $scopeId, $date]
        );
    }
}
