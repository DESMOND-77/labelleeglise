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

    /* ================= Historique des présences (fiche membre) ================= */

    /**
     * Historique réel des présences d'un utilisateur, jointes aux noms
     * réels (culte/centre/bacenta), triées par date décroissante.
     * $fromDate/$toDate au format Y-m-d, optionnels.
     */
    public function historyForUser(int $userId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $sql = "SELECT p.id, p.date_presence, p.culte_id, p.centre_id, p.bacenta_id, p.basonta_id,
                       cu.nom AS culte_nom, ce.nom AS centre_nom, ba.nom AS bacenta_nom
                  FROM presences p
                  LEFT JOIN cultes cu ON cu.id = p.culte_id
                  LEFT JOIN centres ce ON ce.id = p.centre_id
                  LEFT JOIN bacentas ba ON ba.id = p.bacenta_id
                 WHERE p.user_id = ?";
        $params = [$userId];
        if ($fromDate) {
            $sql .= ' AND p.date_presence >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $sql .= ' AND p.date_presence <= ?';
            $params[] = $toDate;
        }
        $sql .= ' ORDER BY p.date_presence DESC, p.id DESC';
        return Query::all($sql, $params);
    }

    public function countForUser(int $userId): int
    {
        return (int) Query::value('SELECT COUNT(*) FROM presences WHERE user_id = ?', [$userId]);
    }

    public function mostRecentDateForUser(int $userId): ?string
    {
        $d = Query::value('SELECT MAX(date_presence) FROM presences WHERE user_id = ?', [$userId]);
        return $d ?: null;
    }

    /** Nombre de dates de culte distinctes enregistrées sur la période (dénominateur honnête d'un "taux"). */
    public function distinctCulteDatesInRange(?string $fromDate, ?string $toDate): int
    {
        $sql = "SELECT COUNT(DISTINCT date_presence) FROM presences WHERE culte_id IS NOT NULL";
        $params = [];
        if ($fromDate) {
            $sql .= ' AND date_presence >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $sql .= ' AND date_presence <= ?';
            $params[] = $toDate;
        }
        return (int) Query::value($sql, $params);
    }

    /* ================= M1 — Présences par occurrence (unité, date, statut) ================= */

    private const UNIT_COLUMNS = ['bacenta' => 'bacenta_id', 'cult' => 'culte_id', 'basonta' => 'basonta_id'];

    private function unitColumn(string $unitType): string
    {
        if (!isset(self::UNIT_COLUMNS[$unitType])) {
            throw new \InvalidArgumentException("Type d'unité inconnu: {$unitType}");
        }
        return self::UNIT_COLUMNS[$unitType];
    }

    /** Upsert des statuts d'une occurrence (unité, date). Appelé sous transaction. */
    public function pointOccurrence(string $unitType, int $unitId, string $date, array $statutByUserId): void
    {
        $col = $this->unitColumn($unitType);
        Query::run("DELETE FROM presences WHERE $col = ? AND date_presence = ?", [$unitId, $date]);
        foreach ($statutByUserId as $userId => $statut) {
            Query::run(
                "INSERT INTO presences (user_id, date_presence, statut, $col) VALUES (?, ?, ?, ?)",
                [(int) $userId, $date, $statut, $unitId]
            );
        }
    }

    /** @return array<int,string> [userId => statut] */
    public function occurrenceStatuts(string $unitType, int $unitId, string $date): array
    {
        $col = $this->unitColumn($unitType);
        $out = [];
        foreach (Query::all("SELECT user_id, statut FROM presences WHERE $col = ? AND date_presence = ?", [$unitId, $date]) as $r) {
            $out[(int) $r['user_id']] = (string) $r['statut'];
        }
        return $out;
    }

    /** @return list<string> dates Y-m-d triées */
    public function distinctDatesForUnit(string $unitType, int $unitId, string $from, string $to): array
    {
        $col = $this->unitColumn($unitType);
        return array_map(
            static fn($r) => (string) $r['date_presence'],
            Query::all(
                "SELECT DISTINCT date_presence FROM presences
                  WHERE $col = ? AND date_presence BETWEEN ? AND ? ORDER BY date_presence",
                [$unitId, $from, $to]
            )
        );
    }

    /** @return array<int,array<string,string>> [userId => [date => statut]] */
    public function matrixForUnit(string $unitType, int $unitId, string $from, string $to): array
    {
        $col = $this->unitColumn($unitType);
        $out = [];
        foreach (Query::all(
            "SELECT user_id, date_presence, statut FROM presences
              WHERE $col = ? AND date_presence BETWEEN ? AND ?",
            [$unitId, $from, $to]
        ) as $r) {
            $out[(int) $r['user_id']][(string) $r['date_presence']] = (string) $r['statut'];
        }
        return $out;
    }
}
