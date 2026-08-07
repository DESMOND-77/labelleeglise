<?php

namespace App\Repositories;

use App\Core\Query;

/**
 * Présences (culte, bacenta, basonta, centre) et pointage.
 */
class AttendanceRepository
{
    public function hasPresence(string $column, int $userId, int $entityId): bool
    {
        return (bool) Query::one("SELECT id FROM presences WHERE user_id = ? AND $column = ? LIMIT 1", [$userId, $entityId]);
    }

    public function insert(int $userId, string $date, array $columns): void
    {
        $cols = implode(', ', array_keys($columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $params = array_values($columns);
        Query::run("INSERT INTO presences (user_id, date_presence, $cols) VALUES (?, ?, $placeholders)", [$userId, $date, ...$params]);
    }

    public function deleteByColumn(string $column, int $userId, int $entityId): void
    {
        Query::run("DELETE FROM presences WHERE user_id = ? AND $column = ?", [$userId, $entityId]);
    }

    /** Pointage de présence à un culte (recenser les présents pour date). */
    public function pointCulte(int $culteId, string $date, array $userIds): void
    {
        Query::run('DELETE FROM presences WHERE culte_id = ? AND date_presence = ?', [$culteId, $date]);
        foreach ($userIds as $uid) {
            Query::run('INSERT INTO presences (user_id, date_presence, culte_id) VALUES (?, ?, ?)', [(int) $uid, $date, $culteId]);
        }
    }

    public function countDistinctForCultes(): int
    {
        return (int) Query::value('SELECT COUNT(DISTINCT user_id) FROM presences WHERE culte_id IS NOT NULL');
    }

    public function countDistinctForBasontas(): int
    {
        return (int) Query::value('SELECT COUNT(DISTINCT user_id) FROM users_basontas');
    }
}
