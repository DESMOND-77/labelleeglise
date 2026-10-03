<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Rapports du Jour : un par cible (centre ou bacenta) et par date.
 */
class RapportJourRepository
{
    /** Colonnes métier écrites par upsert (dans l'ordre). */
    private const WRITABLE = [
        'centre_id', 'date_rapport', 'auteur_id', 'scope_type', 'scope_id', 'bacenta_id',
        'resp_centre_nom', 'resp_bacenta_nom', 'assistants',
        'nb_presents', 'nb_adultes', 'nb_enfants', 'nb_anciens', 'nb_nouveaux', 'nb_nes_de_nouveau',
        'offrande', 'livre_enseigne', 'chapitre_enseigne',
    ];

    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM rapports_jour WHERE id = ?', [$id]);
    }

    public function findByScope(string $scopeType, int $scopeId, string $date): ?array
    {
        return Query::one(
            'SELECT * FROM rapports_jour WHERE scope_type = ? AND scope_id = ? AND date_rapport = ?',
            [$scopeType, $scopeId, $date]
        );
    }

    public function findByCentreDate(int $centreId, string $date, ?string $scopeType = null, ?int $bacentaId = null): ?array
    {
        if ($scopeType === 'centre') {
            return $this->findByScope('centre', $centreId, $date);
        }
        if ($scopeType === 'bacenta' && $bacentaId) {
            return $this->findByScope('bacenta', $bacentaId, $date);
        }
        return Query::one(
            "SELECT * FROM rapports_jour WHERE centre_id = ? AND date_rapport = ? ORDER BY scope_type = 'bacenta', id DESC LIMIT 1",
            [$centreId, $date]
        );
    }

    /** INSERT/UPDATE sur la cible logique (centre ou bacenta). */
    public function upsert(array $data): int
    {
        $existing = $this->findByScope((string) $data['scope_type'], (int) $data['scope_id'], (string) $data['date_rapport']);

        if ($existing) {
            $cols = array_values(array_diff(self::WRITABLE, ['centre_id', 'date_rapport', 'auteur_id', 'scope_type', 'scope_id']));
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
                       CASE WHEN r.scope_type = 'centre' THEN c.nom ELSE ba.nom END AS cible_nom,
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
