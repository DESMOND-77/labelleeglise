<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Rapports du Jour (un par centre et par date).
 */
class RapportJourRepository
{
    /** Colonnes métier écrites par upsert (dans l'ordre). */
    private const WRITABLE = [
        'centre_id', 'date_rapport', 'auteur_id', 'bacenta_id',
        'resp_centre_nom', 'resp_bacenta_nom', 'assistants',
        'nb_presents', 'nb_adultes', 'nb_enfants', 'nb_anciens', 'nb_nouveaux', 'nb_nes_de_nouveau',
        'offrande', 'livre_enseigne', 'chapitre_enseigne',
    ];

    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM rapports_jour WHERE id = ?', [$id]);
    }

    public function findByCentreDate(int $centreId, string $date): ?array
    {
        return Query::one('SELECT * FROM rapports_jour WHERE centre_id = ? AND date_rapport = ?', [$centreId, $date]);
    }

    /** INSERT si (centre_id, date_rapport) libre, sinon UPDATE (auteur_id/created_at préservés). */
    public function upsert(array $data): int
    {
        $existing = $this->findByCentreDate((int) $data['centre_id'], (string) $data['date_rapport']);

        if ($existing) {
            $cols = array_values(array_diff(self::WRITABLE, ['centre_id', 'date_rapport', 'auteur_id']));
            $set = implode(', ', array_map(static fn($c) => "$c = ?", $cols));
            $params = array_map(static fn($c) => $data[$c] ?? null, $cols);
            $params[] = (int) $existing['id'];
            Query::run("UPDATE rapports_jour SET $set WHERE id = ?", $params);
            return (int) $existing['id'];
        }

        $cols = self::WRITABLE;
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $params = array_map(static fn($c) => $data[$c] ?? null, $cols);
        return Query::run('INSERT INTO rapports_jour (' . implode(', ', $cols) . ") VALUES ($placeholders)", $params);
    }

    /**
     * @param ?array<int> $centreIds Restreint la liste à ces centres (périmètre autorisé).
     *                               `null` = pas de restriction ; `[]` = aucun résultat.
     * @return array<int,array<string,mixed>>
     */
    public function list(?int $centreId, ?string $monthKey, ?array $centreIds = null): array
    {
        $sql = "SELECT r.*, c.nom AS centre_nom, ba.nom AS bacenta_nom,
                       au.prenom AS auteur_prenom, au.nom AS auteur_nom
                  FROM rapports_jour r
                  JOIN centres c   ON c.id = r.centre_id
                  LEFT JOIN bacentas ba ON ba.id = r.bacenta_id
                  LEFT JOIN users au    ON au.id = r.auteur_id
                 WHERE 1 = 1";
        $params = [];
        if ($centreId !== null) {
            $sql .= ' AND r.centre_id = ?';
            $params[] = $centreId;
        }
        if ($monthKey !== null && $monthKey !== '') {
            $sql .= " AND DATE_FORMAT(r.date_rapport, '%Y-%m') = ?";
            $params[] = $monthKey;
        }
        if ($centreIds !== null) {
            if ($centreIds === []) {
                $sql .= ' AND 1 = 0';
            } else {
                $sql .= ' AND r.centre_id IN (' . implode(', ', array_fill(0, count($centreIds), '?')) . ')';
                foreach ($centreIds as $cid) {
                    $params[] = (int) $cid;
                }
            }
        }
        $sql .= ' ORDER BY r.date_rapport DESC, r.id DESC';
        return Query::all($sql, $params);
    }
}
