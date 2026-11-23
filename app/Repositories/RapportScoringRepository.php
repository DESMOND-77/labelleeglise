<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Barème configurable + résultats historisés des responsables.
 * L'historique n'est jamais réécrit : chaque résultat porte la version
 * du barème (`rules_version`) et un instantané du message (`message_snapshot`).
 */
class RapportScoringRepository
{
    /* ================= Règles (barème) ================= */

    public function allRules(): array
    {
        return Query::all('SELECT * FROM rapport_scoring_rules ORDER BY sort_order, min_rate DESC, id');
    }

    public function activeRules(): array
    {
        return Query::all('SELECT * FROM rapport_scoring_rules WHERE is_active = 1 ORDER BY sort_order, min_rate DESC, id');
    }

    public function findRule(int $id): ?array
    {
        return Query::one('SELECT * FROM rapport_scoring_rules WHERE id = ?', [$id]);
    }

    /** @param array<string,mixed> $data */
    public function insertRule(array $data): int
    {
        return Query::run(
            'INSERT INTO rapport_scoring_rules
                (name, period_type, min_rate, max_rate, level_key, label, message_template, is_active, sort_order, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'], $data['period_type'], $data['min_rate'], $data['max_rate'],
                $data['level_key'], $data['label'], $data['message_template'],
                $data['is_active'], $data['sort_order'], $data['created_by'],
            ]
        );
    }

    /** @param array<string,mixed> $data */
    public function updateRule(int $id, array $data): void
    {
        Query::run(
            'UPDATE rapport_scoring_rules
                SET name = ?, period_type = ?, min_rate = ?, max_rate = ?, level_key = ?,
                    label = ?, message_template = ?, is_active = ?, sort_order = ?
              WHERE id = ?',
            [
                $data['name'], $data['period_type'], $data['min_rate'], $data['max_rate'],
                $data['level_key'], $data['label'], $data['message_template'],
                $data['is_active'], $data['sort_order'], $id,
            ]
        );
    }

    public function deleteRule(int $id): void
    {
        Query::run('DELETE FROM rapport_scoring_rules WHERE id = ?', [$id]);
    }

    /* ================= Résultats historisés ================= */

    /** @param array<string,mixed> $data */
    public function upsertResult(array $data): void
    {
        Query::run(
            'INSERT INTO rapport_scoring_results
                (user_id, period_type, period_start, period_end, expected_count, submitted_count,
                 on_time_count, late_count, completion_rate, punctuality_rate, level_key,
                 message_snapshot, rules_version, calculated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                expected_count = VALUES(expected_count),
                submitted_count = VALUES(submitted_count),
                on_time_count = VALUES(on_time_count),
                late_count = VALUES(late_count),
                completion_rate = VALUES(completion_rate),
                punctuality_rate = VALUES(punctuality_rate),
                level_key = VALUES(level_key),
                message_snapshot = VALUES(message_snapshot),
                rules_version = VALUES(rules_version),
                calculated_at = NOW()',
            [
                $data['user_id'], $data['period_type'], $data['period_start'], $data['period_end'],
                $data['expected_count'], $data['submitted_count'], $data['on_time_count'], $data['late_count'],
                $data['completion_rate'], $data['punctuality_rate'], $data['level_key'],
                $data['message_snapshot'], $data['rules_version'],
            ]
        );
    }

    public function resultFor(int $userId, string $periodType, string $periodStart, string $periodEnd): ?array
    {
        return Query::one(
            'SELECT * FROM rapport_scoring_results
              WHERE user_id = ? AND period_type = ? AND period_start = ? AND period_end = ?',
            [$userId, $periodType, $periodStart, $periodEnd]
        );
    }

    /** Marque le bilan/félicitation comme notifié (une seule notification par user + période + version). */
    public function markNotified(int $userId, string $periodType, string $periodStart, string $periodEnd, string $levelKey): void
    {
        Query::run(
            'UPDATE rapport_scoring_results
                SET notified_at = NOW(), notified_level_key = ?
              WHERE user_id = ? AND period_type = ? AND period_start = ? AND period_end = ?',
            [$levelKey, $userId, $periodType, $periodStart, $periodEnd]
        );
    }

    /** Historique des bilans/résultats d'un utilisateur (les plus récents d'abord). */
    public function historyForUser(int $userId, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        return Query::all(
            "SELECT * FROM rapport_scoring_results WHERE user_id = ? ORDER BY period_end DESC, id DESC LIMIT $limit",
            [$userId]
        );
    }

    /** Utilisateurs ayant au moins une occurrence attendue sur la période (pour bilans & classement). */
    public function usersWithOccurrences(string $from, string $to, ?array $scopePairs): array
    {
        $params = [$from, $to];
        $occurrenceRepo = new RapportOccurrenceRepository();
        $scope = $occurrenceRepo->scopeWhere($scopePairs, $params, 'o');
        return Query::all(
            "SELECT DISTINCT r.user_id
               FROM rapport_occurrence_responsables r
               JOIN rapport_occurrences o ON o.id = r.occurrence_id
              WHERE o.report_date BETWEEN ? AND ? $scope AND o.status <> 'cancelled' AND r.individual_status <> 'cancelled'",
            $params
        );
    }
}
