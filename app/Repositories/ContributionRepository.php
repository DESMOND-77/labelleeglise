<?php

namespace App\Repositories;

use App\Core\Query;

/**
 * Offrandes (bacentas / centres) et dîmes.
 */
class ContributionRepository
{
    /** Offrandes d'une entité pour un mois (4 semaines). */
    public function offrandesMonth(string $type, int $entityId, string $monthKey): array
    {
        $col = $type === 'centre' ? 'centre_id' : 'bacenta_id';
        $rows = Query::all("SELECT jour_index, montant FROM offrandes WHERE $col = ? AND mois = ? ORDER BY jour_index", [$entityId, $monthKey]);
        $out = [0, 0, 0, 0];
        foreach ($rows as $r) {
            $out[(int) $r['jour_index']] = (int) $r['montant'];
        }
        return $out;
    }

    public function saveOffrandesMonth(string $type, int $entityId, string $monthKey, array $vals): void
    {
        $col = $type === 'centre' ? 'centre_id' : 'bacenta_id';
        $vals = array_pad(array_slice($vals, 0, 4), 4, 0);
        $vals = array_map(fn($v) => max(0, (int) $v), $vals);
        foreach ($vals as $i => $montant) {
            $exists = Query::value("SELECT id FROM offrandes WHERE $col = ? AND mois = ? AND jour_index = ?", [$entityId, $monthKey, $i]);
            if ($exists) {
                Query::run('UPDATE offrandes SET montant = ?, date_offrande = ? WHERE id = ?',
                    [$montant, date('Y-m-d', strtotime("first day of $monthKey") + $i * 86400 * 7), (int) $exists]);
            } elseif ($montant > 0) {
                Query::run("INSERT INTO offrandes ($col, montant, date_offrande, mois, jour_index) VALUES (?, ?, ?, ?, ?)",
                    [$entityId, $montant, date('Y-m-d', strtotime("first day of $monthKey") + $i * 86400 * 7), $monthKey, $i]);
            }
        }
    }

    public function sumOffrandesMonth(string $type, int $entityId, string $monthKey): int
    {
        return array_sum($this->offrandesMonth($type, $entityId, $monthKey));
    }

    public function sumOffrandesYear(string $type, int $entityId, int $year): int
    {
        $col = $type === 'centre' ? 'centre_id' : 'bacenta_id';
        return (int) Query::value("SELECT COALESCE(SUM(montant),0) FROM offrandes WHERE $col = ? AND mois LIKE ?", [$entityId, $year . '-%']);
    }

    public function sumOffrandesSectionYear(string $type, int $year): int
    {
        $col = $type === 'centre' ? 'centre_id' : 'bacenta_id';
        return (int) Query::value("SELECT COALESCE(SUM(montant),0) FROM offrandes WHERE $col IS NOT NULL AND mois LIKE ?", [$year . '-%']);
    }

    /** Dîmes d'un membre sur une année (12 mois). */
    public function dimes(int $userId, int $year): array
    {
        $rows = Query::all('SELECT mois, montant FROM dimes WHERE user_id = ? AND annee = ?', [$userId, $year]);
        $out = array_fill(0, 12, 0);
        foreach ($rows as $r) {
            $out[(int) $r['mois'] - 1] = (int) $r['montant'];
        }
        return $out;
    }

    public function saveDimes(int $userId, int $year, array $vals): void
    {
        foreach (array_slice($vals, 0, 12) as $i => $v) {
            $montant = max(0, (int) $v);
            $mois = $i + 1;
            $exists = Query::value('SELECT id FROM dimes WHERE user_id = ? AND annee = ? AND mois = ?', [$userId, $year, $mois]);
            if ($exists) {
                Query::run('UPDATE dimes SET montant = ? WHERE id = ?', [$montant, (int) $exists]);
            } elseif ($montant > 0) {
                Query::run('INSERT INTO dimes (user_id, annee, mois, montant) VALUES (?, ?, ?, ?)', [$userId, $year, $mois, $montant]);
            }
        }
    }
}
